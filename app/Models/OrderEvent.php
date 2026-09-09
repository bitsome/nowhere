<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 운행 이벤트 — 운행의 주요 상태·단계 변경 기록 (분쟁·정산 대응 근거).
 */
#[Fillable(['order_id', 'user_id', 'event', 'from_status', 'to_status', 'note'])]
class OrderEvent extends Model
{
    public const EVENT_STATUS = 'status';

    public const EVENT_RIDE_STEP = 'ride_step';

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
