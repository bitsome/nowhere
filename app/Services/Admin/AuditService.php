<?php

namespace App\Services\Admin;

use App\Models\AuditLog;
use App\Models\User;

/**
 * 관리자 감사 로그 기록 — 운영 행위의 단일 기록소.
 * 정상 운행은 시스템이 자동 처리하고, 관리자가 손댄 행위만 이력으로 남긴다.
 */
class AuditService
{
    /**
     * 관리자 행위를 감사 로그에 기록한다.
     *
     * @param  array<string, mixed>|null  $meta  함께 남길 구조화 정보 (대상 id·이전 상태 등)
     */
    public static function record(User $admin, string $action, string $message, ?array $meta = null): AuditLog
    {
        return AuditLog::query()->create([
            'admin_id' => $admin->id,
            'admin_name' => $admin->name,
            'action' => $action,
            'message' => mb_substr($message, 0, 500),
            'meta' => $meta,
        ]);
    }
}
