<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 기사 자동 매칭 설정 — 날짜 범위(오늘/내일)/시간대/요일/출발지역/최소수익 조건.
 */
#[Fillable([
    'user_id',
    'name',
    'start_time',
    'end_time',
    'date_range',
    'days',
    'area',
    'max_passengers',
    'min_revenue',
    'is_active',
])]
class MatchPreference extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'days' => 'array',
            'is_active' => 'boolean',
            'max_passengers' => 'integer',
            'min_revenue' => 'integer',
        ];
    }
}
