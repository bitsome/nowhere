
import { getApiErrorMessage, postData } from '../../shared/api/index.js';
import { createToastBridge } from '../../shared/utils/toast-bridge.js';
import { LINE_ITEM_FIELD_KEYS } from './line-items.js';

export function initializeOrderAiStructuring() {
    const root = document.querySelector('[data-order-ai-structure]');

    if (!root) {
        return;
    }

    const requestUrl = root.dataset.structureUrl || '';
    const input = root.querySelector('[data-order-summary-input]');
    const trigger = root.querySelector('[data-order-summary-trigger]');
    const status = root.querySelector('[data-order-summary-status]');
    const saveForm = root.querySelector('[data-order-structured-store]');
    const saveButton = root.querySelector('[data-order-structured-save]');
    const savePayload = root.querySelector('[data-order-structured-payload]');
    const saveOriginalSummary = root.querySelector('[data-order-original-summary]');
    const rawStructuredResult = root.querySelector('[data-order-structured-raw]');
    const structuredResultMap = {
        amount: root.querySelector('[data-order-structured-amount]'),
        dropoff_location: root.querySelector('[data-order-structured-dropoff]'),
        extra_options: root.querySelector('[data-order-structured-extra-options]'),
        flight_number: root.querySelector('[data-order-structured-flight]'),
        group_type: root.querySelector('[data-order-structured-group]'),
        line_items: root.querySelector('[data-order-structured-line-count]'),
        order_type: root.querySelector('[data-order-structured-type]'),
        passenger_count: root.querySelector('[data-order-structured-passenger]'),
        luggage_count: root.querySelector('[data-order-structured-luggage]'),
        pickup_location: root.querySelector('[data-order-structured-pickup]'),
        scheduled_time: root.querySelector('[data-order-structured-time]'),
        service_date: root.querySelector('[data-order-structured-date]'),
        service_type: root.querySelector('[data-order-structured-service]'),
        service_weekday: root.querySelector('[data-order-structured-weekday]'),
        vehicle_type: root.querySelector('[data-order-structured-vehicle]'),
    };

    if (!requestUrl || !input || !trigger || !status) {
        return;
    }

    const setStatus = (message) => {
        status.textContent = message;
    };

    const toastElement = document.querySelector('[data-order-toast]');
    const pushToast = toastElement ? createToastBridge(toastElement) : null;
    const defaultTriggerLabel = trigger.textContent.trim() || 'AI 구조화';

    const setTriggerLoading = (loading) => {
        if (loading) {
            trigger.disabled = true;
            trigger.innerHTML = '<span class="inline-flex items-center gap-2"><span class="h-4 w-4 animate-spin rounded-full border-2 border-[#cfcfcf] border-t-[#555555] dark:border-[#343434] dark:border-t-[#d6d6dd]" aria-hidden="true"></span>구조화 중...</span>';

            return;
        }

        trigger.disabled = false;
        trigger.textContent = defaultTriggerLabel;
    };

    let lastStructuredPayload = null;

    const setSaveEnabled = (enabled) => {
        if (saveButton) {
            saveButton.disabled = !enabled;
        }
    };

    const clearStructuredPayload = () => {
        lastStructuredPayload = null;
        setSaveEnabled(false);
    };

    const resolveServiceTypeLabel = (serviceType) => {
        if (serviceType === 'pickup' || serviceType === '픽업') {
            return '픽업';
        }

        if (serviceType === 'sending' || serviceType === '샌딩') {
            return '샌딩';
        }

        if (serviceType === 'mixed' || serviceType === '혼합') {
            return '혼합';
        }

        return '-';
    };

    const resetRawStructuredResult = () => {
        if (rawStructuredResult) {
            rawStructuredResult.textContent = '-';
        }
    };

    const updateRawStructuredResult = (payload) => {
        if (!rawStructuredResult) {
            return;
        }

        rawStructuredResult.textContent = JSON.stringify(payload || {}, null, 2);
    };

    const resetStructuredResult = () => {
        Object.values(structuredResultMap).forEach((element) => {
            if (element) {
                element.textContent = '-';
            }
        });
    };

    const updateStructuredResult = (structured) => {
        if (structuredResultMap.service_date) {
            structuredResultMap.service_date.textContent = structured.service_date || '-';
        }

        if (structuredResultMap.service_weekday) {
            structuredResultMap.service_weekday.textContent = structured.service_weekday || '-';
        }

        if (structuredResultMap.group_type) {
            structuredResultMap.group_type.textContent = structured.group_type || '-';
        }

        if (structuredResultMap.vehicle_type) {
            structuredResultMap.vehicle_type.textContent = structured.vehicle_type || '-';
        }

        if (structuredResultMap.service_type) {
            structuredResultMap.service_type.textContent = resolveServiceTypeLabel(structured.service_type);
        }

        if (structuredResultMap.passenger_count) {
            structuredResultMap.passenger_count.textContent = structured.passenger_count ? `${structured.passenger_count}명` : '-';
        }

        if (structuredResultMap.luggage_count) {
            structuredResultMap.luggage_count.textContent = structured.luggage_count ? `${structured.luggage_count}개` : '-';
        }

        if (structuredResultMap.amount) {
            if (structured.amount_text && structured.amount_value) {
                structuredResultMap.amount.textContent = `${structured.amount_text} (${Number(structured.amount_value).toLocaleString('ko-KR')}원)`;
            } else if (structured.amount_text) {
                structuredResultMap.amount.textContent = structured.amount_text;
            } else if (structured.amount_value) {
                structuredResultMap.amount.textContent = `${Number(structured.amount_value).toLocaleString('ko-KR')}원`;
            } else {
                structuredResultMap.amount.textContent = '-';
            }
        }

        if (structuredResultMap.line_items) {
            const lineCount = Array.isArray(structured.line_items) ? structured.line_items.length : 0;
            structuredResultMap.line_items.textContent = lineCount > 0 ? `${lineCount}건` : '-';
        }

        if (structuredResultMap.pickup_location) {
            structuredResultMap.pickup_location.textContent = structured.pickup_location || '-';
        }

        if (structuredResultMap.dropoff_location) {
            structuredResultMap.dropoff_location.textContent = structured.dropoff_location || '-';
        }

        if (structuredResultMap.scheduled_time) {
            structuredResultMap.scheduled_time.textContent = structured.scheduled_time || '-';
        }

        if (structuredResultMap.order_type) {
            structuredResultMap.order_type.textContent = structured.order_type || '-';
        }

        if (structuredResultMap.flight_number) {
            structuredResultMap.flight_number.textContent = structured.flight_number || '-';
        }

        if (structuredResultMap.extra_options) {
            const options = Array.isArray(structured.extra_options) ? structured.extra_options : [];
            structuredResultMap.extra_options.textContent = options.length > 0 ? options.join(', ') : '-';
        }
    };

    const applyStructuredFields = (structured) => {
        let items = [];

        if (Array.isArray(structured.line_items) && structured.line_items.length > 0) {
            items = structured.line_items;
        } else {
            const singleItem = {};

            LINE_ITEM_FIELD_KEYS.forEach((key) => {
                const value = structured[key];

                if (value !== undefined && value !== null && value !== '') {
                    singleItem[key] = value;
                }
            });

            if (Object.keys(singleItem).length > 0) {
                items = [singleItem];
            }
        }

        if (items.length > 0) {
            applyStructuredLineItems(items);
        }

        updateOrderPreview(document);
    };

    const updateLineItemsKind = (editor, lineItems = null) => {
        const kindBadge = editor?.querySelector('[data-order-line-items-kind]');

        if (!kindBadge) {
            return;
        }

        // 사용자가 직접 구분을 선택하지 않은 경우에만 AI의 셋트 판별을 자동 반영한다.
        const userTouched = document.documentElement.dataset.orderGroupTypeTouched === 'true';

        if (!userTouched && String(lastStructuredPayload?.structured?.group_type || '').trim() === '셋트') {
            document.querySelectorAll('input[name="group_type"]').forEach((radio) => {
                radio.checked = radio.value === '셋트';
            });
        }

        const checkedType = [...(editor?.querySelectorAll('input[name="group_type"]') || [])]
            .find((radio) => radio.checked)?.value;

        kindBadge.textContent = checkedType === '셋트' ? '셋트 일정' : '단일 일정';
    };

    const applyStructuredLineItems = (lineItems) => {
        const editor = document.querySelector('[data-order-line-items-editor]');

        if (!editor || !Array.isArray(lineItems)) {
            return;
        }

        const body = editor.querySelector('[data-line-items-body]');
        const template = editor.querySelector('template[data-line-items-row]');

        if (!body || !template) {
            return;
        }

        body.querySelectorAll('[data-line-item-row]').forEach((row) => row.remove());

        const emptyRow = body.querySelector('[data-line-items-empty]');
        if (emptyRow) {
            emptyRow.remove();
        }

        lineItems.forEach((lineItem, index) => {
            const key = `ai_${index}`;
            const fragment = template.content.cloneNode(true);

            fragment.querySelectorAll('[name^="line_items"]').forEach((field) => {
                field.name = field.name.replace('__KEY__', key);
                const fieldKey = field.name.split('][').pop().replace(']', '');
                const value = lineItem[fieldKey];

                if (value !== undefined && value !== null && value !== '') {
                    field.value = String(value);
                }
            });

            const row = fragment.querySelector('[data-line-item-row]');
            body.appendChild(fragment);
            refreshLineItemRowSummary(row);
        });

        updateLineItemsKind(editor, lineItems);
    };

    const readLineItemsFromEditor = () => {
        const editor = document.querySelector('[data-order-line-items-editor]');

        if (!editor) {
            return null;
        }

        const body = editor.querySelector('[data-line-items-body]');
        const rows = body ? [...body.querySelectorAll('[data-line-item-row]')] : [];

        if (rows.length === 0) {
            return null;
        }

        return rows.map((row) => {
            const lineItem = {};

            row.querySelectorAll('[name^="line_items"]').forEach((field) => {
                const fieldKey = field.name.split('][').pop().replace(']', '');
                const value = String(field.value || '').trim();

                if (value !== '') {
                    lineItem[fieldKey] = value;
                }
            });

            return lineItem;
        });
    };

    trigger.addEventListener('click', async () => {
        const summary = String(input.value || '').trim();

        if (!summary) {
            setStatus('오더 요약 입력을 먼저 작성하세요.');
            resetStructuredResult();
            resetRawStructuredResult();
            clearStructuredPayload();

            return;
        }

        setTriggerLoading(true);
        setStatus('AI가 오더 요약을 구조화하는 중입니다.');
        resetStructuredResult();
        resetRawStructuredResult();
        clearStructuredPayload();

        try {
            const response = await postData(requestUrl, { summary });
            const structured = response?.structured || {};

            lastStructuredPayload = response;
            setSaveEnabled(true);
            applyStructuredFields(structured);
            applyStructuredLineItems(structured.line_items);
            updateStructuredResult(structured);
            updateRawStructuredResult(response);
            setStatus('AI 구조화가 완료되었습니다. 자동 채운 값은 계속 수정할 수 있습니다.');
            pushToast?.({ type: 'success', title: '완료', message: 'AI 구조화가 완료되었습니다.' });
        } catch (error) {
            resetStructuredResult();
            resetRawStructuredResult();
            clearStructuredPayload();
            setStatus(getApiErrorMessage(error, 'AI 구조화 요청에 실패했습니다.'));
            pushToast?.({ type: 'error', title: '오류 발생', message: 'AI 구조화에 실패했습니다.' });
        } finally {
            setTriggerLoading(false);
        }
    });

    if (saveButton && savePayload) {
        saveButton.addEventListener('click', (event) => {
            if (!lastStructuredPayload) {
                event.preventDefault();
                setStatus('먼저 AI 구조화를 실행하세요.');
                clearStructuredPayload();

                return;
            }

            savePayload.value = JSON.stringify(lastStructuredPayload);

            if (saveOriginalSummary) {
                saveOriginalSummary.value = String(input.value || '').trim();
            }

            // 사용자가 직접 선택한 구분이 있으면 AI 판별보다 우선해 저장한다.
            if (document.documentElement.dataset.orderGroupTypeTouched === 'true') {
                const groupTypeSubmit = root.querySelector('[data-order-group-type-submit]');
                if (groupTypeSubmit) {
                    groupTypeSubmit.value = root.querySelector('input[name="group_type"]:checked')?.value || '단일';
                }
            }

            const editedLineItems = readLineItemsFromEditor();

            if (editedLineItems) {
                const payload = JSON.parse(JSON.stringify(lastStructuredPayload));

                if (payload.structured && typeof payload.structured === 'object') {
                    payload.structured.line_items = editedLineItems;
                } else {
                    payload.line_items = editedLineItems;
                }

                savePayload.value = JSON.stringify(payload);
            }
        });
    }
}
