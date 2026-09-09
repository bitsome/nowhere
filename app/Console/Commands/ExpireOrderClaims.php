<?php

namespace App\Console\Commands;

use App\Services\Order\OrderClaimService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:expire-claims')]
#[Description('승인 대기로 30분이 지난 가져오기 요청을 자동 만료 처리하고 운행을 마켓으로 복귀시킨다.')]
class ExpireOrderClaims extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(OrderClaimService $claimService): int
    {
        $expired = $claimService->autoExpireDueOrders();

        $this->info("가져오기 요청 자동 만료 {$expired}건을 처리했습니다.");

        return self::SUCCESS;
    }
}
