<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * 신고/분쟁 이벤트(접수·처리 결과)로 발생하는 DB 알림.
 * 접수 시 관리자에게, 처리(처리/완료) 시 신고자에게 전달된다.
 */
class ReportNotification extends Notification
{
    public function __construct(
        public readonly string $title,
        public readonly string $message,
        public readonly ?int $reportId = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    /**
     * 웹 푸시 payload — 신고 알림은 알림센터로 이동시킨다 (운행 상세 딥링크 없음).
     *
     * @return array<string, mixed>
     */
    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'url' => '/notifications',
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
            'report_id' => $this->reportId,
        ];
    }
}
