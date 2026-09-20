<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 설문 투표 — 한 사람이 한 설문에 한 표. 선택지는 community_posts.survey_options 의 순번(option_id)을 가리킨다.
 */
#[Fillable(['post_id', 'option_id', 'user_id'])]
class CommunitySurveyVote extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'option_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CommunityPost, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(CommunityPost::class, 'post_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
