<script setup>
import { onMounted, ref } from 'vue';
import { useMessage } from 'naive-ui';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { useUiStore } from '../../stores/ui';
import { useCommunityFeed } from '../../composables/useCommunityFeed';
import { apiCommunityPost } from '../../api/community';
import { getApiErrorMessage } from '../../api/client';
import BaseIcon from '../../components/common/BaseIcon.vue';
import EmptyState from '../../components/common/EmptyState.vue';
import CommunityPostEditor from '../../components/community/CommunityPostEditor.vue';
import FeedCard from '../../components/community/FeedCard.vue';

defineOptions({ name: 'CommunityPostView' });

const route = useRoute();
const router = useRouter();
const message = useMessage();
const auth = useAuthStore();
const ui = useUiStore();

const feed = useCommunityFeed({ message, auth, ui });

const {
    toggleLike, vote, commentText, submitComment, deleteComment, removePost,
    playingVideo, toggleVideo,
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

        <!-- 피드 카드 — 목록과 같은 공용 컴포넌트. 상세는 댓글 전체를 받아 '모두 보기'가 없다 -->
        <FeedCard
            v-else
            class="detail-card"
            :post="post"
            :playing-video="Boolean(playingVideo[post.id])"
            :comment="commentText[post.id] ?? ''"
            @update:comment="(text) => (commentText[post.id] = text)"
            @like="toggleLike"
            @vote="vote"
            @toggle-video="toggleVideo"
            @comment-submit="submitComment"
            @comment-delete="deleteComment"
            @edit="openEdit"
            @delete="removePost"
        />

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

/* ── 카드 ── 공용 컴포넌트(components/community/FeedCard.vue)를 쓴다. 상세만의 여백만 남긴다 */
.detail-card { margin-top: 2px; }

/* 카드 내부(헤드·본문·영상·액션·댓글·입력창) 스타일은 공용 컴포넌트가 갖는다 — FeedCard.vue */

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
