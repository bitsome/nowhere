<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useMessage } from 'naive-ui';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { useUiStore } from '../../stores/ui';
import { useCommunityFeed } from '../../composables/useCommunityFeed';
import { usePostComposer } from '../../composables/usePostComposer';
import { COMMUNITY_CATEGORIES, categoryOf } from '../../utils/communityCategories';
import LevelBadge from '../../components/common/LevelBadge.vue';
import BaseIcon from '../../components/common/BaseIcon.vue';
import EmptyState from '../../components/common/EmptyState.vue';
import CommunityPostEditor from '../../components/community/CommunityPostEditor.vue';

defineOptions({ name: 'CommunityView' });

const message = useMessage();
const router = useRouter();
const auth = useAuthStore();
const ui = useUiStore();

// ── 모듈: 커뮤니티 피드 / 글 작성 ──
const feed = useCommunityFeed({ message, auth, ui });
const composer = usePostComposer({ message, posts: feed.posts });

const {
    posts, popularPosts, categoryCounts, pagination, page, loading, error, category, search,
    load, loadMore, loadPopular, setCategory, applySearch, clearSearch,
    toggleLike, commentText, submitComment, deleteComment,
    expandComments, removePost, timeAgo, avatarText, myId, playingVideo, parseVideo, toggleVideo, visiblePosts,
    scrollToPost, author, period, clearAllFilters,
} = feed;

// 카테고리 칩 건수 — 서버가 내려준 카테고리별 글 수를 배지로 보여준다 (전체 = 합계)
const tabCounts = computed(() => {
    const counts = { all: 0 };

    for (const c of COMMUNITY_CATEGORIES) {
        const n = Number(categoryCounts.value?.[c.key] ?? 0) || 0;
        counts[c.key] = n;
        counts.all += n;
    }

    return counts;
});

const {
    showComposer, composing, draftContent, draftCategory, draftImage, draftPreviewUrl, draftVideoUrl,
    openComposer, pickImage, submitPost,
} = composer;

// 인기 글은 '전체 탭 + 검색 없음 + 내 글만 보기 아님'일 때만 상단 노출
const showPopular = () =>
    category.value === 'all' && search.value === '' && !ui.communityMyPostsOnly && ui.communitySort !== 'popular';

// 헤더 액션 버스 수신 — 글쓰기/필터/정렬/새로고침
watch(
    () => ui.actionSeq,
    () => {
        if (ui.actionName === 'community:write') {
            // 현재 탭의 카테고리를 작성 카테고리 기본값으로 사용 (전체 탭이면 자유)
            openComposer(category.value !== 'all' ? category.value : 'free');
        } else if (ui.actionName === 'community:reload') {
            load(true);
        }
    },
);

// 검색 모달 — 상단 검색 아이콘 클릭 시 열림
const searchOpen = ref(false);
const PERIOD_OPTIONS = [
    { label: '오늘', value: 'today' },
    { label: '이번 주', value: 'week' },
    { label: '이번 달', value: 'month' },
];
const openSearch = () => {
    searchOpen.value = true;
};
const submitSearch = () => {
    applySearch();
    searchOpen.value = false;
};

// 게시글 상세 이동 — 카드 본문 클릭 시 (버튼/링크는 .stop으로 전파 차단)
const openPost = (post) => {
    router.push({ name: 'community-post', params: { id: post.id } });
};

// 글 수정 — 더보기 메뉴에서 열기, 저장되면 피드 목록의 글을 갱신
const editOpen = ref(false);
const editPost = ref(null);
const openEdit = (post) => {
    editPost.value = post;
    editOpen.value = true;
};
const onSaved = (updated) => {
    // 에디터가 이미 서버에 저장했으므로, 반환받은 최신 글을 피드 목록에 반영만 한다
    posts.value = posts.value.map((p) => (p.id === updated.id ? updated : p));
    popularPosts.value = popularPosts.value.map((p) => (p.id === updated.id ? updated : p));
    editOpen.value = false;
    message.success('글이 수정되었습니다.');
};

onMounted(() => {
    load(true);
    loadPopular();
});
</script>

<template>
    <div class="community-page page-shell">
        <n-alert v-if="error" type="error" :show-icon="true" class="community-alert">
            {{ error }}
            <template #action>
                <n-button size="small" :loading="loading" @click="load">다시 시도</n-button>
            </template>
        </n-alert>

        <!-- 카테고리 탭 -->
        <div class="community-tabs">
            <button
                type="button"
                class="community-tabs__item"
                :class="{ 'community-tabs__item--active': category === 'all' }"
                @click="setCategory('all')"
            >
                전체
                <em class="community-tabs__count">{{ tabCounts.all }}</em>
            </button>
            <button
                v-for="c in COMMUNITY_CATEGORIES"
                :key="c.key"
                type="button"
                class="community-tabs__item"
                :class="{ 'community-tabs__item--active': category === c.key }"
                @click="setCategory(c.key)"
            >
                {{ c.label }}
                <em class="community-tabs__count">{{ tabCounts[c.key] }}</em>
            </button>
        </div>

        <!-- 정렬 + 검색 — 좌측 정렬, 우측 검색 -->
        <div class="community-toolbar">
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
            <button type="button" class="community-search-btn" aria-label="게시글 검색" @click="openSearch">
                <BaseIcon name="search" :size="20" />
            </button>
        </div>

        <!-- 인기 글 -->
        <template v-if="showPopular() && popularPosts.length">
            <div class="community-section">
                <span class="community-section__title">인기 글</span>
            </div>
            <div class="community-popular">
                <article
                    v-for="post in popularPosts"
                    :key="'pop-' + post.id"
                    class="popular-card"
                    @click="scrollToPost(post.id)"
                >
                    <span class="popular-card__badge">인기</span>
                    <span class="popular-card__cat">{{ categoryOf(post.category).label }}</span>
                    <p class="popular-card__title">{{ post.content }}</p>
                    <span class="popular-card__meta">
                        {{ post.user?.name }} · {{ timeAgo(post.created_at) }} · ♡ {{ post.likes_count }} · 댓글 {{ post.comments_count }}
                    </span>
                </article>
            </div>
        </template>

        <!-- 로딩 스켈레톤 — 피드 카드 골격 (n-spin 대신 레이아웃 유지) -->
        <div v-if="loading" class="community-body community-skeleton" aria-hidden="true">
            <div v-for="n in 4" :key="`feed-skel-${n}`" class="sk-card feed-skeleton">
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
                <div class="feed-skeleton__actions">
                    <div class="sk-line sk-line--sm" style="width: 64px;" />
                    <div class="sk-line sk-line--sm" style="width: 64px;" />
                </div>
            </div>
        </div>

        <div v-else class="community-body">
            <EmptyState
                v-if="visiblePosts.length === 0"
                icon="chat"
                title="게시글이 없습니다"
                hint="첫 글을 올려 커뮤니티를 시작해 보세요"
            />

            <div v-else class="community-feed">
                <article
                    v-for="post in visiblePosts"
                    :key="post.id"
                    class="feed-card"
                    :data-post-id="post.id"
                    @click="openPost(post)"
                >
                    <div class="feed-card__head">
                        <button
                            type="button"
                            class="feed-avatar feed-avatar--link"
                            @click.stop="router.push({ name: 'user-page', params: { id: post.user.id } })"
                        >
                            {{ avatarText(post.user.name) }}
                        </button>
                        <button
                            type="button"
                            class="feed-card__who"
                            @click.stop="router.push({ name: 'user-page', params: { id: post.user.id } })"
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
                            @select="(key) => (key === 'edit' ? openEdit(post) : removePost(post))"
                        >
                            <button type="button" class="feed-card__more" aria-label="더보기" @click.stop>
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

                    <!-- 영상/숏츠 — 유튜브는 썸네일 클릭 시 재생 -->
                    <template v-if="parseVideo(post.video_url)">
                        <div v-if="parseVideo(post.video_url).kind === 'youtube'">
                            <button
                                v-if="!playingVideo[post.id]"
                                type="button"
                                class="video-player video-player--thumb"
                                @click.stop="toggleVideo(post)"
                            >
                                <img :src="parseVideo(post.video_url).thumb" alt="영상 썸네일" loading="lazy" />
                                <span class="video-player__play">
                                    <BaseIcon name="play" :size="16" />
                                </span>
                                <span class="video-player__badge">숏츠/영상</span>
                            </button>
                            <div v-else class="video-player" @click.stop>
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
                            @click.stop
                        >
                            <BaseIcon name="video" :size="14" />
                            영상 보기
                        </a>
                    </template>

                    <div class="feed-card__actions" @click.stop>
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

                    <div v-if="post.comments.length" class="feed-card__comments" @click.stop>
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
                        <button
                            v-if="post.comments_count > post.comments.length"
                            type="button"
                            class="comments-more"
                            @click="expandComments(post)"
                        >
                            댓글 모두 보기 ({{ post.comments_count - post.comments.length }}개 더)
                        </button>
                    </div>

                    <div class="feed-card__composer" @click.stop>
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
        </div>

        <!-- 글쓰기 FAB -->
        <button
            type="button"
            class="community-fab"
            aria-label="글쓰기"
            @click="openComposer(category.value !== 'all' ? category.value : 'free')"
        >
            ＋
        </button>

        <!-- 글쓰기 모달 -->
        <n-modal
            v-model:show="showComposer"
            preset="card"
            title="새 글"
            :style="{ maxWidth: '520px' }"
        >
            <div class="composer">
                <!-- 카테고리 선택 -->
                <div class="composer__cats">
                    <button
                        v-for="c in COMMUNITY_CATEGORIES"
                        :key="c.key"
                        type="button"
                        class="composer__cat"
                        :class="{ 'composer__cat--active': draftCategory === c.key }"
                        @click="draftCategory = c.key"
                    >
                        <span><BaseIcon :name="c.icon" :size="16" /></span>{{ c.label }}
                    </button>
                </div>

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
                    placeholder="영상/숏츠 링크를 붙여 넣으세요"
                    clearable
                    class="composer__video"
                />

                <div class="composer__footer">
                    <label class="composer__upload">
                        <input type="file" accept="image/*" hidden @change="pickImage" />
                        <BaseIcon name="image" :size="16" />
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
        <!-- 검색 모달 -->
        <n-modal
            v-model:show="searchOpen"
            preset="card"
            title="게시글 검색"
            :style="{ maxWidth: '480px' }"
        >
            <div class="search-modal">
                <n-input
                    v-model:value="search"
                    type="text"
                    placeholder="노선 · 키워드로 게시글 검색"
                    clearable
                    size="large"
                    @keyup.enter="submitSearch"
                    @clear="clearSearch"
                />
                <n-input
                    v-model:value="author"
                    type="text"
                    placeholder="작성자 이름으로 검색"
                    clearable
                    size="large"
                    @keyup.enter="submitSearch"
                />
                <n-select
                    v-model:value="period"
                    :options="PERIOD_OPTIONS"
                    placeholder="기간 (전체)"
                    clearable
                    size="large"
                />
                <div class="search-modal__footer">
                    <n-button quaternary @click="clearAllFilters">초기화</n-button>
                    <n-button type="primary" @click="submitSearch">검색</n-button>
                </div>
            </div>
        </n-modal>

        <!-- 글 수정 모달 -->
        <CommunityPostEditor v-model:show="editOpen" :post="editPost" @saved="onSaved" />
    </div>
</template>

<style scoped>
/* 커뮤니티 — v7 디자인(검색/카테고리/인기글/FAB) 적용 */
.community-page {
    /* 상단 시작은 공용 .page-shell 기준으로 통일 — 하단 여백만 페이지가 관리한다 */
    padding-bottom: 90px;
}

.community-alert { margin-bottom: 12px; }

/* ── 정렬 + 검색 — 한 줄(좌측 정렬, 우측 검색) ── */
.community-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    /* 탭 바로 아래 툴바 — 아래 콘텐츠와 간격을 공용 토큰으로 통일 */
    margin-bottom: var(--chips-gap);
}

.community-search-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border: 1px solid var(--border);
    border-radius: 50%;
    background: var(--surface);
    color: var(--text);
    cursor: pointer;
    transition: border-color 0.15s ease, color 0.15s ease;
}

.community-search-btn:hover { border-color: var(--brand); color: var(--brand); }

/* ── 검색 모달 ── */
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

/* ── 카테고리 탭 ── */
.community-tabs {
    display: flex;
    gap: 6px;
    overflow-x: auto;
    padding-bottom: 4px;
    margin-bottom: var(--chips-gap);
    scrollbar-width: none;
}

.community-tabs::-webkit-scrollbar { display: none; }

/* 탭 아래 정렬 버튼 행(최신순/인기순) — 위로 끌어올려 여백을 작게
   (탭 자체 padding-bottom 4px + 탭 margin-bottom 8px를 상쇄해 실제 간격 약 4px) */
.community-tabs + .community-toolbar {
    margin-top: -8px;
}

.community-tabs__item {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 12px;
    border: 1px solid var(--border);
    border-radius: 999px;
    background: var(--surface);
    color: var(--text-muted);
    font-size: 11.5px;
    font-weight: 600;
    cursor: pointer;
    transition: border-color 0.15s ease, color 0.15s ease, background 0.15s ease;
}

/* hover — 터치 기기는 탭 후 남는 포커스가 hover로 고정될 수 있어 데스크톱에서만 */
@media (hover: hover) {
    .community-tabs__item:hover {
        border-color: color-mix(in srgb, var(--brand) 40%, transparent);
        color: var(--brand);
    }
}

.community-tabs__item--active {
    border-color: var(--brand);
    background: var(--brand-soft);
    color: var(--brand);
    font-weight: 700;
}

/* 건수 배지 — 카테고리별 글 수 */
.community-tabs__count {
    min-width: 18px;
    padding: 1px 5px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--text-muted) 12%, transparent);
    color: var(--text-muted);
    font-size: 10px;
    font-weight: 600;
    font-style: normal;
    text-align: center;
    line-height: 16px;
}
.community-tabs__item--active .community-tabs__count {
    background: var(--brand);
    color: #07120e;
}

/* ── 정렬 칩 ── */
.community-sort {
    display: flex;
    gap: 6px;
    margin-bottom: 0;
    flex-shrink: 0;
}

.community-sort__btn {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    border: 1px solid var(--border);
    border-radius: 999px;
    background: var(--surface);
    color: var(--text-muted);
    font-size: 11.5px;
    font-weight: 600;
    cursor: pointer;
    transition: border-color 0.15s ease, color 0.15s ease, background 0.15s ease;
}

@media (hover: hover) {
    .community-sort__btn:hover {
        border-color: color-mix(in srgb, var(--brand) 40%, transparent);
        color: var(--brand);
    }
}

.community-sort__btn--active {
    border-color: var(--brand);
    background: var(--brand-soft);
    color: var(--brand);
    font-weight: 700;
}

/* ── 섹션 제목 ── */
.community-section {
    display: flex;
    align-items: center;
    gap: 6px;
    margin: 2px 0 8px;
}

.community-section__title {
    font-size: 11px;
    font-weight: 800;
    color: var(--text);
}

/* ── 인기 글 ── */
.community-popular {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 14px;
}

.popular-card {
    display: grid;
    grid-template-columns: auto auto 1fr;
    align-items: center;
    gap: 6px;
    padding: 11px 13px;
    border: 1px solid var(--border);
    border-radius: 13px;
    background: var(--surface);
    cursor: pointer;
    transition: border-color 0.15s ease;
}

.popular-card:hover { border-color: color-mix(in srgb, var(--brand) 40%, transparent); }

.popular-card__badge {
    padding: 1px 6px;
    border-radius: 6px;
    background: color-mix(in srgb, var(--danger) 14%, transparent);
    color: var(--danger);
    font-size: 10px;
    font-weight: 400;
}

.popular-card__cat {
    padding: 1px 6px;
    border-radius: 6px;
    background: color-mix(in srgb, var(--brand) 12%, transparent);
    color: var(--brand);
    font-size: 10px;
    font-weight: 400;
}

.popular-card__title {
    grid-column: 1 / -1;
    margin: 0;
    font-size: 11px;
    font-weight: 700;
    color: var(--text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.popular-card__meta {
    grid-column: 1 / -1;
    font-size: 11px;
    color: var(--text-muted);
}

.community-body { display: block; min-height: 200px; }

.community-feed {
    display: flex;
    flex-direction: column;
    gap: var(--card-gap);
}

/* ── 카드 ── */
.feed-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--card-radius);
    padding: var(--card-pad);
    cursor: pointer;
    transition: border-color 0.15s ease;
}

.feed-card:hover { border-color: color-mix(in srgb, var(--brand) 35%, transparent); }

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
    background: var(--brand-gradient);
    color: #ffffff;
    font-size: 11px;
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

.feed-badge {
    display: inline-flex;
    align-items: center;
    font-size: 10px;
    line-height: 1;
}

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

/* ── 본문 ── */
.feed-card__content {
    margin: 10px 0;
    white-space: pre-wrap;
    word-break: break-word;
    line-height: 1.65;
    font-size: 11px;
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

html.dark .feed-card__image { background: rgba(255, 255, 255, 0.08); }

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

.composer__video { margin-top: 10px; }

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

html.dark .feed-card__comments { background: rgba(255, 255, 255, 0.03); }

.comment-row {
    display: flex;
    gap: 8px;
    font-size: 11px;
    align-items: baseline;
}

.comment-row strong { flex-shrink: 0; color: var(--text); font-size: 11px; }
.comment-row span { word-break: break-word; color: var(--text); }

.comment-row__time {
    color: var(--text-muted) !important;
    font-size: 11px !important;
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

.comments-more {
    border: 0;
    background: none;
    color: var(--brand);
    font-size: 11px;
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

.feed-more {
    display: flex;
    justify-content: center;
    padding: 12px 0 20px;
}

/* ── 글쓰기 FAB ── */
.community-fab {
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

.community-fab:hover { transform: scale(1.06); }

/* ── 글쓰기 모달 ── */
.composer { display: flex; flex-direction: column; gap: 14px; }

.composer__cats {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.composer__cat {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 6px 11px;
    border: 1px solid var(--border);
    border-radius: 999px;
    background: var(--bg);
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    transition: border-color 0.15s ease, color 0.15s ease, background 0.15s ease;
}

.composer__cat--active {
    border-color: var(--brand);
    background: var(--brand-soft);
    color: var(--brand);
}

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
    font-size: 11px;
    cursor: pointer;
}

/* ── 커뮤니티 로딩 스켈레톤 ── */
.community-skeleton {
    display: flex;
    flex-direction: column;
    gap: var(--card-gap);
}

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

.feed-skeleton__actions {
    display: flex;
    gap: 12px;
    padding-top: 8px;
    border-top: 1px solid var(--border);
}

.composer__upload svg { width: 20px; height: 20px; }
</style>
