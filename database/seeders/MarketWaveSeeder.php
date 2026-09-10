<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 밀집 마켓 운행 시드 — 오늘·내일·모레, 약 3,000건 (날짜당 1,000건).
 *
 * 실행:
 *   php artisan db:seed --class=MarketWaveSeeder
 *
 * - 서비스 시간: 매일 05:00~23:50을 10분 슬롯으로 촘촘하게 채운다.
 * - 요금: 50,000~100,000원 (5,000원 단위)
 * - 공항(인천 T1/T2·김포)·항공편(항공사 코드 다양)·차량 종류 다양, 탑승 인원 1~8명
 * - 기존 데이터는 지우지 않고, 이 셰더로 넣은 이전 배치(request_label '밀집')만 교체한다.
 */
class MarketWaveSeeder extends Seeder
{
    private const BATCH_LABEL = '밀집';

    public function run(): void
    {
        // 같은 셰더의 이전 배치만 제거 (실제 운행·데모 데이터 보존)
        Order::query()->where('request_label', self::BATCH_LABEL)->delete();

        $owner = User::query()->where('name', 'Market Operator')->first()
            ?? User::query()->where('role', User::ROLE_OPERATOR)->first()
            ?? User::query()->where('role', User::ROLE_ADMIN)->first()
            ?? User::query()->orderBy('id')->first();

        $airports = ['인천공항 T1', '인천공항 T2', '김포공항 국제선'];
        $cityZones = [
            '서울 중구', '서울 강남구', '서울 서초구', '서울 송파구', '서울 종로구',
            '서울 마포구', '서울 영등포구', '서울 강서구', '서울 용산구',
            '인천 연수구', '인천 부평구',
            '경기 수원시', '경기 성남시 분당구', '경기 고양시 일산서구', '경기 부천시',
        ];
        // 차량 종류 — [표시명, 좌석(탑승 인원 상한)]
        $vehicles = [
            ['그랜저', 4], ['쏘나타', 4], ['쏘렌토', 5], ['투싼', 5], ['GV80', 7],
            ['카니발', 7], ['카니발 리무진', 9], ['스타리아', 9], ['그랜드 스타렉스', 12],
        ];
        $airlines = ['KE', 'OZ', '7C', 'TW', 'ZE', 'BX', 'LJ', 'RS', 'MU', 'CA', 'NH', 'JL', 'CZ'];
        $customers = [
            '김민수', '이지은', '박서준', '최수아', '정다온', '강지훈', '조수아',
            '윤도현', '임예진', '한지우', '오세아', '배준호', '김도윤',
            '왕하오', '장웨이', '리안', '陳小美', '王磊', '이토 유키', '케빈 리',
        ];
        $reservationCompanies = array_values(Order::reservationCompanyOptions());
        $reservationChannels = array_keys(Order::reservationChannelOptions());

        // 10분 슬롯 (05:00 ~ 23:50) — 날짜당 1,000건을 순환해 촘촘하게 분산
        $slotMinutes = [];
        for ($minute = 5 * 60; $minute <= 23 * 60 + 50; $minute += 10) {
            $slotMinutes[] = $minute;
        }
        $slotCount = count($slotMinutes);

        $now = now('Asia/Seoul');
        $todayDate = $now->format('Y-m-d');
        $perDay = 1000;
        $seq = 0;
        $rows = [];

        for ($day = 0; $day < 3; $day++) {
            $date = $now->copy()->addDays($day)->format('Y-m-d');

            for ($j = 0; $j < $perDay; $j++) {
                // 서로소 스트라이드로 하루 전체 슬롯에 고르게 분산 (밀집·균일)
                $slotMinute = $slotMinutes[($j * 137) % $slotCount];

                // 오늘의 과거 슬롯은 '지금 이후 남은 슬롯' 중 하나로 재배치해
                // 한 시각으로 몰리지 않게 분산한다 (23:50 이내)
                if ($date === $todayDate) {
                    $nowMinutes = $now->hour * 60 + $now->minute;

                    if ($slotMinute < $nowMinutes) {
                        $nowIdx = intdiv($nowMinutes - 300, 10); // 05:00=0 기준
                        $firstFuture = max(0, $nowIdx + 1);

                        // 남은 슬롯이 없으면(23:50 직후) 재배치 없이 원 슬롯 유지
                        $slotMinute = $firstFuture < $slotCount
                            ? $slotMinutes[random_int($firstFuture, $slotCount - 1)]
                            : $slotMinute;
                    }
                }

                $hour = intdiv($slotMinute, 60);
                $minute = $slotMinute % 60;
                $serviceTime = sprintf('%02d:%02d', $hour, $minute);

                // 노선 방향 — 공항랜딩/공항샌딩 중심 + 시내 이동 일부
                $roll = random_int(0, 99);
                if ($roll < 35) {
                    $pickup = $airports[array_rand($airports)];
                    $dropoff = $cityZones[array_rand($cityZones)];
                    $isAirport = true;
                } elseif ($roll < 70) {
                    $pickup = $cityZones[array_rand($cityZones)];
                    $dropoff = $airports[array_rand($airports)];
                    $isAirport = true;
                } else {
                    [$pickup, $dropoff] = array_rand($cityZones, 2);
                    $isAirport = false;
                }

                if ($isAirport) {
                    $duration = random_int(40, 80);
                    $distance = random_int(20, 65);
                } else {
                    $duration = random_int(20, 60);
                    $distance = random_int(5, 35);
                }

                [$vehicleName, $seats] = $vehicles[array_rand($vehicles)];
                // 좌석 안에서 1~8명 — 대형 차량에서 8인까지 나온다
                $passengers = random_int(1, min(8, $seats));
                $amount = random_int(10, 20) * 5000; // 50,000~100,000원

                $status = random_int(1, 100) <= 7
                    ? Order::STATUS_TRADING
                    : Order::STATUS_PUBLISHED;

                $seq++;
                $rows[] = [
                    'order_number' => 'MW-'.str_replace('-', '', $date).'-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT),
                    'request_label' => self::BATCH_LABEL,
                    'user_id' => $owner->id,
                    'status' => $status,
                    'pickup_location' => $pickup,
                    'dropoff_location' => $dropoff,
                    'service_date' => $date,
                    'service_time' => $serviceTime,
                    'service_datetime' => "{$date} {$serviceTime}:00",
                    'scheduled_at' => "{$date} {$serviceTime}:00",
                    'estimated_duration_minutes' => $duration,
                    'distance_km' => $distance,
                    'expected_revenue' => $amount,
                    'amount_value' => $amount,
                    'passenger_count' => $passengers,
                    'luggage_count' => random_int(0, min(4, (int) ceil($passengers / 2))),
                    'vehicle_type' => $vehicleName,
                    'customer_name' => $customers[$seq % count($customers)],
                    'reservation_company' => $reservationCompanies[$seq % count($reservationCompanies)],
                    'reservation_channel' => $reservationChannels[$seq % count($reservationChannels)],
                    'group_type' => '단일',
                    'order_type' => $isAirport ? Order::TYPE_AIRPORT : Order::TYPE_GENERAL,
                    'service_type' => mb_strpos((string) $pickup, '공항') !== false
                        ? 'landing'
                        : (mb_strpos((string) $dropoff, '공항') !== false ? 'sending' : 'pickup'),
                    'flight_number' => $isAirport && random_int(1, 100) <= 85
                        ? $airlines[array_rand($airlines)].(100 + random_int(0, 8999))
                        : null,
                    'created_at' => $now->copy()->subMinutes(random_int(5, 4320)),
                    'updated_at' => $now,
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

        // 검증 요약 출력
        $summary = Order::query()
            ->where('request_label', self::BATCH_LABEL)
            ->selectRaw('count(*) as cnt')
            ->selectRaw('min(expected_revenue) as min_amt, max(expected_revenue) as max_amt')
            ->selectRaw('min(passenger_count) as min_pax, max(passenger_count) as max_pax')
            ->first();

        $byDate = Order::query()
            ->where('request_label', self::BATCH_LABEL)
            ->selectRaw('service_date, count(*) as cnt')
            ->groupBy('service_date')
            ->pluck('cnt', 'service_date');

        $byType = Order::query()
            ->where('request_label', self::BATCH_LABEL)
            ->selectRaw('service_type, count(*) as cnt')
            ->groupBy('service_type')
            ->pluck('cnt', 'service_type');

        $byFlight = Order::query()
            ->where('request_label', self::BATCH_LABEL)
            ->whereNotNull('flight_number')
            ->count();

        $this->command->info("MarketWave inserted: {$seq}건 / 등록자: {$owner->name}");
        $this->command->info('요약: '.json_encode([
            'count' => $summary?->cnt,
            '금액범위' => [$summary?->min_amt, $summary?->max_amt],
            '인원범위' => [$summary?->min_pax, $summary?->max_pax],
            '날짜별' => $byDate,
            '유형별' => $byType,
            '항공편있음' => $byFlight,
        ], JSON_UNESCAPED_UNICODE));
    }
}
