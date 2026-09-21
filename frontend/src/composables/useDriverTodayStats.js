import { ref } from 'vue';
import { apiDriverStats } from '../api/driver';

/**
 * 기사 오늘 요약 — 온라인 시간·완료 건수·수입을 한 곳에서 불러온다.
 *
 * 홈 대시보드·프로필·더보기가 같은 값을 각자 ref·fetch·포맷 함수로 따로 구현하고 있어,
 * 한쪽만 고치면 화면마다 다른 숫자가 나올 수 있었다. 기준을 이 모듈 하나로 모은다.
 */
export function useDriverTodayStats() {
    const todayStats = ref(null);

    const loadTodayStats = async () => {
        try {
            const { data } = await apiDriverStats();
            todayStats.value = data.data;
        } catch {
            // 요약을 못 받아도 화면은 살아 있어야 한다 — 각 화면이 null 을 '-' 로 표시한다
            todayStats.value = null;
        }
    };

    /** 초 단위 온라인 시간 → '2시간 30분' */
    const formatDuration = (seconds) => {
        const hours = Math.floor((seconds ?? 0) / 3600);
        const minutes = Math.floor(((seconds ?? 0) % 3600) / 60);

        return hours > 0 ? `${hours}시간 ${minutes}분` : `${minutes}분`;
    };

    return {
        todayStats,
        loadTodayStats,
        formatDuration,
    };
}
