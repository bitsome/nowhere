import { computed, reactive, ref } from 'vue';
import {
    apiCreateBulkOrders,
    apiCreateOrder,
    apiOrder,
    apiStructureOrder,
    apiTransitionOrder,
    apiUpdateOrder,
} from '../api/orders';
import { apiCreateTemplate, apiDeleteTemplate, apiTemplates } from '../api/templates';
import { getApiErrorMessage } from '../api/client';
import { buildSplitOrders, SERVICE_OPTIONS, SERVICE_LABELS, toIsoDate, VEHICLE_OPTIONS, weekdayOf } from '../utils/orderCreate';
import { ORDER_TAGS } from '../utils/tags';

/**
 * 운행 등록/수정 폼 — AI 구조화, 저장, 수정 로드, 운행 템플릿을 담당한다.
 *
 * @param {object} options
 * @param {import('vue-router').RouteLocationNormalizedLoaded} options.route
 * @param {import('vue').Ref<string>} options.screen 폼↔목록 전환 ref
 * @param {import('vue').Ref<string>} options.error
 * @param {import('vue').Ref<string>} options.success
 * @param {import('vue').Ref<boolean>} options.saving
 * @param {() => Promise<void>} options.loadMyOrders 저장 후 목록 새로고침
 */
export function useOrderForm({ route, screen, error, success, saving, loadMyOrders }) {
    // 수정 모드: /orders/:id/edit
    const editId = computed(() => (route.params.id ? Number(route.params.id) : null));
    const isEdit = computed(() => editId.value !== null);

    const summary = ref('');
    const structuring = ref(false);
    const lineItems = ref([]);
    const publishNow = ref(true);
    const loading = ref(false);

    const mode = ref('ai');

    const form = reactive({
        customer_name: '',
        customer_phone: '',
        vehicle_type: '',
        service_type: 'pickup',
        service_date: null,
        service_time: null,
        pickup_location: '',
        dropoff_location: '',
        flight_number: '',
        passenger_count: null,
        luggage_count: null,
        expected_revenue: null,
        reservation_company: '직접예약',
        is_priority: false,
        tags: [],
    });

    const weekdayLabel = computed(() => weekdayOf(form.service_date));

    const customTag = ref('');

    // 태그 — 프리셋/직접 입력 공용 토글 (이미 있으면 제거)
    const toggleTag = (tag) => {
        const list = form.tags;
        const index = list.indexOf(tag);

        if (index >= 0) {
            list.splice(index, 1);
        } else {
            list.push(tag);
        }
    };

    // 직접 입력 태그 추가 — 빈 값·중복 방지
    const addCustomTag = () => {
        const tag = customTag.value.trim();

        if (!tag) {
            return;
        }

        if (!form.tags.includes(tag)) {
            form.tags.push(tag);
        }

        customTag.value = '';
    };

    const removeTag = (tag) => {
        const index = form.tags.indexOf(tag);

        if (index >= 0) {
            form.tags.splice(index, 1);
        }
    };

    // AI 구조화 결과 — 화면에서 바로 고칠 수 있는 등록 초안 목록.
    // 일정이 여러 건이면 각각이 독립 운행 초안이 된다 (셋트로 묶지 않는다).
    const aiOrders = ref([]);

    const removeAiOrder = (index) => {
        aiOrders.value.splice(index, 1);
    };

    // 등록 초안 → API 페이로드 (서비스 일시 형식 정리) — 단일/일괄 등록이 함께 쓴다
    const toOrderPayload = (draft) => {
        const payload = { ...draft };
        const isoDate = toIsoDate(draft.service_date);

        if (isoDate && /^\d{4}-\d{2}-\d{2}$/.test(isoDate)) {
            payload.service_date = isoDate;
            payload.service_datetime = `${isoDate} ${draft.service_time || '00:00'}:00`;
        }

        return payload;
    };

    // 수정 화면의 기존 일정 표시용 행 — 새 등록은 aiOrders(편집 초안)를 쓴다
    const lineItemRows = computed(() =>
        (lineItems.value ?? []).map((item) => ({
            scheduled_time: item.scheduled_time || '-',
            service_date: item.service_date || '-',
            service_weekday: item.service_weekday || '-',
            service_type: SERVICE_LABELS[item.service_type] ?? (item.service_type || '-'),
            pickup_location: item.pickup_location || '-',
            dropoff_location: item.dropoff_location || '-',
            flight_number: item.flight_number || '-',
            amount: item.amount_text ?? (item.amount_value != null ? `${Number(item.amount_value).toLocaleString()}원` : ''),
        })),
    );

    const structure = async () => {
        structuring.value = true;
        error.value = '';
        success.value = '';

        try {
            const { data } = await apiStructureOrder(summary.value);
            const structured = data.data.structured;

            // 결과를 그대로 등록하지 않고 편집 가능한 초안으로 세운다 —
            // 규칙 파서로 대체된 경우(parsed_by = local)엔 오해석이 있으므로 사람이 고쳐야 한다
            aiOrders.value = buildSplitOrders(structured);

            success.value = structured.parsed_by === 'local'
                ? 'AI 대신 규칙으로 해석했습니다. 값을 확인하고 필요하면 수정해 주세요.'
                : 'AI 구조화가 완료되었습니다. 내용을 확인하고 등록해 주세요.';
        } catch (e) {
            error.value = getApiErrorMessage(e, '구조화에 실패했습니다.');
        } finally {
            structuring.value = false;
        }
    };

    // 등록 전 별도 검증 없음 — 모든 필드는 선택 입력 (AI 구조화/부분 입력 허용)
    const save = async () => {
        saving.value = true;
        error.value = '';

        try {
            const payload = toOrderPayload(form);

            if (lineItems.value.length) {
                payload.line_items = lineItems.value;
            }

            let orderId = editId.value;

            if (isEdit.value) {
                await apiUpdateOrder(orderId, payload);
            } else {
                const { data } = await apiCreateOrder(payload);
                orderId = data.data.id;

                // "즉시 공개"면 초안 → 공개 상태로 전환해 마켓에 노출한다
                if (publishNow.value) {
                    await apiTransitionOrder(orderId, 'published').catch(() => {});
                }
            }

            // 등록/수정 완료 → 목록으로 복귀
            success.value = isEdit.value ? '운행이 수정되었습니다.' : '운행이 등록되었습니다.';
            screen.value = 'list';
            await loadMyOrders();
        } catch (e) {
            error.value = getApiErrorMessage(e, isEdit.value ? '운행 수정에 실패했습니다.' : '운행 등록에 실패했습니다.');
        } finally {
            saving.value = false;
        }
    };

    // AI 구조화 결과 등록 — 여러 건이면 각각 독립 운행으로 한 번에 만든다.
    // 필수 정보가 덜 찬 건은 서버가 공개하지 않고 초안으로 남긴다.
    const saveAiOrders = async () => {
        if (!aiOrders.value.length) {
            return;
        }

        saving.value = true;
        error.value = '';
        success.value = '';

        try {
            const { data } = await apiCreateBulkOrders({
                orders: aiOrders.value.map(toOrderPayload),
                publish: publishNow.value,
            });

            const result = data.data;
            const draftCount = result.draft_ids?.length ?? 0;

            success.value = draftCount
                ? `${result.order_ids.length}건 등록했습니다. ${draftCount}건은 필수 정보가 없어 초안으로 남겼습니다.`
                : `${result.order_ids.length}건 등록했습니다.`;

            screen.value = 'list';
            await loadMyOrders();
        } catch (e) {
            error.value = getApiErrorMessage(e, '운행 등록에 실패했습니다.');
        } finally {
            saving.value = false;
        }
    };

    // 수정 모드: 기존 운행을 불러와 폼에 채운다
    const loadForEdit = async () => {
        loading.value = true;
        error.value = '';

        try {
            const { data } = await apiOrder(editId.value);
            const orderData = data.order;

            form.customer_name = orderData.customer_name ?? '';
            form.customer_phone = orderData.customer_phone ?? '';
            // 상세 응답의 위치·차량은 화면 표시용으로 한국어 변환돼 있다 — 수정 화면은 원문(raw_*)을 쓴다
            form.vehicle_type = orderData.raw_vehicle_type ?? '';
            form.service_type = orderData.service_type ?? 'pickup';
            form.service_date = toIsoDate(orderData.service_date ?? '') || null;
            form.service_time = orderData.service_time ?? null;
            form.pickup_location = orderData.raw_pickup_location ?? '';
            form.dropoff_location = orderData.raw_dropoff_location ?? '';
            form.flight_number = orderData.flight_number ?? '';
            form.passenger_count = orderData.passenger_count ?? null;
            form.luggage_count = orderData.luggage_count ?? null;
            form.expected_revenue = orderData.expected_revenue ?? orderData.amount_value ?? null;
            form.reservation_company = orderData.reservation_company ?? '직접예약';
            form.is_priority = orderData.is_priority ?? false;
            form.tags = Array.isArray(orderData.tags) ? [...orderData.tags] : [];

            lineItems.value = (orderData.line_items ?? []).map((item) => {
                const isoDate = toIsoDate(item.service_date ?? '');

                // 표시용으로 바꾼 값·원문 백업 키는 저장 페이로드에 실리지 않도록 걷어낸다
                const { raw_pickup_location, raw_dropoff_location, raw_vehicle_type, ...rest } = item;

                return {
                    ...rest,
                    pickup_location: raw_pickup_location ?? item.pickup_location ?? '',
                    dropoff_location: raw_dropoff_location ?? item.dropoff_location ?? '',
                    service_date: isoDate,
                    service_weekday: item.service_weekday || weekdayOf(isoDate),
                };
            });
        } catch (e) {
            error.value = getApiErrorMessage(e, '운행을 불러오지 못했습니다.');
        } finally {
            loading.value = false;
        }
    };

    // ── 운행 등록 템플릿 ──
    const templates = ref([]);
    const templateOpen = ref(false);
    const templateName = ref('');
    const templateSaving = ref(false);

    const loadTemplates = async () => {
        try {
            const { data } = await apiTemplates();
            templates.value = data.data;
        } catch {
            /* 템플릿 로드 실패는 무시 */
        }
    };

    const templatePayloadFromForm = () => ({
        service_type: form.service_type,
        vehicle_type: form.vehicle_type,
        pickup_location: form.pickup_location,
        dropoff_location: form.dropoff_location,
        passenger_count: form.passenger_count,
        expected_revenue: form.expected_revenue,
        flight_number: form.flight_number,
        reservation_company: form.reservation_company,
    });

    const applyTemplate = (tpl) => {
        form.vehicle_type = tpl.vehicle_type ?? '';
        form.service_type = tpl.service_type ?? 'pickup';
        form.pickup_location = tpl.pickup_location ?? '';
        form.dropoff_location = tpl.dropoff_location ?? '';
        form.flight_number = tpl.flight_number ?? '';
        form.passenger_count = tpl.passenger_count ?? null;
        form.expected_revenue = tpl.expected_revenue ?? null;
        form.reservation_company = tpl.reservation_company ?? '직접예약';
        success.value = `템플릿 "${tpl.name}"이 적용되었습니다.`;
    };

    const saveTemplate = async () => {
        if (!templateName.value.trim()) {
            return;
        }

        templateSaving.value = true;

        try {
            await apiCreateTemplate({
                name: templateName.value.trim(),
                ...templatePayloadFromForm(),
            });
            templateOpen.value = false;
            templateName.value = '';
            success.value = '템플릿이 저장되었습니다.';
            await loadTemplates();
        } catch (e) {
            error.value = getApiErrorMessage(e, '템플릿 저장에 실패했습니다.');
        } finally {
            templateSaving.value = false;
        }
    };

    const removeTemplate = async (tpl) => {
        try {
            await apiDeleteTemplate(tpl.id);
            templates.value = templates.value.filter((t) => t.id !== tpl.id);
        } catch {
            /* 삭제 실패는 무시 */
        }
    };

    return {
        editId,
        isEdit,
        SERVICE_OPTIONS,
        VEHICLE_OPTIONS,
        summary,
        structuring,
        lineItems,
        publishNow,
        loading,
        mode,
        form,
        weekdayLabel,
        aiOrders,
        removeAiOrder,
        lineItemRows,
        ORDER_TAGS,
        customTag,
        toggleTag,
        addCustomTag,
        removeTag,
        structure,
        save,
        saveAiOrders,
        loadForEdit,
        templates,
        templateOpen,
        templateName,
        templateSaving,
        loadTemplates,
        applyTemplate,
        saveTemplate,
        removeTemplate,
    };
}
