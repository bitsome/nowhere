<?php

namespace App\Services\Community;

use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\CommunitySurveyVote;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * 커뮤니티 게시글 — 작성/삭제/좋아요/댓글의 도메인 규칙(파일, XP)과 직렬화를 담당한다.
 */
class CommunityPostService
{
    /**
     * 글 작성 — 내용 + 선택 사진 + 영상/숏츠 URL + 카테고리 (+ 설문 선택지 / 맛집 장소 정보).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $author, array $attributes, ?UploadedFile $image): CommunityPost
    {
        $imagePath = null;

        if ($image instanceof UploadedFile) {
            $imagePath = $image->store('community', 'public');
        }

        $post = CommunityPost::create([
            'user_id' => $author->id,
            'category' => $attributes['category'] ?? 'free',
            'content' => $attributes['content'],
            'image_path' => $imagePath,
            'video_url' => trim((string) ($attributes['video_url'] ?? '')),
            ...$this->surveyAttributes($attributes),
            ...$this->placeAttributes($attributes),
        ]);

        // 레벨링: 커뮤니티 글 작성 +5 XP
        $author->addXp(5, 'community_post', '커뮤니티 글 작성');

        return $post;
    }

    /**
     * 글 수정 — 본인 글만 (첨부 이미지 교체 시 기존 파일 정리).
     * 설문 선택지는 투표가 순번을 가리키므로 수정 대상에서 제외한다(장소 정보만 갱신).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $author, CommunityPost $post, array $attributes, ?UploadedFile $image): CommunityPost
    {
        abort_unless($post->user_id === $author->id, 403);

        $data = [
            'category' => $attributes['category'] ?? 'free',
            'content' => $attributes['content'],
            'video_url' => trim((string) ($attributes['video_url'] ?? '')),
            ...$this->placeAttributes($attributes),
        ];

        if ($image instanceof UploadedFile) {
            if ($post->image_path !== null) {
                Storage::disk('public')->delete($post->image_path);
            }

            $data['image_path'] = $image->store('community', 'public');
        }

        $post->update($data);

        return $post;
    }

    /**
     * 설문 선택지·마감 — 선택지가 2개 미만이면 설문이 아니라 일반 글로 저장한다(설문 컬럼을 비운다).
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function surveyAttributes(array $attributes): array
    {
        $options = collect($attributes['survey_options'] ?? [])
            ->map(fn ($option) => trim((string) $option))
            ->filter(fn (string $option) => $option !== '')
            ->values()
            ->all();

        if (count($options) < 2) {
            return ['survey_options' => null, 'survey_closes_at' => null];
        }

        return [
            'survey_options' => $options,
            'survey_closes_at' => $attributes['survey_closes_at'] ?? null,
        ];
    }

    /**
     * 맛집 장소 정보 — 빈 값은 null 로 저장해 카드가 아닌 일반 글로 남는다.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function placeAttributes(array $attributes): array
    {
        $value = function (string $key) use ($attributes): ?string {
            $trimmed = trim((string) ($attributes[$key] ?? ''));

            return $trimmed !== '' ? $trimmed : null;
        };

        return [
            'place_name' => $value('place_name'),
            'place_region' => $value('place_region'),
            'place_address' => $value('place_address'),
            'place_map_url' => $value('place_map_url'),
        ];
    }

    /**
     * 글 삭제 — 본인 글만 (첨부 이미지 정리 포함).
     */
    public function delete(User $author, CommunityPost $post): void
    {
        abort_unless($post->user_id === $author->id, 403);

        if ($post->image_path !== null) {
            Storage::disk('public')->delete($post->image_path);
        }

        $post->delete();
    }

    /**
     * 댓글 삭제 — 본인 댓글만.
     */
    public function deleteComment(User $author, CommunityComment $comment): void
    {
        abort_unless($comment->user_id === $author->id, 403);

        $comment->delete();
    }

    /**
     * 좋아요 토글 — 글 작성자에게 +1 XP.
     *
     * @return array{liked: bool, likes_count: int}
     */
    public function toggleLike(User $user, CommunityPost $post): array
    {
        if ($post->likes()->where('user_id', $user->id)->exists()) {
            $post->likes()->detach($user->id);
            $liked = false;
        } else {
            $post->likes()->attach($user->id);
            $liked = true;

            $author = $post->user()->first();

            if ($author !== null && $author->id !== $user->id) {
                $author->addXp(1, 'community_liked', '커뮤니티 글 좋아요 받기');
            }
        }

        return [
            'liked' => $liked,
            'likes_count' => $post->likes()->count(),
        ];
    }

    /**
     * 댓글 작성 — 작성자에게 +2 XP.
     */
    public function addComment(User $author, CommunityPost $post, string $content): CommunityComment
    {
        $comment = CommunityComment::create([
            'post_id' => $post->id,
            'user_id' => $author->id,
            'content' => $content,
        ]);

        // 레벨링: 댓글 작성 +2 XP
        $author->addXp(2, 'community_comment', '커뮤니티 댓글 작성');

        return $comment;
    }

    /**
     * 설문 투표 — 한 사람 한 표. 다시 투표하면 선택이 바뀐다(표가 늘지 않는다).
     * 마감된 설문은 막는다.
     *
     * @return array<string, mixed>
     */
    public function vote(User $voter, CommunityPost $post, int $optionId): array
    {
        abort_unless($post->isSurvey(), 422, '설문 글이 아닙니다.');

        $options = $post->survey_options ?? [];

        abort_unless(array_key_exists($optionId, $options), 422, '없는 선택지입니다.');

        if ($post->survey_closes_at !== null && $post->survey_closes_at->isPast()) {
            abort(422, '마감된 설문입니다.');
        }

        CommunitySurveyVote::updateOrCreate(
            ['post_id' => $post->id, 'user_id' => $voter->id],
            ['option_id' => $optionId],
        );

        return $this->surveyPayload($post->fresh(), $voter->id);
    }

    /**
     * 설문 득표 결과 — 선택지별 득표 수, 내가 고른 선택지, 마감 여부.
     *
     * @return array<string, mixed>
     */
    private function surveyPayload(CommunityPost $post, ?int $userId): array
    {
        $options = $post->survey_options ?? [];
        $votes = $post->relationLoaded('surveyVotes') ? $post->surveyVotes : $post->surveyVotes()->get();

        $counts = array_fill(0, count($options), 0);
        $mine = null;

        foreach ($votes as $vote) {
            $index = (int) $vote->option_id;

            if (array_key_exists($index, $counts)) {
                $counts[$index]++;
            }

            if ($userId !== null && (int) $vote->user_id === $userId) {
                $mine = $index;
            }
        }

        return [
            'options' => collect($options)->map(fn ($text, $index) => [
                'id' => $index,
                'text' => $text,
                'votes' => $counts[$index],
            ])->values()->all(),
            'total' => array_sum($counts),
            'my_option' => $mine,
            'closes_at' => $post->survey_closes_at?->toISOString(),
            'closed' => $post->survey_closes_at !== null && $post->survey_closes_at->isPast(),
        ];
    }

    /**
     * 맛집 카드 정보 — 장소명이 없으면 카드가 아니다.
     *
     * @return array<string, string|null>|null
     */
    private function placePayload(CommunityPost $post): ?array
    {
        if ($post->place_name === null) {
            return null;
        }

        return [
            'name' => $post->place_name,
            'region' => $post->place_region,
            'address' => $post->place_address,
            'map_url' => $post->place_map_url,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(CommunityPost $post): array
    {
        $post->loadMissing('user:id,name,email,profile_photo_path,is_vip,is_vehicle_verified,is_license_verified,xp');

        return [
            'id' => $post->id,
            'category' => $post->category ?? 'free',
            'content' => $post->content,
            'image_url' => $post->image_path !== null
                ? '/api/community/images/'.basename($post->image_path)
                : null,
            'video_url' => $post->video_url,
            'survey' => $post->isSurvey() ? $this->surveyPayload($post, auth()->id()) : null,
            'place' => $this->placePayload($post),
            'created_at' => $post->created_at?->toISOString(),
            'user' => [
                'id' => $post->user->id,
                'name' => $post->user->name,
                'profile_photo_path' => $post->user->profile_photo_path,
                'is_vip' => (bool) $post->user->is_vip,
                'is_vehicle_verified' => (bool) $post->user->is_vehicle_verified,
                'is_license_verified' => (bool) $post->user->is_license_verified,
                'level' => $post->user->levelInfo(),
            ],
            'likes_count' => $post->likes_count,
            'comments_count' => $post->comments_count,
            'is_liked' => (bool) ($post->is_liked ?? false),
            'is_mine' => $post->user_id === auth()->id(),
            'comments' => $post->comments->map(fn (CommunityComment $comment) => [
                'id' => $comment->id,
                'content' => $comment->content,
                'created_at' => $comment->created_at?->toISOString(),
                'user' => $comment->user?->only(['id', 'name']),
                'is_mine' => $comment->user_id === auth()->id(),
            ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'profile_photo_path' => $user->profile_photo_path,
            'role' => $user->role,
            'roleLabel' => User::roleLabel($user->role),
            'is_vehicle_verified' => (bool) $user->is_vehicle_verified,
            'is_license_verified' => (bool) $user->is_license_verified,
            'is_vip' => (bool) $user->is_vip,
            'vehicle_info' => $user->vehicle_info,
            'joined_at' => $user->created_at?->toDateString(),
            'posts_count' => $user->communityPosts()->count(),
            'level' => $user->levelInfo(),
            'xp' => (int) $user->xp,
        ];
    }
}
