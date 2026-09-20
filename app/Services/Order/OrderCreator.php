<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\OrderTerm;
use App\Support\Orders\ChineseTextNormalizer;
use App\Support\Orders\ServiceTypeInferrer;
use Illuminate\Support\Facades\DB;

/**
 * 운행 생성/복제/일괄 등록/수정 — 트랜잭션 단위로 순수 도메인 로직만 담당한다.
 */
class OrderCreator
{
    public function __construct(private readonly OrderTermCollector $termCollector) {}

    /**
     * 단일 운행 등록 (초안 상태).
     *
     * @param  array<string, mixed>  $data  검증된 페이로드
     */
    public function create(array $data, int $userId): Order
    {
        return $this->createMany([$data], $userId)[0];
    }

    /**
     * 여러 운행을 한 번에 등록한다 — 각 운행은 서로 묶이지 않는 독립(단일) 운행이 된다.
     *
     * 붙여넣은 문구 한 건에 여러 운행이 섞여 있을 때 쓴다. 셋트(그룹)로 묶지 않으므로
     * 각 운행이 마켓에서 따로 가져가진다. 중간에 실패하면 전부 등록되지 않는다.
     *
     * @param  array<int, array<string, mixed>>  $orders  검증된 페이로드 목록
     * @return array<int, Order>
     */
    public function createMany(array $orders, int $userId): array
    {
        return DB::transaction(function () use ($orders, $userId): array {
            $created = [];

            foreach ($orders as $orderData) {
                $orderData = $this->normalize($orderData);
                $order = Order::create($this->toAttributes($orderData, $userId));

                foreach ($orderData['line_items'] ?? [] as $lineItem) {
                    $order->lineItems()->create($lineItem);
                }

                $created[] = $order;
            }

            return $created;
        });
    }

    /**
     * 운행 복제 — 동일 내용을 초안 상태로 새로 만든다.
     */
    public function duplicate(Order $source, int $userId): Order
    {
        return DB::transaction(function () use ($source, $userId): Order {
            $copy = Order::create([
                ...$this->toAttributes([], $userId),
                'reservation_company' => $source->reservation_company,
                'customer_name' => $source->customer_name,
                'customer_phone' => $source->customer_phone,
                'reservation_channel' => $source->reservation_channel,
                'vehicle_type' => $source->vehicle_type,
                'service_type' => $source->service_type,
                'service_date' => $source->service_date,
                'service_time' => $source->service_time,
                'service_datetime' => $source->service_datetime,
                'pickup_location' => $source->pickup_location,
                'dropoff_location' => $source->dropoff_location,
                'flight_number' => $source->flight_number,
                'passenger_count' => $source->passenger_count,
                'luggage_count' => $source->luggage_count,
                'expected_revenue' => $source->expected_revenue,
                'amount_value' => $source->amount_value,
                'tags' => $source->tags,
            ]);

            foreach ($source->lineItems as $lineItem) {
                $copy->lineItems()->create($lineItem->only([
                    'service_date',
                    'service_time',
                    'scheduled_time',
                    'service_type',
                    'pickup_location',
                    'dropoff_location',
                    'flight_number',
                    'amount_text',
                    'amount_value',
                    'service_weekday',
                ]));
            }

            return $copy;
        });
    }

    /**
     * 셋트 운행 일괄 등록 — 여러 운행을 하나의 그룹으로 묶는다.
     *
     * @param  array<string, mixed>  $data  검증된 페이로드
     */
    public function createBatch(array $data, int $userId): OrderGroup
    {
        return DB::transaction(function () use ($data, $userId): OrderGroup {
            $group = OrderGroup::query()->create([
                'name' => $data['group_name'],
                'type' => '셋트',
            ]);

            foreach ($data['orders'] as $orderData) {
                $orderData = $this->normalize($orderData);
                $order = Order::create($this->toAttributes($orderData, $userId, $group->id));

                foreach ($orderData['line_items'] ?? [] as $lineItem) {
                    $order->lineItems()->create($lineItem);
                }
            }

            return $group;
        });
    }

    /**
     * 운행 정보 수정.
     *
     * @param  array<string, mixed>  $data  검증된 페이로드
     */
    public function update(Order $order, array $data): void
    {
        $order->update([
            'reservation_company' => $data['reservation_company'] ?? $order->reservation_company,
            'customer_name' => $data['customer_name'] ?? $order->customer_name,
            'customer_phone' => $data['customer_phone'] ?? $order->customer_phone,
            'reservation_channel' => $data['reservation_channel'] ?? $order->reservation_channel,
            'vehicle_type' => $data['vehicle_type'] ?? $order->vehicle_type,
            'service_type' => $data['service_type'] ?? $order->service_type,
            'service_date' => $data['service_date'] ?? $order->service_date,
            'service_time' => $data['service_time'] ?? $order->service_time,
            'service_datetime' => $data['service_datetime'] ?? $order->service_datetime,
            'pickup_location' => $data['pickup_location'] ?? $order->pickup_location,
            'dropoff_location' => $data['dropoff_location'] ?? $order->dropoff_location,
            'flight_number' => $data['flight_number'] ?? $order->flight_number,
            'passenger_count' => $data['passenger_count'] ?? $order->passenger_count,
            'luggage_count' => $data['luggage_count'] ?? $order->luggage_count,
            'expected_revenue' => $data['expected_revenue'] ?? $order->expected_revenue,
            'amount_value' => $data['expected_revenue'] ?? $order->amount_value,
            'tags' => $data['tags'] ?? $order->tags,
            'is_priority' => $data['is_priority'] ?? $order->is_priority,
        ]);

        if (array_key_exists('line_items', $data)) {
            $order->lineItems()->delete();

            foreach ($data['line_items'] as $lineItem) {
                $order->lineItems()->create($lineItem);
            }
        }
    }

    /**
     * 중국어로 들어온 지명·차량 표기를 저장 전에 한국어로 바꾼다.
     *
     * 위챗 모니터는 `广津`, `机场`, `小车` 처럼 중국어 표기를 그대로 보내온다.
     * 원본은 order_ingestions 에 남으므로 여기서는 변환만 한다.
     *
     * 지명이 아니라 운영 지시로 쓰이는 표기(예: `帮划客路`)는 태그로 옮긴다 — 값은 그대로 둔다.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        // 태그 표기는 변환 전 원문에서 찾는다 — 지명 변환으로 값이 바뀌면 놓칠 수 있다.
        // 유입 원문(original_summary)도 함께 본다: `秒结`처럼 지명 칸이 아니라 문구에만 있는 표기가 있다.
        $tagSources = [
            $data['pickup_location'] ?? null,
            $data['dropoff_location'] ?? null,
            $data['original_summary'] ?? null,
        ];

        $data = $this->normalizeLocation($data, 'pickup_location', OrderTerm::FIELD_PICKUP);
        $data = $this->normalizeLocation($data, 'dropoff_location', OrderTerm::FIELD_DROPOFF);

        if (array_key_exists('vehicle_type', $data)) {
            $data['vehicle_type'] = ChineseTextNormalizer::vehicleType($data['vehicle_type']);

            $this->termCollector->collect(OrderTerm::FIELD_VEHICLE, $data['vehicle_type']);
        }

        // 구분이 비어 있으면 노선에서 추정한다 (공항 쪽이 어디인지로 픽업/샌딩/시내가 갈린다)
        if (blank($data['service_type'] ?? null)) {
            $data['service_type'] = ServiceTypeInferrer::infer($data['pickup_location'] ?? null, $data['dropoff_location'] ?? null);
        }

        if (isset($data['line_items']) && is_array($data['line_items'])) {
            $data['line_items'] = array_map(function (array $lineItem) use (&$tagSources): array {
                $tagSources[] = $lineItem['pickup_location'] ?? null;
                $tagSources[] = $lineItem['dropoff_location'] ?? null;

                foreach (['pickup_location' => OrderTerm::FIELD_PICKUP, 'dropoff_location' => OrderTerm::FIELD_DROPOFF] as $key => $termField) {
                    if (! array_key_exists($key, $lineItem)) {
                        continue;
                    }

                    $lineItem[$key] = ChineseTextNormalizer::location($lineItem[$key]);

                    $this->termCollector->collect($termField, $lineItem[$key]);
                }

                if (blank($lineItem['service_type'] ?? null)) {
                    $lineItem['service_type'] = ServiceTypeInferrer::infer($lineItem['pickup_location'] ?? null, $lineItem['dropoff_location'] ?? null);
                }

                return $lineItem;
            }, $data['line_items']);
        }

        $derivedTags = ChineseTextNormalizer::tagsFor($tagSources);

        // 유입 파서가 붙이는 내부 태그(wechat-monitor·conf60)는 저장하지 않는다 —
        // 보낸 태그와 사전에서 뽑은 태그를 합친 뒤 같은 기준으로 걷어낸다.
        $tags = ChineseTextNormalizer::withoutInternalTags(
            array_values(array_unique([...($data['tags'] ?? []), ...$derivedTags])),
        );

        $data['tags'] = $tags === [] ? null : $tags;

        // `飞机马上降落`·`客人出来了`는 지금 손이 필요하다는 신호 — 긴급 배지로 올린다
        if (collect($derivedTags)->contains(ChineseTextNormalizer::isUrgentTag(...))) {
            $data['is_priority'] = true;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeLocation(array $data, string $key, string $termField): array
    {
        if (! array_key_exists($key, $data)) {
            return $data;
        }

        $data[$key] = ChineseTextNormalizer::location($data[$key]);

        // 사전에 없어 중국어가 남으면 용어 사전에 모아 관리자가 매핑할 수 있게 한다
        $this->termCollector->collect($termField, $data[$key]);

        return $data;
    }

    /**
     * 검증된 페이로드를 Order 생성 속성으로 변환한다.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function toAttributes(array $data, int $userId, ?int $groupId = null): array
    {
        return [
            'order_number' => Order::generateOrderNumber(),
            'group_id' => $groupId,
            'reservation_company' => $data['reservation_company'] ?? '직접예약',
            'customer_name' => $data['customer_name'] ?? '미지정',
            'customer_phone' => $data['customer_phone'] ?? null,
            'reservation_channel' => $data['reservation_channel'] ?? Order::CHANNEL_KAKAO,
            'group_type' => $groupId === null ? '단일' : '셋트',
            'vehicle_type' => $data['vehicle_type'] ?? null,
            'service_type' => $data['service_type'] ?? null,
            'service_date' => $data['service_date'] ?? null,
            'service_time' => $data['service_time'] ?? null,
            'service_datetime' => $data['service_datetime'] ?? null,
            'pickup_location' => $data['pickup_location'] ?? null,
            'dropoff_location' => $data['dropoff_location'] ?? null,
            'flight_number' => $data['flight_number'] ?? null,
            'passenger_count' => $data['passenger_count'] ?? 1,
            'luggage_count' => $data['luggage_count'] ?? null,
            'expected_revenue' => $data['expected_revenue'] ?? null,
            'amount_value' => $data['expected_revenue'] ?? null,
            'tags' => $data['tags'] ?? null,
            'order_type' => Order::TYPE_GENERAL,
            'status' => Order::STATUS_DRAFT,
            // 원문(original_summary)은 위챗 모니터만 싣는다 — 이 값이 있으면 파이프라인 유입이다
            'source' => filled($data['original_summary'] ?? null) ? Order::SOURCE_PIPELINE : Order::SOURCE_MANUAL,
            'is_priority' => $data['is_priority'] ?? false,
            'user_id' => $userId,
        ];
    }
}
