<script setup>
import { onMounted, ref } from 'vue';
import { useMessage } from 'naive-ui';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { useUiStore } from '../../stores/ui';
import { useCommunityFeed } from '../../composables/useCommunityFeed';
import { apiCommunityPost } from '../../api/community';
import { getApiErrorMessage } from '../../api/client';
import { categoryOf } from '../../utils/communityCategories';
import LevelBadge from '../../components/common/LevelBadge.vue';
import BaseIcon from '../../components/common/BaseIcon.vue';
import EmptyState from '../../components/common/EmptyState.vue';
import CommunityPostEditor from '../../components/community/CommunityPostEditor.vue';
import SurveyBlock from '../../components/community/SurveyBlock.vue';
import PlaceCard from '../../components/community/PlaceCard.vue';

defineOptions({ name: 'CommunityPostView' });

const route = useRoute();
const router = useRouter();
const message = useMessage();
const auth = useAuthStore();
const ui = useUiStore();

const feed = useCommunityFeed({ message, auth, ui });

const {
    toggleLike, vote, commentText, submitComment, deleteComment, removePost,
    timeAgo, avatarText, playingVideo, parseVideo, toggleVideo,
} = feed;

const post = ref(null);
const loading = ref(true);
const error = ref('');

const loadDetail = async () => {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await apiCommunityPost(route.params.id);
        post.value = data.data;
    } catch (e) {
        error.value = getApiErrorMessage(e, '게시글을 불러오지 못했습니다.');
    } finally {
        loading.value = false;
    }
};

// 수정 모달 — 저장되면 상세 글을 갱신된 데이터로 교체
const editOpen = ref(false);
const openEdit = () => {
    editOpen.value = true;
};
const onSaved = (updated) => {
    post.value = updated;
    editOpen.value = false;
    message.success('글이 수정되었습니다.');
};

const goBack = () => {
    if (window.history.length > 1) {
        router.back();
    } else {
        router.push({ name: 'community' });
    }
};

onMounted(loadDetail);
</script>

<template>
    <div class="community-post-page page-shell">
        <div class="post-head">
            <button type="button" class="post-head__back" aria-label="뒤로가기" @click="goBack">
                <BaseIcon name="arrow-back" :size="20" />
            </button>
            <span class="post-head__title">게시글</span>
        </div>

        <n-alert v-if="error" type="error" :show-icon="true" class="post-alert">
            {{ error }}
            <template #action>
                <n-button size="small" :loading="loading" @click="loadDetail">다시 시도</n-button>
            </template>
        </n-alert>

        <!-- 로딩 스켈레톤 -->
        <div v-if="loading" class="feed-skeleton sk-card" aria-hidden="true">
            <div class="feed-skeleton__head">
                <div class="sk-circle" />
                <div class="feed-skeleton__head-lines">
                    <div class="sk-line sk-line--md" style="width: 45%;" />
                    <div class="sk-line sk-line--sm" style="width: 32%;" />
                </div>
            </div>
            <div class="sk-line sk-line--md" />
            <div class="sk-line sk-line--sm" style="width: 85%;" />
            <div class="sk-line sk-line--sm" style="width: 60%;" />
        </div>

        <EmptyState
            v-else-if="!post"
            icon="chat"
            title="게시글을 찾을 수 없습니다"
            hint="삭제되었거나 주소가 잘못되었습니다"
        />

        <article v-else class="feed-card detail-card" :data-post-id="post.id">
            <!-- 헤드: 작성자 + 카테고리 + 시간 + 더보기(수정/삭제) -->
            <div class="feed-card__head">
                <button
                    type="button"
                    class="feed-avatar feed-avatar--link"
                    @click="router.push({ name: 'user-page', params: { id: post.user.id } })"
                >
                    {{ avatarText(post.user.name) }}
                </button>
                <button
                    type="button"
                    class="feed-card__who"
                    @click="router.push({ name: 'user-page', params: { id: post.user.id } })"
                >
                    <span class="feed-card__name-row">
                        <strong>{{ post.user.name }}</strong>
                        <LevelBadge v-if="post.user.level" :level="post.user.level.level" size="sm" />
                        <span v-if="post.user.is_vip" class="feed-badge feed-badge--vip">VIP</span>
                        <span class="feed-card__time">{{ timeAgo(post.created_at) }}</span>
                    </span>
                    <span class="feed-card__cat-row">
                        <span class="feed-card__cat" :class="`feed-card__cat--${post.category}`">
                            {{ categoryOf(post.category).label }}
                        </span>
                    </span>
                </button>
                <n-dropdown
                    v-if="post.is_mine"
                    trigger="click"
                    :options="[
                        { label: '수정', key: 'edit' },
                        { label: '삭제', key: 'delete' },
                    ]"
                    @select="(key) => (key === 'edit' ? openEdit() : removePost(post))"
                >
                    <button type="button" class="feed-card__more" aria-label="더보기">
                        <BaseIcon name="more" :size="16" />
                    </button>
                </n-dropdown>
            </div>

            <p class="feed-card__content" v-text="post.content" />

            <img
                v-if="post.image_url"
                :src="post.image_url"
                alt="게시글 사진"
                class="feed-card__image"
                loading="lazy"
            />

            <!-- 영상/숏츠 -->
            <template v-if="parseVideo(post.video_url)">
                <div v-if="parseVideo(post.video_url).kind === 'youtube'">
                    <button
                        v-if="!playingVideo[post.id]"
                        type="button"
                        class="video-player video-player--thumb"
                        @click="toggleVideo(post)"
                    >
                        <img :src="parseVideo(post.video_url).thumb" alt="영상 썸네일" loading="lazy" />
                        <span class="video-player__play">
                            <BaseIcon name="play" :size="16" />
                        </span>
                        <span class="video-player__badge">숏츠/영상</span>
                    </button>
                    <div v-else class="video-player">
                        <iframe
                            :src="parseVideo(post.video_url).embed"
                            title="YouTube 영상"
                            frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen
                        />
                    </div>
                </div>
                <a
                    v-else
                    :href="parseVideo(post.video_url).url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="video-link"
                >
                    <BaseIcon name="video" :size="14" />
                    영상 보기
                </a>
            </template>

            <!-- 여행지 맛집 카드 / 설문 투표 -->
            <PlaceCard v-if="post.place" :place="post.place" />
            <SurveyBlock
                v-if="post.survey"
                :survey="post.survey"
                @vote="(optionId) => vote(post, optionId)"
            />

            <div class="feed-card__actions">
                <button
                    type="button"
                    class="feed-action"
                    :class="{ 'feed-action--liked': post.is_liked }"
                    @click="toggleLike(post)"
                >
                    <BaseIcon
                        class="feed-action__icon"
                        :name="post.is_liked ? 'heart-filled' : 'heart'"
                        :size="18"
                    />
                    <span>{{ post.likes_count > 0 ? post.likes_count : '좋아요' }}</span>
                </button>

                <span class="feed-action feed-action--static">
                    <BaseIcon class="feed-action__icon" name="comment" :size="16" />
                    <span>{{ post.comments_count > 0 ? post.comments_count : '댓글' }}</span>
                </span>
            </div>

            <!-- 전체 댓글 — 시간 + 내 댓글 삭제 -->
            <div v-if="post.comments.length" class="feed-card__comments">
                <div v-for="comment in post.comments" :key="comment.id" class="comment-row">
                    <strong>{{ comment.user?.name }}</strong>
                    <span>{{ comment.content }}</span>
                    <span class="comment-row__time">{{ timeAgo(comment.created_at) }}</span>
                    <button
                        v-if="comment.is_mine"
                        type="button"
                        class="comment-row__delete"
                        aria-label="댓글 삭제"
                        @click="deleteComment(post, comment)"
                    >
                        <BaseIcon name="trash" :size="13" />
                    </button>
                </div>
            </div>

            <div class="feed-card__composer">
                <input
                    v-model="commentText[post.id]"
                    type="text"
                    placeholder="댓글 달기..."
                    data-post-comment-input
                    @keyup.enter="submitComment(post)"
                />
                <button
                    type="button"
                    :disabled="!(commentText[post.id] ?? '').trim()"
                    @click="submitComment(post)"
                >
                    게시
                </button>
            </div>
        </article>

        <!-- 글 수정 모달 -->
        <CommunityPostEditor v-model:show="editOpen" :post="post" @saved="onSaved" />
    </div>
</template>

<style scoped>
/* 커뮤니티 게시글 상세 — 피드 카드 스타일을 재사용하고 상세 특화 요소를 추가한다 */
.community-post-page {
    /* 상단 시작은 공용 .page-shell 기준으로 통일 — 하단 여백만 페이지가 관리한다 */
    padding-bottom: 60px;
}

.post-alert { margin-bottom: 12px; }

/* ── 상단 헤더(뒤로가기) ── */
.post-head {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
}

.post-head__back {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border: 1px solid var(--border);
    border-radius: 50%;
    background: var(--surface);
    color: var(--text);
    cursor: pointer;
    transition: border-color 0.15s ease, color 0.15s ease;
}

.post-head__back:hover { border-color: var(--brand); color: var(--brand); }

.post-head__title {
    font-size: 11px;
    font-weight: 800;
    color: var(--text);
}

/* ── 카드 (피드와 동일 골격) ── */
.detail-card { margin-top: 2px; }

.feed-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--card-radius);
    padding: var(--card-pad);
}

.feed-card__head {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 2px;
}

.feed-avatar {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: var(--brand-gradient);
    color: #ffffff;
    font-size: 11px;
    font-weight: 700;
    flex-shrink: 0;
    transition: transform 0.15s ease;
}

/* 다크 — 그라디언트가 밝은 틸로 바뀌어 흰 글자 대비가 ≈1.6:1로 떨어짐 → 앱 표준 어두운 글자 */
html.dark .feed-avatar {
    color: #07120e;
}

.feed-avatar--link { border: 0; padding: 0; cursor: pointer; }
.feed-avatar--link:hover { transform: scale(1.05); }

.feed-card__who {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 2px;
    min-width: 0;
    padding: 0;
    border: 0;
    background: none;
    color: var(--text);
    text-align: left;
    cursor: pointer;
}

.feed-card__name-row {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    min-width: 0;
}

.feed-card__name-row strong {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.feed-card__time {
    color: var(--text-muted);
    font-size: 11px;
    white-space: nowrap;
    flex-shrink: 0;
}

.feed-card__time::before {
    content: '·';
    margin: 0 3px;
    color: var(--text-muted);
}

.feed-card__cat-row { display: flex; }

.feed-card__cat {
    display: inline-flex;
    align-items: center;
    padding: 1px 6px;
    border-radius: 6px;
    background: color-mix(in srgb, var(--brand) 12%, transparent);
    color: var(--brand);
    font-size: 10px;
    font-weight: 400;
}

.feed-card__cat--airport { background: color-mix(in srgb, #4a9eff 14%, transparent); color: #4a9eff; }
.feed-card__cat--route { background: color-mix(in srgb, var(--status-accepted) 14%, transparent); color: var(--status-accepted); }
.feed-card__cat--region { background: color-mix(in srgb, #a78bfa 14%, transparent); color: #a78bfa; }
.feed-card__cat--car { background: color-mix(in srgb, #f4be5f 16%, transparent); color: #e8a83c; }
.feed-card__cat--money { background: color-mix(in srgb, #f2994a 16%, transparent); color: #e2873a; }
.feed-card__cat--shop { background: color-mix(in srgb, #63e2b7 14%, transparent); color: var(--status-accepted); }
.feed-card__cat--survey { background: color-mix(in srgb, #8b7bf7 14%, transparent); color: #8b7bf7; }
.feed-card__cat--food { background: color-mix(in srgb, #ff8a5c 16%, transparent); color: #e8734a; }

.feed-badge { display: inline-flex; align-items: center; font-size: 10px; line-height: 1; }

.feed-badge--vip {
    padding: 1px 6px;
    border-radius: 999px;
    background: linear-gradient(135deg, #f7b731, #f2994a);
    color: #ffffff;
    font-size: 10px;
    font-weight: 400;
}

.feed-card__more {
    margin-left: auto;
    border: 0;
    border-radius: 50%;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: none;
    color: var(--text-muted);
    font-size: 14px;
    cursor: pointer;
    padding: 0;
    transition: background 0.12s ease;
}

.feed-card__more:hover { background: rgba(0, 0, 0, 0.05); }
html.dark .feed-card__more:hover { background: rgba(255, 255, 255, 0.06); }

.feed-card__content {
    margin: 10px 0;
    white-space: pre-wrap;
    word-break: break-word;
    line-height: 1.65;
    font-size: 11px;
}

.feed-card__image {
    width: 100%;
    aspect-ratio: 4 / 3;
    object-fit: cover;
    border-radius: 10px;
    background: rgba(0, 0, 0, 0.05);
    margin-bottom: 2px;
}

html.dark .feed-card__image { background: rgba(255, 255, 255, 0.08); }

.video-player {
    position: relative;
    width: 100%;
    border-radius: 10px;
    overflow: hidden;
    background: #000;
    margin-bottom: 2px;
}

.video-player--thumb { border: 0; padding: 0; cursor: pointer; display: block; }

.video-player--thumb img {
    width: 100%;
    aspect-ratio: 16 / 9;
    object-fit: cover;
    display: block;
}

.video-player iframe {
    width: 100%;
    aspect-ratio: 16 / 9;
    display: block;
    border: 0;
}

.video-player__play {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.28);
}

.video-player__play svg {
    width: 52px;
    height: 52px;
    color: #fff;
    filter: drop-shadow(0 2px 8px rgba(0, 0, 0, 0.5));
}

.video-player__badge {
    position: absolute;
    top: 10px;
    left: 10px;
    padding: 3px 10px;
    border-radius: 999px;
    background: rgba(0, 0, 0, 0.6);
    color: #fff;
    font-size: 11px;
    font-weight: 600;
}

.video-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 4px;
    padding: 8px 14px;
    border-radius: 10px;
    border: 1px solid color-mix(in srgb, var(--brand) 35%, transparent);
    background: color-mix(in srgb, var(--brand) 6%, transparent);
    color: var(--accent);
    font-size: 11px;
    font-weight: 600;
    text-decoration: none;
}

.video-link svg { width: 16px; height: 16px; }

.feed-card__actions {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-top: 10px;
    padding-top: 8px;
    border-top: 1px solid var(--border);
}

.feed-action {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    border: 0;
    border-radius: 8px;
    background: none;
    color: var(--text-muted);
    font-size: 11px;
    cursor: pointer;
    padding: 2px 6px;
    transition: background 0.12s ease;
}

.feed-action:hover { background: rgba(0, 0, 0, 0.04); }
html.dark .feed-action:hover { background: rgba(255, 255, 255, 0.06); }
.feed-action--liked { color: #e5484d; }
.feed-action--static { cursor: default; }
.feed-action--static:hover { background: none; }

.feed-action__icon { width: 18px; height: 18px; }

/* ── 댓글 (시간 + 내 댓글 삭제) ── */
.feed-card__comments {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-top: 8px;
    padding: 10px;
    border-radius: 10px;
    background: rgba(0, 0, 0, 0.02);
}

html.dark .feed-card__comments { background: rgba(255, 255, 255, 0.03); }

.comment-row {
    display: flex;
    gap: 8px;
    font-size: 11px;
    align-items: baseline;
}

.comment-row strong { flex-shrink: 0; color: var(--text); font-size: 11px; }
.comment-row span { word-break: break-word; color: var(--text); }

/* 댓글 시간 — 위 `.comment-row span`(본문 글자색)보다 우선하도록 범위를 좁힌다 (선택자 우선순위로 해결) */
.comment-row span.comment-row__time {
    color: var(--text-muted);
    font-size: 11px;
    flex-shrink: 0;
}

.comment-row__delete {
    margin-left: auto;
    border: 0;
    background: none;
    color: var(--text-muted);
    cursor: pointer;
    padding: 2px;
    display: flex;
    align-items: center;
    opacity: 0.6;
    transition: opacity 0.12s ease, color 0.12s ease;
}

.comment-row__delete:hover { opacity: 1; color: var(--danger); }

.comment-row__delete svg { width: 13px; height: 13px; }

.feed-card__composer {
    display: flex;
    gap: 8px;
    margin-top: 8px;
    align-items: center;
}

.feed-card__composer input {
    flex: 1;
    border: 0;
    border-radius: 20px;
    padding: 9px 16px;
    background: rgba(0, 0, 0, 0.04);
    color: var(--text);
    font-size: 11px;
    outline: none;
    transition: background 0.15s ease;
}

.feed-card__composer input:focus { background: rgba(0, 0, 0, 0.07); }

html.dark .feed-card__composer input { background: rgba(255, 255, 255, 0.05); }
html.dark .feed-card__composer input:focus { background: rgba(255, 255, 255, 0.08); }

.feed-card__composer button {
    border: 0;
    background: none;
    color: var(--brand);
    font-weight: 700;
    font-size: 11px;
    cursor: pointer;
    flex-shrink: 0;
    padding: 6px 4px;
    transition: opacity 0.12s ease;
}

.feed-card__composer button:disabled { color: var(--text-muted); cursor: default; opacity: 0.5; }

/* ── 스켈레톤 ── */
.feed-skeleton {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.feed-skeleton__head {
    display: flex;
    align-items: center;
    gap: 10px;
}

.feed-skeleton__head-lines {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.sk-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 14px;
}
</style>
