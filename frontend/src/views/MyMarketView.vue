<script setup>
import { computed, onActivated, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useMessage } from 'naive-ui';
import { apiOrders } from '../api/orders';
import { getApiErrorMessage } from '../api/client';
import OrderCard from '../components/orders/OrderCard.vue';
import OrderCardSkeleton from '../components/orders/OrderCardSkeleton.vue';
import SetGroupCard from '../components/orders/SetGroupCard.vue';
import EmptyState from '../components/common/EmptyState.vue';
import BaseIcon from '../components/common/BaseIcon.vue';

const router = useRouter();
const route = useRoute();
const message = useMessage();

// 내가 직접 등록한 운행만 관리 — 탭은 상태 단계
// 대시보드 등에서 ?tab=진행중 형태로 진입하면 해당 탭을 먼저 연다
const STATUS_TABS = [
    { label: '진행중', value: '진행중' },
    { label: '초안', value: '초안' },
    { label: '완료', value: '완료' },
    { label: '취소', value: '취소' },
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

// 승인 대기 중인 가져오기 요청 건수 (상단 정렬되므로 첫 페이지에서 확인 가능)
const pendingClaims = computed(() => orders.value.filter((o) => o.status === 'acceptance_pending'));

const load = async () => {
    loading.value = true;

    try {
        const params = {
            scope: 'mine',
            source: 'registered',
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

// 운행 등록 폼 열기 — /orders/create 에서 폼 화면으로 시작
const goCreate = () => {
    router.push({ name: 'order-create', query: { form: '1' } });
};

const goMarket = () => router.push({ name: 'market' });

onMounted(load);

// keep-alive로 캐시된 화면 재진입 시 최신 요청 상태를 반영
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
                <p class="page-head__desc">내가 직접 등록한 운행을 관리합니다. 가져오기 요청이 들어오면 상단에 표시됩니다.</p>
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

        <!-- 가져오기 요청 대기 배너 -->
        <button
            v-if="listTab === '진행중' && !loading && pendingClaims.length"
            type="button"
            class="claim-banner"
            @click="router.push({ name: 'order-detail', params: { id: pendingClaims[0].id } })"
        >
            <span class="claim-banner__dot" />
            <span>
                가져오기 요청 <strong>{{ pendingClaims.length }}건</strong> — 승인 대기 중입니다
            </span>
            <BaseIcon name="arrow-forward" :size="14" class="claim-banner__arrow" />
        </button>

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

/* 가져오기 요청 대기 배너 — 진행중 탭 최상단 */
.claim-banner {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    margin-bottom: 10px;
    padding: 11px 14px;
    border: 1px solid color-mix(in srgb, var(--status-acceptance-pending) 45%, transparent);
    border-radius: 12px;
    background: color-mix(in srgb, var(--status-acceptance-pending) 10%, transparent);
    color: var(--text);
    font-family: inherit;
    font-size: 13px;
    font-weight: 600;
    text-align: left;
    cursor: pointer;
    transition: border-color 0.15s ease, background 0.15s ease;
}

.claim-banner:hover {
    border-color: var(--status-acceptance-pending);
    background: color-mix(in srgb, var(--status-acceptance-pending) 16%, transparent);
}

.claim-banner__dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--status-acceptance-pending);
    flex-shrink: 0;
}

.claim-banner strong {
    color: var(--status-acceptance-pending);
}

.claim-banner__arrow {
    margin-left: auto;
    flex-shrink: 0;
    color: var(--text-muted);
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
    font-size: 13px;
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

.create-pagination {
    display: flex;
    justify-content: center;
    margin-top: 20px;
}
</style>
