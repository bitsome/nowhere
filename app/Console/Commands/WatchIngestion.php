<?php

namespace App\Console\Commands;

use App\Models\OrderIngestion;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('orders:watch-ingestion {--hours=6 : 이 시간 이상 유입이 없으면 관리자에게 알린다}')]
#[Description('위챗 유입이 오래 끊기면 관리자에게 알린다 — 발신 측 중단을 조용히 지나치지 않게 한다.')]
class WatchIngestion extends Command
{
    /** 같은 경보를 다시 보내기까지의 최소 간격(분) — 중단이 길어져도 알림이 반복되지 않게 한다. */
    private const ALERT_WINDOW_MINUTES = 720;

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notificationService): int
    {
        $thresholdHours = max(1, (int) $this->option('hours'));
        $latest = OrderIngestion::query()->max('created_at');

        // 유입 기록이 하나도 없으면 판단 기준이 없다 — 새 환경에서 오경보를 내지 않도록 건너뛴다
        if ($latest === null) {
            $this->info('유입 기록이 없어 판단을 건너뜁니다.');

            return self::SUCCESS;
        }

        $lastAt = Carbon::parse($latest);
        $silentMinutes = (int) abs(now()->diffInMinutes($lastAt));

        if ($silentMinutes < $thresholdHours * 60) {
            $this->info("유입 정상 — 마지막 유입 {$silentMinutes}분 전.");

            return self::SUCCESS;
        }

        $silentLabel = round($silentMinutes / 60, 1).'시간';
        $message = "위챗 유입이 {$silentLabel}째 없습니다 (마지막 유입 {$lastAt->timezone('Asia/Seoul')->format('m-d H:i')} KST). 발신 측(파서·브리지) 상태를 확인해 주세요.";

        $sent = 0;

        User::query()
            ->whereIn('role', User::ADMIN_ROLES)
            ->get()
            ->each(function (User $admin) use ($notificationService, $message, &$sent): void {
                if ($notificationService->notifyOnce($admin, '유입 중단 감지', null, $message, self::ALERT_WINDOW_MINUTES)) {
                    $sent++;
                }
            });

        $this->warn("유입 중단 — {$silentLabel}째 (관리자 알림 {$sent}건).");

        return self::SUCCESS;
    }
}
