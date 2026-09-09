<?php

namespace App\Console\Commands;

use App\Services\Settlement\SettlementService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:auto-settle')]
#[Description('완료 후 유예 시간이 지난 정상 운행을 자동 정산 처리하고 당사자에게 알린다.')]
class AutoSettleOrders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SettlementService $settlementService): int
    {
        $processed = $settlementService->autoSettleCompleted();

        $this->info("완료 운행 자동 정산 {$processed}건을 처리했습니다.");

        return self::SUCCESS;
    }
}
