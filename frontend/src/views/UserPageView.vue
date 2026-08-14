<script setup>
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useMessage } from 'naive-ui';
import { apiCommunityUser } from '../api/community';
import { getApiErrorMessage } from '../api/client';
import LevelBadge from '../components/LevelBadge.vue';

const route = useRoute();
const router = useRouter();
const message = useMessage();

const loading = ref(true);
const error = ref('');
const data = ref(null);
const activeTab = ref('posts'); // 'posts' | 'orders' — 글/운행을 탭으로 분리

const load = async () => {
    loading.value = true;
    error.value = '';

    try {
        const { data: response } = await apiCommunityUser(route.params.id);
        data.value = response.data;
    } catch (e) {
        error.value = getApiErrorMessage(e, '유저 정보를 불러오지 못했습니다.');
    } finally {
        loading.value = false;
    }
};

const avatarText = (name) => (name ?? '?').charAt(0).toUpperCase();

const formatWon = (value) => (value ?? 0).toLocaleString();

// 유튜브(영상/숏츠) URL → 썸네일 이미지. 아니면 null
const youtubeThumb = (url) => {
    if (!url) {
        return null;
    }

    const match = String(url).match(/(?:youtube\.com\/(?:watch\?v=|shorts\/)|youtu\.be\/)([\w-]{6,})/);

    return match ? `https://i.ytimg.com/vi/${match[1]}/hqdefault.jpg` : null;
};

const timeAgo = (iso) => {
    if (!iso) {
        return '';
    }

    const diff = (Date.now() - new Date(iso).getTime()) / 1000;

    if (diff < 60) {
        return '방금 전';
    }
    if (diff < 3600) {
        return `${Math.floor(diff / 60)}분 전`;
    }
    if (diff < 86400) {
        return `${Math.floor(diff / 3600)}시간 전`;
    }

    return new Date(iso).toLocaleDateString('ko-KR');
};

onMounted(load);
</script>

<template>
    <div>
        <n-alert v-if="error" type="error" :show-icon="true" class="user-block">
            {{ error }}
            <template #action>
                <n-button size="small" :loading="loading" @click="load">다시 시도</n-button>
            </template>
        </n-alert>

        <n-spin :show="loading" class="user-body">
            <template v-if="data">
                <!-- 프로필 헤더 -->
                <div class="user-hero">
                    <div class="user-hero__avatar">{{ avatarText(data.user.name) }}</div>
                    <div class="user-hero__info">
                        <div class="user-hero__name-row">
                            <strong>{{ data.user.name }}</strong>
                            <LevelBadge v-if="data.user.level" :level="data.user.level.level" size="md" />
                            <span class="user-hero__level-title" v-if="data.user.level">
                                {{ data.user.level.title }}
                            </span>
                            <span v-if="data.user.is_vip" class="badge badge--vip">VIP</span>
                        </div>
                        <span class="user-hero__meta">{{ data.user.roleLabel }}</span>
                        <span class="user-hero__meta">
                            {{ data.user.joined_at }} 가입 · 글 {{ data.user.posts_count }}개
                        </span>

                        <!-- 인증 배지 -->
                        <div class="badge-row">
                            <span v-if="data.user.is_vehicle_verified" class="badge badge--vehicle" title="차량 인증 완료">
                                차량 인증
                            </span>
                            <span v-if="data.user.is_license_verified" class="badge badge--license" title="면허증 인증 완료">
                                면허 인증
                            </span>
                            <span v-if="data.user.vehicle_info" class="badge badge--car" title="등록 차량">
                                {{ data.user.vehicle_info }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- 평점 요약 -->
                <div v-if="data.reviewSummary?.count > 0" class="user-rating">
                    <span class="user-rating__score">{{ data.reviewSummary.avg }}</span>
                    <n-rate
                        :value="data.reviewSummary.avg"
                        readonly
                        allow-half
                        size="small"
                        color="#ffa940"
                    />
                    <span class="user-rating__count">리뷰 {{ data.reviewSummary.count }}개</span>
                </div>

                <!-- 수행 실적 -->
                <div class="user-stats">
                    <div class="user-stats__card">
                        <span class="user-stats__label">완료 운행</span>
                        <strong class="user-stats__value">{{ data.stats.completed_orders }}<small>건</small></strong>
                    </div>
                    <div class="user-stats__card">
                        <span class="user-stats__label">누적 매출</span>
                        <strong class="user-stats__value">{{ formatWon(data.stats.total_revenue) }}<small>원</small></strong>
                    </div>
                </div>

                <!-- 탭 — 올린 글 / 등록한 운행 / 받은 리뷰 -->
                <div class="user-tabs">
                    <button
                        type="button"
                        class="user-tab"
                        :class="{ 'user-tab--active': activeTab === 'posts' }"
                        @click="activeTab = 'posts'"
                    >
                        올린 글 ({{ data.posts.length }})
                    </button>
                    <button
                        type="button"
                        class="user-tab"
                        :class="{ 'user-tab--active': activeTab === 'orders' }"
                        @click="activeTab = 'orders'"
                    >
                        등록한 운행 ({{ data.orders.length }})
                    </button>
                    <button
                        type="button"
                        class="user-tab"
                        :class="{ 'user-tab--active': activeTab === 'reviews' }"
                        @click="activeTab = 'reviews'"
                    >
                        리뷰 ({{ data.reviewSummary?.count ?? 0 }})
                    </button>
                </div>

                <!-- 받은 리뷰 -->
                <section v-if="activeTab === 'reviews'" class="user-section">
                    <n-empty
                        v-if="!data.reviews.length"
                        description="아직 받은 리뷰가 없습니다."
                        :image-size="60"
                    />
                    <div v-else class="review-row-list">
                        <article v-for="review in data.reviews" :key="review.id" class="review-row">
                            <div class="review-row__head">
                                <span class="review-row__author">{{ review.reviewer?.name }}</span>
                                <n-rate :value="review.rating" readonly size="small" color="#ffa940" />
                            </div>
                            <p class="review-row__content" v-text="review.content" />
                            <span class="review-row__time">{{ review.created_at }}</span>
                        </article>
                    </div>
                </section>

                <!-- 등록한 운행 -->
                <section v-if="activeTab === 'orders'" class="user-section">
                    <n-empty
                        v-if="data.orders.length === 0"
                        description="등록한 운행이 없습니다."
                        :image-size="60"
                    />
                    <div v-else class="order-row-list">
                        <button
                            v-for="order in data.orders"
                            :key="order.id"
                            type="button"
                            class="order-row"
                            @click="router.push({ name: 'order-detail', params: { id: order.id } })"
                        >
                            <div class="order-row__main">
                                <strong>{{ order.route }}</strong>
                                <span>{{ order.service_date }} {{ order.service_time }}</span>
                            </div>
                            <span class="order-row__amount">{{ formatWon(order.amount) }}원</span>
                            <span class="order-row__status">{{ order.statusLabel }}</span>
                        </button>
                    </div>
                </section>

                <!-- 올린 글 -->
                <section v-if="activeTab === 'posts'" class="user-section">
                    <n-empty
                        v-if="data.posts.length === 0"
                        description="올린 글이 없습니다."
                        :image-size="60"
                    />
                    <div v-else class="post-row-list">
                        <article
                            v-for="post in data.posts"
                            :key="post.id"
                            class="post-row"
                        >
                            <p class="post-row__content" v-text="post.content" />
                            <img
                                v-if="post.image_url"
                                :src="post.image_url"
                                alt="게시글 사진"
                                class="post-row__image"
                                loading="lazy"
                            />
                            <a
                                v-else-if="youtubeThumb(post.video_url)"
                                :href="post.video_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="post-row__video"
                            >
                                <img :src="youtubeThumb(post.video_url)" alt="영상 썸네일" loading="lazy" />
                                <span class="post-row__video-badge">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z" /></svg>
                                </span>
                            </a>
                            <a
                                v-else-if="post.video_url"
                                :href="post.video_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="post-row__video-link"
                            >
                                ▶ 영상 보기
                            </a>
                            <div class="post-row__meta">
                                <span>{{ timeAgo(post.created_at) }}</span>
                                <span>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px;vertical-align:-2px;margin-right:2px"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21.2l7.8-7.8 1-1a5.5 5.5 0 0 0 0-7.8z" /></svg>
                                    {{ post.likes_count }}
                                </span>
                                <span>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px;vertical-align:-2px;margin-right:2px"><path d="M21 11.5a8.4 8.4 0 0 1-8.5 8.3 8.6 8.6 0 0 1-3.9-.9L3 20l1.2-5.3a8.2 8.2 0 0 1-.7-3.2A8.4 8.4 0 0 1 12 3.2a8.4 8.4 0 0 1 9 8.3z" /></svg>
                                    {{ post.comments_count }}
                                </span>
                            </div>
                        </article>
                    </div>
                </section>
            </template>
        </n-spin>
    </div>
</template>

<style scoped>
.user-block {
    margin-bottom: 16px;
}

.user-body {
    display: block;
    min-height: 200px;
}

.user-hero {
    display: flex;
    align-items: center;
    gap: 16px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 20px 18px;
}

.user-hero__avatar {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 72px;
    height: 72px;
    border-radius: 50%;
    background: var(--brand-gradient);
    color: #ffffff;
    font-size: 28px;
    font-weight: 700;
    flex-shrink: 0;
}

.user-hero__info {
    min-width: 0;
}

.user-hero__name-row {
    display: flex;
    align-items: center;
    gap: 8px;
}

.user-hero__name-row strong {
    font-size: 19px;
}

.user-hero__meta {
    display: block;
    color: var(--text-muted);
    font-size: 13px;
    margin-top: 2px;
}

/* 평점 요약 */
.user-rating {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 14px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 12px 16px;
}

.user-rating__score {
    font-size: 24px;
    font-weight: 800;
    color: #ffa940;
}

.user-rating__count {
    color: var(--text-muted);
    font-size: 13px;
    margin-left: auto;
}

/* 수행 실적 카드 */
.user-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-top: 12px;
}

.user-stats__card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 14px 16px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.user-stats__label {
    color: var(--text-muted);
    font-size: 12px;
}

.user-stats__value {
    font-size: 20px;
    font-weight: 800;
    color: var(--text);
}

.user-stats__value small {
    font-size: 12px;
    font-weight: 600;
    color: var(--text-muted);
    margin-left: 2px;
}

/* 리뷰 목록 */
.review-row-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.review-row {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 14px 16px;
}

.review-row__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.review-row__author {
    font-size: 14px;
    font-weight: 700;
}

.review-row__content {
    margin-top: 8px;
    font-size: 14px;
    line-height: 1.55;
    color: var(--text);
    word-break: break-word;
}

.review-row__time {
    display: block;
    margin-top: 8px;
    color: var(--text-muted);
    font-size: 12px;
}

.badge-row {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 10px;
}

.badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}

.user-hero__level-title {
    color: var(--text-muted);
    font-size: 13px;
    font-weight: 600;
}

.badge--vip {
    background: linear-gradient(135deg, #f7b731, #f2994a);
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(242, 153, 74, 0.4);
}

.badge--vehicle {
    background: #e6f7ff;
    color: #0b7ac8;
    border: 1px solid #b3e3ff;
}

.badge--license {
    background: color-mix(in srgb, var(--status-accepted) 10%, transparent);
    color: var(--status-accepted);
    border: 1px solid color-mix(in srgb, var(--status-accepted) 25%, transparent);
}

.badge--car {
    background: #f6ffed;
    color: #389e0d;
    border: 1px solid #c8e6b0;
}

html.dark .badge--vehicle {
    background: rgba(11, 122, 200, 0.18);
    color: #66c6ff;
    border-color: rgba(11, 122, 200, 0.4);
}

html.dark .badge--license {
    background: rgba(47, 84, 235, 0.2);
    color: #a8b6ff;
    border-color: rgba(47, 84, 235, 0.45);
}

html.dark .badge--car {
    background: rgba(56, 158, 13, 0.16);
    color: #95de64;
    border-color: rgba(56, 158, 13, 0.4);
}

.user-section {
    margin-top: 18px;
}

.user-section__title {
    font-size: 15px;
    margin-bottom: 10px;
}

/* 탭 — 올린 글 / 등록한 운행 */
.user-tabs {
    display: flex;
    gap: 8px;
    margin-top: 18px;
    padding: 4px;
    border-radius: 12px;
    background: rgba(0, 0, 0, 0.04);
}

html.dark .user-tabs {
    background: rgba(255, 255, 255, 0.06);
}

.user-tab {
    flex: 1;
    padding: 9px 0;
    border: 0;
    border-radius: 9px;
    background: none;
    color: var(--text-muted);
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.12s ease, color 0.12s ease;
}

.user-tab--active {
    background: var(--surface);
    color: var(--accent);
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
}

.order-row-list,
.post-row-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.order-row {
    display: flex;
    align-items: center;
    gap: 12px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 16px;
    cursor: pointer;
    text-align: left;
    color: var(--text);
    transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.1s ease;
}

.order-row:hover {
    border-color: var(--brand);
    box-shadow: 0 4px 14px color-mix(in srgb, var(--brand) 12%, transparent);
    transform: translateY(-1px);
}

.order-row__main {
    display: flex;
    flex-direction: column;
    min-width: 0;
    flex: 1;
}

.order-row__main strong {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 14px;
}

.order-row__main span {
    color: var(--text-muted);
    font-size: 13px;
    margin-top: 3px;
}

.order-row__amount {
    font-weight: 700;
    font-size: 14px;
    white-space: nowrap;
}

.order-row__status {
    padding: 4px 12px;
    border-radius: 999px;
    background: var(--brand-soft);
    color: var(--brand);
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}

.post-row {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 16px;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.post-row:hover {
    border-color: color-mix(in srgb, var(--brand) 30%, transparent);
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06);
}

.post-row__content {
    white-space: pre-wrap;
    word-break: break-word;
    line-height: 1.7;
    font-size: 14px;
}

.post-row__image {
    width: 100%;
    max-height: 300px;
    object-fit: cover;
    border-radius: 12px;
    margin-top: 10px;
    background: rgba(0, 0, 0, 0.05);
}

html.dark .post-row__image {
    background: rgba(255, 255, 255, 0.08);
}

/* 올린 글의 영상 미리보기 */
.post-row__video {
    position: relative;
    display: block;
    width: 100%;
    margin-top: 10px;
    border-radius: 12px;
    overflow: hidden;
    background: #000;
}

.post-row__video img {
    width: 100%;
    max-height: 300px;
    object-fit: cover;
    display: block;
}

.post-row__video-badge {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.25);
}

.post-row__video-badge svg {
    width: 44px;
    height: 44px;
    color: #fff;
    filter: drop-shadow(0 2px 8px rgba(0, 0, 0, 0.5));
}

.post-row__video-link {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-top: 10px;
    padding: 6px 12px;
    border-radius: 8px;
    background: color-mix(in srgb, var(--brand) 8%, transparent);
    border: 1px solid color-mix(in srgb, var(--brand) 30%, transparent);
    color: var(--brand);
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
}

.post-row__meta {
    display: flex;
    gap: 14px;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid var(--border);
    color: var(--text-muted);
    font-size: 12px;
}
</style>
