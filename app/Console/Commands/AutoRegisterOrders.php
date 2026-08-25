<?php

namespace App\Console\Commands;

use App\Models\AutoOrderSetting;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 매일 정해진 시각에 관리자 계정으로 운행을 자동 등록한다.
 *
 * 설정(auto_order_settings)에서 활성/비활성·건수·등록 계정을 관리하며,
 * 비활성 상태면 아무것도 생성하지 않는다. (설정 화면에서 언제든 중지/시작)
 *
 * 스케줄: routes/console.php 에서 매일 09:00 실행.
 */
class AutoRegisterOrders extends Command
{
    protected $signature = 'orders:auto-register';

    protected $description = '설정된 관리자 계정으로 매일 운행을 자동 등록한다 (설정에서 중지/시작 가능).';

    /** 서버에 faker(dev 의존성)가 없어도 동작하도록 자체 데이터를 사용한다. */
    private const CUSTOMER_NAMES = [
        '김민준', '이서연', '박지훈', '최수아', '정도윤', '강하은', '조현우', '윤지우', '임도현', '한예진',
        '오세훈', '신지민', '권나은', '홍서준', '문채원', '양도현', '배주원', '서지아', '장우진', '류하은',
        'Emma', 'Liam', 'Sofia', 'Noah', 'Mia', 'Oliver', 'Ava', 'Ethan', 'Isabella', 'Lucas',
    ];

    /** 출발/도착 노선 — 공항·도심·관광지 조합 (왕복 포함) */
    private const ROUTES = [
        ['서울 강남구', '인천국제공항'], ['인천국제공항', '서울 강남구'],
        ['서울 마포구', '김포공항'], ['김포공항', '서울 마포구'],
        ['서울 중구', '인천국제공항'], ['인천국제공항', '서울 중구'],
        ['부산 해운대구', '김해공항'], ['김해공항', '부산 해운대구'],
        ['제주시', '제주공항'], ['제주공항', '제주시'],
        ['서울 강남구', '강릉 정동진'], ['서울 강남구', '속초'],
        ['인천국제공항', '부산 해운대구'], ['서울 영등포구', '수원'],
        ['서울 송파구', '인천국제공항'], ['인천국제공항', '서울 송파구'],
        ['대구 동구', '대구공항'], ['대구공항', '대구 동구'],
        ['서울 강서구', '일산'], ['성남 분당구', '인천국제공항'],
    ];

    private const AIRLINES = ['OZ', 'KE', '7C', 'TW', 'LJ', 'BX', 'RS', 'ZE'];

    private const VEHICLES = ['카니발', '스타리아 9인승', '그랜드 스타렉스', '쏠라티 15인승', '그랜버드', 'K9', '제네시스 G80'];

    private const RESERVATION_COMPANIES = ['KLOOK', 'KKDAY', 'Trip.com', '직접예약', '에어텔', '여행사 제휴'];

    public function handle(): int
    {
        $setting = AutoOrderSetting::current();

        if (! $setting->is_active) {
            $this->info('자동 운행 등록이 꺼져 있습니다. (설정에서 활성화하면 등록됩니다)');

            return self::SUCCESS;
        }

        $owner = $this->resolveOwner($setting);

        if ($owner === null) {
            $this->error('자동 등록 계정(관리자)을 찾을 수 없습니다.');

            return self::FAILURE;
        }

        $count = random_int(max(1, $setting->min_count), max($setting->min_count, $setting->max_count));

        $created = 0;

        DB::transaction(function () use ($owner, $count, &$created): void {
            for ($i = 0; $i < $count; $i++) {
                $this->createOrder($owner);
                $created++;
            }
        });

        $setting->forceFill(['last_run_at' => now()])->save();

        // 등록 결과 알림 — 등록 계정(관리자)에게 매일 등록 건수를 알린다
        $owner->notify(new OrderNotification(
            title: '자동 운행 등록 완료',
            message: sprintf('%s 기준 운행 %d건이 자동 등록되었습니다.', now('Asia/Seoul')->format('Y-m-d'), $created),
        ));

        $this->info(sprintf(
            '[%s] %s 계정으로 운행 %d건 자동 등록 완료',
            now()->format('Y-m-d H:i:s'),
            $owner->name,
            $created,
        ));

        return self::SUCCESS;
    }

    private function resolveOwner(AutoOrderSetting $setting): ?User
    {
        // 설정에 등록 계정이 있으면 그 계정, 없으면 관리자(Admin/Super Admin) 기본 계정
        if ($setting->owner_user_id !== null) {
            return User::query()->find($setting->owner_user_id);
        }

        return User::query()
            ->whereIn('role', [User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN])
            ->orderBy('id')
            ->first();
    }

    /**
     * 마켓에 공개되는 자연스러운 운행 하나를 생성한다.
     */
    private function createOrder(User $owner): void
    {
        $route = self::ROUTES[array_rand(self::ROUTES)];

        Order::create([
            'order_number' => Order::generateOrderNumber(),
            'user_id' => $owner->id,
            'status' => Order::STATUS_PUBLISHED,
            'reservation_company' => self::RESERVATION_COMPANIES[array_rand(self::RESERVATION_COMPANIES)],
            'reservation_channel' => array_rand(Order::reservationChannelOptions()),
            'customer_name' => self::CUSTOMER_NAMES[array_rand(self::CUSTOMER_NAMES)],
            'order_type' => array_rand(Order::orderTypeOptions()),
            'passenger_count' => random_int(1, 8),
            'luggage_count' => random_int(0, 4),
            'vehicle_type' => self::VEHICLES[array_rand(self::VEHICLES)],
            'pickup_location' => $route[0],
            'dropoff_location' => $route[1],
            'flight_number' => random_int(0, 2) === 0 ? self::AIRLINES[array_rand(self::AIRLINES)].random_int(100, 9999) : '',
            'service_date' => now('Asia/Seoul')->addDays(random_int(0, 7))->format('Y-m-d'),
            'service_time' => sprintf('%02d:%02d', random_int(6, 23), [0, 30][random_int(0, 1)]),
            'scheduled_at' => now()->addHours(random_int(1, 72)),
            'expected_revenue' => random_int(25, 200) * 1000,
            'estimated_duration_minutes' => random_int(20, 150),
            'distance_km' => random_int(3, 120) + random_int(0, 9) / 10,
            'is_priority' => random_int(0, 9) === 0, // 10% 확률로 긴급
            'auto_registered' => true, // 관리자 화면에서 자동 등록 운행을 식별/관리하기 위한 마킹
        ]);
    }
}
