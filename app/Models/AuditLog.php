<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 관리자 감사 로그 — 관리자가 수행한 운영 행위(정산 처리·신고 진행·제재·운행 개입·역할 변경 등) 기록.
 * 누가(admin_id)·언제(created_at)·무엇을(action·message)을 한 줄로 남겨 분쟁·정산 대응 근거로 쓴다.
 */
#[Fillable([
    'admin_id',
    'admin_name',
    'action',
    'message',
    'meta',
])]
class AuditLog extends Model
{
    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
