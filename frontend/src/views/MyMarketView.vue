<script setup>
import { computed, onActivated, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useMessage } from 'naive-ui';
import { apiOrders } from '../api/orders';
import { getApiErrorMessage } from '../api/client';
import { useBatchSettle } from '../composables/useBatchSettle';
import OrderCard from '../components/orders/OrderCard.vue';
import OrderCardSkeleton from '../components/orders/OrderCardSkeleton.vue';
import SetGroupCard from '../components/orders/SetGroupCard.vue';
import EmptyState from '../components/common/EmptyState.vue';
import BaseIcon from '../components/common/BaseIcon.vue';

const router = useRouter();
const route = useRoute();
const message = useMessage();

// 내가 등록하거나 가져온 운행 관리 — 탭은 상태 단계
// 대시보드 등에서 ?tab=진행중 형태로 진입하면 해당 탭을 먼저 연다
const STATUS_TABS = [
    { label: '공개', value: '공개' },
    { label: '진행중', value: '진행중' },
    { label: '정산', value: '정산' },
];

const listTab = ref(STATUS_TABS.some((t) => t.value === route.query.tab) ? route.query.tab : '진행중');

const orders = ref([]);
const loading = ref(false);
const page = ref(1);
const pagination = ref(null);
const listSearch = ref('');
const sort = ref('latest');
const SORT_OPTIONS = [
    { label: '등록순', value: 'latest' },
    { label: '서비스순', value: 'date' },
    { label: '금액 높은순', value: 'amount' },
    { label: '금액 낮은순', value: 'amount_asc' },
];

const setRows = computed(() => orders.value.filter((o) => o.kind === 'set'));
const singleRows = computed(() => orders.value.filter((o) => o.kind !== 'set'));

// 정산 탭 — 완료(정산 대기) 운행 일괄 정산 확인
const pendingSettleCount = computed(() => orders.value.filter((o) => o.status === 'completed').length);

const load = async () => {
    loading.value = true;

    try {
        const params = {
            scope: 'mine',
            source: 'all',
            tab: listTab.value,
            per_page: 30,
            page: page.value,
        };

        if (sort.value !== 'latest') {
            params.sort = sort.value;
        }
        if (listSearch.value.trim()) {
            params.search = listSearch.value.trim();
        }

        const { data } = await apiOrders(params);

        orders.value = data.data ?? [];
        pagination.value = data.meta ?? null;
    } catch (e) {
        message.error(getApiErrorMessage(e, '내 마켓 목록을 불러오지 못했습니다.'));
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

const changeSort = (value) => {
    if (sort.value === value) return;

    sort.value = value;
    loadFirstPage();
};

// 정산 탭 — 완료(정산 대기) 운행 일괄 정산 확인
const settle = useBatchSettle({ load });
const { settling, settleMessage, settleAll } = settle;

// 운행 등록 폼 열기 — /orders/create 에서 폼 화면으로 시작
const goCreate = () => {
    router.push({ name: 'order-create', query: { form: '1' } });
};

const goMarket = () => router.push({ name: 'market' });

onMounted(load);

// keep-alive로 캐시된 화면 재진입 시 최신 상태를 반영
onActivated(() => {
    if (!loading.value) {
        load();
    }
});
</script>

<template>
    <div class="market-page">
        <div class="page-head">
            <div>
                <p class="page-head__desc">내가 등록하거나 가져온 운행을 관리합니다.</p>
            </div>
            <div class="page-head__actions">
                <n-tag v-if="pagination" size="large" round>{{ pagination.total ?? 0 }}건</n-tag>
                <n-button quaternary round title="새로고침" @click="loadFirstPage">
                    <BaseIcon name="refresh" :size="18" />
                </n-button>
                <n-button type="primary" size="large" round @click="goCreate">+ 운행 등록</n-button>
            </div>
        </div>

        <div class="create-tabs">
            <n-radio-group v-model:value="listTab" size="small" @update:value="switchTab">
                <n-radio-button v-for="tab in STATUS_TABS" :key="tab.value" :value="tab.value">
                    {{ tab.label }}
                </n-radio-button>
            </n-radio-group>
        </div>

        <!-- 검색 -->
        <n-input
            v-model:value="listSearch"
            size="large"
            round
            clearable
            placeholder="노선 · 고객명 · 예약처 검색"
            class="create-search"
            @keyup.enter="loadFirstPage"
            @clear="loadFirstPage"
        >
            <template #prefix>
                <BaseIcon name="search" :size="16" />
            </template>
            <template #suffix>
                <n-button v-if="listSearch" text type="primary" size="small" @click="loadFirstPage">
                    검색
                </n-button>
            </template>
        </n-input>

        <!-- 정렬 -->
        <div class="create-sort">
            <button
                v-for="opt in SORT_OPTIONS"
                :key="opt.value"
                type="button"
                class="create-sort__btn"
                :class="{ 'create-sort__btn--active': sort === opt.value }"
                @click="changeSort(opt.value)"
            >
                {{ opt.label }}
            </button>
        </div>

        <!-- 정산 탭 — 완료 운행 확인·일괄 정산 -->
        <div v-if="listTab === '정산' && !loading" class="settle-cta">
            <button
                v-if="pendingSettleCount"
                type="button"
                class="settle-cta__btn"
                :disabled="settling"
                @click="settleAll"
            >
                {{ settling ? '정산 처리 중...' : `정산 대기 ${pendingSettleCount}건 확인·정산` }}
            </button>
            <p v-if="settleMessage" class="settle-cta__msg">{{ settleMessage }}</p>
        </div>

        <div v-if="loading" class="my-order-list">
            <OrderCardSkeleton v-for="n in 5" :key="n" />
        </div>
        <EmptyState
            v-else-if="!orders.length"
            icon="inbox"
            title="등록한 운행이 없습니다"
            hint="직접 운행을 등록해 마켓에 공개하세요"
        >
            <template #action>
                <n-button type="primary" round @click="goCreate">+ 운행 등록</n-button>
            </template>
        </EmptyState>
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

        <div class="market-footer">
            <n-button quaternary round @click="goMarket">
                마켓에서 가져올 운행 보기 →
            </n-button>
        </div>
    </div>
</template>

<style scoped>
.market-page {
    min-height: 200px;
}

.market-footer {
    display: flex;
    justify-content: center;
    margin-top: 20px;
}

.create-tabs {
    margin-bottom: 10px;
}

.create-search {
    margin-bottom: 10px;
}

.create-sort {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 14px;
}

.create-sort__btn {
    padding: 6px 14px;
    border: 1px solid var(--border);
    border-radius: 999px;
    background: var(--surface);
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    transition: border-color 0.15s ease, color 0.15s ease, background 0.15s ease;
}

.create-sort__btn--active {
    border-color: var(--brand);
    background: var(--brand-soft);
    color: var(--brand);
}

.my-order-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

/* 정산 탭 — 완료 운행 확인·일괄 정산 */
.settle-cta {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
}

.settle-cta__btn {
    padding: 9px 18px;
    border: 0;
    border-radius: 999px;
    background: var(--brand);
    color: #07120e;
    font-family: inherit;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: opacity 0.12s ease;
}

.settle-cta__btn:disabled {
    opacity: 0.5;
    cursor: default;
}

.settle-cta__msg {
    margin: 0;
    font-size: 11px;
    color: var(--text-muted);
}

.create-pagination {
    display: flex;
    justify-content: center;
    margin-top: 20px;
}
</style>
