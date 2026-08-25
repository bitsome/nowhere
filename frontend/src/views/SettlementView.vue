<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useMessage } from 'naive-ui';
import { apiOrders } from '../api/orders';
import { apiSettlements } from '../api/driver';
import { useBatchSettle } from '../composables/useBatchSettle';
import { getApiErrorMessage } from '../api/client';
import { statusColorVar } from '../utils/colors';
import { useAuthStore } from '../stores/auth';
import EmptyState from '../components/common/EmptyState.vue';
import BaseIcon from '../components/common/BaseIcon.vue';

const router = useRouter();
const message = useMessage();
const auth = useAuthStore();

// 정산 관리 — '내역'(완료/정산 조회) / '대기'(정산 처리 전 운행 일괄 정산)
const viewTab = ref('history');

// 정산 대기 — 등록자만 처리할 수 있다 (진행자는 등록자가 정산하면 '정산 완료'로 전환됨)
const isRegistrant = computed(() => auth.user?.role !== 'Driver');
const pendingRows = ref([]);
const pendingLoading = ref(false);
const settle = useBatchSettle({ load: loadPending });
const { settling, settleMessage, settleAll } = settle;

const loadPending = async () => {
    pendingLoading.value = true;

    try {
        const { data } = await apiOrders({ scope: 'mine', tab: '완료', per_page: 100 });
        const rows = Array.isArray(data.data) ? data.data : data.data?.data ?? [];
        pendingRows.value = rows.filter((row) => row.status === 'completed');
    } catch (e) {
        message.error(getApiErrorMessage(e, '정산 대기 목록을 불러오지 못했습니다.'));
    } finally {
        pendingLoading.value = false;
    }
};

const switchTab = (key) => {
    viewTab.value = key;

    if (key === 'pending') {
        loadPending();
    }
};

// 정산 내역 — 기간별 완료 운행과 금액
const RANGES = [
    { label: '이번 주', value: 'week' },
    { label: '이번 달', value: 'month' },
    { label: '지난 달', value: 'last-month' },
    { label: '전체', value: 'all' },
];

const range = ref('week');
const rows = ref([]);
const summary = ref(null);
const loading = ref(false);

const toIso = (d) => d.toISOString().slice(0, 10);

// 선택 기간을 from/to(YYYY-MM-DD)로 변환
const rangeParams = (key) => {
    const now = new Date();
    const y = now.getFullYear();
    const m = now.getMonth();

    if (key === 'week') {
        const start = new Date(now);
        start.setDate(now.getDate() - ((now.getDay() + 6) % 7)); // 월요일 시작
        start.setHours(0, 0, 0, 0);

        return { from: toIso(start) };
    }
    if (key === 'month') {
        return { from: toIso(new Date(y, m, 1)) };
    }
    if (key === 'last-month') {
        return { from: toIso(new Date(y, m - 1, 1)), to: toIso(new Date(y, m, 0)) };
    }

    return {};
};

const totalAmount = computed(() => summary.value?.total_amount ?? 0);
const avgAmount = computed(() => {
    const count = summary.value?.count ?? 0;

    return count ? Math.round(totalAmount.value / count) : 0;
});

const load = async () => {
    loading.value = true;

    try {
        const { data } = await apiSettlements(rangeParams(range.value));
        rows.value = data.data?.data ?? [];
        summary.value = data.data?.summary ?? null;
    } catch (e) {
        message.error(getApiErrorMessage(e, '정산 내역을 불러오지 못했습니다.'));
    } finally {
        loading.value = false;
    }
};

const switchRange = (key) => {
    range.value = key;
    load();
};

// 정산된 운행 상세로 이동
const openOrder = (row) => {
    if (row.id) {
        router.push({ name: 'order-detail', params: { id: row.id } });
    }
};

const statusColor = (row) => statusColorVar[row.status] ?? 'var(--status-completed)';

// 정산 대기 목록의 금액 — expected_revenue가 있으면 원화, 없으면 amount 텍스트 그대로
const formatPendingAmount = (row) => {
    const revenue = Number(row.expected_revenue);

    if (revenue > 0) {
        return `${revenue.toLocaleString()}원`;
    }

    const raw = row.amount_text ?? row.amount ?? '';

    return raw ? String(raw) : '';
};

onMounted(load);
</script>

<template>
    <div class="settle-page">
        <div class="page-head">
            <div>
                <p class="page-head__desc">완료 운행의 정산 내역을 확인하고, 정산 대기 운행을 처리합니다.</p>
            </div>
            <div class="page-head__actions">
                <n-button quaternary round title="새로고침" @click="viewTab === 'history' ? load() : loadPending()">
                    <BaseIcon name="refresh" :size="18" />
                </n-button>
            </div>
        </div>

        <!-- 정산 관리 탭 — 내역 / 대기 -->
        <div class="settle-tabs">
            <n-radio-group v-model:value="viewTab" size="small" @update:value="switchTab">
                <n-radio-button value="history">정산 내역</n-radio-button>
                <n-radio-button value="pending">정산 대기</n-radio-button>
            </n-radio-group>
        </div>

        <!-- ── 정산 내역 — 기간별 완료 운행 조회 ── -->
        <template v-if="viewTab === 'history'">
            <div class="settle-tabs">
                <n-radio-group v-model:value="range" size="small" @update:value="switchRange">
                    <n-radio-button v-for="r in RANGES" :key="r.value" :value="r.value">
                        {{ r.label }}
                    </n-radio-button>
                </n-radio-group>
            </div>

            <!-- 기간 요약 -->
            <div class="settle-summary">
                <div class="settle-summary__cell">
                    <span>총 수익</span>
                    <strong>{{ totalAmount.toLocaleString() }}원</strong>
                </div>
                <div class="settle-summary__cell">
                    <span>완료 운행</span>
                    <strong>{{ summary?.count ?? 0 }}건</strong>
                </div>
                <div class="settle-summary__cell">
                    <span>건당 평균</span>
                    <strong>{{ avgAmount.toLocaleString() }}원</strong>
                </div>
            </div>

            <div v-if="loading" class="settle-list">
                <div v-for="n in 4" :key="n" class="sk-card settle-skeleton">
                    <div class="sk-line sk-line--md" style="width: 40%" />
                    <div class="sk-line" style="margin-top: 10px" />
                    <div class="sk-line" style="margin-top: 8px; width: 55%" />
                </div>
            </div>

            <EmptyState
                v-else-if="!rows.length"
                icon="wallet"
                title="정산 내역이 없습니다"
                hint="완료된 운행의 금액이 여기에 표시됩니다"
            />

            <div v-else class="settle-list">
                <article
                    v-for="row in rows"
                    :key="row.id"
                    class="settle-item"
                    role="button"
                    tabindex="0"
                    @click="openOrder(row)"
                    @keydown.enter="openOrder(row)"
                >
                    <div class="settle-item__top">
                        <span class="settle-item__date">
                            {{ row.service_date }}<em v-if="row.service_time">{{ row.service_time }}</em>
                        </span>
                        <span class="settle-item__amount">{{ row.amount.toLocaleString() }}원</span>
                    </div>
                    <div class="settle-item__route">
                        <BaseIcon name="map" :size="14" class="settle-item__route-icon" />
                        <span>{{ row.pickup_location || '출발지' }} → {{ row.dropoff_location || '도착지' }}</span>
                    </div>
                    <div class="settle-item__meta">
                        <span class="settle-item__tag" :style="{ background: statusColor(row), borderColor: statusColor(row) }">
                            {{ row.status_label }}
                        </span>
                        <span class="settle-item__order-no">{{ row.order_number }}</span>
                        <span class="settle-item__more">
                            운행 보기 <BaseIcon name="arrow-forward" :size="13" />
                        </span>
                    </div>
                </article>
            </div>
        </template>

        <!-- ── 정산 대기 — 완료 운행 일괄 정산 ── -->
        <template v-else>
            <div class="settle-pending-head">
                <div>
                    <strong>정산 대기 {{ pendingRows.length }}건</strong>
                    <p class="settle-pending-head__desc">
                        {{ isRegistrant
                            ? '완료된 운행을 정산 처리하면 드라이버 화면에 정산 완료로 표시됩니다.'
                            : '등록자가 정산을 처리하면 정산 완료로 전환됩니다.' }}
                    </p>
                </div>
                <n-button
                    v-if="isRegistrant"
                    type="primary"
                    round
                    :loading="settling"
                    :disabled="pendingRows.length === 0"
                    @click="settleAll"
                >
                    전체 정산
                </n-button>
            </div>

            <p v-if="settleMessage" class="settle-message">{{ settleMessage }}</p>

            <div v-if="pendingLoading" class="settle-list">
                <div v-for="n in 3" :key="n" class="sk-card settle-skeleton">
                    <div class="sk-line sk-line--md" style="width: 40%" />
                    <div class="sk-line" style="margin-top: 10px" />
                    <div class="sk-line" style="margin-top: 8px; width: 55%" />
                </div>
            </div>

            <EmptyState
                v-else-if="!pendingRows.length"
                icon="wallet"
                title="정산 대기 운행이 없습니다"
                hint="완료된 운행이 정산을 기다리면 여기에 표시됩니다"
            />

            <div v-else class="settle-list">
                <article
                    v-for="row in pendingRows"
                    :key="row.id"
                    class="settle-item"
                    role="button"
                    tabindex="0"
                    @click="openOrder(row)"
                    @keydown.enter="openOrder(row)"
                >
                    <div class="settle-item__top">
                        <span class="settle-item__date">
                            {{ row.service_date }}<em v-if="row.service_time">{{ row.service_time }}</em>
                        </span>
                        <span class="settle-item__amount">{{ formatPendingAmount(row) }}</span>
                    </div>
                    <div class="settle-item__route">
                        <BaseIcon name="map" :size="14" class="settle-item__route-icon" />
                        <span>{{ row.pickup_location || '출발지' }} → {{ row.dropoff_location || '도착지' }}</span>
                    </div>
                    <div class="settle-item__meta">
                        <span class="settle-item__order-no">{{ row.order_number }}</span>
                        <span class="settle-item__more">
                            운행 보기 <BaseIcon name="arrow-forward" :size="13" />
                        </span>
                    </div>
                </article>
            </div>
        </template>
    </div>
</template>

<style scoped>
.settle-page {
    min-height: 200px;
}

.settle-tabs {
    margin-bottom: 12px;
}

/* 정산 대기 헤더 — 건수 + 일괄 정산 버튼 */
.settle-pending-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
}

.settle-pending-head strong {
    font-size: 15px;
    font-weight: 800;
}

.settle-pending-head__desc {
    margin: 5px 0 0;
    color: var(--text-muted);
    font-size: 12px;
    line-height: 1.5;
}

.settle-message {
    margin: 0 0 12px;
    padding: 10px 14px;
    border-radius: 10px;
    background: color-mix(in srgb, var(--brand) 8%, transparent);
    color: var(--brand);
    font-size: 13px;
    font-weight: 600;
}

/* 기간 요약 — 3칸 그리드 */
.settle-summary {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-bottom: 14px;
}

.settle-summary__cell {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 14px;
    border: 1px solid var(--border);
    border-radius: 14px;
    background: var(--surface);
}

.settle-summary__cell span {
    color: var(--text-muted);
    font-size: 12px;
}

.settle-summary__cell strong {
    color: var(--brand);
    font-size: 16px;
    font-weight: 800;
}

/* 정산 목록 */
.settle-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.settle-item {
    padding: 14px 16px;
    border: 1px solid var(--border);
    border-radius: 14px;
    background: var(--surface);
    cursor: pointer;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.settle-item:hover,
.settle-item:focus-visible {
    border-color: var(--brand);
    box-shadow: 0 4px 16px color-mix(in srgb, var(--brand) 12%, transparent);
    outline: none;
}

.settle-item__top {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 8px;
}

.settle-item__date {
    color: var(--text);
    font-size: 14px;
    font-weight: 700;
}

.settle-item__date em {
    margin-left: 6px;
    color: var(--text-muted);
    font-size: 12px;
    font-style: normal;
    font-weight: 500;
}

.settle-item__amount {
    flex-shrink: 0;
    color: var(--brand);
    font-size: 15px;
    font-weight: 800;
}

.settle-item__route {
    display: flex;
    align-items: center;
    gap: 6px;
    margin: 6px 0 8px;
    color: var(--text);
    font-size: 13px;
    line-height: 1.5;
}

.settle-item__route span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.settle-item__route-icon {
    flex-shrink: 0;
    color: var(--text-muted);
}

.settle-item__meta {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--text-muted);
    font-size: 12px;
}

.settle-item__tag {
    padding: 2px 8px;
    border-radius: 999px;
    color: #fff;
    font-size: 11px;
    font-weight: 700;
}

.settle-item__order-no {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.settle-item__more {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    margin-left: auto;
    flex-shrink: 0;
    color: var(--text-muted);
    font-weight: 600;
    white-space: nowrap;
    transition: color 0.15s ease;
}

.settle-item:hover .settle-item__more {
    color: var(--brand);
}

.settle-skeleton {
    display: flex;
    flex-direction: column;
}
</style>
