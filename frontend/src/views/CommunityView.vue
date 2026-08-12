<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useMessage } from 'naive-ui';
import { useRouter } from 'vue-router';
import {
    apiCommentCommunity,
    apiCommunityPost,
    apiCommunityPosts,
    apiCreateCommunityPost,
    apiDeleteCommunityPost,
    apiToggleCommunityLike,
} from '../api/community';
import { getApiErrorMessage } from '../api/client';
import { useAuthStore } from '../stores/auth';
import { useUiStore } from '../stores/ui';
import LevelBadge from '../components/LevelBadge.vue';

const message = useMessage();
const router = useRouter();
const auth = useAuthStore();
const ui = useUiStore();

const posts = ref([]);
const pagination = ref(null);
const page = ref(1);
const loading = ref(true);
const error = ref('');

// 작성 모달
const showComposer = ref(false);
const composing = ref(false);
const draftContent = ref('');
const draftImage = ref(null);
const draftPreviewUrl = ref('');
const draftVideoUrl = ref('');

const load = async (reset = false) => {
    if (reset) {
        page.value = 1;
    }

    loading.value = true;
    error.value = '';

    try {
        const { data } = await apiCommunityPosts(page.value);
        posts.value = reset ? data.data : [...posts.value, ...data.data];
        pagination.value = data.meta.pagination;
    } catch (e) {
        error.value = getApiErrorMessage(e, '커뮤니티 글을 불러오지 못했습니다.');
    } finally {
        loading.value = false;
    }
};

const loadMore = () => {
    if (pagination.value && page.value < pagination.value.last_page) {
        page.value += 1;
        load();
    }
};

const openComposer = () => {
    draftContent.value = '';
    draftImage.value = null;
    draftPreviewUrl.value = '';
    draftVideoUrl.value = '';
    showComposer.value = true;
};

const pickImage = async (event) => {
    const file = event.target.files?.[0];

    if (!file) {
        return;
    }

    // 업로드 전 최대 1080px로 리사이즈 (대용량 원본 그대로 업로드 방지 → 업로드/로딩 최적화)
    const resized = await resizeImage(file, 1080);

    draftImage.value = resized ?? file;
    draftPreviewUrl.value = URL.createObjectURL(draftImage.value);
};

/**
 * 이미지를 canvas로 리사이즈해 JPEG Blob으로 변환한다.
 * 실패(비이미지 등)하면 null — 원본 그대로 사용.
 */
const resizeImage = (file, maxSize) =>
    new Promise((resolve) => {
        const url = URL.createObjectURL(file);
        const img = new Image();

        img.onload = () => {
            const scale = Math.min(1, maxSize / Math.max(img.width, img.height));
            const width = Math.max(1, Math.round(img.width * scale));
            const height = Math.max(1, Math.round(img.height * scale));
            const canvas = document.createElement('canvas');

            canvas.width = width;
            canvas.height = height;
            canvas.getContext('2d').drawImage(img, 0, 0, width, height);
            URL.revokeObjectURL(url);

            canvas.toBlob(
                (blob) => {
                    if (!blob) {
                        resolve(null);

                        return;
                    }

                    resolve(new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' }));
                },
                'image/jpeg',
                0.82,
            );
        };

        img.onerror = () => {
            URL.revokeObjectURL(url);
            resolve(null);
        };

        img.src = url;
    });

const submitPost = async () => {
    const content = draftContent.value.trim();

    if (content === '' && !draftImage.value) {
        message.warning('글 내용을 입력해주세요.');

        return;
    }

    composing.value = true;

    try {
        const { data } = await apiCreateCommunityPost({
            content,
            image: draftImage.value,
            video_url: draftVideoUrl.value.trim(),
        });
        posts.value.unshift(data.data);
        showComposer.value = false;
        message.success('글이 게시되었습니다.');
    } catch (e) {
        message.error(getApiErrorMessage(e, '글 작성에 실패했습니다.'));
    } finally {
        composing.value = false;
    }
};

const toggleLike = async (post) => {
    try {
        const { data } = await apiToggleCommunityLike(post.id);
        post.is_liked = data.data.liked;
        post.likes_count = data.data.likes_count;
    } catch (e) {
        message.error(getApiErrorMessage(e, '좋아요 처리에 실패했습니다.'));
    }
};

const commentText = ref({});

const submitComment = async (post) => {
    const content = (commentText.value[post.id] ?? '').trim();

    if (content === '') {
        return;
    }

    try {
        const { data } = await apiCommentCommunity(post.id, content);
        post.comments.push(data.data);
        post.comments_count = data.data.comments_count;
        commentText.value[post.id] = '';
    } catch (e) {
        message.error(getApiErrorMessage(e, '댓글 작성에 실패했습니다.'));
    }
};

// 댓글 '모두 보기' — 피드에는 최근 3개만 내려오므로 나머지를 지연 로드한다
const expandComments = async (post) => {
    try {
        const { data } = await apiCommunityPost(post.id);
        post.comments = data.data.comments;
        post.comments_count = data.data.comments_count;
    } catch (e) {
        message.error(getApiErrorMessage(e, '댓글을 불러오지 못했습니다.'));
    }
};

const removePost = async (post) => {
    try {
        await apiDeleteCommunityPost(post.id);
        posts.value = posts.value.filter((p) => p.id !== post.id);
        message.success('글이 삭제되었습니다.');
    } catch (e) {
        message.error(getApiErrorMessage(e, '글 삭제에 실패했습니다.'));
    }
};

// 상대 시간 (NaN 방어 포함)
const timeAgo = (iso) => {
    if (!iso) return '';
    const d = new Date(iso);
    if (isNaN(d.getTime())) return String(iso).slice(0, 10);

    const diff = (Date.now() - d.getTime()) / 1000;

    if (diff < 60) return '방금 전';
    if (diff < 3600) return `${Math.floor(diff / 60)}분 전`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}시간 전`;
    if (diff < 86400 * 7) return `${Math.floor(diff / 86400)}일 전`;

    return d.toLocaleDateString('ko-KR');
};

const avatarText = (name) => (name ?? '?').charAt(0).toUpperCase();

const myId = computed(() => auth.user?.id);

// 재생 중인 영상 (post.id → true) — 클릭 시 iframe 임베드로 전환
const playingVideo = ref({});

// 영상 URL 해석 — 유튜브(영상/숏츠)는 썸네일+임베드, 그 외는 링크
const parseVideo = (url) => {
    if (!url) {
        return null;
    }

    const match = String(url).match(/(?:youtube\.com\/(?:watch\?v=|shorts\/)|youtu\.be\/)([\w-]{6,})/);

    if (match) {
        return {
            kind: 'youtube',
            id: match[1],
            thumb: `https://i.ytimg.com/vi/${match[1]}/hqdefault.jpg`,
            embed: `https://www.youtube.com/embed/${match[1]}`,
        };
    }

    return { kind: 'link', url };
};

const toggleVideo = (post) => {
    const info = parseVideo(post.video_url);
    if (info?.kind === 'youtube') {
        playingVideo.value = { ...playingVideo.value, [post.id]: !playingVideo.value[post.id] };
    }
};

// 헤더 액션 버스 수신 — 글쓰기/필터/정렬/새로고침
watch(
    () => ui.actionSeq,
    () => {
        if (ui.actionName === 'community:write') {
            openComposer();
        } else if (ui.actionName === 'community:reload') {
            load(true);
        }
    },
);

// 표시할 글 — 헤더 메뉴의 '내 글만 보기' + 정렬(최신/인기) 반영
const visiblePosts = computed(() => {
    let list = ui.communityMyPostsOnly ? posts.value.filter((p) => p.user.id === myId.value) : posts.value;

    if (ui.communitySort === 'popular') {
        list = [...list].sort((a, b) => b.likes_count - a.likes_count || new Date(b.created_at) - new Date(a.created_at));
    }

    return list;
});

onMounted(() => load(true));
</script>

<template>
    <div>
        <n-alert v-if="error" type="error" :show-icon="true" class="community-alert">
            {{ error }}
            <template #action>
                <n-button size="small" :loading="loading" @click="load">다시 시도</n-button>
            </template>
        </n-alert>

        <!-- 빠른 정렬 — 드로어를 열지 않아도 바로 바꾼다 (클라이언트 정렬) -->
        <div class="community-sort">
            <button
                type="button"
                class="community-sort__btn"
                :class="{ 'community-sort__btn--active': ui.communitySort === 'latest' }"
                @click="ui.communitySort = 'latest'"
            >
                최신순
            </button>
            <button
                type="button"
                class="community-sort__btn"
                :class="{ 'community-sort__btn--active': ui.communitySort === 'popular' }"
                @click="ui.communitySort = 'popular'"
            >
                인기순
            </button>
        </div>

        <n-spin :show="loading" class="community-body">
            <n-empty
                v-if="!loading && visiblePosts.length === 0"
                description="게시글이 없습니다. 헤더의 '글쓰기'로 첫 글을 올려보세요!"
                :image-size="80"
            />

            <div v-else class="community-feed">
                <article
                    v-for="post in visiblePosts"
                    :key="post.id"
                    class="feed-card"
                >
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
                                <span v-if="post.user.is_vehicle_verified" class="feed-badge" title="차량 인증">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:13px;height:13px"><path d="M5 11l1.5-4.5A2 2 0 0 1 8.4 5h7.2a2 2 0 0 1 1.9 1.5L19 11" /><path d="M4 11h16a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1z" /><circle cx="7" cy="16" r="1.6" /><circle cx="17" cy="16" r="1.6" /></svg>
                                </span>
                                <span v-if="post.user.is_license_verified" class="feed-badge" title="면허 인증">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:13px;height:13px"><rect x="2.5" y="4.5" width="19" height="15" rx="2.5" /><path d="M2.5 9h19" /><circle cx="6.5" cy="13.5" r="1.6" /><path d="M15.5 13h4M15.5 16h4" /></svg>
                                </span>
                                <span class="feed-card__time">{{ timeAgo(post.created_at) }}</span>
                            </span>
                        </button>
                        <n-dropdown
                            v-if="post.is_mine"
                            trigger="click"
                            :options="[{ label: '삭제', key: 'delete' }]"
                            @select="removePost(post)"
                        >
                            <button type="button" class="feed-card__more" aria-label="더보기">
                                <svg viewBox="0 0 24 24" fill="currentColor" style="width:16px;height:16px"><circle cx="5" cy="12" r="1.7" /><circle cx="12" cy="12" r="1.7" /><circle cx="19" cy="12" r="1.7" /></svg>
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

                    <!-- 영상/숏츠 — 유튜브는 썸네일 클릭 시 재생 -->
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
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z" /></svg>
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
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                            </svg>
                            영상 보기
                        </a>
                    </template>

                    <div class="feed-card__actions">
                        <button
                            type="button"
                            class="feed-action"
                            :class="{ 'feed-action--liked': post.is_liked }"
                            @click="toggleLike(post)"
                        >
                            <svg
                                class="feed-action__icon"
                                viewBox="0 0 24 24"
                                :fill="post.is_liked ? 'currentColor' : 'none'"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21.2l7.8-7.8 1-1a5.5 5.5 0 0 0 0-7.8z" />
                            </svg>
                            <span>{{ post.likes_count > 0 ? post.likes_count : '좋아요' }}</span>
                        </button>

                        <span class="feed-action feed-action--static">
                            <svg class="feed-action__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 11.5a8.4 8.4 0 0 1-8.5 8.3 8.6 8.6 0 0 1-3.9-.9L3 20l1.2-5.3a8.2 8.2 0 0 1-.7-3.2A8.4 8.4 0 0 1 12 3.2a8.4 8.4 0 0 1 9 8.3z" />
                            </svg>
                            <span>{{ post.comments_count > 0 ? post.comments_count : '댓글' }}</span>
                        </span>
                    </div>

                    <div v-if="post.comments.length" class="feed-card__comments">
                        <div v-for="comment in post.comments" :key="comment.id" class="comment-row">
                            <strong>{{ comment.user?.name }}</strong>
                            <span>{{ comment.content }}</span>
                        </div>
                        <button
                            v-if="post.comments_count > post.comments.length"
                            type="button"
                            class="comments-more"
                            @click="expandComments(post)"
                        >
                            댓글 모두 보기 ({{ post.comments_count - post.comments.length }}개 더)
                        </button>
                    </div>

                    <div class="feed-card__composer">
                        <input
                            v-model="commentText[post.id]"
                            type="text"
                            placeholder="댓글 달기..."
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

                <div v-if="pagination && page < pagination.last_page" class="feed-more">
                    <n-button size="large" secondary :loading="loading" @click="loadMore">
                        더 보기
                    </n-button>
                </div>
            </div>
        </n-spin>

        <!-- 글쓰기 모달 -->
        <n-modal
            v-model:show="showComposer"
            preset="card"
            title="새 글"
            :style="{ maxWidth: '520px' }"
        >
            <div class="composer">
                <n-input
                    v-model:value="draftContent"
                    type="textarea"
                    placeholder="무슨 일이 있었나요? 공유해보세요."
                    :rows="4"
                    maxlength="2000"
                    show-count
                />

                <img v-if="draftPreviewUrl" :src="draftPreviewUrl" alt="첨부 미리보기" class="composer__preview" />

                <n-input
                    v-model:value="draftVideoUrl"
                    type="text"
                    placeholder="영상/숏츠 URL (예: https://youtube.com/shorts/...) 선택"
                    clearable
                    class="composer__video"
                />

                <div class="composer__footer">
                    <label class="composer__upload">
                        <input type="file" accept="image/*" hidden @change="pickImage" />
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="5" width="18" height="14" rx="2" />
                            <circle cx="8.5" cy="10" r="1.5" />
                            <path d="M21 15l-5-5L5 21" />
                        </svg>
                        사진 첨부
                    </label>
                    <n-button
                        type="primary"
                        :loading="composing"
                        :disabled="!draftContent.trim() && !draftImage"
                        @click="submitPost"
                    >
                        게시하기
                    </n-button>
                </div>
            </div>
        </n-modal>
    </div>
</template>

<style scoped>
.community-alert { margin-bottom: 16px; }

/* 빠른 정렬 칩 — 피드와 동일 폭 정렬 */
.community-sort {
    display: flex;
    gap: 6px;
    max-width: 600px;
    margin: 0 auto 12px;
}

.community-sort__btn {
    padding: 6px 12px;
    border: 1px solid var(--border);
    border-radius: 999px;
    background: var(--surface);
    color: var(--text-muted);
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: border-color 0.15s ease, color 0.15s ease, background 0.15s ease;
}

.community-sort__btn:hover {
    border-color: #36adff;
}

.community-sort__btn--active {
    border-color: #36adff;
    background: rgba(54, 173, 255, 0.08);
    color: #36adff;
}

.community-body { display: block; min-height: 200px; }

.community-feed {
    display: flex;
    flex-direction: column;
    gap: 12px;
    max-width: 600px;
    margin: 0 auto;
}

/* ── 카드 ── */
.feed-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 14px;
}

/* ── 헤드(아바타+이름) ── */
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
    background: linear-gradient(135deg, #36adff, #2f54eb);
    color: #ffffff;
    font-size: 15px;
    font-weight: 700;
    flex-shrink: 0;
    transition: transform 0.15s ease;
}

.feed-avatar--link { border: 0; padding: 0; cursor: pointer; }
.feed-avatar--link:hover { transform: scale(1.05); }

.feed-card__who {
    display: flex;
    flex-direction: column;
    justify-content: center;
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
    font-size: 14px;
    min-width: 0;
}

.feed-card__name-row strong {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.feed-card__time {
    color: var(--text-muted);
    font-size: 12px;
    white-space: nowrap;
    flex-shrink: 0;
}

.feed-card__time::before {
    content: '·';
    margin: 0 3px;
    color: var(--text-muted);
}

.feed-badge {
    display: inline-flex;
    align-items: center;
    font-size: 11px;
    line-height: 1;
}

.feed-badge--vip {
    padding: 2px 6px;
    border-radius: 999px;
    background: linear-gradient(135deg, #f7b731, #f2994a);
    color: #ffffff;
    font-size: 10px;
    font-weight: 700;
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
    font-size: 18px;
    cursor: pointer;
    padding: 0;
    transition: background 0.12s ease;
}

.feed-card__more:hover {
    background: rgba(0, 0, 0, 0.05);
}

/* ── 본문 ── */
.feed-card__content {
    margin: 10px 0;
    white-space: pre-wrap;
    word-break: break-word;
    line-height: 1.65;
    font-size: 14px;
}

/* ── 이미지 ── */
.feed-card__image {
    width: 100%;
    aspect-ratio: 4 / 3;
    object-fit: cover;
    border-radius: 10px;
    background: rgba(0, 0, 0, 0.05);
    margin-bottom: 2px;
}

html.dark .feed-card__image {
    background: rgba(255, 255, 255, 0.08);
}

/* ── 영상/숏츠 ── */
.video-player {
    position: relative;
    width: 100%;
    border-radius: 10px;
    overflow: hidden;
    background: #000;
    margin-bottom: 2px;
}

.video-player--thumb {
    border: 0;
    padding: 0;
    cursor: pointer;
    display: block;
}

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
    font-size: 12px;
    font-weight: 600;
}

.video-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 4px;
    padding: 8px 14px;
    border-radius: 10px;
    border: 1px solid rgba(54, 173, 255, 0.35);
    background: rgba(54, 173, 255, 0.06);
    color: var(--accent);
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
}

.video-link svg {
    width: 16px;
    height: 16px;
}

.composer__video {
    margin-top: 10px;
}

/* ── 액션 버튼 ── */
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
    font-size: 13px;
    cursor: pointer;
    padding: 2px 6px;
    transition: background 0.12s ease;
}

.feed-action:hover { background: rgba(0, 0, 0, 0.04); }
.feed-action--liked { color: #e5484d; }
.feed-action--static { cursor: default; }
.feed-action--static:hover { background: none; }

.feed-action__icon { width: 18px; height: 18px; }

/* ── 댓글 ── */
.feed-card__comments {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-top: 8px;
    padding: 10px;
    border-radius: 10px;
    background: rgba(0, 0, 0, 0.02);
}

html.dark .feed-card__comments {
    background: rgba(255, 255, 255, 0.03);
}

.comment-row {
    display: flex;
    gap: 8px;
    font-size: 13px;
    align-items: baseline;
}

.comment-row strong { flex-shrink: 0; color: var(--text); font-size: 13px; }
.comment-row span { word-break: break-word; color: var(--text); }

.comments-more {
    border: 0;
    background: none;
    color: #36adff;
    font-size: 13px;
    cursor: pointer;
    padding: 2px 0;
    text-align: left;
    font-weight: 500;
}

.comments-more:hover { opacity: 0.8; }

/* ── 댓글 입력 ── */
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
    font-size: 13px;
    outline: none;
    transition: background 0.15s ease;
}

.feed-card__composer input:focus {
    background: rgba(0, 0, 0, 0.07);
}

html.dark .feed-card__composer input {
    background: rgba(255, 255, 255, 0.05);
}

html.dark .feed-card__composer input:focus {
    background: rgba(255, 255, 255, 0.08);
}

.feed-card__composer button {
    border: 0;
    background: none;
    color: #36adff;
    font-weight: 700;
    font-size: 14px;
    cursor: pointer;
    flex-shrink: 0;
    padding: 6px 4px;
    transition: opacity 0.12s ease;
}

.feed-card__composer button:disabled { color: var(--text-muted); cursor: default; opacity: 0.5; }

.feed-more {
    display: flex;
    justify-content: center;
    padding: 12px 0 20px;
}

/* ── 글쓰기 모달 ── */
.composer { display: flex; flex-direction: column; gap: 14px; }

.composer__preview {
    width: 100%;
    max-height: 260px;
    object-fit: cover;
    border-radius: 10px;
}

.composer__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.composer__upload {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: var(--text-muted);
    font-size: 13px;
    cursor: pointer;
}

.composer__upload svg { width: 20px; height: 20px; }
</style>
