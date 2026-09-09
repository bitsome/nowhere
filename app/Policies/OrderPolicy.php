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
        // 수정은 운행 등록자(원 등록자)만 가능 — 가져온 기사는 운행 정보를 수정할 수 없다
        return $order->original_owner_id !== null
            ? $order->original_owner_id === $user->id
            : $order->user_id === $user->id;
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

        // 완료 → 정산(settled) 전이는 등록자(원 등록자)만 가능 — 수행 기사는 완료가 마지막
        if ($order->status === Order::STATUS_COMPLETED) {
            return $order->original_owner_id !== null
                ? $order->original_owner_id === $user->id
                : $order->user_id === $user->id;
        }

        // 운행 진행(예약→운행중→완료)은 운행자(운행 소유자)·수행자(가져오기 신청자)만 진행할 수 있다.
        // 등록자(원 등록자)는 운행 수정만 가능하고 진행 상태를 바꿀 수 없다.
        return $order->user_id === $user->id || $order->hasPendingClaimBy($user->id);
    }
}
