import { ref } from 'vue';
import { apiBatchSettle, apiOrders } from '../api/orders';
import { getApiErrorMessage } from '../api/client';

/**
 * 완료 운행 일괄 정산 — 완료 목록 조회 후 선택 정산 처리.
 *
 * @param {object} options
 * @param {() => Promise<void>} options.load 정산 후 통계 새로고침
 */
export function useBatchSettle({ load }) {
    const settling = ref(false);
    const settleMessage = ref('');

    const settleAll = async () => {
        settling.value = true;
        settleMessage.value = '';

        try {
            const { data } = await apiOrders({ scope: 'mine', tab: '완료', per_page: 100 });
            const rows = Array.isArray(data.data) ? data.data : data.data?.data ?? [];
            const ids = rows.filter((row) => row.status === 'completed').map((row) => row.id);

            if (!ids.length) {
                settleMessage.value = '정산 대기 운행이 없습니다.';
                return;
            }

            const result = await apiBatchSettle(ids);
            settleMessage.value = `${result.data.settled ?? ids.length}건이 정산 완료되었습니다.`;
            await load();
        } catch (e) {
            settleMessage.value = getApiErrorMessage(e, '정산에 실패했습니다.');
        } finally {
            settling.value = false;
        }
    };

    return {
        settling,
        settleMessage,
        settleAll,
    };
}
