<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 정산 원장 — 정산(settled)된 운행별 금액 확정 기록.
 * 운행금액·플랫폼 수수료·실지급액을 확정하고, 출금 신청(지급) 상태를 관리한다.
 */
#[Fillable(['order_id', 'driver_id', 'registrant_id', 'gross_amount', 'fee_amount', 'fee_rate', 'net_amount', 'status', 'collection_status', 'collected_at', 'collected_by', 'collection_note', 'payout_id', 'paid_at', 'hold_reason'])]
class Settlement extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const COLLECTION_PENDING = 'pending';

    public const COLLECTION_PAID = 'paid';

    // 자기 수행(등록자 = 수행자) — 플랫폼을 통과하는 돈이 없어 수금할 것이 없다
    public const COLLECTION_NOT_REQUIRED = 'not_required';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fee_rate' => 'float',
            'paid_at' => 'datetime',
            'collected_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * 수행 기사 — 정산을 받을 대상.
     *
     * @return BelongsTo<User, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function registrant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrant_id');
    }

    /**
     * @return BelongsTo<PayoutRequest, $this>
     */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(PayoutRequest::class);
    }

    /**
     * 입금 확인을 처리한 관리자.
     *
     * @return BelongsTo<User, $this>
     */
    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }
}
