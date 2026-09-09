<?php

namespace App\Console\Commands;

use App\Services\Order\OrderOfferService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:expire-offers')]
#[Description('운행 시작 시각이 지났거나 운행이 취소된 대기 요금 제안을 자동 철회하고 기사에게 알린다.')]
class ExpireOffers extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(OrderOfferService $offerService): int
    {
        $expired = $offerService->autoExpirePastDue();

        $this->info("대기 요금 제안 자동 만료 {$expired}건을 처리했습니다.");

        return self::SUCCESS;
    }
}
