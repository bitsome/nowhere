import { computed, ref } from 'vue';
import { apiOrderStats } from '../api/stats';
import { apiDriverStats } from '../api/driver';
import { getApiErrorMessage } from '../api/client';

/**
 * 대시보드 통계 — 기간별 매출/운행 통계와 기사 오늘 요약을 담당한다.
 *
 * @param {object} options
 * @param {object} options.auth useAuthStore
 */
export function useDashboardStats({ auth }) {
    const loading = ref(true);
    const error = ref('');
    const days = ref(7);
    const stats = ref(null);
    const driverToday = ref(null);

    const load = async () => {
        try {
            const { data } = await apiOrderStats(days.value);
            stats.value = data.data;
        } catch (e) {
            error.value = getApiErrorMessage(e, '통계를 불러오지 못했습니다.');
        } finally {
            loading.value = false;
        }
    };

    const changeDays = (value) => {
        days.value = value;
        loading.value = true;
        load();
    };

    // ── 기사 오늘 요약 (드라이버 전용) ──
    const isDriver = computed(() => auth.user?.role === 'Driver');

    const loadDriverToday = async () => {
        try {
            const { data } = await apiDriverStats();
            driverToday.value = data.data;
        } catch {
            driverToday.value = null;
        }
    };

    const formatDuration = (seconds) => {
        const hours = Math.floor((seconds ?? 0) / 3600);
        const minutes = Math.floor(((seconds ?? 0) % 3600) / 60);

        return hours > 0 ? `${hours}시간 ${minutes}분` : `${minutes}분`;
    };

    const summary = computed(() => stats.value?.summary ?? {});

    // 7일 매출 차트 — 최대값 기준 막대 높이 계산
    const revenueSeries = computed(() => stats.value?.daily ?? []);
    const maxRevenue = computed(() => Math.max(1, ...revenueSeries.value.map((d) => d.revenue)));
    const revenuePercent = (revenue) => `${Math.max(4, Math.round(((revenue ?? 0) / maxRevenue.value) * 100))}%`;
    const maxCount = computed(() => Math.max(1, ...revenueSeries.value.map((d) => d.count)));
    const countPercent = (count) => `${Math.max(4, Math.round(((count ?? 0) / maxCount.value) * 100))}%`;
    const totalRevenue = computed(() => revenueSeries.value.reduce((sum, d) => sum + (d.revenue ?? 0), 0));

    // 월별 매출 차트 (최근 6개월)
    const monthlySeries = computed(() => stats.value?.monthly ?? []);
    const maxMonthRevenue = computed(() => Math.max(1, ...monthlySeries.value.map((d) => d.revenue)));
    const monthRevenuePercent = (revenue) => `${Math.max(4, Math.round(((revenue ?? 0) / maxMonthRevenue.value) * 100))}%`;
    const maxMonthCount = computed(() => Math.max(1, ...monthlySeries.value.map((d) => d.count)));
    const monthCountPercent = (count) => `${Math.max(4, Math.round(((count ?? 0) / maxMonthCount.value) * 100))}%`;
    const totalMonthRevenue = computed(() => monthlySeries.value.reduce((sum, d) => sum + (d.revenue ?? 0), 0));

    return {
        loading,
        error,
        days,
        stats,
        driverToday,
        load,
        changeDays,
        isDriver,
        loadDriverToday,
        formatDuration,
        summary,
        revenueSeries,
        maxRevenue,
        revenuePercent,
        maxCount,
        countPercent,
        totalRevenue,
        monthlySeries,
        maxMonthRevenue,
        monthRevenuePercent,
        maxMonthCount,
        monthCountPercent,
        totalMonthRevenue,
    };
}
