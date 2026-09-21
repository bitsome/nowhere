<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useMessage } from 'naive-ui';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { useUiStore } from '../../stores/ui';
import { useCommunityFeed } from '../../composables/useCommunityFeed';
import { usePostComposer } from '../../composables/usePostComposer';
import { COMMUNITY_CATEGORIES, categoryOf } from '../../utils/communityCategories';
import { timeAgo } from '../../utils/communityPost';
import BaseIcon from '../../components/common/BaseIcon.vue';
import EmptyState from '../../components/common/EmptyState.vue';
import CommunityPostEditor from '../../components/community/CommunityPostEditor.vue';
import FeedCard from '../../components/community/FeedCard.vue';

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
    toggleLike, vote, commentText, submitComment, deleteComment,
    expandComments, removePost, myId, playingVideo, toggleVideo, visiblePosts,
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
    draftSurveyOptions, draftSurveyCloses, draftPlaceName, draftPlaceRegion, draftPlaceAddress, draftPlaceMapUrl,
    isSurveyCategory, isPlaceCategory, openComposer, pickImage, addSurveyOption, removeSurveyOption, submitPost,
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
                <!-- 피드 카드 — 목록·상세가 함께 쓰는 공용 컴포넌트 (components/community/FeedCard.vue) -->
                <FeedCard
                    v-for="post in visiblePosts"
                    :key="post.id"
                    class="feed-card--link"
                    :post="post"
                    :can-expand-comments="true"
                    :playing-video="Boolean(playingVideo[post.id])"
                    :comment="commentText[post.id] ?? ''"
                    @update:comment="(text) => (commentText[post.id] = text)"
                    @open="openPost"
                    @like="toggleLike"
                    @vote="vote"
                    @toggle-video="toggleVideo"
                    @comment-submit="submitComment"
                    @comment-delete="deleteComment"
                    @expand-comments="expandComments"
                    @edit="openEdit"
                    @delete="removePost"
                />

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
                    placeholder="무슨 일이 있었나요? 공유해 보세요."
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

                <!-- 설문조사 — 선택지 2개 이상 + 마감일 -->
                <div v-if="isSurveyCategory" class="composer__survey">
                    <span class="composer__group-title">선택지 (2개 이상)</span>
                    <div v-for="(option, index) in draftSurveyOptions" :key="index" class="composer__option-row">
                        <n-input
                            v-model:value="draftSurveyOptions[index]"
                            type="text"
                            :placeholder="`선택지 ${index + 1}`"
                            maxlength="80"
                        />
                        <button
                            v-if="draftSurveyOptions.length > 2"
                            type="button"
                            class="composer__option-remove"
                            aria-label="선택지 삭제"
                            @click="removeSurveyOption(index)"
                        >
                            <BaseIcon name="close" :size="14" />
                        </button>
                    </div>
                    <button
                        v-if="draftSurveyOptions.length < 10"
                        type="button"
                        class="composer__option-add"
                        @click="addSurveyOption"
                    >
                        <BaseIcon name="add" :size="14" />
                        선택지 추가
                    </button>
                    <n-date-picker
                        v-model:formatted-value="draftSurveyCloses"
                        type="date"
                        value-format="yyyy-MM-dd"
                        placeholder="마감일 (선택)"
                        clearable
                    />
                </div>

                <!-- 여행지 맛집 — 장소 정보가 카드로 노출된다 -->
                <div v-if="isPlaceCategory" class="composer__place">
                    <span class="composer__group-title">맛집 정보</span>
                    <n-input v-model:value="draftPlaceName" type="text" placeholder="맛집 이름 (필수)" maxlength="80" />
                    <n-input v-model:value="draftPlaceRegion" type="text" placeholder="지역 (예: 제주 서귀포)" maxlength="40" />
                    <n-input v-model:value="draftPlaceAddress" type="text" placeholder="주소" maxlength="150" />
                    <n-input v-model:value="draftPlaceMapUrl" type="text" placeholder="지도 링크 (선택)" maxlength="500" />
                </div>

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

/* ── 카드 ──
   카드 골격(.feed-card·.feed-avatar·.feed-action …)은 공용 컴포넌트가 갖는다.
   (components/community/FeedCard.vue) 여기에는 목록만의 차이만 남긴다. */
.feed-card--link {
    cursor: pointer;
    transition: border-color 0.15s ease;
}

.feed-card--link:hover { border-color: color-mix(in srgb, var(--brand) 35%, transparent); }

/* ── 글쓰기 모달의 영상 미리보기 ── */
.composer__video { margin-top: 10px; }

/* 카드 내부(액션·댓글·입력창) 스타일도 공용 컴포넌트가 갖는다 — FeedCard.vue */

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

/* ── 설문조사 / 여행지 맛집 입력 ── */
.composer__survey,
.composer__place {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 12px;
    border: 1px solid var(--border);
    border-radius: 12px;
    background: rgba(0, 0, 0, 0.02);
}

html.dark .composer__survey,
html.dark .composer__place { background: rgba(255, 255, 255, 0.03); }

.composer__group-title {
    font-size: 11px;
    font-weight: 700;
    color: var(--text);
}

.composer__option-row {
    display: flex;
    align-items: center;
    gap: 6px;
}

.composer__option-row :deep(.n-input) { flex: 1; }

.composer__option-remove {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 28px;
    height: 28px;
    border: 0;
    border-radius: 50%;
    background: none;
    color: var(--text-muted);
    cursor: pointer;
    transition: color 0.12s ease;
}

.composer__option-remove:hover { color: var(--danger); }

.composer__option-add {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    align-self: flex-start;
    padding: 5px 10px;
    border: 1px dashed var(--border);
    border-radius: 999px;
    background: none;
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    transition: border-color 0.12s ease, color 0.12s ease;
}

.composer__option-add:hover { border-color: var(--brand); color: var(--brand); }

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
