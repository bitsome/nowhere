<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Push\WebPushService;
use Illuminate\Console\Command;

/**
 * 테스트 웹 푸시 발송 — 구독 중인 사용자에게 확인용 푸시를 보낸다.
 *
 * 사용법: php artisan push:test --user=test@example.com --message="..."
 */
class SendTestPush extends Command
{
    protected $signature = 'push:test
        {--user= : 대상 사용자 이메일 (기본: 첫 번째 구독 보유자)}';

    protected $description = '구독 중인 사용자에게 테스트 웹 푸시를 보냅니다.';

    public function handle(WebPushService $webPush): int
    {
        $email = $this->option('user');

        $user = $email !== null
            ? User::query()->where('email', $email)->first()
            : User::query()->whereHas('pushSubscriptions')->first();

        if ($user === null) {
            $this->error('푸시 구독을 보유한 사용자를 찾을 수 없습니다.');

            return self::FAILURE;
        }

        $count = $user->pushSubscriptions()->count();

        if ($count === 0) {
            $this->error("{$user->email} 사용자의 구독이 없습니다. 브라우저에서 알림을 먼저 켜세요.");

            return self::FAILURE;
        }

        $webPush->sendToUser(
            $user,
            '푸시 테스트',
            '브라우저 알림이 정상 동작하고 있습니다. 이 알림을 클릭하면 알림센터로 이동합니다.',
            '/notifications',
        );

        $this->info("{$user->email}에게 {$count}개 구독으로 테스트 푸시를 보냈습니다.");

        return self::SUCCESS;
    }
}
