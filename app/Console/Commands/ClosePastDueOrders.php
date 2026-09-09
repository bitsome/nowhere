<?php

namespace App\Console\Commands;

use App\Services\Order\OrderTransitionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:close-published')]
#[Description('시작 시각 후 유예가 지나도록 매칭되지 않은 공개 운행을 자동 취소하고 등록자·제안 기사에게 알린다.')]
class ClosePastDueOrders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(OrderTransitionService $transitionService): int
    {
        $closed = $transitionService->autoClosePastDuePublished();

        $this->info("미매칭 공개 운행 자동 취소 {$closed}건을 처리했습니다.");

        return self::SUCCESS;
    }
}
