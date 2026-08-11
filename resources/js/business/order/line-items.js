export const LINE_ITEM_FIELD_KEYS = [
    'scheduled_time',
    'service_date',
    'service_month',
    'service_day',
    'service_weekday',
    'service_type',
    'location',
    'pickup_location',
    'dropoff_location',
    'flight_number',
    'passenger_count',
    'luggage_count',
    'amount_value',
    'amount_text',
];

function refreshLineItemRowSummary(row) {
    if (!row) {
        return;
    }

    const read = (key) => row.querySelector(`[name$="[${key}]"]`)?.value?.trim() || '';

    const setSummary = (key, text) => {
        const element = row.querySelector(`[data-line-item-summary="${key}"]`);

        if (element) {
            element.textContent = text;
        }
    };

    const time = read('scheduled_time');
    const month = read('service_month');
    const day = read('service_day');
    const weekday = read('service_weekday');
    const serviceDate = read('service_date');
    const serviceType = read('service_type');
    const pickup = read('pickup_location');
    const dropoff = read('dropoff_location');
    const flight = read('flight_number');
    const passenger = read('passenger_count');
    const luggage = read('luggage_count');
    const amountValue = read('amount_value');
    const amountText = read('amount_text');

    setSummary('time', time || '-');

    let dateText = '-';

    if (month && day) {
        dateText = `${month}월${day}일`;

        if (weekday) {
            dateText += ` (${weekday})`;
        }
    } else if (serviceDate) {
        dateText = serviceDate;
    }

    setSummary('date', dateText);
    setSummary('type', serviceType || '-');

    let routeText = '-';

    if (pickup || dropoff) {
        routeText = `${pickup || '?'} → ${dropoff || '?'}`;
    }

    setSummary('route', routeText);
    setSummary('flight', flight || '-');
    setSummary('passenger', passenger ? `${passenger}명` : '-');
    setSummary('luggage', luggage ? `${luggage}개` : '-');

    let amountLabel = '-';

    if (amountText) {
        amountLabel = amountText;
    } else if (amountValue) {
        amountLabel = `${Number(amountValue).toLocaleString('ko-KR')}원`;
    }

    setSummary('amount', amountLabel);
}

function updateOrderPreview(root) {
    const summaryElement = root?.querySelector('[data-order-preview-summary]');

    if (!summaryElement) {
        return;
    }

    const editor = document.querySelector('[data-order-line-items-editor]');
    const firstRow = editor?.querySelector('[data-line-item-row]');
    const read = (key) => firstRow?.querySelector(`[name$="[${key}]"]`)?.value?.trim() || '';

    const pickup = read('pickup_location') || '서울 강남구';
    const dropoff = read('dropoff_location') || '인천공항';
    const time = read('scheduled_time') || '08:42';
    const flight = read('flight_number') || 'KE123';
    const passenger = read('passenger_count') || '';
    const amountValue = read('amount_value') || '';
    const amountText = read('amount_text') || '';
    const statusSelect = root.querySelector('[name="status"]');
    const statusLabel = statusSelect?.selectedOptions?.[0]?.textContent?.trim() || '수락 대기';

    summaryElement.textContent = `${pickup} - ${dropoff} ${time}`;

    const flightElement = root.querySelector('[data-order-preview-flight]');

    if (flightElement) {
        flightElement.textContent = `항공편 ${flight}`;
    }

    const passengerElement = root.querySelector('[data-order-preview-passenger]');

    if (passengerElement) {
        passengerElement.textContent = passenger ? `인원 ${passenger}명` : '-';
    }

    const amountElement = root.querySelector('[data-order-preview-amount]');

    if (amountElement) {
        amountElement.textContent = amountText || (amountValue ? `금액 ${Number(amountValue).toLocaleString('ko-KR')}원` : '-');
    }

    const statusElement = root.querySelector('[data-order-preview-status]');

    if (statusElement) {
        statusElement.textContent = statusLabel;
    }
}
/**
 * 오더 예약의 단일/셋트 구분 선택을 페이지 전체에서 동기화한다.
 * 오더 요약 입력(생성 화면)과 일정 목록 에디터(등록/수정 화면)의 라디오 그룹이
 * 하나로 동작하도록 하며, 사용자가 직접 선택한 경우 이를 우선순위로 기록한다.
 */
export function mountOrderGroupTypeSync() {
    document.addEventListener('change', (event) => {
        const radio = event.target.closest('input[name="group_type"]');

        if (!radio || !radio.checked) {
            return;
        }

        // 사용자가 직접 구분을 선택했으므로 AI 자동 판별보다 선택값을 우선한다.
        document.documentElement.dataset.orderGroupTypeTouched = 'true';

        // 동일한 name을 가진 모든 라디오 그룹(오더 요약 입력 / 일정 목록)을 함께 동기화한다.
        document.querySelectorAll('input[name="group_type"]').forEach((target) => {
            target.checked = target.value === radio.value;
        });

        document.querySelectorAll('[data-order-line-items-kind]').forEach((badge) => {
            badge.textContent = radio.value === '셋트' ? '셋트 일정' : '단일 일정';
        });

        const groupTypeSubmit = document.querySelector('[data-order-group-type-submit]');
        if (groupTypeSubmit) {
            groupTypeSubmit.value = radio.value;
        }

        updateOrderPreview(document);
    });
}

export function mountOrderLineItemEditors() {
    document.querySelectorAll('[data-order-line-items-editor]').forEach((root) => {
        const addButton = root.querySelector('[data-line-items-add]');
        const body = root.querySelector('[data-line-items-body]');
        const template = root.querySelector('template[data-line-items-row]');
        const modal = root.querySelector('[data-line-item-modal]');

        if (!addButton || !body || !template || !modal) {
            return;
        }

        let newIndex = 0;
        let activeRow = null;

        const syncLineItemsKind = () => {
            const kindBadge = root.querySelector('[data-order-line-items-kind]');

            if (!kindBadge) {
                return;
            }

            const checkedType = root.querySelector('input[name="group_type"]:checked')?.value;
            kindBadge.textContent = checkedType === '셋트' ? '셋트 일정' : '단일 일정';
        };

        const getRowField = (row, key) => row.querySelector(`[name$="[${key}]"]`);
        const getModalField = (key) => modal.querySelector(`[data-line-item-field="${key}"]`);

        const openModal = (row) => {
            activeRow = row;

            LINE_ITEM_FIELD_KEYS.forEach((key) => {
                const field = getModalField(key);

                if (field) {
                    field.value = getRowField(row, key)?.value || '';
                }
            });

            modal.hidden = false;
            document.body.classList.add('overflow-hidden');
        };

        const closeModal = () => {
            modal.hidden = true;
            activeRow = null;
            document.body.classList.remove('overflow-hidden');
        };

        const saveModal = () => {
            if (!activeRow) {
                return;
            }

            LINE_ITEM_FIELD_KEYS.forEach((key) => {
                const field = getModalField(key);
                const rowField = getRowField(activeRow, key);

                if (field && rowField) {
                    rowField.value = field.value;
                }
            });

            refreshLineItemRowSummary(activeRow);
            syncLineItemsKind();
            updateOrderPreview(document);
            closeModal();
        };

        const createNewRow = () => {
            newIndex += 1;
            const key = `new_${newIndex}`;
            const fragment = template.content.cloneNode(true);

            fragment.querySelectorAll('[name]').forEach((field) => {
                field.name = field.name.replace('__KEY__', key);
            });

            const emptyRow = body.querySelector('[data-line-items-empty]');
            if (emptyRow) {
                emptyRow.remove();
            }

            const row = fragment.querySelector('[data-line-item-row]');
            body.appendChild(fragment);
            syncLineItemsKind();

            return row;
        };

        addButton.addEventListener('click', () => {
            const row = createNewRow();
            openModal(row);
        });

        body.addEventListener('click', (event) => {
            const editButton = event.target.closest('[data-line-item-edit]');

            if (editButton) {
                const row = editButton.closest('[data-line-item-row]');
                if (row) {
                    openModal(row);
                }

                return;
            }

            const removeButton = event.target.closest('[data-line-items-remove]');

            if (!removeButton) {
                return;
            }

            const row = removeButton.closest('[data-line-item-row]');
            if (row) {
                row.remove();
            }

            syncLineItemsKind();
            updateOrderPreview(document);
        });

        modal.addEventListener('click', (event) => {
            const closeButton = event.target.closest('[data-modal-close]');
            const saveButton = event.target.closest('[data-line-item-save]');

            if (closeButton || event.target.classList.contains('modal__backdrop')) {
                closeModal();

                return;
            }

            if (saveButton) {
                saveModal();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !modal.hidden) {
                closeModal();
            }
        });

        syncLineItemsKind();
    });
}
