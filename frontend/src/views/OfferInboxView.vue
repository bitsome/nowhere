<script setup>
import { computed, onActivated, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useDialog, useMessage } from 'naive-ui';
import { apiAcceptOffer, apiOfferInbox, apiRejectOffer } from '../api/orders';
import { getApiErrorMessage } from '../api/client';
import BaseIcon from '../components/common/BaseIcon.vue';
import EmptyState from '../components/common/EmptyState.vue';

// keep-alive 캐시 매칭용 이름
defineOptions({ name: 'OfferInboxView' });

const router = useRouter();
const message = useMessage();
const dialog = useDialog();

const items = ref([]);
const loading = ref(true);
const actingOfferId = ref(null);

// 등록 금액 대비 제안 추이 라벨
const diffLabel = (offer, order) => {
    const base = Number(order.expected_revenue) || 0;
    const amount = Number(offer.amount) || 0;

    if (base <= 0) return '';
    if (amount > base) return `등록가 대비 +${(amount - base).toLocaleString('ko-KR')}원`;
    if (amount < base) return `등록가 대비 -${(base - amount).toLocaleString('ko-KR')}원`;

    return '등록가와 동일';
};

const totalPending = computed(() => items.value.reduce((sum, item) => sum + (item.pending_count || 0), 0));

const load = async () => {
    loading.value = true;

    try {
        const { data } = await apiOfferInbox();
        items.value = data.data ?? [];
    } catch (e) {
        message.error(getApiErrorMessage(e, '제안 목록을 불러오지 못했습니다.'));
    } finally {
        loading.value = false;
    }
};

const formatWon = (v) => `${Number(v ?? 0).toLocaleString('ko-KR')}원`;

const goDetail = (orderId) => router.push({ name: 'order-detail', params: { id: orderId } });

const acceptOffer = (order, offer) => {
    dialog.warning({
        title: '요금 제안 수락',
        content: `${offer.driver?.name}님의 ${formatWon(offer.amount)} 제안을 수락할까요?\n수락하면 운행이 해당 기사에게 넘어가고 딜 금액으로 확정됩니다.`,
        positiveText: '수락',
        negativeText: '취소',
        onPositiveClick: async () => {
            actingOfferId.value = offer.id;

            try {
                await apiAcceptOffer(order.id, offer.id);
                message.success(`제안을 수락했습니다. ${formatWon(offer.amount)}에 딜이 성사되었습니다.`);
                await load();
            } catch (e) {
                message.error(getApiErrorMessage(e, '제안 수락에 실패했습니다.'));
            } finally {
                actingOfferId.value = null;
            }
        },
    });
};

const rejectOffer = (order, offer) => {
    dialog.warning({
        title: '요금 제안 거절',
        content: `${offer.driver?.name}님의 제안을 거절할까요? 운행은 마켓에 그대로 남습니다.`,
        positiveText: '거절',
        negativeText: '취소',
        onPositiveClick: async () => {
            actingOfferId.value = offer.id;

            try {
                await apiRejectOffer(order.id, offer.id);
                message.success('제안을 거절했습니다.');
                await load();
            } catch (e) {
                message.error(getApiErrorMessage(e, '제안 거절에 실패했습니다.'));
            } finally {
                actingOfferId.value = null;
            }
        },
    });
};

onMounted(load);

// keep-alive 복귀 시 최신 제안 상태 반영
onActivated(() => {
    if (!loading.value) {
        load();
    }
});
</script>

<template>
    <div class="offers-page">
        <div class="offers-head">
            <div>
                <h1 class="offers-head__title">제안 관리</h1>
                <p class="offers-head__desc">내 운행에 들어온 요금 제안을 한곳에서 비교하고 수락할 수 있습니다.</p>
            </div>
            <div class="offers-head__actions">
                <span v-if="items.length" class="offers-head__count">대기 {{ totalPending }}건</span>
                <button type="button" class="offers-head__refresh" title="새로고침" @click="load">
                    <BaseIcon name="refresh" :size="16" />
                </button>
            </div>
        </div>

        <!-- 로딩 -->
        <div v-if="loading" class="offers-list">
            <div v-for="n in 2" :key="n" class="offers-card offers-card--skeleton">
                <div class="sk-line" style="width: 55%; height: 14px;" />
                <div class="sk-line" style="width: 80%;" />
                <div class="sk-line" style="width: 35%;" />
            </div>
        </div>

        <EmptyState
            v-else-if="!items.length"
            icon="inbox"
            title="대기 중인 제안이 없습니다"
            hint="기사가 요금을 제안하면 이곳에서 한 번에 비교·수락할 수 있어요"
        >
            <template #action>
                <button type="button" class="offers-empty-btn" @click="router.push({ name: 'my-market' })">
                    내가 등록한 운행 보기 →
                </button>
            </template>
        </EmptyState>

        <!-- 운행별 제안 카드 -->
        <div v-else class="offers-list">
            <article v-for="order in items" :key="order.id" class="offers-card">
                <button type="button" class="offers-card__head" @click="goDetail(order.id)">
                    <div class="offers-card__route">
                        <strong>{{ order.route }}</strong>
                        <span class="offers-card__meta">
                            {{ order.service_date }} {{ order.service_time }}
                            <span class="offers-card__meta-sep">·</span>
                            등록가 {{ formatWon(order.expected_revenue) }}
                        </span>
                    </div>
                    <span class="offers-card__count">{{ order.pending_count }}건</span>
                </button>

                <div class="offer-item" :class="{ 'offer-item--acting': actingOfferId === offer.id }">
                    <div class="offer-item__info">
                        <div class="offer-item__row">
                            <strong class="offer-item__amount">{{ formatWon(offer.amount) }}</strong>
                            <span class="offer-item__diff">{{ diffLabel(offer, order) }}</span>
                        </div>
                        <div class="offer-item__driver">
                            <span class="offer-item__name">{{ offer.driver?.name || '기사' }}</span>
                            <span v-if="offer.driver?.rating" class="offer-item__rating">
                                ★ {{ offer.driver.rating }} ({{ offer.driver.review_count }})
                            </span>
                        </div>
                        <p v-if="offer.message" class="offer-item__msg">{{ offer.message }}</p>
                    </div>
                    <div class="offer-item__actions">
                        <button
                            type="button"
                            class="offer-item__btn offer-item__btn--no"
                            :disabled="actingOfferId"
                            @click="rejectOffer(order, offer)"
                        >
                            거절
                        </button>
                        <button
                            type="button"
                            class="offer-item__btn offer-item__btn--ok"
                            :disabled="actingOfferId"
                            @click="acceptOffer(order, offer)"
                        >
                            수락
                        </button>
                    </div>
                </div>
            </article>
        </div>
    </div>
</template>

<style scoped>
.offers-page {
    width: 100%;
    max-width: 880px;
    margin: 0 auto;
    padding: 4px 20px 24px;
}

@media (max-width: 480px) {
    .offers-page {
        width: calc(100% + 40px);
        margin: 0 -20px;
        padding: 4px 14px 24px;
        max-width: none;
    }
}

.offers-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-top: 6px;
}

.offers-head__title {
    margin: 0;
    font-size: 22px;
    font-weight: 800;
    letter-spacing: -0.5px;
}

.offers-head__desc {
    margin: 3px 0 0;
    font-size: 12px;
    color: var(--text-muted);
}

.offers-head__actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.offers-head__count {
    padding: 5px 10px;
    border-radius: 9px;
    background: color-mix(in srgb, var(--status-trading) 12%, transparent);
    border: 1px solid color-mix(in srgb, var(--status-trading) 30%, transparent);
    color: var(--status-trading);
    font-size: 11px;
    font-weight: 700;
}

.offers-head__refresh {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    border: 1px solid var(--border);
    background: var(--surface);
    color: var(--text-muted);
    cursor: pointer;
    font-family: inherit;
}

.offers-list {
    display: flex;
    flex-direction: column;
    gap: 14px;
    margin-top: 16px;
}

.offers-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 14px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
}

.offers-card--skeleton {
    display: flex;
    flex-direction: column;
    gap: 10px;
    min-height: 100px;
}

.offers-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    width: 100%;
    padding: 0 2px 12px;
    border: 0;
    border-bottom: 1px solid color-mix(in srgb, var(--border) 80%, transparent);
    background: transparent;
    color: var(--text);
    font-family: inherit;
    text-align: left;
    cursor: pointer;
}

.offers-card__route {
    min-width: 0;
}

.offers-card__route strong {
    display: block;
    font-size: 15px;
    font-weight: 800;
    letter-spacing: -0.2px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.offers-card__meta {
    display: block;
    margin-top: 4px;
    font-size: 12px;
    color: var(--text-muted);
}

.offers-card__meta-sep {
    margin: 0 4px;
    opacity: 0.6;
}

.offers-card__count {
    flex-shrink: 0;
    padding: 4px 10px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--status-trading) 12%, transparent);
    color: var(--status-trading);
    font-size: 11px;
    font-weight: 700;
}

.offer-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 2px 2px;
    opacity: 1;
    transition: opacity 0.15s ease;
}

.offer-item--acting {
    opacity: 0.55;
}

.offer-item__info {
    flex: 1;
    min-width: 0;
}

.offer-item__row {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.offer-item__amount {
    font-size: 16px;
    font-weight: 800;
    letter-spacing: -0.3px;
    color: var(--brand);
}

.offer-item__diff {
    font-size: 10px;
    font-weight: 700;
    color: var(--text-muted);
}

.offer-item__driver {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 4px;
}

.offer-item__name {
    font-size: 12px;
    font-weight: 700;
}

.offer-item__rating {
    font-size: 11px;
    color: var(--text-muted);
}

.offer-item__msg {
    margin: 6px 0 0;
    font-size: 11px;
    color: var(--text-muted);
    line-height: 1.45;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.offer-item__actions {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
}

.offer-item__btn {
    padding: 7px 14px;
    border-radius: 999px;
    border: 0;
    font-family: inherit;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: opacity 0.12s ease, transform 0.1s ease;
}

.offer-item__btn:disabled {
    opacity: 0.5;
    cursor: default;
}

.offer-item__btn:not(:disabled):active {
    transform: scale(0.96);
}

.offer-item__btn--ok {
    background: var(--brand);
    color: #07120e;
}

.offer-item__btn--no {
    background: color-mix(in srgb, var(--danger) 12%, transparent);
    color: var(--danger);
}

.offers-empty-btn {
    padding: 9px 16px;
    border: 1px solid color-mix(in srgb, var(--brand) 40%, transparent);
    border-radius: 999px;
    background: transparent;
    color: var(--brand);
    font-family: inherit;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
}
</style>
