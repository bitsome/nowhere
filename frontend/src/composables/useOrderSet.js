import { ref } from 'vue';
import { apiCreateSetOrders } from '../api/orders';
import { getApiErrorMessage } from '../api/client';
import { parseSetLine, toIsoDate, VEHICLE_HINTS } from '../utils/orderCreate';

/**
 * 셋트 운행 등록 — 여러 줄 일괄 입력 파싱과 셋트 저장을 담당한다.
 *
 * @param {object} options
 * @param {import('vue').Ref<boolean>} options.saving
 * @param {import('vue').Ref<string>} options.error
 * @param {import('vue-router').Router} options.router
 * @param {object} options.message naive-ui 메시지
 */
export function useOrderSet({ saving, error, router, message }) {
    const setName = ref('');
    const setLineItems = ref([]);

    const addSetLine = () => {
        setLineItems.value.push({
            scheduled_time: '',
            service_date: null,
            service_time: null,
            service_type: '',
            pickup_location: '',
            dropoff_location: '',
            flight_number: '',
            passenger_count: null,
            luggage_count: null,
            expected_revenue: null,
            vehicle_type: '',
        });
    };

    // ── 셋트: 여러 줄 한 번에 입력 → 각 줄을 운행으로 파싱 ──
    const bulkInput = ref('');
    const bulkError = ref('');

    const convertBulkToSet = () => {
        bulkError.value = '';

        const lines = bulkInput.value
            .split('\n')
            .map((line) => line.trim())
            .filter(Boolean);

        if (lines.length === 0) {
            bulkError.value = '입력할 내용이 없습니다.';
            return;
        }

        const added = [];

        lines.forEach((line) => {
            const item = parseSetLine(line);

            // 노선·날짜·시간 중 하나라도 해석되면 추가 (부분 입력 허용)
            if (item.pickup_location || item.dropoff_location || item.service_date || item.service_time) {
                setLineItems.value.push(item);
                added.push(line);
            } else {
                bulkError.value = '해석하지 못한 줄이 있습니다. 형식을 확인해 주세요.';
            }
        });

        if (added.length) {
            message.success(`셋트 일정 ${added.length}건이 추가되었습니다.`);
            bulkInput.value = '';
        }
    };

    const removeSetLine = (index) => {
        setLineItems.value.splice(index, 1);
    };

    const saveSet = async () => {
        saving.value = true;
        error.value = '';

        try {
            const orders = setLineItems.value.map((item) => {
                const isoDate = toIsoDate(item.service_date);
                const payload = {
                    customer_name: '',
                    vehicle_type: item.vehicle_type || '',
                    service_type: item.service_type || 'pickup',
                    service_date: '',
                    service_time: item.service_time || '',
                    pickup_location: item.pickup_location || '',
                    dropoff_location: item.dropoff_location || '',
                    flight_number: item.flight_number || '',
                    passenger_count: item.passenger_count ?? null,
                    luggage_count: item.luggage_count ?? null,
                    expected_revenue: item.expected_revenue ?? null,
                    reservation_company: '직접예약',
                };

                if (isoDate && /^\d{4}-\d{2}-\d{2}$/.test(isoDate)) {
                    payload.service_date = isoDate;
                    payload.service_datetime = `${isoDate} ${item.service_time || '00:00'}:00`;
                }

                return payload;
            });

            await apiCreateSetOrders({ group_name: setName.value, orders });
            router.push({ name: 'market' });
        } catch (e) {
            error.value = getApiErrorMessage(e, '셋트 등록에 실패했습니다.');
        } finally {
            saving.value = false;
        }
    };

    return {
        setName,
        setLineItems,
        addSetLine,
        bulkInput,
        bulkError,
        VEHICLE_HINTS,
        convertBulkToSet,
        removeSetLine,
        saveSet,
    };
}
