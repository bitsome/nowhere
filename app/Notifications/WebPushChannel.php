<?php

namespace App\Notifications;

use App\Services\Push\WebPushService;
use Illuminate\Notifications\Notification;

/**
 * Laravel 알림 채널 — OrderNotification 등이 저장될 때 함께 웹 푸시를 보낸다.
 * 알림 클래스가 toWebPush()를 구현한 경우에만 동작한다.
 */
class WebPushChannel
{
    public function __construct(private WebPushService $service) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWebPush')) {
            return;
        }

        $payload = $notification->toWebPush($notifiable);

        $this->service->sendToUser(
            $notifiable,
            $payload['title'] ?? '알림',
            $payload['message'] ?? '',
            $payload['url'] ?? '/notifications',
        );
    }
}
