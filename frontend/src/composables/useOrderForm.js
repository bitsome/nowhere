import { computed, reactive, ref } from 'vue';
import { apiCreateOrder, apiOrder, apiStructureOrder, apiTransitionOrder, apiUpdateOrder } from '../api/orders';
import { apiCreateTemplate, apiDeleteTemplate, apiTemplates } from '../api/templates';
import { getApiErrorMessage } from '../api/client';
import { SERVICE_OPTIONS, SERVICE_LABELS, toIsoDate, toServiceCode, VEHICLE_OPTIONS, weekdayOf } from '../utils/orderCreate';
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

    const applyStructured = (s) => {
        form.service_type = toServiceCode(s.service_type);
        form.service_date = toIsoDate(s.service_date ?? '') || null;
        form.service_time = s.service_time ?? s.scheduled_time ?? null;
        form.vehicle_type = s.vehicle_type ?? '';
        form.passenger_count = s.passenger_count ?? null;
        form.luggage_count = s.luggage_count ?? null;
        form.pickup_location = s.pickup_location ?? '';
        form.dropoff_location = s.dropoff_location ?? '';
        form.flight_number = s.flight_number ?? '';
        form.expected_revenue = s.amount_value ?? null;
        lineItems.value = (s.line_items ?? []).map((item) => {
            const isoDate = toIsoDate(item.service_date ?? '');

            return {
                ...item,
                service_date: isoDate,
                service_weekday: item.service_weekday || weekdayOf(isoDate),
            };
        });
    };

    // AI 구조화 결과 요약 (읽기 전용) — 입력 폼은 직접 등록(manual)에서만 제공
    const structuredPreview = computed(() => {
        if (mode.value !== 'ai') return null;

        const hasValue =
            form.pickup_location || form.dropoff_location || form.service_date ||
            form.service_time || form.vehicle_type || form.flight_number ||
            form.passenger_count != null || form.expected_revenue != null;

        if (!hasValue) return null;

        return {
            route: [form.pickup_location, form.dropoff_location].filter(Boolean).join(' → '),
            service_date: form.service_date,
            service_time: form.service_time,
            vehicle_type: form.vehicle_type,
            flight_number: form.flight_number,
            passenger_count: form.passenger_count,
            luggage_count: form.luggage_count,
            expected_revenue: form.expected_revenue,
            service_type: SERVICE_LABELS[form.service_type] ?? form.service_type,
        };
    });

    // 일정(AI 구조화) 테이블 표시용 행 — 날짜/요일/금액 포함 (금액은 없으면 빈칸)
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
            applyStructured(data.data.structured);
            success.value = 'AI 구조화가 완료되었습니다. 내용을 확인하고 저장해 주세요.';
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
            const payload = { ...form };

            // 서비스 일시를 "YYYY-MM-DD HH:MM:SS" 형태로 저장
            const isoDate = toIsoDate(form.service_date);

            if (isoDate && /^\d{4}-\d{2}-\d{2}$/.test(isoDate)) {
                payload.service_date = isoDate;
                payload.service_datetime = `${isoDate} ${form.service_time || '00:00'}:00`;
            }

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

    // 수정 모드: 기존 운행을 불러와 폼에 채운다
    const loadForEdit = async () => {
        loading.value = true;
        error.value = '';

        try {
            const { data } = await apiOrder(editId.value);
            const orderData = data.order;

            form.customer_name = orderData.customer_name ?? '';
            form.customer_phone = orderData.customer_phone ?? '';
            form.vehicle_type = orderData.vehicle_type ?? '';
            form.service_type = orderData.service_type ?? 'pickup';
            form.service_date = toIsoDate(orderData.service_date ?? '') || null;
            form.service_time = orderData.service_time ?? null;
            form.pickup_location = orderData.pickup_location ?? '';
            form.dropoff_location = orderData.dropoff_location ?? '';
            form.flight_number = orderData.flight_number ?? '';
            form.passenger_count = orderData.passenger_count ?? null;
            form.luggage_count = orderData.luggage_count ?? null;
            form.expected_revenue = orderData.expected_revenue ?? orderData.amount_value ?? null;
            form.reservation_company = orderData.reservation_company ?? '직접예약';
            form.is_priority = orderData.is_priority ?? false;
            form.tags = Array.isArray(orderData.tags) ? [...orderData.tags] : [];

            lineItems.value = (orderData.line_items ?? []).map((item) => {
                const isoDate = toIsoDate(item.service_date ?? '');

                return {
                    ...item,
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
        structuredPreview,
        lineItemRows,
        ORDER_TAGS,
        customTag,
        toggleTag,
        addCustomTag,
        removeTag,
        structure,
        save,
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
