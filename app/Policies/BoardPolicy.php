<?php

namespace App\Policies;

use App\Models\Board;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class BoardPolicy
{
    public function viewAny(User $user): bool
    {
        if (! $user->hasPermission('board.view')) {
            throw ValidationException::withMessages([
                'permission' => '현재 계정에는 해당 게시판 작업 권한이 없습니다.',
            ]);
        }

        return true;
    }

    public function view(User $user, Board $board): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        if (! ($user->hasPermission('board.create') || $user->hasPermission('board.view'))) {
            throw ValidationException::withMessages([
                'permission' => '현재 계정에는 게시글 등록 권한이 없습니다.',
            ]);
        }

        return true;
    }

    public function update(User $user, Board $board): bool
    {
        if (! $user->hasPermission('board.update')) {
            throw ValidationException::withMessages([
                'permission' => '현재 계정에는 해당 게시판 작업 권한이 없습니다.',
            ]);
        }

        return true;
    }

    public function delete(User $user, Board $board): bool
    {
        if (! $user->hasPermission('board.delete')) {
            throw ValidationException::withMessages([
                'permission' => '현재 계정에는 해당 게시판 작업 권한이 없습니다.',
            ]);
        }

        return true;
    }
}
