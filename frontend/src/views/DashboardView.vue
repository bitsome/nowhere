<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { apiBatchSettle, apiOrders } from '../api/orders';
import { apiOrderStats } from '../api/stats';
import { apiDriverStats } from '../api/driver';
import { getApiErrorMessage } from '../api/client';
import { useAuthStore } from '../stores/auth';
import { useUiStore } from '../stores/ui';

const ui = useUiStore();
const auth = useAuthStore();
const router = useRouter();
const loading = ref(true);
const error = ref('');
const days = ref(7);
const stats = ref(null);
const driverToday = ref(null);
let timer = null;

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

const goMyOrders = () => {
    router.push({ name: 'order-create' });
};

const goOrder = (id) => {
    router.push({ name: 'order-detail', params: { id } });
};

const formatWon = (value) => (value ?? 0).toLocaleString();

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
const totalMonthRevenue = computed(() => monthlySeries.value.reduce((sum, d) => sum + (d.revenue ?? 0), 0));

// ── 정산 처리 ──
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

onMounted(() => {
    load();
    if (isDriver.value) {
        loadDriverToday();
    }
    // 30초마다 통계 자동 갱신
    timer = setInterval(() => {
        load();
        if (isDriver.value) {
            loadDriverToday();
        }
    }, 30000);
});

// 헤더 '···' 메뉴의 새로고침 수신
watch(
    () => ui.actionSeq,
    () => {
        if (ui.actionName === 'refresh') {
            load();
        }
    },
);

onBeforeUnmount(() => clearInterval(timer));
</script>

<template>
    <div>
        <div class="page-head">
            <n-radio-group v-model:value="days" size="large" @update:value="changeDays">
                <n-radio-button :value="7">7일</n-radio-button>
                <n-radio-button :value="30">30일</n-radio-button>
            </n-radio-group>
        </div>

        <!-- 기사 오늘 요약 — 온라인 시간 / 완료 운행 / 수입 -->
        <n-card v-if="isDriver" :bordered="true" class="dash-block dash-driver">
            <div class="dash-driver__head">
                <strong>오늘의 운행</strong>
                <span class="dash-driver__status">
                    <span class="dash-driver__dot" :class="{ 'dash-driver__dot--active': driverToday?.status === 'online' || driverToday?.status === 'on_trip' }" />
                    {{ driverToday?.status_label ?? '-' }}
                </span>
            </div>
            <div class="dash-driver__grid">
                <div class="dash-driver__cell">
                    <span class="dash-driver__label">온라인 시간</span>
                    <strong>{{ driverToday ? formatDuration(driverToday.online_seconds) : '-' }}</strong>
                </div>
                <div class="dash-driver__cell">
                    <span class="dash-driver__label">완료 운행</span>
                    <strong>{{ driverToday?.today_completed ?? '-' }}<small>건</small></strong>
                </div>
                <div class="dash-driver__cell">
                    <span class="dash-driver__label">오늘 수입</span>
                    <strong>{{ driverToday ? formatWon(driverToday.today_income) : '-' }}<small>원</small></strong>
                </div>
            </div>
        </n-card>

        <n-alert v-if="error" type="error" :show-icon="true" class="dash-block">
            {{ error }}
            <template #action>
                <n-button size="small" :loading="loading" @click="load">다시 시도</n-button>
            </template>
        </n-alert>

        <n-spin :show="loading" class="dash-body">
            <template v-if="stats">
                <!-- 오늘/내일 운행 -->
                <div class="dash-upcoming">
                    <button type="button" class="dash-upcoming__card dash-upcoming__card--today" @click="goMyOrders('진행중')">
                        <span class="dash-upcoming__label">오늘 운행</span>
                        <strong class="dash-upcoming__value">{{ stats.upcoming?.today ?? 0 }}<small>건</small></strong>
                    </button>
                    <button type="button" class="dash-upcoming__card dash-upcoming__card--tomorrow" @click="goMyOrders('진행중')">
                        <span class="dash-upcoming__label">내일 운행</span>
                        <strong class="dash-upcoming__value">{{ stats.upcoming?.tomorrow ?? 0 }}<small>건</small></strong>
                    </button>
                </div>

                <!-- 오늘/내일 운행 리스트 -->
                <div class="dash-schedule">
                    <div class="dash-card">
                        <div class="dash-card__head">
                            <strong>오늘 운행</strong>
                            <span class="dash-card__hint">{{ stats.upcoming?.today ?? 0 }}건</span>
                        </div>
                        <div v-if="stats.upcoming?.todayList?.length" class="mini-list">
                            <button
                                v-for="item in stats.upcoming.todayList"
                                :key="item.id"
                                type="button"
                                class="mini-list__row"
                                @click="goOrder(item.id)"
                            >
                                <span class="mini-list__time">{{ item.time }}</span>
                                <span class="mini-list__route">{{ item.route }}</span>
                                <span class="mini-list__meta">{{ item.serviceLabel }}{{ item.passengerCount ? ` · ${item.passengerCount}명` : '' }}</span>
                            </button>
                        </div>
                        <p v-else class="mini-list__empty">오늘 운행이 없습니다.</p>
                    </div>
                    <div class="dash-card">
                        <div class="dash-card__head">
                            <strong>내일 운행</strong>
                            <span class="dash-card__hint">{{ stats.upcoming?.tomorrow ?? 0 }}건</span>
                        </div>
                        <div v-if="stats.upcoming?.tomorrowList?.length" class="mini-list">
                            <button
                                v-for="item in stats.upcoming.tomorrowList"
                                :key="item.id"
                                type="button"
                                class="mini-list__row"
                                @click="goOrder(item.id)"
                            >
                                <span class="mini-list__time">{{ item.time }}</span>
                                <span class="mini-list__route">{{ item.route }}</span>
                                <span class="mini-list__meta">{{ item.serviceLabel }}{{ item.passengerCount ? ` · ${item.passengerCount}명` : '' }}</span>
                            </button>
                        </div>
                        <p v-else class="mini-list__empty">내일 운행이 없습니다.</p>
                    </div>
                </div>

                <!-- 요약 카드 -->
                <div class="dash-grid">
                    <div class="dash-card">
                        <span class="dash-card__label">총 운행</span>
                        <strong class="dash-card__value">{{ summary.total }}</strong>
                        <span class="dash-card__hint">기간 내 등록 건수</span>
                    </div>
                    <div class="dash-card">
                        <span class="dash-card__label">진행중</span>
                        <strong class="dash-card__value">{{ summary.inProgress }}</strong>
                        <span class="dash-card__hint">완료·취소 제외</span>
                    </div>
                    <div class="dash-card">
                        <span class="dash-card__label">완료</span>
                        <strong class="dash-card__value">{{ summary.completed }}</strong>
                        <span class="dash-card__hint">완료 + 정산</span>
                    </div>
                    <div class="dash-card dash-card--accent">
                        <span class="dash-card__label">매출</span>
                        <strong class="dash-card__value">{{ formatWon(summary.revenue) }}원</strong>
                        <span class="dash-card__hint">{{ stats.period.from }} ~ {{ stats.period.to }}</span>
                    </div>
                    <div v-if="summary.rating != null" class="dash-card">
                        <span class="dash-card__label">평점</span>
                        <strong class="dash-card__value">★ {{ summary.rating }}<small class="dash-card__unit">/ 5</small></strong>
                        <span class="dash-card__hint">리뷰 {{ summary.reviewCount }}개</span>
                    </div>
                </div>

                <!-- 정산 현황 -->
                <div class="dash-card dash-block">
                    <div class="dash-card__head">
                        <strong>정산 현황</strong>
                        <n-button
                            type="primary"
                            size="small"
                            round
                            :loading="settling"
                            :disabled="summary.settlementPending === 0"
                            @click="settleAll"
                        >
                            정산 처리
                        </n-button>
                    </div>
                    <div class="settle-grid">
                        <div class="settle-item">
                            <span class="settle-item__label">정산 대기</span>
                            <strong class="settle-item__value">{{ formatWon(summary.settlementPending) }}원</strong>
                        </div>
                        <div class="settle-item">
                            <span class="settle-item__label">정산 완료</span>
                            <strong class="settle-item__value">{{ formatWon(summary.settled) }}원</strong>
                        </div>
                    </div>
                    <p v-if="settleMessage" class="settle-message">{{ settleMessage }}</p>
                </div>

                <!-- 7일 매출 + 운행 건수 차트 -->
                <div class="dash-card dash-block">
                    <div class="dash-card__head">
                        <strong>일별 현황</strong>
                        <span class="dash-card__hint">합계 {{ formatWon(totalRevenue) }}원</span>
                    </div>
                    <div class="bar-chart">
                        <div v-for="(item, index) in revenueSeries" :key="index" class="bar-chart__col">
                            <div class="bar-chart__track">
                                <div
                                    class="bar-chart__bar bar-chart__bar--count"
                                    :style="{ height: countPercent(item.count) }"
                                    :title="`${item.count}건`"
                                >
                                    {{ item.count > 0 ? item.count : '' }}
                                </div>
                                <div
                                    class="bar-chart__bar bar-chart__bar--revenue"
                                    :style="{ height: revenuePercent(item.revenue) }"
                                    :title="`${formatWon(item.revenue)}원`"
                                />
                            </div>
                            <span class="bar-chart__date">{{ item.date }}</span>
                        </div>
                    </div>
                </div>

                <div class="dash-columns">
                    <!-- 월별 매출 추이 -->
                    <div class="dash-card dash-block">
                        <div class="dash-card__head">
                            <strong>월별 현황</strong>
                            <span class="dash-card__hint">최근 6개월 · {{ formatWon(totalMonthRevenue) }}원</span>
                        </div>
                        <div class="bar-chart bar-chart--monthly">
                            <div v-for="(item, index) in monthlySeries" :key="index" class="bar-chart__col">
                                <div class="bar-chart__track">
                                    <div
                                        class="bar-chart__bar bar-chart__bar--count"
                                        :style="{ height: countPercent(item.count) }"
                                        :title="`${item.count}건`"
                                    >
                                        {{ item.count > 0 ? item.count : '' }}
                                    </div>
                                    <div
                                        class="bar-chart__bar bar-chart__bar--revenue"
                                        :style="{ height: monthRevenuePercent(item.revenue) }"
                                        :title="`${formatWon(item.revenue)}원`"
                                    />
                                </div>
                                <span class="bar-chart__date">{{ item.month }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- 상태 분포 -->
                    <div class="dash-card">
                        <div class="dash-card__head">
                            <strong>상태별 분포</strong>
                        </div>
                        <div class="status-list">
                            <div
                                v-for="item in stats.statusDistribution"
                                :key="item.status"
                                class="status-list__row"
                            >
                                <span class="status-list__label">{{ item.label }}</span>
                                <span class="status-list__bar">
                                    <span
                                        class="status-list__fill"
                                        :style="{
                                            width: `${Math.max(3, (item.count / Math.max(1, summary.total)) * 100)}%`,
                                        }"
                                    />
                                </span>
                                <span class="status-list__count">{{ item.count }}건</span>
                            </div>
                            <n-empty
                                v-if="stats.statusDistribution.length === 0"
                                description="기간 내 운행이 없습니다."
                                :image-size="60"
                            />
                        </div>
                    </div>

                    <!-- 정산 현황 -->
                    <div class="dash-card">
                        <div class="dash-card__head">
                            <strong>정산 현황</strong>
                        </div>
                        <div class="settle-list">
                            <div class="settle-item">
                                <span class="settle-item__label">정산 완료</span>
                                <strong class="settle-item__value">{{ formatWon(summary.settled) }}원</strong>
                            </div>
                            <div class="settle-item">
                                <span class="settle-item__label">정산 대기</span>
                                <strong class="settle-item__value">{{ formatWon(summary.settlementPending) }}원</strong>
                            </div>
                            <div class="settle-bar">
                                <div
                                    class="settle-bar__fill"
                                    :style="{
                                        width: `${summary.revenue > 0 ? (summary.settled / summary.revenue) * 100 : 0}%`,
                                    }"
                                />
                            </div>
                            <p class="settle-hint">완료된 운행은 상세에서 "정산" 상태로 전환하면 정산 완료에 반영됩니다.</p>
                        </div>
                    </div>
                </div>
            </template>

            <n-empty v-else-if="!loading" description="통계 데이터가 없습니다." :image-size="80" />
        </n-spin>
    </div>
</template>

<style scoped>
.page-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 14px;
}

.dash-block {
    margin-bottom: 16px;
    border-radius: 16px;
}

.dash-body {
    display: block;
    min-height: 200px;
}

/* 오늘/내일 운행 위젯 */
.dash-upcoming {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 14px;
    margin-bottom: 16px;
}

.dash-upcoming__card {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 18px;
    border-radius: 16px;
    border: 1px solid var(--border);
    background: var(--surface);
    color: var(--text);
    text-align: left;
    cursor: pointer;
    transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.1s ease;
}

.dash-upcoming__card:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
}

.dash-upcoming__card--today {
    border-color: rgba(24, 160, 88, 0.35);
    background: linear-gradient(135deg, rgba(24, 160, 88, 0.08), rgba(24, 160, 88, 0.02));
}

.dash-upcoming__card--tomorrow {
    border-color: rgba(255, 169, 64, 0.35);
    background: linear-gradient(135deg, rgba(255, 169, 64, 0.1), rgba(255, 169, 64, 0.02));
}

.dash-upcoming__label {
    color: var(--text-muted);
    font-size: 13px;
}

.dash-upcoming__value {
    font-size: 26px;
    font-weight: 700;
}

.dash-upcoming__value small {
    margin-left: 2px;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-muted);
}

/* 오늘/내일 운행 미니리스트 */
.dash-schedule {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 16px;
    margin-bottom: 16px;
}

.mini-list {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.mini-list__row {
    display: flex;
    align-items: baseline;
    gap: 10px;
    width: 100%;
    border: 0;
    background: transparent;
    color: var(--text);
    text-align: left;
    cursor: pointer;
    padding: 8px 6px;
    border-radius: 8px;
    transition: background 0.12s ease;
}

.mini-list__row:hover {
    background: color-mix(in srgb, var(--brand) 8%, transparent);
}

.mini-list__time {
    font-weight: 700;
    font-size: 13px;
    flex-shrink: 0;
    min-width: 42px;
}

.mini-list__route {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 13px;
}

.mini-list__meta {
    flex-shrink: 0;
    color: var(--text-muted);
    font-size: 12px;
    white-space: nowrap;
}

.mini-list__empty {
    margin: 8px 0 0;
    padding: 10px 6px;
    color: var(--text-muted);
    font-size: 13px;
    border-radius: 8px;
    background: rgba(0, 0, 0, 0.02);
}

html.dark .mini-list__empty {
    background: rgba(255, 255, 255, 0.04);
}

.dash-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 14px;
    margin-bottom: 16px;
}

.dash-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 18px;
}

.dash-card--accent {
    border-color: color-mix(in srgb, var(--brand) 40%, transparent);
    background: linear-gradient(135deg, color-mix(in srgb, var(--brand) 6%, transparent), color-mix(in srgb, var(--status-accepted) 6%, transparent));
}

.dash-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
}

.dash-card__head strong {
    font-size: 15px;
}

.dash-card__label {
    display: block;
    color: var(--text-muted);
    font-size: 13px;
}

.dash-card__value {
    display: block;
    margin: 6px 0 4px;
    font-size: 22px;
    font-weight: 700;
}

.dash-card__unit {
    font-size: 13px;
    font-weight: 500;
    color: var(--text-muted);
}

/* 정산 현황 */
.settle-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-top: 12px;
}

.settle-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 14px 16px;
    border: 1px solid var(--border);
    border-radius: 12px;
    background: rgba(128, 128, 128, 0.05);
}

.settle-item__label {
    color: var(--text-muted);
    font-size: 12px;
    font-weight: 600;
}

.settle-item__value {
    font-size: 18px;
    font-weight: 700;
}

.settle-message {
    margin: 10px 0 0;
    font-size: 13px;
    color: var(--text-muted);
}

.dash-card__hint {
    color: var(--text-muted);
    font-size: 12px;
}

/* 바 차트 */
.bar-chart {
    display: flex;
    align-items: flex-end;
    gap: 8px;
    height: 180px;
    padding-top: 8px;
}

.bar-chart__col {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex: 1;
    min-width: 0;
    height: 100%;
}

.bar-chart__track {
    display: flex;
    align-items: flex-end;
    justify-content: center;
    gap: 3px;
    flex: 1;
    width: 100%;
}

.bar-chart__bar {
    border-radius: 4px 4px 0 0;
    transition: height 0.3s ease;
}

.bar-chart__bar--count {
    width: 10px;
    background: var(--brand);
    color: #ffffff;
    font-size: 10px;
    text-align: center;
    line-height: 1.2;
}

.bar-chart__bar--revenue {
    width: 14px;
    background: #18a058;
}

.bar-chart__date {
    margin-top: 6px;
    color: var(--text-muted);
    font-size: 11px;
}

.dash-columns {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 16px;
}

/* 상태 분포 */
.status-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.status-list__row {
    display: flex;
    align-items: center;
    gap: 10px;
}

.status-list__label {
    width: 56px;
    font-size: 13px;
    flex-shrink: 0;
}

.status-list__bar {
    flex: 1;
    height: 8px;
    border-radius: 4px;
    background: rgba(0, 0, 0, 0.08);
    overflow: hidden;
}

html.dark .status-list__bar {
    background: rgba(255, 255, 255, 0.1);
}

.status-list__fill {
    display: block;
    height: 100%;
    border-radius: 4px;
    background: var(--brand);
}

.status-list__count {
    width: 40px;
    text-align: right;
    color: var(--text-muted);
    font-size: 12px;
}

/* 정산 */
.settle-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.settle-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.settle-item__label {
    color: var(--text-muted);
    font-size: 13px;
}

.settle-item__value {
    font-size: 16px;
    font-weight: 700;
}

.settle-bar {
    height: 10px;
    border-radius: 5px;
    background: rgba(0, 0, 0, 0.08);
    overflow: hidden;
}

html.dark .settle-bar {
    background: rgba(255, 255, 255, 0.1);
}

.settle-bar__fill {
    display: block;
    height: 100%;
    border-radius: 5px;
    background: #18a058;
    transition: width 0.3s ease;
}

.settle-hint {
    color: var(--text-muted);
    font-size: 12px;
    line-height: 1.5;
}

.dash-columns {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 16px;
}

/* ── 기사 오늘 요약 ── */
.dash-driver__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 14px;
}

.dash-driver__status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 700;
    color: var(--text-muted);
}

.dash-driver__dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--text-muted);
}

.dash-driver__dot--active {
    background: var(--status-completed);
}

.dash-driver__grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
}

.dash-driver__cell {
    display: flex;
    flex-direction: column;
    gap: 3px;
    padding: 12px;
    border: 1px solid var(--border);
    border-radius: 12px;
    background: var(--bg);
}

.dash-driver__label {
    color: var(--text-muted);
    font-size: 11px;
}

.dash-driver__cell strong {
    font-size: 17px;
}

.dash-driver__cell small {
    font-size: 11px;
    font-weight: 600;
    color: var(--text-muted);
}
</style>
