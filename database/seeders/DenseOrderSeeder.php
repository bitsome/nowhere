<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 밀집 마켓 운행 시드 — 오늘(현재)부터 내일까지 15분 간격, 약 2,000건.
 *
 * 실행:
 *   php artisan db:seed --class=DenseOrderSeeder
 *
 * 기존 운행(orders·line_items·groups·offers)을 먼저 지우고 다시 만든다.
 * 모든 운행은 마켓 등록자(Market Operator)가 공개(Published) 상태로 올린다.
 */
class DenseOrderSeeder extends Seeder
{
    public function run(): void
    {
        // 기존 운행 데이터를 모두 지운다 (연관 데이터 포함).
        Order::query()->delete();
        DB::table('order_line_items')->delete();
        DB::table('order_offers')->delete();
        OrderGroup::query()->delete();

        $marketUser = User::query()->where('name', 'Market Operator')->first()
            ?? User::query()->where('role', User::ROLE_OPERATOR)->first()
            ?? User::query()->orderBy('id')->first();

        // [출발지, 도착지, 소요(분), 거리(km), 요금(원), 운행종류]
        $routes = [
            ['서울 중구', '인천공항 T1', 60, 58, 120000, 'airport'],
            ['서울 강남구', '인천공항 T1', 60, 62, 130000, 'airport'],
            ['서울 강남구', '김포공항 국내선', 40, 25, 55000, 'airport'],
            ['서울 서초구', '인천공항 T1', 60, 55, 115000, 'airport'],
            ['서울 송파구', '인천공항 T2', 55, 58, 120000, 'airport'],
            ['서울 종로구', '김포공항 국제선', 35, 18, 45000, 'airport'],
            ['서울 마포구', '경기 고양시 일산', 35, 16, 38000, 'general'],
            ['인천공항 T1', '서울 강남구', 60, 62, 130000, 'airport'],
            ['인천공항 T2', '서울 마포구', 55, 50, 110000, 'airport'],
            ['인천공항 T1', '서울 중구', 60, 58, 120000, 'airport'],
            ['인천공항 T2', '경기 성남시 분당구', 65, 55, 115000, 'airport'],
            ['김포공항 국내선', '서울 강서구', 30, 12, 35000, 'airport'],
            ['경기 수원시', '서울 잠실', 40, 32, 65000, 'general'],
            ['서울 영등포구', '경기 부천시', 25, 12, 28000, 'general'],
        ];

        // 오늘 다음 15분 슬롯부터 내일 23:45까지 (과거 시간대 제외)
        $now = now('Asia/Seoul');

        $slot = $now->copy()->startOfHour();
        while ($slot->lt($now)) {
            $slot->addMinutes(15);
        }

        $end = $slot->copy()->addDay()->endOfDay();

        $reservationCompanies = array_values(Order::reservationCompanyOptions());
        $reservationChannels = array_keys(Order::reservationChannelOptions());
        $vehicles = ['카니발', '스타리아', '그랜저'];
        $customers = ['박서연', '이준호', '최민서', '정하은', '강지훈', '조수아', '윤도현', '임예진', '한지우', '오세아', '배준호', '김도윤'];
        $airlines = ['KE', 'OZ', '7C', 'TW', 'ZE', 'BX'];

        $seq = 0;
        $rows = [];

        for (; $slot->lte($end); $slot->addMinutes(15)) {
            foreach ($routes as $route) {
                $seq++;

                $rows[] = [
                    'order_number' => 'DEN-'.$slot->format('m-d').'-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
                    'user_id' => $marketUser->id,
                    'status' => Order::STATUS_PUBLISHED,
                    'pickup_location' => $route[0],
                    'dropoff_location' => $route[1],
                    'service_date' => $slot->format('Y-m-d'),
                    'service_time' => $slot->format('H:i'),
                    'scheduled_at' => $slot->format('Y-m-d H:i').':00',
                    'estimated_duration_minutes' => $route[2],
                    'distance_km' => $route[3],
                    'expected_revenue' => $route[4],
                    'customer_name' => $customers[$seq % count($customers)],
                    'reservation_company' => $reservationCompanies[$seq % count($reservationCompanies)],
                    'reservation_channel' => $reservationChannels[$seq % count($reservationChannels)],
                    'passenger_count' => ($seq % 6) + 1,
                    'vehicle_type' => $vehicles[$seq % count($vehicles)],
                    'service_type' => str_contains($route[0], '공항') ? 'landing' : (str_contains($route[1], '공항') ? 'sending' : 'pickup'),
                    'order_type' => $route[5] === 'airport' ? Order::TYPE_AIRPORT : Order::TYPE_GENERAL,
                    'flight_number' => $route[5] === 'airport'
                        ? $airlines[$seq % count($airlines)].(100 + ($seq % 900))
                        : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (count($rows) >= 500) {
                    Order::insert($rows);
                    $rows = [];
                }
            }
        }

        if ($rows !== []) {
            Order::insert($rows);
        }

        $this->command->info("Dense orders inserted: {$seq}건 (등록자: {$marketUser->name})");
    }
}
