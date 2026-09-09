import { computed, nextTick, ref } from 'vue';
import {
    apiCommentCommunity,
    apiCommunityPost,
    apiCommunityPosts,
    apiDeleteCommunityComment,
    apiDeleteCommunityPost,
    apiToggleCommunityLike,
    apiUpdateCommunityPost,
} from '../api/community';
import { getApiErrorMessage } from '../api/client';

/**
 * 커뮤니티 피드 — 글 목록/카테고리/검색/인기글/좋아요/댓글/삭제/영상 재생/표시 필터를 담당한다.
 *
 * @param {object} options
 * @param {object} options.message naive-ui message
 * @param {object} options.auth useAuthStore
 * @param {object} options.ui useUiStore
 */
export function useCommunityFeed({ message, auth, ui }) {
    const posts = ref([]);
    const popularPosts = ref([]);
    const categoryCounts = ref({});
    const pagination = ref(null);
    const page = ref(1);
    const loading = ref(true);
    const error = ref('');

    // 카테고리/검색 필터 — 변경 시 첫 페이지부터 다시 불러온다
    const category = ref('all');
    const search = ref('');
    const author = ref('');
    const period = ref('');

    const load = async (reset = false) => {
        if (reset) {
            page.value = 1;
        }

        loading.value = true;
        error.value = '';

        try {
            const params = {};

            if (category.value !== 'all') {
                params.category = category.value;
            }

            if (search.value.trim() !== '') {
                params.search = search.value.trim();
            }

            if (author.value.trim() !== '') {
                params.author = author.value.trim();
            }

            if (period.value !== '') {
                params.period = period.value;
            }

            const { data } = await apiCommunityPosts(page.value, params);
            posts.value = reset ? data.data : [...posts.value, ...data.data];
            categoryCounts.value = data.meta?.category_counts ?? {};
            pagination.value = data.meta.pagination;
        } catch (e) {
            error.value = getApiErrorMessage(e, '커뮤니티 글을 불러오지 못했습니다.');
        } finally {
            loading.value = false;
        }
    };

    // 🔥 인기 글 — 좋아요 많은 상위 3건 (전체 탭 상단 노출용)
    const loadPopular = async () => {
        try {
            const { data } = await apiCommunityPosts(1, { sort: 'popular', per_page: 3 });
            popularPosts.value = data.data;
        } catch {
            popularPosts.value = [];
        }
    };

    const setCategory = (key) => {
        if (category.value === key) {
            return;
        }

        category.value = key;
        load(true);
    };

    const applySearch = () => {
        load(true);
    };

    const clearSearch = () => {
        if (search.value === '') {
            return;
        }

        search.value = '';
        load(true);
    };

    const setAuthor = (value) => {
        author.value = value ?? '';
        load(true);
    };

    const setPeriod = (value) => {
        period.value = value ?? '';
        load(true);
    };

    // 검색/작성자/기간 모두 초기화 — 검색 모달 '초기화'에서 사용
    const clearAllFilters = () => {
        if (search.value === '' && author.value === '' && period.value === '') {
            return;
        }

        search.value = '';
        author.value = '';
        period.value = '';
        load(true);
    };

    const loadMore = () => {
        if (pagination.value && page.value < pagination.value.last_page) {
            page.value += 1;
            load();
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

    // 댓글 입력창으로 포커스 복귀 — 카드(data-post-id) 또는 상세 화면(input[data-post-comment])에서
    const focusCommentInput = (postId) => {
        nextTick(() => {
            const root = document.querySelector(`[data-post-id="${postId}"]`) ?? document.body;
            const input = root.querySelector('.feed-card__composer input') ?? document.querySelector('[data-post-comment-input]');

            input?.focus();
        });
    };

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
            focusCommentInput(post.id);
        } catch (e) {
            message.error(getApiErrorMessage(e, '댓글 작성에 실패했습니다.'));
        }
    };

    // 댓글 삭제 — 본인 댓글만 (서버에서 403 거부, 프론트에서도 버튼 숨김)
    const deleteComment = async (post, comment) => {
        try {
            const { data } = await apiDeleteCommunityComment(post.id, comment.id);
            post.comments = post.comments.filter((c) => c.id !== comment.id);
            post.comments_count = data.data.comments_count;
            message.success('댓글이 삭제되었습니다.');
        } catch (e) {
            message.error(getApiErrorMessage(e, '댓글 삭제에 실패했습니다.'));
        }
    };

    // 글 수정 — 피드 목록의 해당 글을 갱신된 데이터로 교체한다
    const updatePost = async (post, payload) => {
        try {
            const { data } = await apiUpdateCommunityPost(post.id, payload);
            const updated = data.data;

            posts.value = posts.value.map((p) => (p.id === updated.id ? updated : p));
            popularPosts.value = popularPosts.value.map((p) => (p.id === updated.id ? updated : p));

            return updated;
        } catch (e) {
            message.error(getApiErrorMessage(e, '글 수정에 실패했습니다.'));
            return null;
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
            popularPosts.value = popularPosts.value.filter((p) => p.id !== post.id);
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

    // 표시할 글 — 헤더 메뉴의 '내 글만 보기' + 정렬(최신/인기) 반영
    const visiblePosts = computed(() => {
        let list = ui.communityMyPostsOnly ? posts.value.filter((p) => p.user.id === myId.value) : posts.value;

        if (ui.communitySort === 'popular') {
            list = [...list].sort((a, b) => b.likes_count - a.likes_count || new Date(b.created_at) - new Date(a.created_at));
        }

        return list;
    });

    // 인기 글 클릭 → 전체 탭(인기순)으로 전환 후 해당 글 위치로 이동.
    // 피드에 아직 없으면 페이지를 더 불러오며 최대 3페이지까지 찾는다.
    const scrollToPost = async (postId) => {
        if (category.value !== 'all') {
            setCategory('all');
        }
        if (search.value !== '') {
            clearSearch();
        }
        ui.communityMyPostsOnly = false;
        ui.communitySort = 'popular';

        for (let i = 0; i < 3 && !posts.value.some((p) => p.id === postId); i++) {
            page.value = 1;
            await load(true);

            if (pagination.value && page.value < pagination.value.last_page) {
                page.value += 1;
                await load();
            }
        }

        await nextTick();
        document.querySelector(`[data-post-id="${postId}"]`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };

    return {
        posts,
        popularPosts,
        categoryCounts,
        pagination,
        page,
        loading,
        error,
        category,
        search,
        load,
        loadPopular,
        setCategory,
        applySearch,
        clearSearch,
        loadMore,
        toggleLike,
        commentText,
        submitComment,
        deleteComment,
        updatePost,
        expandComments,
        removePost,
        timeAgo,
        avatarText,
        myId,
        playingVideo,
        parseVideo,
        toggleVideo,
        visiblePosts,
        scrollToPost,
        author,
        period,
        setAuthor,
        setPeriod,
        clearAllFilters,
    };
}
