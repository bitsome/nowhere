<script setup>
import { computed, onActivated, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useNotification } from 'naive-ui';
import { apiOrders } from '../api/orders';
import { getApiErrorMessage } from '../api/client';
import OrderCard from '../components/orders/OrderCard.vue';
import SetGroupCard from '../components/orders/SetGroupCard.vue';
import OrderCardSkeleton from '../components/orders/OrderCardSkeleton.vue';
import EmptyState from '../components/common/EmptyState.vue';

defineOptions({ name: 'OrderFavoritesView' });

const router = useRouter();
const notification = useNotification();

// 찜한 운행 — 마켓에서 아직 가져올 수 있는 운행만 서버가 정리해서 내려준다
const orders = ref([]);
const pagination = ref(null);
const loading = ref(true);
const moreLoading = ref(false);

// '더보기' 한 번에 붙는 운행 수 — 마켓 목록과 동일하게 20건 단위
const PAGE_SIZE = 20;

const rows = computed(() => orders.value);
const hasMore = computed(() => {
    const total = pagination.value?.total;

    return total !== undefined && total !== null && orders.value.length < total;
});

const load = async () => {
    loading.value = true;

    try {
        const { data } = await apiOrders({
            scope: 'market',
            quick: 'favorites',
            sort: 'date',
            per_page: PAGE_SIZE,
        });
        orders.value = data.data ?? [];
        pagination.value = data.meta?.pagination ?? null;
    } catch (e) {
        notification.error(getApiErrorMessage(e, '찜한 운행을 불러오지 못했습니다.'));
    } finally {
        loading.value = false;
    }
};

const loadMore = async () => {
    if (moreLoading.value || !hasMore.value) {
        return;
    }

    moreLoading.value = true;

    try {
        const nextPage = Math.floor(orders.value.length / PAGE_SIZE) + 1;
        const { data } = await apiOrders({
            scope: 'market',
            quick: 'favorites',
            sort: 'date',
            per_page: PAGE_SIZE,
            page: nextPage,
        });

        const seen = new Set(orders.value.map((o) => o.key ?? o.id));

        for (const row of data.data ?? []) {
            if (!seen.has(row.key ?? row.id)) {
                orders.value.push(row);
                seen.add(row.key ?? row.id);
            }
        }
    } catch (e) {
        notification.error(getApiErrorMessage(e, '찜한 운행을 더 불러오지 못했습니다.'));
    } finally {
        moreLoading.value = false;
    }
};

// 하트 해제 — 목록에서 바로 제거한다 (찜 해제는 서버에 이미 반영됨)
const onFavoriteChanged = (orderId, favorited) => {
    if (!favorited) {
        orders.value = orders.value.filter((o) => o.id !== orderId);
    }
};

const goMarket = () => {
    router.push({ name: 'market' });
};

onMounted(() => {
    load();
});

// keep-alive 복귀 — 마켓에서 찜이 바뀌었을 수 있으니 조용히 다시 불러온다
onActivated(() => {
    load();
});
</script>

<template>
    <div class="fav-page page-shell">
        <!-- 찜 소개 — 보관 중인 운행은 마켓에서 빠질 수 있다는 점을 짧게 안내 -->
        <div class="fav-head">
            <div class="fav-head__text">
                <p class="fav-head__eyebrow">찜한 운행</p>
                <p class="fav-head__hint">
                    하트로 모아둔 운행이에요. 다른 기사님이 가져가거나 취소되면 자동으로 정리돼요.
                </p>
            </div>
        </div>

        <n-spin :show="false" class="fav-body">
            <!-- 로딩 스켈레톤 -->
            <div v-if="loading" class="fav-list">
                <OrderCardSkeleton v-for="n in 4" :key="n" />
            </div>

            <EmptyState
                v-else-if="orders.length === 0"
                icon="heart"
                :title="'찜한 운행이 없습니다'"
                :hint="'마켓에서 마음에 드는 운행의 하트를 눌러 모아보세요'"
            >
                <template #action>
                    <button type="button" class="fav-empty__cta" @click="goMarket">마켓 보러 가기</button>
                </template>
            </EmptyState>

            <template v-else>
                <div class="fav-list">
                    <SetGroupCard
                        v-for="(order, si) in orders.filter((o) => o.kind === 'set')"
                        :key="order.key"
                        :set="order"
                    />
                    <OrderCard
                        v-for="(order, oi) in orders.filter((o) => o.kind !== 'set')"
                        :key="order.key"
                        :order="order"
                        :favoriteable="true"
                        :favorited="true"
                        @favorite-change="onFavoriteChanged"
                    />
                </div>

                <div class="fav-more-wrap">
                    <button
                        v-if="hasMore"
                        type="button"
                        class="fav-more"
                        :disabled="moreLoading"
                        @click="loadMore"
                    >
                        {{ moreLoading ? '불러오는 중…' : '더보기' }}
                    </button>
                    <p v-else class="fav-more__end">찜한 운행을 모두 확인했어요</p>
                </div>
            </template>
        </n-spin>
    </div>
</template>

<style scoped>
.fav-page {
    padding-bottom: 24px;
}

/* 찜 소개 헤더 */
.fav-head {
    padding: 14px 2px 12px;
}
.fav-head__eyebrow {
    margin: 0 0 4px;
    font-size: 10px;
    font-weight: 700;
    color: var(--danger);
}
.fav-head__hint {
    margin: 0;
    font-size: 11px;
    line-height: 1.5;
    color: var(--text-muted);
}

/* 운행 카드 목록 — 마켓과 동일한 세로 카드 흐름 */
.fav-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.fav-more-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-top: 14px;
}
.fav-more {
    padding: 9px 22px;
    border: 1px solid var(--border);
    border-radius: 999px;
    background: var(--surface);
    color: var(--text-muted);
    font-size: 11.5px;
    font-weight: 600;
    cursor: pointer;
    transition: border-color 0.15s ease, color 0.15s ease;
}
.fav-more:hover:not(:disabled) {
    border-color: var(--brand);
    color: var(--brand);
}
.fav-more:disabled {
    opacity: 0.6;
    cursor: default;
}
.fav-more__end {
    margin: 0;
    font-size: 10.5px;
    color: var(--text-muted);
}

/* brand는 라이트(#36adff)·다크(#63e2b7) 모두 밝아 흰 글자 대비가 약함 — 앱 표준 #07120e를 두 모드 공통 사용 */
.fav-empty__cta {
    margin-top: 4px;
    padding: 9px 20px;
    border: 0;
    border-radius: 999px;
    background: var(--brand);
    color: #07120e;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
}
</style>
