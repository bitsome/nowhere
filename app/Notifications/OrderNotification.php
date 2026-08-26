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
        public readonly ?int $offerId = null,
        public readonly ?int $offerAmount = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    /**
     * 웹 푸시 payload — 알림을 받은 기기가 백그라운드라도 제목/내용을 보여준다.
     *
     * @return array<string, mixed>
     */
    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->orderId !== null ? "/orders/{$this->orderId}" : '/notifications',
        ];
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
            'offer_id' => $this->offerId,
            'offer_amount' => $this->offerAmount,
        ];
    }
}
