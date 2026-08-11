<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'content',
    'image_path',
    'video_url',
])]
class CommunityPost extends Model
{
    /**
     * 작성자.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 좋아요를 누른 사용자들.
     */
    public function likes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'community_post_likes', 'post_id', 'user_id')
            ->withTimestamps();
    }

    /**
     * 댓글.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(CommunityComment::class, 'post_id')->latest();
    }

    /**
     * 피드 — 최신 글, 좋아요/댓글 수와 내 좋아요 여부를 함께 가져온다.
     */
    public function scopeFeed(Builder $query, int $userId): Builder
    {
        return $query->with([
            'user:id,name,email,profile_photo_path,is_vip,is_vehicle_verified,is_license_verified,xp',
            // 피드 응답 최적화 — 글마다 최근 3개 댓글만 미리 로드 (나머지는 '모두 보기'로 지연 로드)
            'comments' => fn ($q) => $q->with('user:id,name,email,profile_photo_path')->latest()->limit(3),
        ])
            ->withCount('likes')
            ->withCount('comments')
            ->withExists(['likes as is_liked' => fn (Builder $q) => $q->where('user_id', $userId)])
            ->latest();
    }
}
