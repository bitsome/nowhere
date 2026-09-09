<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\OrderGroup;
use Illuminate\Support\Facades\DB;

/**
 * 운행 생성/복제/일괄 등록/수정 — 트랜잭션 단위로 순수 도메인 로직만 담당한다.
 */
class OrderCreator
{
    /**
     * 단일 운행 등록 (초안 상태).
     *
     * @param  array<string, mixed>  $data  검증된 페이로드
     */
    public function create(array $data, int $userId): Order
    {
        return DB::transaction(function () use ($data, $userId): Order {
            $order = Order::create($this->toAttributes($data, $userId));

            foreach ($data['line_items'] ?? [] as $lineItem) {
                $order->lineItems()->create($lineItem);
            }

            return $order;
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
            'is_priority' => $data['is_priority'] ?? false,
            'user_id' => $userId,
        ];
    }
}
