<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 마켓을 채우는 랜덤 운행 데모 데이터.
 * test 계정(데모 뷰어)을 제외한 사용자를 작성자로 삼아 마켓에 노출되게 한다.
 */
class RandomOrderSeeder extends Seeder
{
    public function run(): void
    {
        // 재실행 시 이전 랜덤 배치만 제거 (데모·실데이터는 보존)
        Order::query()->where('request_label', '랜덤')->delete();

        $authors = User::query()
            ->whereNotIn('email', ['test@example.com'])
            ->whereIn('role', ['Operator', 'Admin', 'Driver'])
            ->pluck('id')
            ->all();

        if ($authors === []) {
            $authors = [User::query()->value('id') ?? 1];
        }

        $pickups = ['명동', '강남', '홍대', '잠실', '마포', '용산구', '동대문구', '종로', '서울역', '강서구', '송파구', '서초구', '영등포', '은평구', '광진구'];
        $cities = ['명동', '강남', '홍대', '잠실', '마포', '용산구', '동대문구', '종로', '서울역', '강서구', '송파구', '서초구', '영등포', '은평구', '광진구'];
        $vehicles = ['카니발', '스타리아', '더뉴카니발', '소형 승용차'];
        $flights = ['KE101', 'KE217', 'OZ102', 'OZ352', '7C1101', 'TW203', 'JL93', 'NH862', 'MU5051', 'CZ313'];
        $names = ['김민수', '이지은', '박서준', '최수아', '정다온', '왕하오', '장웨이', '리안', '陳小美', '王磊'];
        $reservations = ['직접예약', 'KLOOK', '카카오T', '네이버예약', '마이리얼트립'];
        $statuses = ['published', 'published', 'published', 'published', 'trading'];

        for ($i = 0; $i < 25; $i++) {
            $serviceType = ['pickup', 'sending', 'landing'][random_int(0, 2)];
            $city = $cities[array_rand($cities)];

            // 샌딩: 도시 → 인천 / 픽업·랜딩: 인천 → 도시
            if ($serviceType === 'sending') {
                $pickup = $pickups[array_rand($pickups)];
                $dropoff = '인천';
            } else {
                $pickup = '인천';
                $dropoff = $city;
            }

            $date = now()->addDays(random_int(0, 5))->format('Y-m-d');
            $time = sprintf('%02d:%02d', random_int(5, 22), random_int(0, 59));
            $amount = random_int(3, 40) * 10000;

            // 등록 시각: 일부는 1시간 이내(N 배지 표시용), 나머지는 1~3일 전
            $minutesAgo = random_int(0, 2) === 0
                ? random_int(3, 55)
                : random_int(60, 3 * 24 * 60);
            $createdAt = now()->subMinutes($minutesAgo);

            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'request_label' => '랜덤',
                'reservation_company' => $reservations[array_rand($reservations)],
                'customer_name' => $names[array_rand($names)],
                'reservation_channel' => Order::CHANNEL_KAKAO,
                'group_type' => '단일',
                'vehicle_type' => $vehicles[array_rand($vehicles)],
                'service_type' => $serviceType,
                'service_date' => $date,
                'service_time' => $time,
                'service_datetime' => "{$date} {$time}:00",
                'pickup_location' => $pickup,
                'dropoff_location' => $dropoff,
                'flight_number' => in_array($serviceType, ['pickup', 'landing'], true) ? $flights[array_rand($flights)] : null,
                'passenger_count' => random_int(1, 9),
                'luggage_count' => random_int(0, 5),
                'expected_revenue' => $amount,
                'amount_value' => $amount,
                'order_type' => Order::TYPE_GENERAL,
                'status' => $statuses[array_rand($statuses)],
                'user_id' => $authors[array_rand($authors)],
            ]);

            $order->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
        }

        $this->command?->info('랜덤 운행 25건을 생성했습니다.');
    }
}
