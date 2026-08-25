<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 자동 운행 등록 설정 — 단일 행(싱글턴)으로 관리한다.
 * 매일 정해진 시각에 관리자 계정으로 운행을 자동 등록하고,
 * 설정 화면에서 언제든 중지/시작·건수를 조정할 수 있다.
 */
class AutoOrderSetting extends Model
{
    protected $fillable = [
        'is_active',
        'min_count',
        'max_count',
        'owner_user_id',
        'last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'min_count' => 'integer',
            'max_count' => 'integer',
            'last_run_at' => 'datetime',
        ];
    }

    /**
     * 설정 단일 행을 반환한다 (없으면 기본값으로 생성).
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate(
            ['id' => 1],
            ['is_active' => false, 'min_count' => 10, 'max_count' => 20],
        );
    }
}
