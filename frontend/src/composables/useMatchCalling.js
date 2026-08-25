import { computed } from 'vue';
import { getApiErrorMessage } from '../api/client';

/**
 * 콜링 상태 — 온라인 + 매칭 켜짐 + 활성 설정이 있을 때 '콜링 중'.
 *
 * @param {object} options
 * @param {object} options.driver useDriverStore
 * @param {import('vue').Ref<Array>} options.matchPrefs 매칭 설정 목록
 * @param {object} options.message naive-ui message
 */
export function useMatchCalling({ driver, matchPrefs, message }) {
    const isCalling = computed(
        () => driver.isOnline && driver.matchEnabled && matchPrefs.value.some((p) => p.is_active),
    );

    const callingHint = computed(() => {
        if (!driver.matchEnabled) {
            return '매칭이 꺼져 있어 콜링이 중지되었습니다';
        }
        if (!driver.isOnline) {
            return '온라인 상태일 때만 콜링이 동작합니다';
        }
        if (!matchPrefs.value.some((p) => p.is_active)) {
            return '활성 매칭 설정이 없습니다 — 아래에서 등록해 주세요';
        }

        return '조건에 맞는 운행을 계속 찾는 중입니다';
    });

    const toggleCalling = async (enabled) => {
        try {
            await driver.setMatchEnabled(enabled);
            message.success(enabled ? '콜링을 시작했습니다.' : '콜링을 중지했습니다.');
        } catch (e) {
            message.error(getApiErrorMessage(e, '콜링 상태 변경에 실패했습니다.'));
        }
    };

    return {
        isCalling,
        callingHint,
        toggleCalling,
    };
}
