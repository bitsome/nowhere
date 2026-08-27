<script setup>
import { computed, onMounted, ref } from 'vue';
import { useMessage } from 'naive-ui';
import { apiOrders } from '../api/orders';
import { getApiErrorMessage } from '../api/client';
import OrderCard from '../components/orders/OrderCard.vue';
import OrderCardSkeleton from '../components/orders/OrderCardSkeleton.vue';
import SetGroupCard from '../components/orders/SetGroupCard.vue';
import EmptyState from '../components/common/EmptyState.vue';
import BaseIcon from '../components/common/BaseIcon.vue';

const message = useMessage();

// 운행 기록 — 완료/취소/전체 필터 (받은 운행 기준)
const STATUS_TABS = [
    { label: '전체', value: '전체' },
    { label: '완료', value: '완료' },
    { label: '취소', value: '취소' },
];

const listTab = ref('전체');
const orders = ref([]);
const loading = ref(false);
const page = ref(1);
const pagination = ref(null);

const setRows = computedFilter('set');
const singleRows = computedFilter('!set');

function computedFilter(kind) {
    return computed(() => {
        if (kind === 'set') return orders.value.filter((o) => o.kind === 'set');
        return orders.value.filter((o) => o.kind !== 'set');
    });
}

// 금액 추출 — expected_revenue/amount_value 숫자 우선, 없으면 포맷된 amount 문자열에서 파싱
const orderAmount = (o) => {
    const raw = o.expected_revenue ?? o.amount_value;

    if (raw !== null && raw !== undefined) {
        return Number(raw) || 0;
    }

    const digits = String(o.amount ?? '').replace(/[^\d]/g, '');

    return digits ? Number(digits) : 0;
};

// 완료/취소 전체 금액 합계 (단건 운행 기준)
const totalAmount = computed(() => singleRows.value.reduce((sum, o) => sum + orderAmount(o), 0));

// 건당 평균 금액
const avgAmount = computed(() => (singleRows.value.length ? Math.round(totalAmount.value / singleRows.value.length) : 0));

const amountLabel = computed(() => (listTab.value === '완료' ? '완료 수익' : listTab.value === '취소' ? '해당 금액' : '총 금액'));

const load = async () => {
    loading.value = true;

    try {
        const params = {
            scope: 'mine',
            tab: listTab.value,
            per_page: 30,
            page: page.value,
        };

        const { data } = await apiOrders(params);

        orders.value = data.data ?? [];
        pagination.value = data.meta ?? null;
    } catch (e) {
        message.error(getApiErrorMessage(e, '운행 기록을 불러오지 못했습니다.'));
    } finally {
        loading.value = false;
    }
};

const loadFirstPage = () => {
    page.value = 1;
    load();
};

const handlePage = (p) => {
    page.value = p;
    load();
};

const switchTab = (tab) => {
    listTab.value = tab;
    loadFirstPage();
};

onMounted(load);
</script>

<template>
    <div class="history-page">
        <div class="page-head">
            <div>
                <p class="page-head__desc">지난 운행 기록을 확인합니다. 정산 완료 여부도 함께 표시됩니다.</p>
            </div>
            <div class="page-head__actions">
                <n-tag v-if="pagination" size="large" round>{{ pagination.total ?? 0 }}건</n-tag>
                <n-button quaternary round title="새로고침" @click="loadFirstPage">
                    <BaseIcon name="refresh" :size="18" />
                </n-button>
            </div>
        </div>

        <div class="history-tabs">
            <n-radio-group v-model:value="listTab" size="small" @update:value="switchTab">
                <n-radio-button v-for="tab in STATUS_TABS" :key="tab.value" :value="tab.value">
                    {{ tab.label }}
                </n-radio-button>
            </n-radio-group>
        </div>

        <div v-if="!loading && singleRows.length" class="history-hero">
            <div class="history-hero__cell">
                <span>{{ amountLabel }}</span>
                <strong>{{ totalAmount.toLocaleString() }}원</strong>
            </div>
            <div class="history-hero__cell">
                <span>총 {{ listTab === '전체' ? '운행' : listTab }} 건수</span>
                <strong>{{ pagination?.total ?? singleRows.length }}건</strong>
            </div>
            <div class="history-hero__cell">
                <span>건당 평균</span>
                <strong>{{ avgAmount.toLocaleString() }}원</strong>
            </div>
        </div>

        <div v-if="loading" class="my-order-list">
            <OrderCardSkeleton v-for="n in 5" :key="n" />
        </div>
        <EmptyState
            v-else-if="!orders.length"
            icon="truck"
            title="운행 기록이 없습니다"
            hint="완료된 운행이 여기에 표시됩니다"
        />
        <div v-else class="my-order-list">
            <SetGroupCard
                v-for="order in setRows"
                :key="order.key"
                :set="order"
            />
            <OrderCard
                v-for="order in singleRows"
                :key="order.key"
                :order="order"
            />
        </div>

        <div v-if="pagination && pagination.last_page > 1" class="create-pagination">
            <n-pagination
                :page="page"
                :page-size="pagination.per_page"
                :item-count="pagination.total"
                @update:page="handlePage"
            />
        </div>
    </div>
</template>

<style scoped>
.history-page {
    min-height: 200px;
}

.history-tabs {
    margin-bottom: 12px;
}

/* 요약 히어로 — 금액/건수/평균 3칸 */
.history-hero {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-bottom: 14px;
}

.history-hero__cell {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 14px;
    border: 1px solid var(--border);
    border-radius: 14px;
    background: var(--surface);
}

.history-hero__cell span {
    color: var(--text-muted);
    font-size: 11px;
}

.history-hero__cell strong {
    color: var(--brand);
    font-size: 12px;
    font-weight: 800;
}

.my-order-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.create-pagination {
    display: flex;
    justify-content: center;
    margin-top: 20px;
}
</style>
