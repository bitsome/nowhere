<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, User $targetUser): bool
    {
        return true;
    }

    public function manage(User $actor, User $targetUser): bool
    {
        if (! $actor->canManageUser($targetUser)) {
            throw ValidationException::withMessages([
                'user' => '자신의 권한보다 동등하거나 상위 권한 사용자에게는 변경 작업을 수행할 수 없습니다.',
            ]);
        }

        return true;
    }
}
