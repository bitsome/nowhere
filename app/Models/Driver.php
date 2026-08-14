<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 기사 가용 상태 — users 테이블과 1:1.
 */
#[Fillable([
    'user_id',
    'status',
    'status_updated_at',
    'online_seconds',
    'online_date',
])]
class Driver extends Model
{
    public const STATUS_OFFLINE = 'offline';

    public const STATUS_ONLINE = 'online';

    public const STATUS_ON_TRIP = 'on_trip';

    public const STATUS_REST = 'rest';

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_OFFLINE => '오프라인',
            self::STATUS_ONLINE => '온라인',
            self::STATUS_ON_TRIP => '운행 중',
            self::STATUS_REST => '휴식',
        ];
    }

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
            'status_updated_at' => 'datetime',
            'online_date' => 'date',
            'online_seconds' => 'integer',
        ];
    }
}
