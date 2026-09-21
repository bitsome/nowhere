import { describe, expect, it, vi } from 'vitest';
import { useDriverTodayStats } from './useDriverTodayStats';
import { apiDriverStats } from '../api/driver';

vi.mock('../api/driver', () => ({
    apiDriverStats: vi.fn(),
}));

/**
 * 기사 오늘 요약은 홈·프로필·더보기 세 화면이 함께 쓴다.
 * 한 곳만 어긋나면 같은 시각에 화면마다 다른 숫자가 보이므로 포맷과 실패 처리를 고정한다.
 */
describe('useDriverTodayStats', () => {
    it('초 단위 온라인 시간을 시간·분으로 읽는다', () => {
        const { formatDuration } = useDriverTodayStats();

        expect(formatDuration(5400)).toBe('1시간 30분');
        expect(formatDuration(600)).toBe('10분');
        expect(formatDuration(0)).toBe('0분');
        expect(formatDuration(null)).toBe('0분');
    });

    it('받아온 요약을 그대로 담는다', async () => {
        apiDriverStats.mockResolvedValue({ data: { data: { today_completed: 3, today_income: 120000 } } });

        const { todayStats, loadTodayStats } = useDriverTodayStats();
        await loadTodayStats();

        expect(todayStats.value.today_completed).toBe(3);
        expect(todayStats.value.today_income).toBe(120000);
    });

    it('조회에 실패해도 화면을 막지 않는다 — 빈 값으로 두고 각 화면이 "-" 를 쓴다', async () => {
        apiDriverStats.mockRejectedValue(new Error('network'));

        const { todayStats, loadTodayStats } = useDriverTodayStats();
        await loadTodayStats();

        expect(todayStats.value).toBeNull();
    });
});
