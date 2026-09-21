import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import FeedCard from './FeedCard.vue';
import { categoryOf } from '../../utils/communityCategories';

/**
 * 피드 카드는 목록(CommunityView)과 상세(CommunityPostView)가 함께 쓴다.
 * 두 화면이 각자 마크업을 갖고 있던 시절의 동작(카드 탭·아바타 이동·좋아요·댓글)이
 * 공용 컴포넌트로 옮겨오면서도 그대로인지 고정한다.
 */
const { push } = vi.hoisted(() => ({ push: vi.fn() }));

vi.mock('vue-router', () => ({ useRouter: () => ({ push }) }));

const makePost = (over = {}) => ({
    id: 7,
    content: '공항 갑니다',
    category: 'airport',
    created_at: new Date().toISOString(),
    image_url: null,
    video_url: null,
    is_liked: false,
    likes_count: 0,
    comments_count: 0,
    comments: [],
    is_mine: false,
    user: { id: 3, name: '김민준', is_vip: false, level: null },
    ...over,
});

const mountCard = (post, props = {}) => mount(FeedCard, {
    props: { post, ...props },
    global: { stubs: { 'n-dropdown': { template: '<div><slot /></div>' } } },
});

const comment = () => ({
    id: 1,
    content: '안녕하세요',
    created_at: new Date().toISOString(),
    is_mine: false,
    user: { name: '이서연' },
});

beforeEach(() => push.mockClear());

describe('FeedCard — 글 표시', () => {
    it('내용·아바타 머리글자·카테고리를 보여준다', () => {
        const wrapper = mountCard(makePost());

        expect(wrapper.find('.feed-card__content').text()).toBe('공항 갑니다');
        expect(wrapper.find('.feed-avatar').text()).toBe('김');
        expect(wrapper.find('.feed-card__cat').text()).toBe(categoryOf('airport').label);
    });

    it('data-post-id 를 남긴다 — 댓글 포커스 복귀·스크롤이 이 표식을 찾는다', () => {
        expect(mountCard(makePost()).find('.feed-card').attributes('data-post-id')).toBe('7');
    });

    it('영상이 없으면 영상 영역을 그리지 않는다', () => {
        expect(mountCard(makePost()).find('.video-player').exists()).toBe(false);
    });
});

describe('FeedCard — 동작 분리', () => {
    it('카드를 누르면 open 을 올린다 (목록에서만 듣는다)', async () => {
        const post = makePost();
        const wrapper = mountCard(post);

        await wrapper.find('.feed-card').trigger('click');

        expect(wrapper.emitted('open')?.[0]).toEqual([post]);
    });

    it('아바타를 누르면 사용자 페이지로 가고 카드 열기는 올리지 않는다', async () => {
        const wrapper = mountCard(makePost());

        await wrapper.find('.feed-avatar').trigger('click');

        expect(push).toHaveBeenCalledWith({ name: 'user-page', params: { id: 3 } });
        expect(wrapper.emitted('open')).toBeUndefined();
    });

    it('좋아요를 누르면 like 를 올리고 카드 열기는 올리지 않는다', async () => {
        const post = makePost();
        const wrapper = mountCard(post);

        await wrapper.find('.feed-action').trigger('click');

        expect(wrapper.emitted('like')?.[0]).toEqual([post]);
        expect(wrapper.emitted('open')).toBeUndefined();
    });

    it('내 댓글의 삭제 버튼은 comment-delete 를 올린다', async () => {
        const post = makePost({ comments_count: 1, comments: [{ ...comment(), is_mine: true }] });
        const wrapper = mountCard(post);

        await wrapper.find('.comment-row__delete').trigger('click');

        expect(wrapper.emitted('comment-delete')?.[0]?.[0]).toEqual(post);
    });
});

describe('FeedCard — 댓글', () => {
    it('"댓글 모두 보기"는 일부만 내려온 목록에서만 나온다', () => {
        const post = makePost({ comments_count: 5, comments: [comment()] });

        expect(mountCard(post).find('.comments-more').exists()).toBe(false);
        expect(mountCard(post, { canExpandComments: true }).find('.comments-more').text()).toContain('4개 더');
    });

    it('"댓글 모두 보기"를 누르면 expand-comments 를 올린다', async () => {
        const post = makePost({ comments_count: 5, comments: [comment()] });
        const wrapper = mountCard(post, { canExpandComments: true });

        await wrapper.find('.comments-more').trigger('click');

        expect(wrapper.emitted('expand-comments')?.[0]).toEqual([post]);
    });

    it('입력값은 update:comment 로 올린다', async () => {
        const wrapper = mountCard(makePost(), { comment: '' });

        await wrapper.find('.feed-card__composer input').setValue('도착했습니다');

        expect(wrapper.emitted('update:comment')?.[0]).toEqual(['도착했습니다']);
    });

    it('게시 버튼은 입력값이 비면 잠기고, 누르면 comment-submit 을 올린다', async () => {
        const post = makePost();
        const empty = mountCard(post, { comment: '' });
        const filled = mountCard(post, { comment: '도착했습니다' });

        expect(empty.find('.feed-card__composer button').attributes('disabled')).toBeDefined();
        expect(filled.find('.feed-card__composer button').attributes('disabled')).toBeUndefined();

        await filled.find('.feed-card__composer button').trigger('click');

        expect(filled.emitted('comment-submit')?.[0]).toEqual([post]);
    });
});
