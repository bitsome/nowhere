<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Order List 화면 검증용 SET(셋트) 시연 데이터를 입력한다.
 */
class OrderSetDemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->first() ?? User::factory()->create();

        $group = OrderGroup::query()->create([
            'name' => 'KLOOK 8월 셋트',
            'type' => '셋트',
        ]);

        $orders = [
            [
                'customer_name' => '김민수',
                'reservation_company' => 'KLOOK',
                'reservation_channel' => Order::CHANNEL_ONLINE_PARTNER,
                'service_type' => 'pickup',
                'service_date' => '2026-08-10',
                'service_time' => '09:00',
                'pickup_location' => '인천공항 T1',
                'dropoff_location' => '명동',
                'passenger_count' => 4,
                'status' => Order::STATUS_ACCEPTED,
            ],
            [
                'customer_name' => '김민수',
                'reservation_company' => 'KLOOK',
                'reservation_channel' => Order::CHANNEL_ONLINE_PARTNER,
                'service_type' => '서울투어',
                'service_date' => '2026-08-11',
                'service_time' => '10:00',
                'pickup_location' => '명동 호텔',
                'dropoff_location' => '경복궁 일대',
                'passenger_count' => 4,
                'status' => Order::STATUS_ACCEPTED,
            ],
            [
                'customer_name' => '김민수',
                'reservation_company' => 'KLOOK',
                'reservation_channel' => Order::CHANNEL_ONLINE_PARTNER,
                'service_type' => 'sending',
                'service_date' => '2026-08-12',
                'service_time' => '17:00',
                'pickup_location' => '명동 호텔',
                'dropoff_location' => '인천공항 T2',
                'passenger_count' => 4,
                'status' => Order::STATUS_TRADING,
            ],
        ];

        foreach ($orders as $order) {
            Order::factory()->create([
                ...$order,
                'group_id' => $group->id,
                'user_id' => $user->id,
            ]);
        }
    }
}
