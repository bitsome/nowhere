<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderIngestion;
use App\Models\OrderLineItem;
use App\Models\OrderTerm;
use App\Support\Orders\ChineseTextNormalizer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:apply-terms')]
#[Description('내장 사전에 추가된 표기를 이미 저장된 운행·일정과 미매핑 용어에 소급 적용한다.')]
class ApplyOrderTerms extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $orders = $this->normalizeOrders();
        $lineItems = $this->normalizeLineItems();
        $tags = $this->attachTags();
        $terms = $this->settleTerms();

        $this->info("운행 {$orders}건 · 일정 {$lineItems}건 값을 바꿨고, 태그 {$tags}건을 붙였습니다.");
        $this->info("미매핑 용어 정리 — 매핑 완료 {$terms['mapped']}건(태그 표기 포함) · 무시 {$terms['ignored']}건.");

        return self::SUCCESS;
    }

    /**
     * 지명이 남아 있는 운행·차량 표기를 한국어로 바꾼다.
     */
    private function normalizeOrders(): int
    {
        $changed = 0;

        Order::query()->chunkById(200, function ($orders) use (&$changed): void {
            foreach ($orders as $order) {
                $attributes = [];

                foreach (['pickup_location', 'dropoff_location'] as $column) {
                    $attributes += $this->normalizedColumn($column, $order->{$column}, ChineseTextNormalizer::location(...));
                }

                $attributes += $this->normalizedColumn('vehicle_type', $order->vehicle_type, ChineseTextNormalizer::vehicleType(...));

                if ($attributes === []) {
                    continue;
                }

                $order->update($attributes);
                $changed++;
            }
        });

        return $changed;
    }

    /**
     * 일정(order_line_items)에 남은 지명도 함께 바꾼다 — 차량은 일정에 없다.
     */
    private function normalizeLineItems(): int
    {
        $changed = 0;

        OrderLineItem::query()->chunkById(200, function ($items) use (&$changed): void {
            foreach ($items as $item) {
                $attributes = [];

                foreach (['pickup_location', 'dropoff_location'] as $column) {
                    $attributes += $this->normalizedColumn($column, $item->{$column}, ChineseTextNormalizer::location(...));
                }

                if ($attributes === []) {
                    continue;
                }

                $item->update($attributes);
                $changed++;
            }
        });

        return $changed;
    }

    /**
     * 태그로 등록된 표기가 들어 있는 운행에 해당 태그를 붙인다 — 값은 그대로 둔다.
     *
     * 지명 칸뿐 아니라 유입 원문도 본다: `秒结`처럼 문구에만 적히는 운영 지시가 있다.
     */
    private function attachTags(): int
    {
        $changed = 0;
        $originals = $this->originalSummariesByOrder();

        Order::query()->chunkById(200, function ($orders) use (&$changed, $originals): void {
            foreach ($orders as $order) {
                $tags = ChineseTextNormalizer::tagsFor([
                    $order->pickup_location,
                    $order->dropoff_location,
                    $originals[$order->id] ?? null,
                ]);

                // 태그는 저장·표시와 같은 기준으로 쓴다 — 유입 파서 내부 태그는 여기서도 걷어낸다
                $raw = array_values(array_filter(array_map('trim', (array) ($order->tags ?? []))));
                $current = ChineseTextNormalizer::withoutInternalTags($raw);
                $merged = ChineseTextNormalizer::withoutInternalTags(array_values(array_unique([...$current, ...$tags])));

                $attributes = [];

                // 붙일 태그가 없어도 내부 태그가 남아 있으면 지운다 — 원본과 비교한다
                if ($merged !== $raw) {
                    $attributes['tags'] = $merged === [] ? null : $merged;
                }

                // `飞机马上降落`·`客人出来了`가 붙은 운행은 긴급으로 올린다 — 이미 태그가 있어도 한 번은 올린다
                $isUrgent = collect($merged)->contains(ChineseTextNormalizer::isUrgentTag(...));

                if ($isUrgent && ! $order->is_priority) {
                    $attributes['is_priority'] = true;
                }

                if ($attributes === []) {
                    continue;
                }

                $order->update($attributes);
                $changed++;
            }
        });

        return $changed;
    }

    /**
     * 유입 원본(order_ingestions)에 남은 운행별 원문 — 운행 행에는 원문이 저장되지 않는다.
     *
     * @return array<int, string>
     */
    private function originalSummariesByOrder(): array
    {
        $summaries = [];

        OrderIngestion::query()->chunkById(100, function ($ingestions) use (&$summaries): void {
            foreach ($ingestions as $ingestion) {
                $orderIds = (array) ($ingestion->order_ids ?? []);
                $payload = (array) ($ingestion->payload ?? []);
                $rows = isset($payload['orders']) && is_array($payload['orders']) ? $payload['orders'] : [$payload];

                foreach (array_values($orderIds) as $index => $orderId) {
                    $orderId = (int) $orderId;

                    if ($orderId <= 0 || array_key_exists($orderId, $summaries)) {
                        continue;
                    }

                    $text = $rows[$index]['original_summary'] ?? ($payload['original_summary'] ?? '');

                    if (filled($text)) {
                        $summaries[$orderId] = (string) $text;
                    }
                }
            }
        });

        return $summaries;
    }

    /**
     * 이미 쌓인 미매핑 용어를 내장 사전으로 정리한다.
     *
     * 태그 표기는 '태그' 분야 행으로 등록하고, 같은 표기가 지명 분야에 쌓여 있으면 무시한다.
     *
     * @return array{mapped: int, ignored: int}
     */
    private function settleTerms(): array
    {
        $mapped = 0;
        $ignored = 0;

        foreach (ChineseTextNormalizer::tagDictionary() as $term => $tag) {
            $row = OrderTerm::query()->firstOrCreate(
                ['field' => OrderTerm::FIELD_TAG, 'term' => $term],
                ['status' => OrderTerm::STATUS_MAPPED, 'occurrences' => 0],
            );

            if ($row->status === OrderTerm::STATUS_MAPPED && $row->mapped_to === $tag) {
                continue;
            }

            $row->update([
                'status' => OrderTerm::STATUS_MAPPED,
                'mapped_to' => $tag,
                'mapped_at' => now(),
            ]);
            $mapped++;
        }

        OrderTerm::query()
            ->where('status', OrderTerm::STATUS_PENDING)
            ->chunkById(200, function ($terms) use (&$mapped, &$ignored): void {
                foreach ($terms as $term) {
                    if (ChineseTextNormalizer::isTagTerm($term->term)) {
                        $term->update([
                            'status' => OrderTerm::STATUS_IGNORED,
                            'mapped_to' => null,
                            'mapped_by' => null,
                            'mapped_at' => null,
                        ]);
                        $ignored++;

                        continue;
                    }

                    $value = match ($term->field) {
                        OrderTerm::FIELD_PICKUP, OrderTerm::FIELD_DROPOFF => ChineseTextNormalizer::location($term->term),
                        OrderTerm::FIELD_VEHICLE, OrderTerm::FIELD_MODEL => ChineseTextNormalizer::vehicleType($term->term),
                        default => $term->term,
                    };

                    if ($value === $term->term) {
                        continue;
                    }

                    $term->update([
                        'status' => OrderTerm::STATUS_MAPPED,
                        'mapped_to' => $value,
                        'mapped_at' => now(),
                    ]);
                    $mapped++;
                }
            });

        return ['mapped' => $mapped, 'ignored' => $ignored];
    }

    /**
     * 표기가 바뀔 때만 갱신할 열을 돌려준다 — 값이 그대로면 손대지 않는다.
     *
     * @param  callable(string): ?string  $normalize
     * @return array<string, string>
     */
    private function normalizedColumn(string $column, ?string $value, callable $normalize): array
    {
        if (! ChineseTextNormalizer::containsChinese($value)) {
            return [];
        }

        $normalized = $normalize($value);

        if ($normalized === null || $normalized === $value) {
            return [];
        }

        return [$column => $normalized];
    }
}
