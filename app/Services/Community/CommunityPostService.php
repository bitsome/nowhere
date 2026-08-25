<?php

namespace App\Services\Community;

use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * 커뮤니티 게시글 — 작성/삭제/좋아요/댓글의 도메인 규칙(파일, XP)과 직렬화를 담당한다.
 */
class CommunityPostService
{
    /**
     * 글 작성 — 내용 + 선택 사진 + 영상/숏츠 URL + 카테고리.
     */
    public function create(User $author, string $content, ?UploadedFile $image, string $videoUrl, string $category = 'free'): CommunityPost
    {
        $imagePath = null;

        if ($image instanceof UploadedFile) {
            $imagePath = $image->store('community', 'public');
        }

        $post = CommunityPost::create([
            'user_id' => $author->id,
            'category' => $category,
            'content' => $content,
            'image_path' => $imagePath,
            'video_url' => trim($videoUrl),
        ]);

        // 레벨링: 커뮤니티 글 작성 +5 XP
        $author->addXp(5, 'community_post', '커뮤니티 글 작성');

        return $post;
    }

    /**
     * 글 수정 — 본인 글만 (첨부 이미지 교체 시 기존 파일 정리).
     */
    public function update(
        User $author,
        CommunityPost $post,
        string $content,
        ?UploadedFile $image,
        string $videoUrl,
        string $category = 'free',
    ): CommunityPost {
        abort_unless($post->user_id === $author->id, 403);

        $data = [
            'category' => $category,
            'content' => $content,
            'video_url' => trim($videoUrl),
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
            'roleLabel' => [
                'Super Admin' => '최고 관리자',
                'Admin' => '관리자',
                'Operator' => '운영자',
                'Driver' => '드라이버',
            ][$user->role] ?? $user->role,
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
