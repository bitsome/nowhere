<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class OrderDemoSeeder extends Seeder
{
    public function run(): void
    {
        // 기존 오더 데이터를 모두 지운다. (line_items는 cascade 삭제)
        Order::query()->delete();
        OrderGroup::query()->delete();

        $user = User::query()->where('email', 'test@example.com')->first()
            ?? User::factory()->create([
                'email' => 'test@example.com',
                'name' => 'Test User',
                'password' => bcrypt('password'),
            ]);

        // 마켓에 오더를 올리는 등록자 — 테스트 계정은 이 계정의 오더를 가져온다.
        $marketUser = User::query()->where('email', 'market@example.com')->first()
            ?? User::factory()->create([
                'email' => 'market@example.com',
                'name' => 'Market Operator',
            ]);

        $singleOrders = [
            [
                'reservation_company' => '직접예약',
                'customer_name' => '김도윤',
                'reservation_channel' => Order::CHANNEL_KAKAO,
                'group_type' => '단일',
                'vehicle_type' => '카니발',
                'service_type' => 'pickup',
                'service_date' => '2026-08-06',
                'service_time' => '09:20',
                'pickup_location' => '인천공항 T1',
                'dropoff_location' => '서울 강남구',
                'flight_number' => 'KE101',
                'passenger_count' => 4,
                'luggage_count' => 2,
                'amount_value' => 18000,
                'expected_revenue' => 18000,
                'request_label' => '8월6일 오전 픽업',
                'scheduled_at' => '2026-08-06 09:20:00',
                'order_type' => Order::TYPE_AIRPORT,
                'status' => Order::STATUS_PUBLISHED,
            ],
            [
                'reservation_company' => 'KLOOK',
                'customer_name' => '이하린',
                'reservation_channel' => Order::CHANNEL_ONLINE_PARTNER,
                'group_type' => '단일',
                'vehicle_type' => '스타리아 7인승',
                'service_type' => 'sending',
                'service_date' => '2026-08-06',
                'service_time' => '10:15',
                'pickup_location' => '서울 마포구',
                'dropoff_location' => '인천공항 T2',
                'flight_number' => 'OZ702',
                'passenger_count' => 2,
                'luggage_count' => 1,
                'amount_value' => 20000,
                'expected_revenue' => 20000,
                'request_label' => '8월6일 샌딩',
                'scheduled_at' => '2026-08-06 10:15:00',
                'order_type' => Order::TYPE_AIRPORT,
                'status' => Order::STATUS_TRADING,
            ],
            [
                'reservation_company' => 'KKDAY',
                'customer_name' => '박현우',
                'reservation_channel' => Order::CHANNEL_PHONE,
                'group_type' => '단일',
                'vehicle_type' => '스타리아 9인승',
                'service_type' => 'pickup',
                'service_date' => '2026-08-06',
                'service_time' => '11:40',
                'pickup_location' => '김포공항',
                'dropoff_location' => '서울 용산구',
                'flight_number' => 'LJ310',
                'passenger_count' => 5,
                'luggage_count' => 2,
                'amount_value' => 23000,
                'expected_revenue' => 23000,
                'request_label' => '8월6일 김포 픽업',
                'scheduled_at' => '2026-08-06 11:40:00',
                'order_type' => Order::TYPE_AIRPORT,
                'status' => Order::STATUS_ACCEPTANCE_PENDING,
            ],
            [
                'reservation_company' => 'Trip.com',
                'customer_name' => '정수빈',
                'reservation_channel' => Order::CHANNEL_KAKAO,
                'group_type' => '단일',
                'vehicle_type' => '카니발',
                'service_type' => 'sending',
                'service_date' => '2026-08-06',
                'service_time' => '14:30',
                'pickup_location' => '서울 송파구',
                'dropoff_location' => '김포공항',
                'flight_number' => 'ZE201',
                'passenger_count' => 3,
                'luggage_count' => 2,
                'amount_value' => 21000,
                'expected_revenue' => 21000,
                'request_label' => '8월6일 오후 샌딩',
                'scheduled_at' => '2026-08-06 14:30:00',
                'order_type' => Order::TYPE_AIRPORT,
                'status' => Order::STATUS_PUBLISHED,
            ],
            [
                'reservation_company' => '직접예약',
                'customer_name' => '최예린',
                'reservation_channel' => Order::CHANNEL_WALK_IN,
                'group_type' => '단일',
                'vehicle_type' => '스타리아 11인승',
                'service_type' => 'pickup',
                'service_date' => '2026-08-07',
                'service_time' => '07:00',
                'pickup_location' => '인천공항 T1',
                'dropoff_location' => '서울 중구',
                'flight_number' => 'KE207',
                'passenger_count' => 8,
                'luggage_count' => 4,
                'amount_value' => 32000,
                'expected_revenue' => 32000,
                'request_label' => '8월7일 대형 픽업',
                'scheduled_at' => '2026-08-07 07:00:00',
                'order_type' => Order::TYPE_AIRPORT,
                'status' => Order::STATUS_PUBLISHED,
            ],
            [
                'reservation_company' => 'KLOOK',
                'customer_name' => '강민재',
                'reservation_channel' => Order::CHANNEL_ONLINE_PARTNER,
                'group_type' => '단일',
                'vehicle_type' => '카니발',
                'service_type' => 'sending',
                'service_date' => '2026-08-07',
                'service_time' => '09:45',
                'pickup_location' => '서울 성동구',
                'dropoff_location' => '인천공항 T1',
                'flight_number' => 'KE630',
                'passenger_count' => 4,
                'luggage_count' => 1,
                'amount_value' => 19000,
                'expected_revenue' => 19000,
                'request_label' => '8월7일 오전 샌딩',
                'scheduled_at' => '2026-08-07 09:45:00',
                'order_type' => Order::TYPE_AIRPORT,
                'status' => Order::STATUS_TRADING,
            ],
            [
                'reservation_company' => 'KKDAY',
                'customer_name' => '윤지후',
                'reservation_channel' => Order::CHANNEL_PHONE,
                'group_type' => '단일',
                'vehicle_type' => '스타리아 7인승',
                'service_type' => 'pickup',
                'service_date' => '2026-08-07',
                'service_time' => '13:20',
                'pickup_location' => '김포공항',
                'dropoff_location' => '서울 서초구',
                'flight_number' => 'TW715',
                'passenger_count' => 2,
                'luggage_count' => 2,
                'amount_value' => 21000,
                'expected_revenue' => 21000,
                'request_label' => '8월7일 오후 픽업',
                'scheduled_at' => '2026-08-07 13:20:00',
                'order_type' => Order::TYPE_AIRPORT,
                'status' => Order::STATUS_ACCEPTED,
            ],
            [
                'reservation_company' => 'Trip.com',
                'customer_name' => '오세린',
                'reservation_channel' => Order::CHANNEL_KAKAO,
                'group_type' => '단일',
                'vehicle_type' => '스타리아 9인승',
                'service_type' => 'sending',
                'service_date' => '2026-08-07',
                'service_time' => '17:50',
                'pickup_location' => '서울 강서구',
                'dropoff_location' => '인천공항 T2',
                'flight_number' => 'BX880',
                'passenger_count' => 6,
                'luggage_count' => 3,
                'amount_value' => 28000,
                'expected_revenue' => 28000,
                'request_label' => '8월7일 저녁 샌딩',
                'scheduled_at' => '2026-08-07 17:50:00',
                'order_type' => Order::TYPE_AIRPORT,
                'status' => Order::STATUS_DRIVING,
            ],
            [
                'reservation_company' => '직접예약',
                'customer_name' => '서지안',
                'reservation_channel' => Order::CHANNEL_PHONE,
                'group_type' => '단일',
                'vehicle_type' => '카니발',
                'service_type' => 'pickup',
                'service_date' => '2026-08-08',
                'service_time' => '08:10',
                'pickup_location' => '인천공항 T2',
                'dropoff_location' => '서울 종로구',
                'flight_number' => 'CX410',
                'passenger_count' => 3,
                'luggage_count' => 2,
                'amount_value' => 22000,
                'expected_revenue' => 22000,
                'request_label' => '8월8일 오전 픽업',
                'scheduled_at' => '2026-08-08 08:10:00',
                'order_type' => Order::TYPE_AIRPORT,
                'status' => Order::STATUS_PUBLISHED,
            ],
            [
                'reservation_company' => 'KLOOK',
                'customer_name' => '한시우',
                'reservation_channel' => Order::CHANNEL_ONLINE_PARTNER,
                'group_type' => '단일',
                'vehicle_type' => '스타리아 11인승',
                'service_type' => 'sending',
                'service_date' => '2026-08-08',
                'service_time' => '12:00',
                'pickup_location' => '서울 강남구',
                'dropoff_location' => '김포공항',
                'flight_number' => 'TW731',
                'passenger_count' => 7,
                'luggage_count' => 4,
                'amount_value' => 33000,
                'expected_revenue' => 33000,
                'request_label' => '8월8일 단체 샌딩',
                'scheduled_at' => '2026-08-08 12:00:00',
                'order_type' => Order::TYPE_AIRPORT,
                'status' => Order::STATUS_TRADING,
            ],
            [
                'reservation_company' => 'KKDAY',
                'customer_name' => '노유진',
                'reservation_channel' => Order::CHANNEL_KAKAO,
                'group_type' => '단일',
                'vehicle_type' => '스타리아 7인승',
                'service_type' => 'pickup',
                'service_date' => '2026-08-05',
                'service_time' => '16:40',
                'pickup_location' => '김포공항',
                'dropoff_location' => '서울 동작구',
                'flight_number' => 'RS500',
                'passenger_count' => 2,
                'luggage_count' => 1,
                'amount_value' => 19000,
                'expected_revenue' => 19000,
                'request_label' => '8월5일 픽업',
                'scheduled_at' => '2026-08-05 16:40:00',
                'order_type' => Order::TYPE_AIRPORT,
                'status' => Order::STATUS_COMPLETED,
            ],
            [
                'reservation_company' => '직접예약',
                'customer_name' => '문태오',
                'reservation_channel' => Order::CHANNEL_WALK_IN,
                'group_type' => '단일',
                'vehicle_type' => '카니발',
                'service_type' => 'sending',
                'service_date' => '2026-08-04',
                'service_time' => '10:30',
                'pickup_location' => '서울 광진구',
                'dropoff_location' => '인천공항 T1',
                'flight_number' => 'VN410',
                'passenger_count' => 4,
                'luggage_count' => 2,
                'amount_value' => 20000,
                'expected_revenue' => 20000,
                'request_label' => '8월4일 샌딩',
                'scheduled_at' => '2026-08-04 10:30:00',
                'order_type' => Order::TYPE_AIRPORT,
                'status' => Order::STATUS_SETTLED,
            ],
            [
                'reservation_company' => 'Trip.com',
                'customer_name' => '배준호',
                'reservation_channel' => Order::CHANNEL_PHONE,
                'group_type' => '단일',
                'vehicle_type' => '스타리아 9인승',
                'service_type' => 'pickup',
                'service_date' => '2026-08-03',
                'service_time' => '15:00',
                'pickup_location' => '인천공항 T1',
                'dropoff_location' => '서울 영등포구',
                'flight_number' => 'NH850',
                'passenger_count' => 5,
                'luggage_count' => 3,
                'amount_value' => 24000,
                'expected_revenue' => 24000,
                'request_label' => '8월3일 픽업',
                'scheduled_at' => '2026-08-03 15:00:00',
                'order_type' => Order::TYPE_AIRPORT,
                'status' => Order::STATUS_CANCELLED,
            ],
        ];

        foreach ($singleOrders as $singleOrder) {
            Order::factory()->create([
                ...$singleOrder,
                'user_id' => $this->ownerFor($singleOrder['status'], $user, $marketUser)->id,
            ])->lineItems()->create($this->buildLineItem($singleOrder));
        }

        $setOrders = [
            [
                'group_name' => '8월 6일 공항 셋트',
                'customer_name' => '박서준',
                'reservation_company' => 'KKDAY',
                'reservation_channel' => Order::CHANNEL_ONLINE_PARTNER,
                'vehicle_type' => '스타리아 9인승',
                'status' => Order::STATUS_PUBLISHED,
                'orders' => [
                    [
                        'service_type' => 'pickup',
                        'service_date' => '2026-08-06',
                        'service_time' => '08:30',
                        'pickup_location' => '인천공항 T1',
                        'dropoff_location' => '서울 중구',
                        'flight_number' => 'KE150',
                        'passenger_count' => 3,
                        'luggage_count' => 1,
                        'amount_value' => 22000,
                        'expected_revenue' => 22000,
                        'scheduled_at' => '2026-08-06 08:30:00',
                    ],
                    [
                        'service_type' => 'sending',
                        'service_date' => '2026-08-06',
                        'service_time' => '13:10',
                        'pickup_location' => '서울 용산구',
                        'dropoff_location' => '인천공항 T1',
                        'flight_number' => '7C220',
                        'passenger_count' => 2,
                        'luggage_count' => 1,
                        'amount_value' => 21000,
                        'expected_revenue' => 21000,
                        'scheduled_at' => '2026-08-06 13:10:00',
                    ],
                    [
                        'service_type' => 'pickup',
                        'service_date' => '2026-08-06',
                        'service_time' => '18:40',
                        'pickup_location' => '김포공항',
                        'dropoff_location' => '서울 송파구',
                        'flight_number' => 'LJ305',
                        'passenger_count' => 4,
                        'luggage_count' => 2,
                        'amount_value' => 21000,
                        'expected_revenue' => 21000,
                        'scheduled_at' => '2026-08-06 18:40:00',
                    ],
                ],
            ],
            [
                'group_name' => '8월 7일 비즈니스 셋트',
                'customer_name' => '장현서',
                'reservation_company' => 'Trip.com',
                'reservation_channel' => Order::CHANNEL_PHONE,
                'vehicle_type' => '스타리아 11인승',
                'status' => Order::STATUS_TRADING,
                'orders' => [
                    [
                        'service_type' => 'pickup',
                        'service_date' => '2026-08-07',
                        'service_time' => '07:40',
                        'pickup_location' => '인천공항 T2',
                        'dropoff_location' => '서울 서초구',
                        'flight_number' => 'JL954',
                        'passenger_count' => 6,
                        'luggage_count' => 4,
                        'amount_value' => 30000,
                        'expected_revenue' => 30000,
                        'scheduled_at' => '2026-08-07 07:40:00',
                    ],
                    [
                        'service_type' => 'sending',
                        'service_date' => '2026-08-07',
                        'service_time' => '12:20',
                        'pickup_location' => '서울 강남구',
                        'dropoff_location' => '인천공항 T1',
                        'flight_number' => 'KE648',
                        'passenger_count' => 5,
                        'luggage_count' => 3,
                        'amount_value' => 28000,
                        'expected_revenue' => 28000,
                        'scheduled_at' => '2026-08-07 12:20:00',
                    ],
                    [
                        'service_type' => 'pickup',
                        'service_date' => '2026-08-07',
                        'service_time' => '19:30',
                        'pickup_location' => '인천공항 T1',
                        'dropoff_location' => '서울 송파구',
                        'flight_number' => 'PR468',
                        'passenger_count' => 4,
                        'luggage_count' => 2,
                        'amount_value' => 29000,
                        'expected_revenue' => 29000,
                        'scheduled_at' => '2026-08-07 19:30:00',
                    ],
                ],
            ],
            [
                'group_name' => '8월 8일 패밀리 셋트',
                'customer_name' => '문서연',
                'reservation_company' => 'KLOOK',
                'reservation_channel' => Order::CHANNEL_ONLINE_PARTNER,
                'vehicle_type' => '카니발',
                'status' => Order::STATUS_ACCEPTED,
                'orders' => [
                    [
                        'service_type' => 'pickup',
                        'service_date' => '2026-08-08',
                        'service_time' => '09:10',
                        'pickup_location' => '인천공항 T1',
                        'dropoff_location' => '서울 종로구',
                        'flight_number' => 'CX418',
                        'passenger_count' => 4,
                        'luggage_count' => 3,
                        'amount_value' => 23000,
                        'expected_revenue' => 23000,
                        'scheduled_at' => '2026-08-08 09:10:00',
                    ],
                    [
                        'service_type' => 'sending',
                        'service_date' => '2026-08-08',
                        'service_time' => '14:00',
                        'pickup_location' => '서울 중구',
                        'dropoff_location' => '김포공항',
                        'flight_number' => 'ZE552',
                        'passenger_count' => 4,
                        'luggage_count' => 2,
                        'amount_value' => 21000,
                        'expected_revenue' => 21000,
                        'scheduled_at' => '2026-08-08 14:00:00',
                    ],
                    [
                        'service_type' => 'pickup',
                        'service_date' => '2026-08-08',
                        'service_time' => '17:20',
                        'pickup_location' => '김포공항',
                        'dropoff_location' => '서울 강남구',
                        'flight_number' => 'TW726',
                        'passenger_count' => 3,
                        'luggage_count' => 2,
                        'amount_value' => 22000,
                        'expected_revenue' => 22000,
                        'scheduled_at' => '2026-08-08 17:20:00',
                    ],
                ],
            ],
        ];

        foreach ($setOrders as $setData) {
            $memberOrders = $setData['orders'];
            unset($setData['orders']);

            $group = OrderGroup::query()->create([
                'name' => $setData['group_name'],
                'type' => '셋트',
            ]);

            unset($setData['group_name']);

            foreach ($memberOrders as $memberOrder) {
                $order = Order::factory()->create([
                    ...$setData,
                    ...$memberOrder,
                    'group_id' => $group->id,
                    'group_type' => '셋트',
                    'request_label' => $memberOrder['service_date'].' 셋트 일정',
                    'order_type' => Order::TYPE_AIRPORT,
                    'user_id' => $this->ownerFor($setData['status'], $user, $marketUser)->id,
                ]);

                $order->lineItems()->create($this->buildLineItem($memberOrder));
            }
        }

        // 픽업/샌딩/랜딩 추가 데이터 50건
        $this->seedMarketSingles($user, $marketUser);

        // 배송 흐름(수락 이후) 상태의 오더는 마켓에서 받은(가져온) 오더로 표시한다.
        Order::query()
            ->whereIn('status', [
                Order::STATUS_ACCEPTED,
                Order::STATUS_DRIVING,
                Order::STATUS_COMPLETED,
                Order::STATUS_SETTLED,
                Order::STATUS_CANCELLED,
            ])
            ->update(['claimed_at' => now()->subDays(2)]);
    }

    /**
     * 픽업(서울→강릉) / 샌딩(서울→인천 T1) / 랜딩(인천 T2→서울) 단일 오더를 생성한다.
     */
    private function seedMarketSingles(User $user, User $marketUser): void
    {
        $patterns = [
            'pickup' => [
                'pickupLocations' => ['서울 강남구', '서울 마포구', '서울 송파구', '서울 강북구', '서울 종로구', '서울 용산구', '서울 성동구', '서울 서대문구'],
                'dropoffLocations' => ['강릉 정동진', '강릉 경포대', '강릉 안목해변', '강릉 주문진', '강릉 사천해변'],
                'revenue' => 320000,
                'label' => '픽업 예약',
            ],
            'sending' => [
                'pickupLocations' => ['서울 강남구', '서울 마포구', '홍대입구', '서울 용산구', '성수동', '서울 명동', '여의도', '서울 잠실'],
                'dropoffLocations' => ['인천공항 T1'],
                'revenue' => 120000,
                'label' => '공항 샌딩',
            ],
            'landing' => [
                'pickupLocations' => ['인천공항 T2'],
                'dropoffLocations' => ['서울 마포구', '홍대입구', '서울 강남구', '성수동', '서울 한남동', '광화문', '서울 잠실', '서울 이태원'],
                'revenue' => 150000,
                'label' => '공항 랜딩',
            ],
        ];

        $customers = ['박서연', '이준호', '최민서', '정하은', '강지훈', '조수아', '윤도현', '임예진', '한지우', '오세아'];
        $companies = ['KLOOK', '투어리스트', '트리플', '마이리얼트립', '직접예약'];
        $channels = [Order::CHANNEL_KAKAO, Order::CHANNEL_PHONE, Order::CHANNEL_ONLINE_PARTNER];
        $vehicles = ['카니발', '스타렉스', '그랜버', '스타리아'];
        $airlines = ['KE', 'OZ', '7C', 'TW', 'ZE'];

        $statusPool = [
            Order::STATUS_PUBLISHED,
            Order::STATUS_PUBLISHED,
            Order::STATUS_PUBLISHED,
            Order::STATUS_TRADING,
            Order::STATUS_TRADING,
            Order::STATUS_ACCEPTANCE_PENDING,
            Order::STATUS_ACCEPTED,
            Order::STATUS_DRIVING,
        ];

        $sequence = 0;

        foreach ($patterns as $serviceType => $pattern) {
            $count = $serviceType === 'landing' ? 16 : 17;

            for ($i = 0; $i < $count; $i++) {
                $status = $statusPool[$sequence % count($statusPool)];
                $date = Carbon::now()->addDays(($sequence % 14) + 1)->format('Y-m-d');
                $revenue = $pattern['revenue'] + ($sequence % 5) * 10000;

                $orderData = [
                    'reservation_company' => $companies[$sequence % count($companies)],
                    'customer_name' => $customers[$sequence % count($customers)],
                    'reservation_channel' => $channels[$sequence % count($channels)],
                    'group_type' => '단일',
                    'vehicle_type' => $vehicles[$sequence % count($vehicles)],
                    'service_type' => $serviceType,
                    'service_date' => $date,
                    'service_time' => sprintf('%02d:%02d', 5 + ($sequence % 17), ($sequence * 7) % 60),
                    'pickup_location' => $pattern['pickupLocations'][$sequence % count($pattern['pickupLocations'])],
                    'dropoff_location' => $pattern['dropoffLocations'][$sequence % count($pattern['dropoffLocations'])],
                    'flight_number' => $serviceType === 'pickup' ? '' : $airlines[$sequence % count($airlines)].random_int(100, 999),
                    'passenger_count' => 1 + ($sequence % 6),
                    'luggage_count' => $sequence % 4,
                    'amount_value' => $revenue,
                    'expected_revenue' => $revenue,
                    'request_label' => $pattern['label'],
                    'status' => $status,
                    'user_id' => $this->ownerFor($status, $user, $marketUser)->id,
                ];

                Order::factory()->create($orderData)
                    ->lineItems()
                    ->create($this->buildLineItem($orderData));

                $sequence++;
            }
        }
    }

    /**
     * 단일 오더 필드에서 라인아이템 계약을 만든다.
     *
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     */
    private function buildLineItem(array $order): array
    {
        $date = Carbon::parse($order['service_date']);

        return [
            'scheduled_time' => $order['service_time'],
            'service_date' => $date->month.'월'.$date->day.'일',
            'service_month' => $date->month,
            'service_day' => $date->day,
            'service_weekday' => $this->koreanWeekday($date),
            'service_type' => match ($order['service_type']) {
                'pickup' => '픽업',
                'sending' => '샌딩',
                'landing' => '랜딩',
                default => '픽업',
            },
            'location' => $order['dropoff_location'],
            'pickup_location' => $order['pickup_location'],
            'dropoff_location' => $order['dropoff_location'],
            'flight_number' => $order['flight_number'],
            'passenger_count' => $order['passenger_count'],
            'luggage_count' => $order['luggage_count'],
            'amount_value' => $order['amount_value'],
        ];
    }

    private function koreanWeekday(Carbon $date): string
    {
        return ['일요일', '월요일', '화요일', '수요일', '목요일', '금요일', '토요일'][$date->dayOfWeek];
    }

    /**
     * 배송 흐름(수락 이후) 상태는 테스트 계정(받은 오더), 그 외는 마켓 등록자로 배정한다.
     */
    private function ownerFor(string $status, User $user, User $marketUser): User
    {
        return in_array($status, [
            Order::STATUS_ACCEPTED,
            Order::STATUS_DRIVING,
            Order::STATUS_COMPLETED,
            Order::STATUS_SETTLED,
            Order::STATUS_CANCELLED,
        ], true) ? $user : $marketUser;
    }
}
