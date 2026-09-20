<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\User;
use App\Services\Order\OrderTransitionService;
use App\Support\Orders\ServiceTypeInferrer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:publish-ready {--dry-run : 실제 전이 없이 대상만 확인한다}')]
#[Description('초안 중 마켓 공개 요건을 충족한 운행을 공개한다. 구분이 비어 있으면 노선으로 추정해 채운다.')]
class PublishReadyOrders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(OrderTransitionService $transitionService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $published = 0;
        $filled = 0;
        $skipped = ['요건 미달' => 0, '관리자 보류' => 0];

        Order::query()
            ->where('status', Order::STATUS_DRAFT)
            ->chunkById(200, function ($orders) use ($transitionService, $dryRun, &$published, &$filled, &$skipped): void {
                foreach ($orders as $order) {
                    // 지난 날짜·먼 날짜도 공개 요건이 함께 본다
                    if ($order->publishRequirementError() !== null) {
                        $skipped['요건 미달']++;

                        continue;
                    }

                    if ((bool) $order->admin_hold) {
                        $skipped['관리자 보류']++;

                        continue;
                    }

                    // 구분이 비어 있으면 노선으로 추정해 채운다 — 마켓 카드의 구분 표시를 위해
                    if (blank($order->service_type)) {
                        $inferred = ServiceTypeInferrer::infer($order->pickup_location, $order->dropoff_location);

                        if ($inferred !== null) {
                            if (! $dryRun) {
                                $order->forceFill(['service_type' => $inferred])->save();
                            }
                            $filled++;
                        }
                    }

                    if (! $dryRun) {
                        $owner = User::query()->find($order->user_id);

                        if ($owner === null) {
                            continue;
                        }

                        $transitionService->transition($owner, $order, Order::STATUS_PUBLISHED);
                    }

                    $published++;
                }
            });

        $this->info(($dryRun ? '[모의 실행] ' : '')."공개 가능 {$published}건 · 구분 추정 {$filled}건");
        $this->line('제외 — '.collect($skipped)->map(fn (int $count, string $reason): string => "{$reason} {$count}건")->implode(' · '));

        return self::SUCCESS;
    }
}
