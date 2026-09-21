<script setup>
import { useRouter } from 'vue-router';
import { categoryOf } from '../../utils/communityCategories';
import { avatarText, parseVideo, timeAgo } from '../../utils/communityPost';
import BaseIcon from '../common/BaseIcon.vue';
import LevelBadge from '../common/LevelBadge.vue';
import PlaceCard from './PlaceCard.vue';
import SurveyBlock from './SurveyBlock.vue';

/**
 * 커뮤니티 피드 카드 — 목록(CommunityView)과 글 상세(CommunityPostView)가 함께 쓴다.
 *
 * 두 화면이 같은 마크업과 CSS 를 각자 복사해 갖고 있어, 한쪽만 고치면 같은 글이 화면마다
 * 다르게 보였다. 카드의 생김새는 이제 여기서만 관리한다.
 *
 * 카드가 쓰는 상태(이 영상이 재생 중인지·댓글 입력값)는 부모가 post.id 기준으로 들고 있고
 * props/emit 으로 오간다. 로그인 사용자 페이지 이동은 라우터라 카드가 직접 처리한다.
 */
defineOptions({ name: 'FeedCard' });

defineProps({
    post: { type: Object, required: true },
    /** 댓글이 일부만 내려온 목록 카드 — '댓글 모두 보기'를 노출한다 (상세는 전체를 받는다) */
    canExpandComments: { type: Boolean, default: false },
    /** 이 글의 영상이 재생 중인지 */
    playingVideo: { type: Boolean, default: false },
    /** 댓글 입력값 */
    comment: { type: String, default: '' },
});

const emit = defineEmits([
    'open',             // 카드 탭 — 목록에서만 듣는다 (상세 화면은 카드가 클릭 대상이 아니다)
    'like',
    'vote',
    'toggle-video',
    'update:comment',
    'comment-submit',
    'comment-delete',
    'expand-comments',
    'edit',
    'delete',
]);

const router = useRouter();
const goUserPage = (userId) => router.push({ name: 'user-page', params: { id: userId } });
</script>

<template>
    <!-- 카드 내부 버튼은 stop 으로 카드 탭과 분리한다 — 상세 화면에서는 카드 탭이 없어 무해하다 -->
    <article class="feed-card" :data-post-id="post.id" @click="emit('open', post)">
        <div class="feed-card__head">
            <button
                type="button"
                class="feed-avatar feed-avatar--link"
                @click.stop="goUserPage(post.user.id)"
            >
                {{ avatarText(post.user.name) }}
            </button>
            <button
                type="button"
                class="feed-card__who"
                @click.stop="goUserPage(post.user.id)"
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
                @select="(key) => emit(key === 'edit' ? 'edit' : 'delete', post)"
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
                    v-if="!playingVideo"
                    type="button"
                    class="video-player video-player--thumb"
                    @click.stop="emit('toggle-video', post)"
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

        <!-- 여행지 맛집 카드 / 설문 투표 -->
        <PlaceCard v-if="post.place" :place="post.place" />
        <SurveyBlock
            v-if="post.survey"
            :survey="post.survey"
            @vote="(optionId) => emit('vote', post, optionId)"
        />

        <div class="feed-card__actions" @click.stop>
            <button
                type="button"
                class="feed-action"
                :class="{ 'feed-action--liked': post.is_liked }"
                @click="emit('like', post)"
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
                    @click="emit('comment-delete', post, comment)"
                >
                    <BaseIcon name="trash" :size="13" />
                </button>
            </div>
            <button
                v-if="canExpandComments && post.comments_count > post.comments.length"
                type="button"
                class="comments-more"
                @click="emit('expand-comments', post)"
            >
                댓글 모두 보기 ({{ post.comments_count - post.comments.length }}개 더)
            </button>
        </div>

        <div class="feed-card__composer" @click.stop>
            <!-- data-post-comment-input — 댓글 등록 후 입력창으로 포커스를 되돌릴 때 쓰는 표식 -->
            <input
                :value="comment"
                type="text"
                placeholder="댓글 달기..."
                data-post-comment-input
                @input="emit('update:comment', $event.target.value)"
                @keyup.enter="emit('comment-submit', post)"
            />
            <button
                type="button"
                :disabled="!comment.trim()"
                @click="emit('comment-submit', post)"
            >
                게시
            </button>
        </div>
    </article>
</template>

<style scoped>
/* ── 카드 골격 — 목록·상세 공통. 목록에서만 카드 전체가 클릭 대상이 된다(각 화면의 스타일) ── */
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

/* ── 댓글 (시간 + 내 댓글 삭제 + 더보기) ── */
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
</style>
