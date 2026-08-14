<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 알림·채팅 실시간 푸시 (SSE).
 *
 * - EventSource는 Authorization 헤더를 못 보내므로 ?token=으로 인증한다.
 * - 상태(알림/채팅 안 읽음 수)가 바뀌면 이벤트를 푸시한다 (2초 감시).
 * - 테스트/디버그: ?once=1 을 붙이면 초기 이벤트만 보내고 종료한다.
 */
class StreamController extends Controller
{
    public function stream(Request $request): StreamedResponse
    {
        $token = (string) $request->query('token', '') ?: (string) $request->bearerToken();
        $user = PersonalAccessToken::findToken($token)?->tokenable;

        abort_unless($user instanceof User, 401);

        $request->setUserResolver(fn () => $user);

        return response()->stream(function () use ($user, $request): void {
            $this->prepareStream();

            // 연결 종료 후 10초 뒤 재연결 (단일 워커 환경에서 서버 점유 방지)
            echo "retry: 10000\n\n";
            flush();

            $knownNotifications = $this->notificationUnread($user);
            $knownMessages = $this->messageUnread($user);

            $this->emit('state', $knownNotifications, $knownMessages);

            // 기사 상태 변경 이벤트 — 운영자/관리자에게 (최근 10분 내 변경분)
            if (in_array($user->role, [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN], true)) {
                $this->emitDrivers();
            }

            if ($request->boolean('once')) {
                return;
            }

            // php artisan serve(단일 워커)가 장기간 점유되지 않도록 최대 20초 후 자동 종료한다.
            $startedAt = time();

            while (time() - $startedAt < 20) {
                if (connection_aborted()) {
                    break;
                }

                $notifications = $this->notificationUnread($user);
                $messages = $this->messageUnread($user);

                if ($notifications !== $knownNotifications) {
                    $knownNotifications = $notifications;
                    $this->emit('notification', $knownNotifications, $knownMessages);
                }

                if ($messages !== $knownMessages) {
                    $knownMessages = $messages;
                    $this->emit('message', $knownNotifications, $knownMessages);
                }

                echo ": ping\n\n";
                flush();

                sleep(2);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=utf-8',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function notificationUnread(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    /**
     * SSE 스트리밍이 즉시 전송되도록 PHP 출력 버퍼를 해제한다.
     * (output_buffering 은 PHP 8.3+ 에서 런타임 변경 불가 → 버퍼를 직접 비운다)
     */
    private function prepareStream(): void
    {
        @ini_set('zlib.output_compression', 'off');
        @ini_set('implicit_flush', '1');
        header('X-Accel-Buffering: no');

        ob_implicit_flush(true);

        // 테스트(PHPUnit)의 출력 캡처 버퍼는 건드리지 않는다.
        if (app()->runningUnitTests()) {
            return;
        }

        while (ob_get_level() > 0) {
            @ob_end_flush();
        }
    }

    private function messageUnread(User $user): int
    {
        return Message::query()
            ->whereIn('conversation_id', $user->conversations()->pluck('conversations.id'))
            ->where('user_id', '!=', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * @return array<string, int>
     */
    private function emit(string $type, int $notifications, int $messages): void
    {
        echo "event: {$type}\n";
        echo 'data: '.json_encode([
            'unread_notifications' => $notifications,
            'unread_messages' => $messages,
        ], JSON_UNESCAPED_UNICODE)."\n\n";
        flush();
    }

    /**
     * 최근 변경된 기사 상태를 운영자/관리자에게 푸시한다.
     */
    private function emitDrivers(): void
    {
        $drivers = Driver::query()
            ->where('status_updated_at', '>=', now()->subMinutes(10))
            ->with('user:id,name')
            ->get()
            ->map(fn (Driver $driver) => [
                'id' => $driver->user_id,
                'name' => $driver->user?->name,
                'status' => $driver->status,
                'status_updated_at' => $driver->status_updated_at?->toIso8601String(),
            ])
            ->values();

        if ($drivers->isEmpty()) {
            return;
        }

        echo "event: driver\n";
        echo 'data: '.json_encode(['drivers' => $drivers], JSON_UNESCAPED_UNICODE)."\n\n";
        flush();
    }
}
