<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useNotification } from 'naive-ui';
import { apiOrders } from '../api/orders';
import { getApiErrorMessage } from '../api/client';
import { useUiStore } from '../stores/ui';
import OrderCard from '../components/orders/OrderCard.vue';
import SetGroupCard from '../components/orders/SetGroupCard.vue';

const ui = useUiStore();
const notification = useNotification();

const orders = ref([]);
const pagination = ref(null);
const loading = ref(true);
const error = ref('');
const filterOpen = ref(false);

const applyFilter = () => {
    filterOpen.value = false;
    handleFilterChange();
};

// kind에 따라 분류
const singleRows = computed(() => orders.value.filter((o) => o.kind !== 'set'));
const setRows = computed(() => orders.value.filter((o) => o.kind === 'set'));
const search = ref('');
const serviceType = ref('');
const date = ref('');
const region = ref('');
const vehicleType = ref('');
const minAmount = ref(null);
const maxAmount = ref(null);
const minPassengers = ref(null);
const sort = ref('latest');
const page = ref(1);

// 필터·검색 상태 기억 (재방문 시 유지)
const MARKET_STATE_KEY = 'nowhere:market:filter';
const savedState = (() => {
    try {
        return JSON.parse(localStorage.getItem(MARKET_STATE_KEY) ?? '{}');
    } catch {
        return {};
    }
})();

search.value = savedState.search ?? '';
serviceType.value = savedState.serviceType ?? '';
date.value = savedState.date ?? '';
region.value = savedState.region ?? '';
vehicleType.value = savedState.vehicleType ?? '';
minAmount.value = savedState.minAmount ?? null;
maxAmount.value = savedState.maxAmount ?? null;
minPassengers.value = savedState.minPassengers ?? null;
sort.value = savedState.sort ?? 'latest';

const persistState = () => {
    try {
        localStorage.setItem(MARKET_STATE_KEY, JSON.stringify({
            search: search.value,
            serviceType: serviceType.value,
            date: date.value,
            region: region.value,
            vehicleType: vehicleType.value,
            minAmount: minAmount.value,
            maxAmount: maxAmount.value,
            minPassengers: minPassengers.value,
            sort: sort.value,
        }));
    } catch {
        /* 저장 실패 무시 */
    }
};

// 모든 필터 초기화 (검색어 제외)
const resetFilters = () => {
    serviceType.value = '';
    date.value = '';
    region.value = '';
    vehicleType.value = '';
    minAmount.value = null;
    maxAmount.value = null;
    minPassengers.value = null;
    sort.value = 'latest';
};

// 활성 필터 개수 (초기화 버튼 표시용)
const activeFilterCount = computed(() =>
    [serviceType.value, date.value, region.value, vehicleType.value, minAmount.value, maxAmount.value, minPassengers.value, sort.value !== 'latest' ? sort.value : '']
        .filter(Boolean).length,
);

const SERVICE_FILTER_OPTIONS = [
    { label: '전체', value: '' },
    { label: '픽업', value: 'pickup' },
    { label: '공항샌딩', value: 'sending' },
    { label: '공항랜딩', value: 'landing' },
];

const SORT_OPTIONS = [
    { label: '최신순', value: 'latest' },
    { label: '날짜순', value: 'date' },
    { label: '금액 높은순', value: 'amount' },
    { label: '금액 낮은순', value: 'amount_asc' },
];

const VEHICLE_OPTIONS = [
    { label: '전체', value: '' },
    { label: '스타리아', value: '스타리아' },
    { label: '카니발', value: '카니발' },
    { label: '그랜드 스타렉스', value: '스타렉스' },
];

const PASSENGER_OPTIONS = [
    { label: '전체', value: null },
    { label: '1명 이상', value: 1 },
    { label: '4명 이상', value: 4 },
    { label: '7명 이상', value: 7 },
    { label: '9명 이상', value: 9 },
];

// 오늘/내일 빠른 날짜 필터 (YYYY-MM-DD)
const today = new Date().toISOString().slice(0, 10);
const tomorrow = new Date(Date.now() + 86400000).toISOString().slice(0, 10);

const load = async (silent = false) => {
    persistState();

    if (!silent) {
        loading.value = true;
    }

    error.value = '';

    try {
        const params = { scope: 'market', search: search.value, page: page.value };

        if (serviceType.value) {
            params.service_type = serviceType.value;
        }
        if (date.value) {
            params.date = date.value;
        }
        if (region.value) {
            params.region = region.value;
        }
        if (vehicleType.value) {
            params.vehicle_type = vehicleType.value;
        }
        if (minAmount.value) {
            params.min_amount = minAmount.value;
        }
        if (maxAmount.value) {
            params.max_amount = maxAmount.value;
        }
        if (minPassengers.value) {
            params.min_passengers = minPassengers.value;
        }
        if (sort.value !== 'latest') {
            params.sort = sort.value;
        }

        const { data } = await apiOrders(params);
        notifyNewOrders(data.data);
        orders.value = data.data;
        pagination.value = data.meta.pagination;
    } catch (e) {
        error.value = getApiErrorMessage(e, '오더 목록을 불러오지 못했습니다.');
    } finally {
        loading.value = false;
    }
};

// 새 오더 감지 — 첫 페이지, 필터 없이 보는 상태에서만 토스트로 알린다.
let knownTopKey = null;
let baselineDone = false;
let prevKeys = new Set();
const highlightKeys = ref(new Set());

const clearHighlight = (key) => {
    const next = new Set(highlightKeys.value);
    next.delete(key);
    highlightKeys.value = next;
};

const notifyNewOrders = (rows) => {
    if (!baselineDone) {
        baselineDone = true;
        prevKeys = new Set(rows.map((r) => r.key));
        knownTopKey = rows[0]?.key ?? null;
        return;
    }

    if (search.value || serviceType.value || date.value || region.value || vehicleType.value || minAmount.value || maxAmount.value || minPassengers.value || page.value > 1 || rows.length === 0) {
        prevKeys = new Set(rows.map((r) => r.key));
        knownTopKey = rows[0]?.key ?? null;
        return;
    }

    const topKey = rows[0]?.key ?? null;
    if (topKey && topKey !== knownTopKey) {
        notification.info({
            title: '새 오더가 도착했어요',
            content: '마켓에 새로운 오더가 등록되었습니다.',
            duration: 4000,
        });
    }
    knownTopKey = topKey;

    // 신규 오더 카드 하이라이트 (이전 목록에 없던 key)
    const newKeys = rows.filter((r) => !prevKeys.has(r.key)).map((r) => r.key);
    if (newKeys.length) {
        const next = new Set(highlightKeys.value);
        newKeys.forEach((key) => next.add(key));
        highlightKeys.value = next;
        newKeys.forEach((key) => setTimeout(() => clearHighlight(key), 6000));
    }
    prevKeys = new Set(rows.map((r) => r.key));
};

const handleFilterChange = () => {
    page.value = 1;
    load();
};

const handlePage = (nextPage) => {
    page.value = nextPage;
    load();
};

// 실시간 반영 — 새 오더가 등록되면 화면을 조용히 갱신한다 (30초 폴링, 백그라운드 시 중지)
let pollTimer = null;

const silentRefresh = () => {
    load(true).catch(() => {});
};

const onVisibility = () => {
    if (document.visibilityState === 'visible') {
        silentRefresh();
    }
};

const onSseRefresh = () => {
    silentRefresh();
};

onMounted(() => {
    load();
    pollTimer = setInterval(silentRefresh, 30000);
    document.addEventListener('visibilitychange', onVisibility);
    window.addEventListener('app:sse-refresh', onSseRefresh);
});

onBeforeUnmount(() => {
    clearInterval(pollTimer);
    document.removeEventListener('visibilitychange', onVisibility);
    window.removeEventListener('app:sse-refresh', onSseRefresh);
});

// 헤더 '···' 메뉴의 새로고침 수신
watch(
    () => ui.actionSeq,
    () => {
        if (ui.actionName === 'refresh') {
            load();
        }
        if (ui.actionName === 'filter') {
            filterOpen.value = true;
        }
    },
);

</script>

<template>
    <div>
        <div class="page-head">
            <n-tag size="large" round>{{ pagination?.total ?? 0 }}건</n-tag>
            <n-tag
                v-if="serviceType"
                size="medium"
                round
                closable
                @close="serviceType = ''; load()"
            >
                {{ SERVICE_FILTER_OPTIONS.find(o => o.value === serviceType)?.label }}
            </n-tag>
            <n-tag
                v-if="date"
                size="medium"
                round
                closable
                @close="date = ''; load()"
            >
                📅 {{ date }}
            </n-tag>
            <n-tag
                v-if="region"
                size="medium"
                round
                closable
                @close="region = ''; load()"
            >
                📍 {{ region }}
            </n-tag>
            <n-tag
                v-if="vehicleType"
                size="medium"
                round
                closable
                @close="vehicleType = ''; load()"
            >
                🚐 {{ vehicleType }}
            </n-tag>
            <n-tag
                v-if="minAmount || maxAmount"
                size="medium"
                round
                closable
                @close="minAmount = null; maxAmount = null; load()"
            >
                💰 {{ minAmount ? minAmount.toLocaleString() : 0 }}~{{ maxAmount ? maxAmount.toLocaleString() : '∞' }}원
            </n-tag>
            <n-tag
                v-if="minPassengers"
                size="medium"
                round
                closable
                @close="minPassengers = null; load()"
            >
                👥 {{ minPassengers }}명 이상
            </n-tag>
            <n-tag
                v-if="sort !== 'latest'"
                size="medium"
                round
                closable
                @close="sort = 'latest'; load()"
            >
                {{ SORT_OPTIONS.find(o => o.value === sort)?.label }}
            </n-tag>
            <n-button
                v-if="activeFilterCount > 0"
                size="small"
                round
                tertiary
                @click="resetFilters(); load()"
            >
                전체 초기화
            </n-button>
        </div>

        <div class="market-toolbar">
            <n-input
                v-model:value="search"
                placeholder="노선 · 고객명 검색"
                clearable
                round
                size="large"
                class="market-search"
                @keyup.enter="load"
                @clear="load"
            />
            <n-button type="primary" size="large" round @click="load">검색</n-button>
        </div>

        <!-- 필터 모달 -->
        <n-modal v-model:show="filterOpen" preset="card" title="필터" :style="{ maxWidth: '400px' }">
            <div class="filter-body">
                <label class="filter-label">서비스 구분</label>
                <n-select
                    v-model:value="serviceType"
                    :options="SERVICE_FILTER_OPTIONS"
                    size="large"
                />
                <label class="filter-label">날짜</label>
                <div class="filter-date-row">
                    <n-button
                        size="small"
                        round
                        :type="date === today ? 'primary' : 'default'"
                        @click="date = date === today ? '' : today"
                    >
                        오늘
                    </n-button>
                    <n-button
                        size="small"
                        round
                        :type="date === tomorrow ? 'primary' : 'default'"
                        @click="date = date === tomorrow ? '' : tomorrow"
                    >
                        내일
                    </n-button>
                    <input v-model="date" type="date" class="filter-date" />
                    <n-button v-if="date" size="small" round @click="date = ''">전체</n-button>
                </div>
                <label class="filter-label">지역(노선)</label>
                <n-input
                    v-model:value="region"
                    placeholder="출발·도착 지역 (예: 인천공항)"
                    clearable
                    size="large"
                />
                <label class="filter-label">차량</label>
                <n-select
                    v-model:value="vehicleType"
                    :options="VEHICLE_OPTIONS"
                    size="large"
                />
                <label class="filter-label">인원</label>
                <n-select
                    v-model:value="minPassengers"
                    :options="PASSENGER_OPTIONS"
                    size="large"
                />
                <label class="filter-label">금액 범위 (원)</label>
                <div class="filter-amount-row">
                    <n-input v-model:value="minAmount" type="number" placeholder="최소" clearable size="large" />
                    <span class="filter-amount-sep">~</span>
                    <n-input v-model:value="maxAmount" type="number" placeholder="최대" clearable size="large" />
                </div>
                <label class="filter-label">정렬</label>
                <n-select
                    v-model:value="sort"
                    :options="SORT_OPTIONS"
                    size="large"
                />
            </div>
            <template #footer>
                <div class="filter-footer">
                    <n-button @click="filterOpen = false">취소</n-button>
                    <n-button v-if="activeFilterCount > 0" @click="resetFilters()">초기화</n-button>
                    <n-button type="primary" @click="applyFilter">적용</n-button>
                </div>
            </template>
        </n-modal>

        <n-alert v-if="error" type="error" :show-icon="true" class="market-alert">
            {{ error }}
            <template #action>
                <n-button size="small" :loading="loading" @click="load">다시 시도</n-button>
            </template>
        </n-alert>

        <n-spin :show="false" class="market-body">
            <!-- 로딩 스켈레톤 -->
            <div v-if="loading" class="order-grid">
                <OrderCardSkeleton v-for="n in 6" :key="n" />
            </div>

            <n-empty
                v-else-if="orders.length === 0"
                description="가져올 수 있는 오더가 없습니다."
            />
            <div v-else class="order-grid">
                <SetGroupCard
                    v-for="order in setRows"
                    :key="order.key"
                    :set="order"
                    :highlight="highlightKeys.has(order.key)"
                />
                <OrderCard
                    v-for="order in singleRows"
                    :key="order.key"
                    :order="order"
                    :highlight="highlightKeys.has(order.key)"
                />
            </div>

            <div v-if="pagination && pagination.last_page > 1" class="market-pagination">
                <n-pagination
                    :page="page"
                    :page-size="pagination.per_page"
                    :item-count="pagination.total"
                    @update:page="handlePage"
                />
            </div>
        </n-spin>
    </div>
</template>

<style scoped>
.page-head {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 14px;
}

.page-head__desc {
    margin: 0;
    color: var(--text-muted);
    font-size: 13px;
}

.market-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    margin-bottom: 18px;
}

.market-search {
    flex: 1 1 240px;
    max-width: 360px;
}

.filter-body {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.filter-date-row {
    display: flex;
    align-items: center;
    gap: 8px;
}

.filter-amount-row {
    display: flex;
    align-items: center;
    gap: 8px;
}

.filter-amount-row .n-input {
    flex: 1;
    min-width: 0;
}

.filter-amount-sep {
    color: var(--text-muted);
    font-size: 14px;
    flex-shrink: 0;
}

.filter-date {
    flex: 1;
    min-width: 0;
    padding: 8px 10px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--surface);
    color: var(--text);
    font-size: 14px;
    outline: none;
}

.filter-label {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-muted);
}

.filter-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

.market-alert {
    margin-bottom: 16px;
}

.market-body {
    display: block;
    min-height: 200px;
}

.order-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 14px;
}

.market-pagination {
    display: flex;
    justify-content: center;
    margin-top: 24px;
}
</style>
