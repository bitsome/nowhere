<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\OrderNotification;

/**
 * 알림 피로도 관리 — 같은 사용자에게 동일한 (제목, 운행) 알림이 최근에 이미 발송됐으면
 * 건너뛰어 반복 알림을 줄인다. 스케줄러·재시도 등 예기치 않은 중복 발송을 막는 공용 가드로 쓴다.
 */
class NotificationService
{
    /**
     * 같은 제목·운행의 알림을 중복 없이 보낸다.
     *
     * @param  int|null  $orderId  연결된 운행 id (없으면 null — 운행 없는 알림은 시간 창 중복만 확인)
     * @param  int  $windowMinutes  중복 판단 시간 창(분) — 이 안의 같은 알림은 보내지 않는다
     * @return bool 보냈으면 true, 중복으로 스킵됐으면 false
     */
    public function notifyOnce(User $user, string $title, ?int $orderId, string $message, int $windowMinutes = 60): bool
    {
        $query = $user->notifications()
            ->where('type', OrderNotification::class)
            ->where('data->title', $title)
            ->where('created_at', '>=', now()->subMinutes($windowMinutes));

        if ($orderId !== null) {
            $query->where('data->order_id', $orderId);
        }

        if ($query->exists()) {
            return false;
        }

        $user->notify(new OrderNotification($title, $message, $orderId));

        return true;
    }
}
