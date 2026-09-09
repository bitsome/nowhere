<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 요금 제안(오퍼) — 기사가 공개 운행에 제안한 운임.
 * 등록자가 여러 제안을 비교해 하나를 수락하면 그 기사에게 운행이 넘어간다.
 */
#[Fillable([
    'order_id',
    'driver_id',
    'amount',
    'message',
    'status',
    'reject_reason',
])]
class OrderOffer extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING => '대기',
            self::STATUS_ACCEPTED => '수락',
            self::STATUS_REJECTED => '거절',
            self::STATUS_CANCELLED => '철회',
        ];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }
}
