<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 테스트용 트래픽 시뮬레이션 커맨드.
 *
 * 한 사이클마다 새 운행 / 새 채팅 / 새 알림을 각각 랜덤(min~max)개 생성한다.
 * SPA 폴링(운행·채팅·알림)이 실시간으로 잡아내는지 검증하기 위한 데모 데이터 생성기.
 *
 * 예:
 *   php artisan app:simulate-traffic --once
 *   php artisan app:simulate-traffic --loop --interval=60 --min=1 --max=6
 */
class SimulateTraffic extends Command
{
    protected $signature = 'app:simulate-traffic
        {--to=test@example.com : 테스트 대상 사용자 이메일}
        {--loop : 무한 루프 모드 (기본은 1회 실행)}
        {--interval=60 : 루프 간격(분)}
        {--min=1 : 유형별 최소 생성 수}
        {--max=6 : 유형별 최대 생성 수}';

    protected $description = '새 운행/채팅/알림을 랜덤 개수로 생성하는 테스트 트래픽 시뮬레이터.';

    private const CHAT_PHRASES = [
        '안녕하세요! 예약 확정 가능한가요?',
        '픽업 장소를 한 번 더 확인해주세요.',
        '승객 수가 1명 추가되었습니다.',
        '예약 시간 30분 당겨도 될까요?',
        '공항 도착 시간이 지연될 예정입니다.',
        '결제는 현장에서 진행할 수 있나요?',
        '차량 번호를 미리 알려주실 수 있을까요?',
        '짐이 많아서 트렁크 확인 부탁드립니다.',
    ];

    /** 서버에 faker(dev 의존성)가 없어도 동작하도록 자체 데이터를 사용한다. */
    private const CUSTOMER_NAMES = [
        '김민준', '이서연', '박지훈', '최수아', '정도윤',
        '강하은', '조현우', '윤지우', '임도현', '한예진',
    ];

    private const ROUTES = [
        ['서울 강남구', '인천국제공항'],
        ['인천국제공항', '서울 강남구'],
        ['서울 강남구', '강릉 정동진'],
        ['서울 중구', '김포공항'],
        ['부산 해운대구', '김해공항'],
        ['서울 강남구', '속초'],
        ['인천국제공항', '부산 해운대구'],
        ['서울 마포구', '제주공항'],
    ];

    private const AIRLINES = ['OZ', 'KE', '7C', 'TW', 'LJ', 'BX'];

    public function handle(): int
    {
        $min = max(1, (int) $this->option('min'));
        $max = max($min, (int) $this->option('max'));

        $target = User::query()->where('email', $this->option('to'))->first();

        if ($target === null) {
            $this->error("사용자를 찾을 수 없습니다: {$this->option('to')}");

            return self::FAILURE;
        }

        $intervalMinutes = max(1, (int) $this->option('interval'));
        $loop = (bool) $this->option('loop');

        $this->info("대상: {$target->name} ({$target->email})");

        do {
            $this->runCycle($target, $min, $max);

            if (! $loop) {
                break;
            }

            $this->info("다음 사이클까지 {$intervalMinutes}분 대기... (Ctrl+C 로 중단)");
            sleep($intervalMinutes * 60);
        } while (true);

        return self::SUCCESS;
    }

    /**
     * 한 사이클: 새 운행 / 새 채팅 / 새 알림을 각각 랜덤 개수 생성한다.
     */
    private function runCycle(User $target, int $min, int $max): void
    {
        $partners = $this->partners($target);

        $orderCount = random_int($min, $max);
        $chatCount = random_int($min, $max);
        $notificationCount = random_int($min, $max);

        $createdOrders = [];
        for ($i = 0; $i < $orderCount; $i++) {
            $createdOrders[] = $this->createOrder($partners->random());
        }

        $sentChats = [];
        for ($i = 0; $i < $chatCount; $i++) {
            $sentChats[] = $this->sendChat($target, $partners->random());
        }

        $notified = [];
        for ($i = 0; $i < $notificationCount; $i++) {
            $notified[] = $this->notify($target, $createdOrders[array_rand($createdOrders)] ?? null);
        }

        $this->info(sprintf(
            '[%s] 새 운행 %d건, 새 채팅 %d건, 새 알림 %d건 생성 완료',
            now()->format('Y-m-d H:i:s'),
            count($createdOrders),
            count($sentChats),
            count($notified),
        ));
    }

    /**
     * 대상 사용자 외 파트너 사용자 풀. 없으면 데모 파트너 계정을 만든다.
     *
     * @return Collection<int, User>
     */
    private function partners(User $target): Collection
    {
        $partners = User::query()
            ->where('id', '!=', $target->id)
            ->where('status', User::STATUS_ACTIVE)
            ->get();

        if ($partners->isNotEmpty()) {
            return $partners;
        }

        $this->warn('파트너 사용자가 없어 데모 파트너 계정(partner@example.com)을 생성합니다.');

        return collect([
            User::query()->create([
                'name' => '데모 파트너',
                'email' => 'partner@example.com',
                'password' => '123456',
                'role' => User::ROLE_DRIVER,
                'status' => User::STATUS_ACTIVE,
            ]),
        ]);
    }

    /**
     * 마켓에 보이는 새 운행을 생성한다 (다른 사용자 소유, 공개 상태).
     * faker 없이 자체 데이터로 생성한다.
     */
    private function createOrder(User $owner): Order
    {
        return DB::transaction(function () use ($owner): Order {
            $route = self::ROUTES[array_rand(self::ROUTES)];
            $serviceDate = now('Asia/Seoul')->addDays(random_int(0, 7))->format('Y-m-d');

            return Order::create([
                'order_number' => Order::generateOrderNumber(),
                'user_id' => $owner->id,
                'status' => Order::STATUS_PUBLISHED,
                'reservation_company' => Order::reservationCompanyOptions()[array_rand(Order::reservationCompanyOptions())],
                'reservation_channel' => array_rand(Order::reservationChannelOptions()),
                'customer_name' => self::CUSTOMER_NAMES[array_rand(self::CUSTOMER_NAMES)],
                'order_type' => array_rand(Order::orderTypeOptions()),
                'passenger_count' => random_int(1, 8),
                'luggage_count' => random_int(0, 4),
                'pickup_location' => $route[0],
                'dropoff_location' => $route[1],
                'flight_number' => self::AIRLINES[array_rand(self::AIRLINES)].random_int(100, 9999),
                'service_date' => $serviceDate,
                'service_time' => sprintf('%02d:%02d', random_int(6, 23), [0, 30][random_int(0, 1)]),
                'scheduled_at' => now()->addHours(random_int(1, 72)),
                'expected_revenue' => random_int(12000, 180000),
                'estimated_duration_minutes' => random_int(20, 120),
                'distance_km' => random_int(3, 80) + random_int(0, 9) / 10,
                'is_priority' => random_int(0, 9) === 0, // 10% 확률로 긴급
            ]);
        });
    }

    /**
     * 대상 사용자와 파트너 사이 대화에 새 메시지를 보낸다 (읽지 않은 상태).
     */
    private function sendChat(User $target, User $partner): Message
    {
        return DB::transaction(function () use ($target, $partner): Message {
            $conversation = Conversation::query()
                ->whereHas('users', fn ($q) => $q->where('users.id', $target->id))
                ->whereHas('users', fn ($q) => $q->where('users.id', $partner->id))
                ->first();

            if ($conversation === null) {
                $conversation = Conversation::create();
                $conversation->users()->attach([$target->id, $partner->id]);
            }

            $message = $conversation->messages()->create([
                'user_id' => $partner->id,
                'body' => self::CHAT_PHRASES[array_rand(self::CHAT_PHRASES)],
            ]);

            $conversation->forceFill(['last_message_at' => now()])->save();

            return $message;
        });
    }

    /**
     * 대상 사용자에게 새 알림을 보낸다.
     */
    private function notify(User $target, ?Order $order): void
    {
        $titles = [
            '새 운행이 등록되었습니다',
            '운행 상태가 변경되었습니다',
            '새로운 파트너가 응답했습니다',
            '정산이 완료되었습니다',
        ];

        $target->notify(new OrderNotification(
            title: $titles[array_rand($titles)],
            message: '서버에서 생성된 테스트 알림입니다.',
            orderId: $order?->id,
        ));
    }
}
