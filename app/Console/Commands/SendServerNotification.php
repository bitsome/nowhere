<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\OrderNotification;
use Illuminate\Console\Command;

/**
 * 서버에서 특정 사용자에게 알림을 보내는 데모 커맨드.
 *
 * 예: php artisan notification:send --message="출발 준비 완료되었습니다."
 */
class SendServerNotification extends Command
{
    protected $signature = 'notification:send
        {--to=test@example.com : 수신자 이메일}
        {--title=알림 : 알림 제목}
        {--message=서버에서 보낸 알림입니다. : 알림 내용}
        {--order= : 연결할 오더 id (선택)}';

    protected $description = '서버에서 사용자에게 알림을 보낸다 (알림 데모).';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->option('to'))->first();

        if ($user === null) {
            $this->error("사용자를 찾을 수 없습니다: {$this->option('to')}");

            return self::FAILURE;
        }

        $orderId = $this->option('order') !== null ? (int) $this->option('order') : null;

        $user->notify(new OrderNotification(
            title: $this->option('title'),
            message: $this->option('message'),
            orderId: $orderId,
        ));

        $this->info("알림을 보냈습니다: {$this->option('title')}");

        return self::SUCCESS;
    }
}
