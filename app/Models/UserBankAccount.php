<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 기사 출금 계좌 — 기사별 1개. 출금 신청 시 이 정보를 신청 건에 스냅샷으로 복사한다.
 */
#[Fillable(['user_id', 'bank_name', 'account_number', 'account_holder'])]
class UserBankAccount extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
