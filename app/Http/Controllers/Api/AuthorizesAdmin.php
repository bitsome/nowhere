<?php

namespace App\Http\Controllers\Api;

use App\Models\User;

/**
 * 관리자(Admin/Super Admin) 전용 API 공통 인가.
 *
 * 컨트롤러마다 중복되던 관리자 판정을 한 곳으로 모은다.
 * 루트 사용자(id=1)는 role과 무관하게 전체 접근한다(User::isRootUser).
 */
trait AuthorizesAdmin
{
    /**
     * 관리자/슈퍼 관리자가 아니면 403. user 미지정 시 현재 인증 사용자 기준.
     */
    protected function authorizeAdmin(?User $user = null): void
    {
        $user ??= auth()->user();

        abort_unless($user !== null && in_array($user->role, User::ADMIN_ROLES, true), 403, '관리자만 접근할 수 있습니다.');
    }

    /**
     * @deprecated authorizeAdmin()로 통일 — 기존 컨트롤러 호출 하위 호환용.
     */
    protected function assertAdmin(?User $user = null): void
    {
        $this->authorizeAdmin($user);
    }
}
