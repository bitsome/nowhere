<script setup>
import { computed, onActivated, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useDialog, useMessage } from 'naive-ui';
import { useChatsStore } from '../stores/chats';
import {
    apiAcceptOffer, apiActions, apiApproveClaim, apiRejectClaim, apiRejectOffer,
} from '../api/orders';
import { getApiErrorMessage } from '../api/client';
import BaseIcon from '../components/common/BaseIcon.vue';
import EmptyState from '../components/common/EmptyState.vue';
import UiSection from '../components/ui/UiSection.vue';

// keep-alive 캐시 매칭용 이름
defineOptions({ name: 'ActionCenterView' });

const router = useRouter();
const message = useMessage();
const dialog = useDialog();
const chats = useChatsStore();

const claims = ref([]);
const offers = ref([]);
const chatRequests = ref([]);
const loading = ref(true);
const actingId = ref(null);

const offerPendingTotal = computed(() => offers.value.reduce((sum, item) => sum + (item.pending_count || 0), 0));
const totalPending = computed(() => claims.value.length + offerPendingTotal.value + chatRequests.value.length);

const load = async () => {
    loading.value = true;

    try {
        const { data } = await apiActions();
        claims.value = data.data.claims ?? [];
        offers.value = data.data.offers ?? [];
        chatRequests.value = data.data.chat_requests ?? [];
    } catch (e) {
        message.error(getApiErrorMessage(e, '처리할 일 목록을 불러오지 못했습니다.'));
    } finally {
        loading.value = false;
    }
};

const formatWon = (v) => `${Number(v ?? 0).toLocaleString('ko-KR')}원`;
const formatWhen = (item) => `${item.service_date ?? ''} ${item.service_time ?? ''}`.trim() || '-';

const goDetail = (orderId) => router.push({ name: 'order-detail', params: { id: orderId } });

// ── 가져오기 승인/거절 ──
const approveClaim = (claim) => {
    dialog.warning({
        title: '가져오기 승인',
        content: `${claim.claimant?.name}님이 이 운행을 가져오기 요청했습니다.\n승인하면 운행이 드라이버에게 넘어갑니다.`,
        positiveText: '승인',
        negativeText: '취소',
        onPositiveClick: async () => {
            actingId.value = `c-${claim.id}`;

            try {
                await apiApproveClaim(claim.id);
                message.success('가져오기를 승인했습니다. 드라이버가 운행을 진행할 수 있습니다.');
                await load();
            } catch (e) {
                message.error(getApiErrorMessage(e, '승인에 실패했습니다.'));
            } finally {
                actingId.value = null;
            }
        },
    });
};

const rejectClaim = (claim) => {
    dialog.warning({
        title: '가져오기 거절',
        content: `${claim.claimant?.name}님의 가져오기 요청을 거절할까요?\n거절하면 운행은 마켓에 그대로 남습니다.`,
        positiveText: '거절',
        negativeText: '취소',
        onPositiveClick: async () => {
            actingId.value = `c-${claim.id}`;

            try {
                await apiRejectClaim(claim.id);
                message.success('가져오기를 거절했습니다. 운행은 마켓에 그대로 남습니다.');
                await load();
            } catch (e) {
                message.error(getApiErrorMessage(e, '거절에 실패했습니다.'));
            } finally {
                actingId.value = null;
            }
        },
    });
};

// ── 요금 제안 수락/거절 ──
const diffLabel = (offer, order) => {
    const base = Number(order.expected_revenue) || 0;
    const amount = Number(offer.amount) || 0;

    if (base <= 0) return '';
    if (amount > base) return `등록가 대비 +${(amount - base).toLocaleString('ko-KR')}원`;
    if (amount < base) return `등록가 대비 -${(base - amount).toLocaleString('ko-KR')}원`;

    return '등록가와 동일';
};

const acceptOffer = (order, offer) => {
    dialog.warning({
        title: '요금 제안 수락',
        content: `${offer.driver?.name}님의 ${formatWon(offer.amount)} 제안을 수락할까요?\n수락하면 운행이 해당 기사에게 넘어가고 딜 금액으로 확정됩니다.`,
        positiveText: '수락',
        negativeText: '취소',
        onPositiveClick: async () => {
            actingId.value = `o-${offer.id}`;

            try {
                await apiAcceptOffer(order.id, offer.id);
                message.success(`제안을 수락했습니다. ${formatWon(offer.amount)}에 딜이 성사되었습니다.`);
                await load();
            } catch (e) {
                message.error(getApiErrorMessage(e, '제안 수락에 실패했습니다.'));
            } finally {
                actingId.value = null;
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
            actingId.value = `o-${offer.id}`;

            try {
                await apiRejectOffer(order.id, offer.id);
                message.success('제안을 거절했습니다.');
                await load();
            } catch (e) {
                message.error(getApiErrorMessage(e, '제안 거절에 실패했습니다.'));
            } finally {
                actingId.value = null;
            }
        },
    });
};

// ── 채팅 요청 — 대화방에서 응답 ──
const respondInChat = async (request) => {
    try {
        await chats.open(request.conversation_id);
        router.push({ name: 'chat' });
    } catch (e) {
        message.error(getApiErrorMessage(e, '대화방을 열지 못했습니다.'));
    }
};

// 채팅 요청 카드 유형 색상
const CHAT_TAG = {
    time_change: '시간 변경',
    route_change: '경로 변경',
    payment_change: '요금 협의',
    cancel: '취소',
};

onMounted(load);

// keep-alive 복귀 시 최신 상태 반영
onActivated(() => {
    if (!loading.value) {
        load();
    }
});
</script>

<template>
    <div class="actions-page">
        <div class="actions-head">
            <div>
                <h1 class="actions-head__title">처리할 일</h1>
                <p class="actions-head__desc">운행 승인·요금 제안·채팅 요청을 한곳에서 처리합니다.</p>
            </div>
            <div class="actions-head__actions">
                <span v-if="totalPending" class="actions-head__count">대기 {{ totalPending }}건</span>
                <button type="button" class="actions-head__refresh" title="새로고침" @click="load">
                    <BaseIcon name="refresh" :size="16" />
                </button>
            </div>
        </div>

        <!-- 로딩 -->
        <div v-if="loading" class="actions-list">
            <div v-for="n in 3" :key="n" class="actions-card actions-card--skeleton">
                <div class="sk-line" style="width: 45%; height: 14px;" />
                <div class="sk-line" style="width: 80%;" />
                <div class="sk-line" style="width: 35%;" />
            </div>
        </div>

        <EmptyState
            v-else-if="!totalPending"
            icon="inbox"
            title="처리할 일이 없습니다"
            hint="운행 승인·요금 제안·채팅 요청이 도착하면 이곳에 모입니다"
        />

        <div v-else class="actions-list">
            <!-- 승인 요청 -->
            <UiSection v-if="claims.length" title="승인 요청">
                <div class="actions-sub">
                    <span class="actions-sub__badge">{{ claims.length }}건</span>
                </div>
                <article v-for="claim in claims" :key="claim.id" class="actions-card">
                    <button type="button" class="actions-card__head" @click="goDetail(claim.id)">
                        <div class="actions-card__route">
                            <strong>{{ claim.route }}</strong>
                            <span class="actions-card__meta">{{ formatWhen(claim) }} · {{ claim.order_number }}</span>
                        </div>
                        <BaseIcon name="arrow-forward" :size="14" class="actions-card__arrow" />
                    </button>
                    <div class="claim-item">
                        <div class="claim-item__info">
                            <span class="claim-item__name">{{ claim.claimant?.name || '기사' }}</span>
                            <span v-if="claim.claimant?.rating" class="claim-item__rating">
                                ★ {{ claim.claimant.rating }} ({{ claim.claimant.review_count }})
                            </span>
                            <span class="claim-item__text">운행 시작 승인을 요청했습니다</span>
                        </div>
                        <div class="claim-item__actions">
                            <button
                                type="button"
                                class="actions-btn actions-btn--no"
                                :disabled="actingId"
                                @click="rejectClaim(claim)"
                            >
                                거절
                            </button>
                            <button
                                type="button"
                                class="actions-btn actions-btn--ok"
                                :disabled="actingId"
                                @click="approveClaim(claim)"
                            >
                                승인
                            </button>
                        </div>
                    </div>
                </article>
            </UiSection>

            <!-- 요금 제안 -->
            <UiSection v-if="offers.length" title="요금 제안">
                <div class="actions-sub">
                    <span class="actions-sub__badge">{{ offerPendingTotal }}건</span>
                </div>
                <article v-for="order in offers" :key="order.id" class="actions-card">
                    <button type="button" class="actions-card__head" @click="goDetail(order.id)">
                        <div class="actions-card__route">
                            <strong>{{ order.route }}</strong>
                            <span class="actions-card__meta">
                                {{ formatWhen(order) }} · 등록가 {{ formatWon(order.expected_revenue) }}
                            </span>
                        </div>
                        <span class="actions-card__count">{{ order.pending_count }}건</span>
                    </button>

                    <div v-for="offer in order.offers" :key="offer.id" class="offer-item">
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
                                class="actions-btn actions-btn--no"
                                :disabled="actingId"
                                @click="rejectOffer(order, offer)"
                            >
                                거절
                            </button>
                            <button
                                type="button"
                                class="actions-btn actions-btn--ok"
                                :disabled="actingId"
                                @click="acceptOffer(order, offer)"
                            >
                                수락
                            </button>
                        </div>
                    </div>
                </article>
            </UiSection>

            <!-- 채팅 요청 -->
            <UiSection v-if="chatRequests.length" title="채팅 요청">
                <div class="actions-sub">
                    <span class="actions-sub__badge">{{ chatRequests.length }}건</span>
                </div>
                <article v-for="request in chatRequests" :key="request.id" class="actions-card">
                    <div class="chat-item">
                        <span class="chat-item__tag" :class="`chat-item__tag--${request.type}`">
                            {{ CHAT_TAG[request.type] ?? '요청' }}
                        </span>
                        <div class="chat-item__body">
                            <strong class="chat-item__title">{{ request.title }}</strong>
                            <span v-if="request.order_route" class="chat-item__route">{{ request.order_route }}</span>
                            <p v-if="request.lines.length" class="chat-item__lines">
                                <span v-for="line in request.lines" :key="line">{{ line }}</span>
                            </p>
                        </div>
                        <button type="button" class="actions-btn actions-btn--chat" @click="respondInChat(request)">
                            채팅에서 응답
                        </button>
                    </div>
                </article>
            </UiSection>
        </div>
    </div>
</template>

<style scoped>
.actions-page {
    width: 100%;
    max-width: 880px;
    margin: 0 auto;
    padding: 4px 20px 24px;
}

@media (max-width: 480px) {
    .actions-page {
        width: calc(100% + 40px);
        margin: 0 -20px;
        padding: 4px 14px 24px;
        max-width: none;
    }
}

.actions-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-top: 6px;
}

.actions-head__title {
    margin: 0;
    font-size: 22px;
    font-weight: 800;
    letter-spacing: -0.5px;
}

.actions-head__desc {
    margin: 3px 0 0;
    font-size: 12px;
    color: var(--text-muted);
}

.actions-head__actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.actions-head__count {
    padding: 5px 10px;
    border-radius: 9px;
    background: color-mix(in srgb, var(--brand) 10%, transparent);
    border: 1px solid color-mix(in srgb, var(--brand) 28%, transparent);
    color: var(--brand);
    font-size: 11px;
    font-weight: 700;
}

.actions-head__refresh {
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

.actions-list {
    display: flex;
    flex-direction: column;
    gap: 18px;
    margin-top: 16px;
}

.actions-sub {
    display: flex;
    align-items: center;
    margin: 2px 0 8px;
}

.actions-sub__badge {
    padding: 3px 9px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--text-muted) 12%, transparent);
    color: var(--text-muted);
    font-size: 10px;
    font-weight: 700;
}

.actions-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 14px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
    margin-bottom: 10px;
}

.actions-card--skeleton {
    display: flex;
    flex-direction: column;
    gap: 10px;
    min-height: 90px;
}

.actions-card__head {
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

.actions-card__route {
    min-width: 0;
}

.actions-card__route strong {
    display: block;
    font-size: 15px;
    font-weight: 800;
    letter-spacing: -0.2px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.actions-card__meta {
    display: block;
    margin-top: 4px;
    font-size: 12px;
    color: var(--text-muted);
}

.actions-card__count {
    flex-shrink: 0;
    padding: 4px 10px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--status-trading) 12%, transparent);
    color: var(--status-trading);
    font-size: 11px;
    font-weight: 700;
}

.actions-card__arrow {
    flex-shrink: 0;
    color: var(--text-muted);
}

/* 가져오기 승인 */
.claim-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 2px 2px;
}

.claim-item__info {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.claim-item__name {
    font-size: 13px;
    font-weight: 700;
}

.claim-item__rating {
    font-size: 11px;
    color: var(--text-muted);
}

.claim-item__text {
    width: 100%;
    font-size: 11px;
    color: var(--text-muted);
}

.claim-item__actions {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
}

/* 요금 제안 */
.offer-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 2px 2px;
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

/* 공용 액션 버튼 */
.actions-btn {
    padding: 7px 14px;
    border-radius: 999px;
    border: 0;
    font-family: inherit;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: opacity 0.12s ease, transform 0.1s ease;
}

.actions-btn:disabled {
    opacity: 0.5;
    cursor: default;
}

.actions-btn:not(:disabled):active {
    transform: scale(0.96);
}

.actions-btn--ok {
    background: var(--brand);
    color: #07120e;
}

.actions-btn--no {
    background: color-mix(in srgb, var(--danger) 12%, transparent);
    color: var(--danger);
}

.actions-btn--chat {
    flex-shrink: 0;
    border: 1px solid color-mix(in srgb, var(--brand) 40%, transparent);
    background: transparent;
    color: var(--brand);
}

/* 채팅 요청 */
.chat-item {
    display: flex;
    align-items: center;
    gap: 10px;
}

.chat-item__tag {
    flex-shrink: 0;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 700;
    background: color-mix(in srgb, var(--brand) 14%, transparent);
    color: var(--brand);
}

.chat-item__tag--cancel {
    background: color-mix(in srgb, var(--danger) 14%, transparent);
    color: var(--danger);
}

.chat-item__body {
    flex: 1;
    min-width: 0;
}

.chat-item__title {
    display: block;
    font-size: 13px;
    font-weight: 700;
}

.chat-item__route {
    display: block;
    margin-top: 2px;
    font-size: 11px;
    color: var(--text-muted);
}

.chat-item__lines {
    margin: 5px 0 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.chat-item__lines span {
    font-size: 11px;
    color: var(--text-muted);
}
</style>
