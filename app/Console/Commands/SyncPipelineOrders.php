<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderIngestion;
use App\Support\Orders\PipelineOwner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:sync-pipeline {--dry-run : 값을 바꾸지 않고 대상만 확인한다}')]
#[Description('유입 기록을 근거로 파이프라인 운행을 표시하고 소유 계정을 운영 계정으로 맞춘다.')]
class SyncPipelineOrders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $owner = PipelineOwner::resolve();

        if ($owner === null) {
            $this->error('파이프라인 소유 계정을 찾지 못했습니다 — config(orders.pipeline_owner_email)를 확인해 주세요.');

            return self::FAILURE;
        }

        $this->line("소유 계정: {$owner->name} <{$owner->email}> (id {$owner->id})");

        $pipelineIds = $this->pipelineOrderIds();
        $target = count($pipelineIds);

        if ($target === 0) {
            $this->info('파이프라인으로 들어온 운행이 없습니다.');

            return self::SUCCESS;
        }

        $marked = 0;
        $reassigned = 0;

        Order::query()
            ->whereIn('id', array_keys($pipelineIds))
            ->chunkById(200, function ($orders) use ($owner, $dryRun, &$marked, &$reassigned): void {
                foreach ($orders as $order) {
                    $attributes = [];

                    if ($order->source !== Order::SOURCE_PIPELINE) {
                        $attributes['source'] = Order::SOURCE_PIPELINE;
                        $marked++;
                    }

                    if ((int) $order->user_id !== (int) $owner->id) {
                        $attributes['user_id'] = $owner->id;
                        $reassigned++;
                    }

                    if ($attributes === [] || $dryRun) {
                        continue;
                    }

                    $order->forceFill($attributes)->save();
                }
            });

        $prefix = $dryRun ? '[모의 실행] ' : '';
        $this->info("{$prefix}파이프라인 표시 {$marked}건 · 소유자 이전 {$reassigned}건 (대상 {$target}건)");

        return self::SUCCESS;
    }

    /**
     * 유입 기록에서 파이프라인으로 들어온 운행 id 를 모은다.
     *
     * 원문(original_summary)이 실린 행만 유입이다 — 앱에서 직접 등록한 건은 원문이 없다.
     *
     * @return array<int, true>
     */
    private function pipelineOrderIds(): array
    {
        $ids = [];

        OrderIngestion::query()->chunkById(200, function ($ingestions) use (&$ids): void {
            foreach ($ingestions as $ingestion) {
                $payload = (array) ($ingestion->payload ?? []);
                $orderIds = array_values(array_filter(
                    array_map('intval', (array) ($ingestion->order_ids ?? [])),
                    fn (int $id): bool => $id > 0,
                ));

                if ($orderIds === []) {
                    continue;
                }

                $rows = isset($payload['orders']) && is_array($payload['orders'])
                    ? array_values($payload['orders'])
                    : [$payload];

                foreach ($orderIds as $index => $orderId) {
                    // 묶음 등록은 order_ids 와 행 순서가 같다. 어긋나면 payload 전체 표기를 본다
                    $isPipeline = count($rows) === count($orderIds)
                        ? filled($rows[$index]['original_summary'] ?? null)
                        : filled($payload['original_summary'] ?? null);

                    if ($isPipeline) {
                        $ids[$orderId] = true;
                    }
                }
            }
        });

        return $ids;
    }
}
