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
    'category',
    'content',
    'image_path',
    'video_url',
    'survey_options',
    'survey_closes_at',
    'place_name',
    'place_region',
    'place_address',
    'place_map_url',
])]
class CommunityPost extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'survey_options' => 'array',
            'survey_closes_at' => 'datetime',
        ];
    }

    /**
     * 작성자.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 설문 투표 — 설문 글에만 존재한다.
     */
    public function surveyVotes(): HasMany
    {
        return $this->hasMany(CommunitySurveyVote::class, 'post_id');
    }

    /**
     * 설문 글인지 — 선택지 목록이 있으면 설문으로 본다.
     */
    public function isSurvey(): bool
    {
        return ! empty($this->survey_options);
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
            // 설문 득표 집계 — 글마다 조회하면 N+1이 되므로 한 번에 함께 불러온다
            'surveyVotes:id,post_id,option_id,user_id',
        ])
            ->withCount('likes')
            ->withCount('comments')
            ->withExists(['likes as is_liked' => fn (Builder $q) => $q->where('user_id', $userId)])
            ->latest();
    }
}
