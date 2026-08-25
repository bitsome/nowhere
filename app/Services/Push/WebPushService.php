<?php

namespace App\Services\Push;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\WebPush;

/**
 * 웹 푸시 발송 — VAPID 서명 + 구독 엔드포인트로 푸시를 보낸다.
 * 실패한 구독(404/410)은 정리해 DB가 쌓이지 않게 한다.
 */
class WebPushService
{
    private ?WebPush $webPush = null;

    private function client(): WebPush
    {
        if ($this->webPush !== null) {
            return $this->webPush;
        }

        $this->webPush = new WebPush([
            'VAPID' => [
                'subject' => config('webpush.vapid_subject', 'mailto:no-reply@nowhere.app'),
                'publicKey' => config('webpush.vapid_public_key'),
                'privateKey' => config('webpush.vapid_private_key'),
            ],
        ], [
            'TTL' => 600,
        ]);

        return $this->webPush;
    }

    /**
     * 사용자의 모든 구독에 푸시를 보낸다.
     */
    public function sendToUser(User $user, string $title, string $message, string $url = '/notifications'): void
    {
        foreach ($user->pushSubscriptions()->get() as $subscription) {
            $this->sendToSubscription($subscription, $title, $message, $url);
        }
    }

    public function sendToSubscription(PushSubscription $subscription, string $title, string $message, string $url = '/notifications'): void
    {
        try {
            $this->client()->sendNotification(
                $subscription->endpoint,
                json_encode([
                    'title' => $title,
                    'message' => $message,
                    'url' => $url,
                ], JSON_UNESCAPED_UNICODE),
                $subscription->public_key,
                $subscription->auth_token,
            );

            foreach ($this->client()->flush() as $report) {
                if ($report->isSuccess()) {
                    continue;
                }

                // 404/410 — 구독이 더 이상 유효하지 않음 → 삭제
                if (in_array($report->getStatusCode(), [404, 410], true)) {
                    $subscription->delete();
                } else {
                    Log::warning('웹 푸시 전송 실패', [
                        'status' => $report->getStatusCode(),
                        'reason' => $report->getReason(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('웹 푸시 예외', ['message' => $e->getMessage()]);
        }
    }
}
