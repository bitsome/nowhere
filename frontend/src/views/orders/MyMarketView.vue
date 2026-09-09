<script setup>
import { computed, onActivated, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useMessage } from 'naive-ui';
import { apiOrders, apiWithdrawClaim } from '../../api/orders';
import { getApiErrorMessage } from '../../api/client';
import { useBatchSettle } from '../../composables/useBatchSettle';
import OrderCard from '../../components/orders/OrderCard.vue';
import OrderCardSkeleton from '../../components/orders/OrderCardSkeleton.vue';
import SetGroupCard from '../../components/orders/SetGroupCard.vue';
import EmptyState from '../../components/common/EmptyState.vue';
import BaseIcon from '../../components/common/BaseIcon.vue';

const router = useRouter();
const route = useRoute();
const message = useMessage();

// 내가 등록하거나 가져온 운행 관리 — 탭은 상태 단계
// 대시보드 등에서 ?tab=진행중 형태로 진입하면 해당 탭을 먼저 연다
const STATUS_TABS = [
    { label: '공개', value: '공개' },
    { label: '진행중', value: '진행중' },
    { label: '정산', value: '정산' },
    { label: '요청', value: '요청' },
];

// 요청 탭 — 보낸(내가 가져오기 요청)/받은(내 운행에 요청) 방향
const REQUEST_CATEGORIES = [
    { label: '받음', value: 'received' },
    { label: '보냄', value: 'sent' },
];

// 진행중 탭 — 세분화된 상태 카테고리 (전체/예약/운행중). 요청이 수락되면 '예약'이 된다.
const PROGRESS_STATUS_CATEGORIES = [
    { label: '전체', value: 'all' },
    { label: '예약', value: 'accepted' },
    { label: '운행중', value: 'driving' },
];

const listTab = ref(STATUS_TABS.some((t) => t.value === route.query.tab) ? route.query.tab : '진행중');
// 홈의 '일괄요청중' 버튼 등에서 ?tab=요청&cat=sent 형태로 진입하면 요청보냄을 먼저 연다
const requestCategory = ref(route.query.cat === 'sent' ? 'sent' : 'received');
// 진행중 탭의 상태 세분화 필터 (전체/예약/운행중)
const progressStatus = ref('all');

const orders = ref([]);
const loading = ref(false);
const page = ref(1);
const pagination = ref(null);
const search = ref('');
const searchOpen = ref(false);

const setRows = computed(() => orders.value.filter((o) => o.kind === 'set'));
const singleRows = computed(() => orders.value.filter((o) => o.kind !== 'set'));

// 요청보냄 — 요청 후 30분 안에 승인되지 않으면 자동 거절(휴지통)로 취급한다.
// (백엔드 OrderClaimService::CLAIM_EXPIRE_SECONDS = 1800과 일치)
const CLAIM_EXPIRE_MS = 30 * 60 * 1000;

// 화면을 보고 있는 동안에도 만료가 지나가면 자동으로 휴지통으로 옮기기 위해 주기적으로 시각을 갱신한다
const nowTick = ref(Date.now());
let expireTimer = null;

watch(
    [listTab, requestCategory],
    ([tab, category]) => {
        if (expireTimer) clearInterval(expireTimer);
        expireTimer = null;

        if (tab === '요청' && category === 'sent') {
            expireTimer = setInterval(() => {
                nowTick.value = Date.now();
            }, 1000);
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    if (expireTimer) clearInterval(expireTimer);
});

// 만료 여부 — claimedAt 기준 30분 경과 시 만료 (now 미지정 시 화면 갱신 시각 사용)
const isExpiredClaim = (order, now = nowTick.value) => {
    if (!order.claimedAt) return false;

    return now - Date.parse(order.claimedAt) >= CLAIM_EXPIRE_MS;
};

// 보낸 시간 표시 — 오늘 보낸 건 '보낸 HH:MM', 다른 날은 '보낸 M/D HH:MM'
const sentTimeText = (order) => {
    if (!order.claimedAt) return '';

    const sent = new Date(order.claimedAt);
    const now = new Date();
    const time = `${String(sent.getHours()).padStart(2, '0')}:${String(sent.getMinutes()).padStart(2, '0')}`;
    const sameDay = sent.getFullYear() === now.getFullYear()
        && sent.getMonth() === now.getMonth()
        && sent.getDate() === now.getDate();

    return sameDay ? `보낸 ${time}` : `보낸 ${sent.getMonth() + 1}/${sent.getDate()} ${time}`;
};

// 수락 대기 옆 남은 시간 — 만료 전까지 카운트다운 (만료·만료 예정 아님 시 빈 값)
const claimRemainText = (order) => {
    if (!order.claimedAt || isExpiredClaim(order)) return '';

    const remainMs = CLAIM_EXPIRE_MS - (nowTick.value - Date.parse(order.claimedAt));
    const seconds = Math.max(1, Math.ceil(remainMs / 1000));

    return `남은 ${seconds}초`;
};

// 보낸 요청을 최근 보낸순(claimed_at 내림차순)으로 나열하고, 같은 일괄요청끼리 묶는다
const groupSentChronological = (ordersList) => {
    const sorted = [...ordersList].sort((a, b) => {
        const ta = Date.parse(a.claimedAt) || 0;
        const tb = Date.parse(b.claimedAt) || 0;

        return tb - ta;
    });

    const groups = [];
    const byBatch = new Map();

    for (const order of sorted) {
        const batchId = order.claimBatchId || null;

        if (!batchId) {
            groups.push({ key: order.key, batchId: null, orders: [order] });
            continue;
        }

        if (!byBatch.has(batchId)) {
            const group = { key: `batch-${batchId}`, batchId, orders: [] };
            byBatch.set(batchId, group);
            groups.push(group);
        }

        byBatch.get(batchId).orders.push(order);
    }

    return groups;
};

const sentActiveOrders = computed(() => {
    if (listTab.value !== '요청' || requestCategory.value !== 'sent') return [];

    return orders.value.filter((o) => !isExpiredClaim(o));
});

const sentActiveGroups = computed(() => groupSentChronological(sentActiveOrders.value));

// 휴지통 — 30분 안에 승인되지 않아 만료된 보낸 요청. 받음/보냄 어느 카테고리에서도 관리할 수 있도록
// 전용 조회(요청·보냄)로 채우고, 목록에서 만료된 것만 남긴다.
const trashOpen = ref(false);
const trashLoading = ref(false);
const trashOrders = ref([]);

// 만료 시 자동 요청취소 기록 — 서버에서 철회되면 보낸 목록·조회에서 사라지므로
// 세션 동안 휴지통에 남겨 '자동 취소됨'을 관리할 수 있게 한다.
const autoCanceled = ref([]);

// 휴지통 데이터 = 아직 서버에 남은 만료 요청 + 이번 화면에서 자동 취소한 요청 (id 기준 중복 제거)
const trashAll = computed(() => {
    const map = new Map();

    for (const order of trashOrders.value) {
        map.set(order.id, order);
    }

    for (const order of autoCanceled.value) {
        map.set(order.id, order);
    }

    return [...map.values()];
});

const trashGroups = computed(() => groupSentChronological(trashAll.value));
const trashCount = computed(() => trashAll.value.length);

// 휴지통 카드 상태 배지 — 서버 상태(수락 대기)와 관계없이 '요청취소'로 표시
const REQUEST_CANCEL_STATUS = { label: '요청취소', color: 'var(--status-cancelled)' };

const loadTrash = async () => {
    trashLoading.value = true;

    try {
        const params = {
            scope: 'mine',
            source: 'all',
            tab: '요청',
            request_category: 'sent',
            per_page: 50,
            page: 1,
        };

        const { data } = await apiOrders(params);

        trashOrders.value = (data.data ?? []).filter((o) => isExpiredClaim(o, Date.now()));
    } catch {
        trashOrders.value = [];
    } finally {
        trashLoading.value = false;
    }
};

const openTrash = () => {
    loadTrash();
    trashOpen.value = true;
};

// ── 만료 자동 요청취소 — 보냄 탭에서 1초 틱마다 만료를 감지하면 요청취소 API를 먼저 호출하고
//    취소 기록을 휴지통에 넣는다 (운행은 마켓으로 복귀).
const prevActiveKeys = new Set();
const cancelInFlight = new Set();

const autoWithdraw = async (order) => {
    if (cancelInFlight.has(order.id)) return;

    cancelInFlight.add(order.id);

    try {
        await apiWithdrawClaim(order.id);
        autoCanceled.value.push({ ...order, __autoCancelled: true });
        message.info('요청이 만료되어 자동 취소되었습니다.');
    } catch {
        // 이미 취소된 요청이면 조용히 무시
    } finally {
        cancelInFlight.delete(order.id);
        load(); // 보낸 목록에서 빠지도록 갱신
    }
};

// 남은 시간이 만료되는 순간 감지 — 이전 틱에서 살아 있던 요청이 만료되면 자동 취소
watch(nowTick, () => {
    for (const order of orders.value) {
        if (!order.claimedAt) continue;

        if (isExpiredClaim(order) && prevActiveKeys.has(order.key ?? order.id)) {
            autoWithdraw(order);
        }
    }

    prevActiveKeys.clear();

    for (const order of orders.value) {
        if (order.claimedAt && !isExpiredClaim(order)) {
            prevActiveKeys.add(order.key ?? order.id);
        }
    }
});

// 휴지통에서 개별 삭제 — 이미 자동 취소된 기록은 로컬에서만 제거하고,
// 서버에 남아 있는 만료 요청은 철회해 운행이 마켓으로 돌아간다
const removingId = ref(null);

const removeTrash = async (order) => {
    removingId.value = order.id;

    try {
        if (order.__autoCancelled) {
            autoCanceled.value = autoCanceled.value.filter((o) => o.id !== order.id);
        } else {
            await apiWithdrawClaim(order.id);
            await loadTrash();
        }

        message.success('요청을 삭제했습니다.');
        await load();
    } catch (e) {
        message.error(getApiErrorMessage(e, '요청 삭제에 실패했습니다.'));
    } finally {
        removingId.value = null;
    }
};

// 휴지통 전체 삭제 — 서버에 남은 만료 요청은 모두 철회하고, 자동 취소 기록도 비운다
const trashClearing = ref(false);

const clearTrash = async () => {
    if (!trashAll.value.length) return;

    trashClearing.value = true;

    try {
        for (const order of trashOrders.value) {
            await apiWithdrawClaim(order.id);
        }

        autoCanceled.value = [];
        message.success(`만료된 요청 ${trashAll.value.length}건을 모두 삭제했습니다.`);
        await Promise.all([load(), loadTrash()]);
    } catch (e) {
        message.error(getApiErrorMessage(e, '요청 삭제에 실패했습니다.'));
    } finally {
        trashClearing.value = false;
    }
};

// 정산 탭 — 완료(정산 대기) 운행 일괄 정산 확인
const pendingSettleCount = computed(() => orders.value.filter((o) => o.status === 'completed').length);

// 요청 탭 — 보낸/받은 방향에 따라 빈 상태 문구를 바꾼다
const emptyState = computed(() => {
    if (listTab.value === '요청') {
        return requestCategory.value === 'sent'
            ? { title: '보낸 요청이 없습니다', hint: '요청 후 30분 안에 승인되지 않으면 자동으로 요청취소되어 휴지통에 들어갑니다' }
            : { title: '받은 요청이 없습니다', hint: '등록한 운행에 가져오기 요청이 오면 여기에 모입니다' };
    }

    return { title: '등록한 운행이 없습니다', hint: '직접 운행을 등록해 마켓에 공개하세요' };
});

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

        // 진행중 탭 — 서비스 시간순(빠른 운행 먼저)으로 나열
        if (listTab.value === '진행중') {
            params.sort = 'date';

            // 상태 세분화 — 전체/예약/운행중
            if (progressStatus.value !== 'all') {
                params.progress_status = progressStatus.value;
            }
        }

        if (listTab.value === '요청') {
            params.request_category = requestCategory.value;
        }

        if (search.value.trim()) {
            params.search = search.value.trim();
        }

        const { data } = await apiOrders(params);

        orders.value = data.data ?? [];
        pagination.value = data.meta ?? null;

        // 요청 탭 — 휴지통 배지 건수도 함께 갱신 (받음/보냄 어느 카테고리든)
        if (listTab.value === '요청') {
            loadTrash();
        }

        // 보냄 탭 — 아직 만료 전인 요청을 자동 요청취소 감지 대상으로 등록
        if (listTab.value === '요청' && requestCategory.value === 'sent') {
            prevActiveKeys.clear();

            for (const o of orders.value) {
                if (o.claimedAt && !isExpiredClaim(o)) {
                    prevActiveKeys.add(o.key ?? o.id);
                }
            }
        }
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

// 검색 적용/해제 — 노선·고객명·예약처 등 내 운행 정보에서 찾는다 (백엔드 search 파라미터)
const submitSearch = () => {
    searchOpen.value = false;
    loadFirstPage();
};

const clearSearch = () => {
    search.value = '';
    searchOpen.value = false;
    loadFirstPage();
};

const handlePage = (p) => {
    page.value = p;
    load();
};

const switchTab = (tab) => {
    listTab.value = tab;
    loadFirstPage();
};

const switchRequestCategory = (category) => {
    if (requestCategory.value === category) return;

    requestCategory.value = category;
    loadFirstPage();
};

// 진행중 탭 — 상태 세분화(전체/예약/운행중) 전환
const switchProgressStatus = (status) => {
    if (progressStatus.value === status) return;

    progressStatus.value = status;
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

// 홈 '일괄요청중' 등에서 ?tab=요청&cat=sent로 재진입하면 캐시된 화면이라도 해당 탭을 연다
watch(
    () => route.query,
    (query) => {
        let changed = false;

        if (query.tab && STATUS_TABS.some((t) => t.value === query.tab) && query.tab !== listTab.value) {
            listTab.value = query.tab;
            changed = true;
        }

        if ((query.cat === 'sent' || query.cat === 'received') && query.cat !== requestCategory.value) {
            requestCategory.value = query.cat;
            changed = true;
        }

        if (changed) {
            loadFirstPage();
        }
    },
);

onMounted(load);

// keep-alive로 캐시된 화면 재진입 시 최신 상태를 반영
onActivated(() => {
    if (!loading.value) {
        load();
    }
});
</script>

<template>
    <div class="market-page page-shell">
        <!-- 상태 탭 — 운행마켓의 필터 칩과 동일한 스타일, 우측에 검색·새로고침 -->
        <div class="status-tabs">
            <button
                v-for="tab in STATUS_TABS"
                :key="tab.value"
                type="button"
                class="status-tabs__btn"
                :class="{ 'status-tabs__btn--active': listTab === tab.value }"
                @click="switchTab(tab.value)"
            >
                {{ tab.label }}
            </button>
            <button
                type="button"
                class="status-tabs__refresh"
                aria-label="새로고침"
                title="새로고침"
                @click="loadFirstPage"
            >
                <BaseIcon name="refresh" :size="15" />
            </button>
            <button
                type="button"
                class="status-tabs__search"
                aria-label="검색"
                :class="{ 'status-tabs__search--active': search }"
                @click="searchOpen = true"
            >
                <BaseIcon name="search" :size="15" />
                <span v-if="search" class="status-tabs__search-text">{{ search }}</span>
            </button>
        </div>

        <!-- 요청 탭 — 보낸/받은 방향 카테고리, 우측에 휴지통 관리 -->
        <div v-if="listTab === '요청'" class="status-tabs status-tabs--sub">
            <button
                v-for="cat in REQUEST_CATEGORIES"
                :key="cat.value"
                type="button"
                class="status-tabs__btn"
                :class="{ 'status-tabs__btn--active': requestCategory === cat.value }"
                @click="switchRequestCategory(cat.value)"
            >
                {{ cat.label }}
            </button>
            <button
                type="button"
                class="status-tabs__trash"
                aria-label="휴지통"
                title="만료된 요청 관리"
                @click="openTrash"
            >
                <BaseIcon name="trash" :size="15" />
                <span v-if="trashCount" class="status-tabs__trash-count">{{ trashCount }}</span>
            </button>
        </div>

        <!-- 진행중 탭 — 상태 세분화 (전체/예약/운행중) -->
        <div v-if="listTab === '진행중'" class="status-tabs status-tabs--sub">
            <button
                v-for="cat in PROGRESS_STATUS_CATEGORIES"
                :key="cat.value"
                type="button"
                class="status-tabs__btn"
                :class="{ 'status-tabs__btn--active': progressStatus === cat.value }"
                @click="switchProgressStatus(cat.value)"
            >
                {{ cat.label }}
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
            :title="emptyState.title"
            :hint="emptyState.hint"
        >
            <template #action>
                <n-button type="primary" round @click="goCreate">+ 운행 등록</n-button>
            </template>
        </EmptyState>
        <!-- 요청보냄 — 최근 보낸순, 보낸 시간 구분선, 수락 대기 옆 남은 시간 -->
        <div v-else-if="listTab === '요청' && requestCategory === 'sent'" class="my-order-list">
            <div v-for="group in sentActiveGroups" :key="group.key" class="claim-group">
                <!-- 개별 요청 — 카드 밖 구분선 + 보낸 시간을 우측에 표시, 뒤에 남은 시간 카운트다운 -->
                <div v-if="!group.batchId && sentTimeText(group.orders[0])" class="claim-time">
                    <span class="claim-time__line" />
                    <span class="claim-time__meta">
                        <span class="claim-time__text">{{ sentTimeText(group.orders[0]) }}</span>
                        <span v-if="claimRemainText(group.orders[0])" class="claim-time__remain">
                            {{ claimRemainText(group.orders[0]) }}
                        </span>
                    </span>
                </div>
                <!-- 일괄요청 그룹 — 보낸 시각이 같으므로 박스·구분선 없이 한 번만 표시 -->
                <div v-if="group.batchId" class="claim-group__head">
                    <span class="claim-group__badge">일괄요청</span>
                    <span class="claim-group__count">{{ group.orders.length }}건</span>
                    <span v-if="sentTimeText(group.orders[0])" class="claim-time__text claim-time__text--head">
                        {{ sentTimeText(group.orders[0]) }}
                    </span>
                    <span v-if="claimRemainText(group.orders[0])" class="claim-time__remain">
                        {{ claimRemainText(group.orders[0]) }}
                    </span>
                </div>
                <OrderCard
                    v-for="order in group.orders"
                    :key="order.key"
                    :order="order"
                    :status-extra="claimRemainText(order)"
                />
            </div>
        </div>
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

        <!-- 검색 모달 — 노선·고객명·예약처로 내 운행 검색 -->
        <n-modal v-model:show="searchOpen" preset="card" title="검색" :style="{ maxWidth: '480px' }">
            <div class="search-modal">
                <n-input
                    v-model:value="search"
                    type="text"
                    placeholder="노선 · 고객명 · 예약처로 검색"
                    clearable
                    size="large"
                    @keyup.enter="submitSearch"
                    @clear="clearSearch"
                />
                <div class="search-modal__footer">
                    <n-button quaternary @click="clearSearch">초기화</n-button>
                    <n-button type="primary" @click="submitSearch">검색</n-button>
                </div>
            </div>
        </n-modal>

        <!-- 휴지통 모달 — 30분 안에 승인되지 않아 만료된 보낸 요청 관리 -->
        <n-modal v-model:show="trashOpen" preset="card" title="휴지통" :style="{ maxWidth: '560px' }">
            <div class="trash-modal">
                <div v-if="trashLoading" class="my-order-list">
                    <OrderCardSkeleton v-for="n in 3" :key="n" />
                </div>
                <div v-else-if="!trashAll.length" class="trash-modal__empty">
                    <p class="trash-modal__empty-title">휴지통이 비어 있습니다</p>
                    <p class="trash-modal__empty-hint">요청 후 30분 안에 승인되지 않으면 자동으로 요청취소되고 여기로 이동합니다</p>
                </div>
                <template v-else>
                    <div class="trash-modal__head">
                        <span class="trash-modal__count">{{ trashCount }}건</span>
                        <button
                            type="button"
                            class="trash-modal__clear"
                            :disabled="trashClearing"
                            @click="clearTrash"
                        >
                            {{ trashClearing ? '삭제 중...' : '전체 삭제' }}
                        </button>
                    </div>
                    <div class="my-order-list">
                        <div v-for="group in trashGroups" :key="group.key" class="claim-group">
                            <div v-if="!group.batchId && sentTimeText(group.orders[0])" class="claim-time">
                                <span class="claim-time__line" />
                                <span class="claim-time__meta">
                                    <span class="claim-time__text">{{ sentTimeText(group.orders[0]) }}</span>
                                </span>
                            </div>
                            <div v-if="group.batchId" class="claim-group__head">
                                <span class="claim-group__badge">일괄요청</span>
                                <span class="claim-group__count">{{ group.orders.length }}건</span>
                                <span v-if="sentTimeText(group.orders[0])" class="claim-time__text claim-time__text--head">
                                    {{ sentTimeText(group.orders[0]) }}
                                </span>
                            </div>
                            <div v-for="order in group.orders" :key="order.key" class="claim-trash-row">
                                <OrderCard :order="order" :status-override="REQUEST_CANCEL_STATUS" />
                                <button
                                    type="button"
                                    class="claim-trash-row__remove"
                                    :disabled="removingId === order.id"
                                    @click="removeTrash(order)"
                                >
                                    {{ removingId === order.id ? '삭제 중...' : '삭제' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </n-modal>

        <!-- 운행 등록 FAB — 커뮤니티 글쓰기와 동일한 하단 우측 원형 버튼 (정산 탭·빈 목록에서는 숨김) -->
        <button
            v-if="listTab !== '정산' && (orders.length || loading)"
            type="button"
            class="my-market-fab"
            aria-label="운행 등록"
            title="운행 등록"
            @click="goCreate"
        >
            ＋
        </button>
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

/* 운행 등록 FAB — 커뮤니티 글쓰기와 동일한 하단 우측 원형 버튼 */
.my-market-fab {
    position: fixed;
    right: max(18px, calc((100vw - 880px) / 2 + 18px));
    bottom: calc(84px + env(safe-area-inset-bottom));
    z-index: 60;
    width: 54px;
    height: 54px;
    border: 0;
    border-radius: 50%;
    background: var(--brand);
    color: #fff;
    font-size: 22px;
    font-weight: 600;
    line-height: 1;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.18);
    cursor: pointer;
    transition: transform 0.12s ease;
}

.my-market-fab:hover {
    transform: scale(1.06);
}

/* 상태 탭 — 운행마켓의 필터 칩과 동일한 스타일 */
.status-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    /* 칩 행 아래 여백 — 마켓·홈과 동일하게 공용 토큰 사용 */
    margin-bottom: var(--chips-gap);
}

.status-tabs__btn {
    flex-shrink: 0;
    padding: 7px 11px;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: var(--surface);
    color: var(--text-muted);
    font-size: 11.5px;
    font-weight: 600;
    cursor: pointer;
    transition: border-color 0.15s ease, color 0.15s ease;
}

.status-tabs__btn--active {
    border-color: var(--brand);
    color: var(--brand);
    background: var(--brand-soft);
}

/* 상태 탭 우측 — 검색·새로고침·휴지통 (마켓 필터 행의 우측 아이콘 버튼과 동일한 스타일) */
.status-tabs__search,
.status-tabs__refresh,
.status-tabs__trash {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
    padding: 7px 11px;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: var(--surface);
    color: var(--text-muted);
    cursor: pointer;
    transition: border-color 0.15s ease, color 0.15s ease;
}

.status-tabs__refresh {
    margin-left: auto;
}

.status-tabs__trash {
    margin-left: auto;
}

/* hover — 데스크톱에서만. 터치 기기는 탭 후 남는 포커스로 hover가 고정돼 '선택된 것처럼' 보이므로 제외 */
@media (hover: hover) {
    .status-tabs__search:hover,
    .status-tabs__refresh:hover,
    .status-tabs__trash:hover {
        border-color: var(--brand);
        color: var(--brand);
    }
}

.status-tabs__search--active {
    border-color: var(--brand);
    color: var(--brand);
    background: var(--brand-soft);
}

.status-tabs__search-text {
    font-size: 11.5px;
    font-weight: 600;
    max-width: 120px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* 휴지통 버튼 건수 배지 — HeaderBar .hb-badge와 동일한 16px 원형 카운트 배지 */
.status-tabs__trash-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 16px;
    height: 16px;
    padding: 0 4px;
    border-radius: 999px;
    background: var(--danger);
    color: #fff;
    font-size: 10px;
    font-weight: 400;
    line-height: 16px;
}

/* 검색 모달 */
.search-modal {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.search-modal__footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

/* 요청 탭 하위 카테고리 — 한 단계 축소된 칩 */
.status-tabs--sub {
    margin-top: -4px;
    margin-bottom: var(--chips-gap);
}

.status-tabs--sub .status-tabs__btn {
    padding: 5px 10px;
    font-size: 11px;
}

.my-order-list {
    display: flex;
    flex-direction: column;
    gap: var(--card-gap);
}

/* 요청보냄 — 일괄요청 그룹 */
.claim-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

/* 카드 밖 보낸 시간 — 우측 구분선을 채우고 보낸 시간을 우측에 표시 */
.claim-time {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 2px;
}

.claim-time__text {
    flex-shrink: 0;
    color: var(--text-muted);
    font-size: 10px;
    font-weight: 700;
    white-space: nowrap;
}

/* 보낸 시간 + 남은 시간 카운트다운 묶음 — 우측 끝에 함께 표시 */
.claim-time__meta {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
}

.claim-time__remain {
    color: var(--danger);
    font-size: 10px;
    font-weight: 700;
    white-space: nowrap;
}

.claim-time__line {
    flex: 1;
    height: 1px;
    background: var(--border);
}

/* 일괄요청 그룹 헤더 안 보낸 시간 — 구분선 없이 한 번만 */
.claim-time__text--head {
    margin-left: auto;
    font-weight: 600;
}

.claim-group__head {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 0 2px;
}

.claim-group__badge {
    display: inline-flex;
    align-items: center;
    padding: 1px 6px;
    border-radius: 999px;
    background: var(--brand-soft);
    color: var(--brand);
    font-size: 10px;
    font-weight: 400;
}

.claim-group__count {
    font-size: 10px;
    color: var(--text-muted);
}

/* 휴지통 모달 — 30분 내 미승인으로 자동 거절된 보낸 요청 관리 */
.trash-modal {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.trash-modal__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.trash-modal__count {
    font-size: 11px;
    color: var(--text-muted);
}

.trash-modal__clear {
    padding: 6px 12px;
    border: 1px solid var(--danger);
    border-radius: 9px;
    background: transparent;
    color: var(--danger);
    font-family: inherit;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.12s ease;
}

.trash-modal__clear:hover:not(:disabled) {
    background: color-mix(in srgb, var(--danger) 10%, transparent);
}

.trash-modal__clear:disabled {
    opacity: 0.5;
    cursor: default;
}

.trash-modal__empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    padding: 28px 0 12px;
}

.trash-modal__empty-title {
    margin: 0;
    font-size: 13px;
    font-weight: 700;
}

.trash-modal__empty-hint {
    margin: 0;
    font-size: 11px;
    color: var(--text-muted);
}

/* 휴지통 개별 항목 — 운행 카드 + 삭제 버튼 */
.claim-trash-row {
    display: flex;
    align-items: stretch;
    gap: 8px;
}

.claim-trash-row .order-card {
    flex: 1;
    min-width: 0;
}

.claim-trash-row__remove {
    align-self: center;
    flex-shrink: 0;
    padding: 6px 12px;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: var(--surface);
    color: var(--text-muted);
    font-family: inherit;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    transition: border-color 0.12s ease, color 0.12s ease;
}

.claim-trash-row__remove:hover:not(:disabled) {
    border-color: var(--danger);
    color: var(--danger);
}

.claim-trash-row__remove:disabled {
    opacity: 0.5;
    cursor: default;
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
