<script setup>
import { ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useMessage } from 'naive-ui';
import { useAuthStore } from '../stores/auth';
import { apiMyReviews, apiReviews } from '../api/reviews';
import { getApiErrorMessage } from '../api/client';
import EmptyState from '../components/common/EmptyState.vue';
import BaseIcon from '../components/common/BaseIcon.vue';

const router = useRouter();
const auth = useAuthStore();
const message = useMessage();

const tab = ref('received'); // received(받은 리뷰) | sent(내가 쓴 리뷰)
const reviews = ref([]);
const summary = ref(null);
const loading = ref(true);
const sentReviews = ref([]);
const sentLoading = ref(false);

// auth.user 로드 후 id가 준비됐을 때만 조회 (직접 URL 진입/새로고침 대응)
const loadReceived = async (userId) => {
    if (!userId) {
        return;
    }

    loading.value = true;

    try {
        const { data } = await apiReviews(userId);
        reviews.value = data.data ?? [];
        summary.value = data.summary ?? null;
    } catch (e) {
        message.error(getApiErrorMessage(e, '리뷰를 불러오지 못했습니다.'));
    } finally {
        loading.value = false;
    }
};

const loadSent = async (userId) => {
    if (!userId) {
        return;
    }

    sentLoading.value = true;

    try {
        const { data } = await apiMyReviews(userId);
        sentReviews.value = data.data ?? [];
    } catch (e) {
        message.error(getApiErrorMessage(e, '리뷰를 불러오지 못했습니다.'));
    } finally {
        sentLoading.value = false;
    }
};

const setTab = (name) => {
    tab.value = name;

    if (name === 'sent' && !sentReviews.value.length && !sentLoading.value) {
        loadSent(auth.user?.id);
    }
};

const maxDistribution = () => Math.max(1, ...(summary.value?.distribution ?? []).map((d) => d.count));

// 리뷰가 남겨진 운행 상세로 이동
const openOrder = (review) => {
    if (review.order?.id) {
        router.push({ name: 'order-detail', params: { id: review.order.id } });
    }
};

watch(
    () => auth.user?.id,
    (id) => {
        if (id) {
            loadReceived(id);
        }
    },
    { immediate: true },
);
</script>

<template>
    <div class="reviews-page">
        <div class="page-head">
            <div>
                <p class="page-head__desc">운행 완료 후 남긴 리뷰를 확인합니다.</p>
            </div>
        </div>

        <!-- 받은 리뷰 / 내가 쓴 리뷰 -->
        <div class="reviews-tabs">
            <button
                type="button"
                class="reviews-tabs__item"
                :class="{ 'reviews-tabs__item--active': tab === 'received' }"
                @click="setTab('received')"
            >
                받은 리뷰
                <span class="reviews-tabs__count">{{ summary?.count ?? 0 }}</span>
            </button>
            <button
                type="button"
                class="reviews-tabs__item"
                :class="{ 'reviews-tabs__item--active': tab === 'sent' }"
                @click="setTab('sent')"
            >
                내가 쓴 리뷰
                <span class="reviews-tabs__count">{{ sentReviews.length }}</span>
            </button>
        </div>

        <!-- 받은 리뷰 -->
        <template v-if="tab === 'received'">
            <div v-if="loading" class="reviews-skeleton">
                <div v-for="n in 3" :key="n" class="sk-card reviews-skeleton__item">
                    <div class="sk-line sk-line--md" style="width: 35%" />
                    <div class="sk-line" style="margin-top: 10px" />
                    <div class="sk-line" style="margin-top: 8px; width: 70%" />
                </div>
            </div>

            <template v-else>
                <!-- 평점 요약 -->
                <div v-if="summary" class="review-summary">
                    <div class="review-summary__score">
                        <strong>{{ summary.rating ?? 0 }}</strong>
                        <span>/ 5</span>
                        <em class="review-summary__count">{{ summary.count ?? 0 }}개 리뷰</em>
                    </div>
                    <div class="review-summary__dist">
                        <div v-for="d in summary.distribution" :key="d.star" class="review-dist">
                            <span class="review-dist__star">
                                <BaseIcon name="star" :size="11" />
                                {{ d.star }}
                            </span>
                            <div class="review-dist__bar">
                                <div class="review-dist__fill" :style="{ width: `${(d.count / maxDistribution()) * 100}%` }" />
                            </div>
                            <span class="review-dist__count">{{ d.count }}</span>
                        </div>
                    </div>
                </div>

                <EmptyState
                    v-if="!reviews.length"
                    icon="inbox"
                    title="아직 리뷰가 없습니다"
                    hint="운행을 완료하면 리뷰가 여기에 쌓입니다"
                />

                <div v-else class="review-list">
                    <div v-for="review in reviews" :key="review.id" class="review-item">
                        <div class="review-item__head">
                            <div class="review-item__who">
                                <span class="review-item__avatar">{{ review.reviewer?.name?.charAt(0) ?? '?' }}</span>
                                <strong>{{ review.reviewer?.name ?? '익명' }}</strong>
                                <span class="review-item__stars">
                                    <BaseIcon v-for="n in review.rating" :key="`s${n}`" name="star" :size="12" />
                                    <BaseIcon v-for="n in 5 - review.rating" :key="`o${n}`" name="star-o" :size="12" />
                                </span>
                            </div>
                            <span class="review-item__time">{{ review.created_at }}</span>
                        </div>
                        <p v-if="review.content" class="review-item__content">{{ review.content }}</p>
                        <button
                            v-if="review.order?.id"
                            type="button"
                            class="review-item__order"
                            @click="openOrder(review)"
                        >
                            <BaseIcon name="truck" :size="14" />
                            운행 {{ review.order.order_number ?? '#' + review.order.id }}
                            <BaseIcon name="arrow-forward" :size="14" class="review-item__order-arrow" />
                        </button>
                    </div>
                </div>
            </template>
        </template>

        <!-- 내가 쓴 리뷰 -->
        <template v-else>
            <div v-if="sentLoading" class="reviews-skeleton">
                <div v-for="n in 3" :key="n" class="sk-card reviews-skeleton__item">
                    <div class="sk-line sk-line--md" style="width: 35%" />
                    <div class="sk-line" style="margin-top: 10px" />
                </div>
            </div>

            <EmptyState
                v-else-if="!sentReviews.length"
                icon="edit"
                title="아직 쓴 리뷰가 없습니다"
                hint="완료된 운행 상세에서 리뷰를 남길 수 있습니다"
            />

            <div v-else class="review-list">
                <div v-for="review in sentReviews" :key="review.id" class="review-item">
                    <div class="review-item__head">
                        <div class="review-item__who">
                                <span class="review-item__avatar">{{ review.reviewee?.name?.charAt(0) ?? '?' }}</span>
                                <strong>{{ review.reviewee?.name ?? '익명' }}에게</strong>
                                <span class="review-item__stars">
                                    <BaseIcon v-for="n in review.rating" :key="`f${n}`" name="star" :size="12" />
                                    <BaseIcon v-for="n in 5 - review.rating" :key="`e${n}`" name="star-o" :size="12" />
                                </span>
                            </div>
                        <span class="review-item__time">{{ review.created_at }}</span>
                    </div>
                    <p v-if="review.content" class="review-item__content">{{ review.content }}</p>
                    <button
                        v-if="review.order?.id"
                        type="button"
                        class="review-item__order"
                        @click="openOrder(review)"
                    >
                        <BaseIcon name="truck" :size="14" />
                        운행 {{ review.order.order_number ?? '#' + review.order.id }}
                        <BaseIcon name="arrow-forward" :size="14" class="review-item__order-arrow" />
                    </button>
                </div>
            </div>
        </template>
    </div>
</template>

<style scoped>
.reviews-page {
    min-height: 200px;
}

/* 받은 리뷰 / 내가 쓴 리뷰 탭 */
.reviews-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 14px;
    padding: 4px;
    border: 1px solid var(--border);
    border-radius: 12px;
    background: var(--surface);
}

.reviews-tabs__item {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 8px 12px;
    border: 0;
    border-radius: 9px;
    background: transparent;
    color: var(--text-muted);
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s, color 0.15s;
}

.reviews-tabs__item--active {
    background: var(--accent);
    color: #fff;
}

.reviews-tabs__count {
    padding: 1px 7px;
    border-radius: 999px;
    background: var(--border);
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 700;
}

.reviews-tabs__item--active .reviews-tabs__count {
    background: rgba(255, 255, 255, 0.25);
    color: #fff;
}

.reviews-skeleton {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.reviews-skeleton__item {
    display: flex;
    flex-direction: column;
}

/* 평점 요약 — 점수 + 별점 분포 */
.review-summary {
    display: flex;
    gap: 20px;
    align-items: center;
    margin-bottom: 14px;
    padding: 16px;
    border: 1px solid var(--border);
    border-radius: 14px;
    background: var(--surface);
}

.review-summary__score {
    flex-shrink: 0;
    display: flex;
    align-items: baseline;
    gap: 2px;
}

.review-summary__score strong {
    font-size: 34px;
    font-weight: 900;
    color: var(--brand);
}

.review-summary__score span {
    color: var(--text-muted);
    font-size: 13px;
}

.review-summary__count {
    display: block;
    margin-top: 2px;
    color: var(--text-muted);
    font-size: 11px;
    font-style: normal;
    font-weight: 600;
}

.review-summary__dist {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.review-dist {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
}

.review-dist__star {
    flex-shrink: 0;
    width: 28px;
    color: var(--text-muted);
}

.review-dist__bar {
    flex: 1;
    height: 6px;
    border-radius: 999px;
    background: var(--border);
    overflow: hidden;
}

.review-dist__fill {
    height: 100%;
    border-radius: 999px;
    background: var(--brand);
}

.review-dist__count {
    flex-shrink: 0;
    width: 20px;
    text-align: right;
    color: var(--text-muted);
}

/* 리뷰 목록 */
.review-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.review-item {
    padding: 14px 16px;
    border: 1px solid var(--border);
    border-radius: 14px;
    background: var(--surface);
}

.review-item__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.review-item__who {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
}

.review-item__avatar {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    flex-shrink: 0;
    border-radius: 50%;
    background: var(--accent);
    color: #fff;
    font-size: 13px;
    font-weight: 700;
}

.review-item__who strong {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.review-item__stars {
    display: inline-flex;
    align-items: center;
    gap: 1px;
    color: #ffa940;
    font-size: 12px;
    flex-shrink: 0;
}

.review-item__stars :deep(.n-icon) {
    color: #ffa940;
}

.review-item__time {
    color: var(--text-muted);
    font-size: 12px;
    flex-shrink: 0;
}

.review-item__content {
    margin: 8px 0 0;
    color: var(--text);
    font-size: 14px;
    line-height: 1.6;
}

/* 운행 연결 행 — 클릭 시 운행 상세로 이동 */
.review-item__order {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 10px;
    padding: 6px 10px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: transparent;
    color: var(--text-muted);
    font-family: inherit;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: border-color 0.15s ease, color 0.15s ease, background 0.15s ease;
}

.review-item__order:hover {
    border-color: var(--brand);
    background: var(--brand-soft);
    color: var(--brand);
}

.review-item__order-arrow {
    transition: transform 0.15s ease;
}

.review-item__order:hover .review-item__order-arrow {
    transform: translateX(2px);
}
</style>
