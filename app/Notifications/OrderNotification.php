<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * 운행 이벤트(가져오기·상태 변경·등록)로 발생하는 DB 알림.
 * data에는 API가 읽는 title/message/order_id가 담긴다.
 */
class OrderNotification extends Notification
{
    public function __construct(
        public readonly string $title,
        public readonly string $message,
        public readonly ?int $orderId = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'order_id' => $this->orderId,
        ];
    }
}
