<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Order $order): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('order.create');
    }

    public function update(User $user, Order $order): bool
    {
        return true;
    }

    public function delete(User $user, Order $order): bool
    {
        return true;
    }

    public function transition(User $user, Order $order): bool
    {
        if ($user->hasPermission('order.status.update')) {
            return true;
        }

        // 완료 → 정산(settled) 전이는 등록자(원 등록자)만 가능 — 진행자는 '정산 진행중'으로 대기
        if ($order->status === Order::STATUS_COMPLETED) {
            return $order->original_owner_id !== null
                ? $order->original_owner_id === $user->id
                : $order->user_id === $user->id;
        }

        // 운행 소유자(가져온 드라이버)는 본인 운행의 상태를 진행시킬 수 있다.
        // 가져오기 승인 전에도 실제 운행을 맡을 드라이버(신청자)는 상태 진행이 가능해야 한다.
        return $order->user_id === $user->id || $order->claimant_user_id === $user->id;
    }
}
