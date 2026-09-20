<?php

use App\Models\Conversation;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\User;
use App\Support\Orders\ChineseTextNormalizer;
use App\Support\Orders\OrderWorkspaceListBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->driver = User::factory()->create(['role' => User::ROLE_DRIVER]);
    $this->owner = User::factory()->create(['role' => User::ROLE_OPERATOR]);

    Sanctum::actingAs($this->driver);
});

test('목록 행은 위치·차량의 한자를 한국어로 바꾸고, 풀리지 않는 한자는 미정으로 둔다', function () {
    $order = Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '弘大',
        'dropoff_location' => '卡全部跑完结算',
        'vehicle_type' => '利亚7',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        // 마켓은 시작 시각이 1시간 넘게 지난 운행을 감추므로, 하루 중 언제 돌려도 남도록 늦은 시각을 쓴다
        'service_time' => '23:00',
    ]);

    $response = $this->getJson('/api/orders?scope=market')->assertOk();
    $row = collect($response->json('data'))->firstWhere('id', $order->id);

    expect($row)->not->toBeNull()
        ->and($row['route'])->toBe('홍대 → 미정')
        ->and($row['vehicle'])->toBe('스타리아 7인승');
});

test('셋트 카드의 다리 노선도 같은 규칙으로 바꾼다', function () {
    $group = OrderGroup::factory()->create(['name' => '셋트']);

    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'group_id' => $group->id,
        'pickup_location' => '弘大',
        'dropoff_location' => '仁川',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '11:00',
    ]);

    // 셋트 카드는 운행이 둘 이상일 때만 만들어진다 (한 건이면 일반 카드로 내려간다)
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'group_id' => $group->id,
        'pickup_location' => '明洞',
        'dropoff_location' => '金浦',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '13:00',
    ]);

    $rows = app(OrderWorkspaceListBuilder::class)->build(
        Order::query()->where('group_id', $group->id)->get(),
    );

    expect($rows[0]['kind'])->toBe('set')
        ->and($rows[0]['routes'][0]['route'])->toBe('홍대 → 인천');
});

test('상세 응답은 표시용 한국어 값과 함께 원문(raw_*)을 내려 수정 화면이 원문을 쓸 수 있게 한다', function () {
    $order = Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '弘大',
        'dropoff_location' => '卡全部跑完结算',
        'vehicle_type' => '利亚7',
    ]);

    $this->getJson("/api/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.order.pickup_location', '홍대')
        ->assertJsonPath('data.order.dropoff_location', '미정')
        ->assertJsonPath('data.order.vehicle_type', '스타리아 7인승')
        // 원문은 그대로 남는다 — 등록자가 수정 화면에서 되살릴 수 있어야 한다
        ->assertJsonPath('data.order.raw_pickup_location', '弘大')
        ->assertJsonPath('data.order.raw_dropoff_location', '卡全部跑完结算')
        ->assertJsonPath('data.order.raw_vehicle_type', '利亚7');
});

test('표시용 노선은 양쪽이 비면 빈 문자열, 한자가 남으면 미정으로 둔다', function () {
    expect(ChineseTextNormalizer::routeLabel('弘大', '仁川'))->toBe('홍대 → 인천')
        ->and(ChineseTextNormalizer::routeLabel('弘大', '卡全部跑完结算'))->toBe('홍대 → 미정')
        ->and(ChineseTextNormalizer::routeLabel(null, ''))->toBe('');
});

test('알림 문구에 쓰는 운행 요약도 위치 한자를 한국어로 바꾼다', function () {
    $order = Order::factory()->create([
        'user_id' => $this->owner->id,
        'pickup_location' => '弘大',
        'dropoff_location' => '卡全部跑完结算',
        'service_date' => '2026-09-13',
        'service_time' => '11:00',
    ]);

    $summary = $order->rideSummary();

    expect($summary)->toContain('홍대 → 미정')
        ->and($summary)->not->toContain('弘大');
});

test('알림 카드의 운행 노선도 같은 규칙으로 바꾼다', function () {
    $order = Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '弘大',
        'dropoff_location' => '仁川',
    ]);

    DatabaseNotification::unguarded(fn () => DatabaseNotification::create([
        'id' => (string) Str::uuid(),
        'type' => 'App\Notifications\OrderNotification',
        'notifiable_id' => $this->driver->id,
        'notifiable_type' => User::class,
        'data' => ['title' => '승인 요청', 'message' => '내용', 'order_id' => $order->id],
        'read_at' => null,
    ]));

    $this->getJson('/api/notifications')
        ->assertOk()
        ->assertJsonPath('data.0.order_route', '홍대 → 인천');
});

test('채팅 목록의 운행 노선도 같은 규칙으로 바꾼다', function () {
    $order = Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '弘大',
        'dropoff_location' => '仁川',
    ]);

    $conversation = Conversation::create(['order_id' => $order->id, 'last_message_at' => now()]);
    $conversation->users()->attach([$this->driver->id, $this->owner->id]);

    $this->getJson('/api/chats')
        ->assertOk()
        ->assertJsonPath('data.0.order.route', '홍대 → 인천');
});

test('정산 내역의 출발·도착지도 같은 규칙으로 바꾼다', function () {
    Order::factory()->create([
        'user_id' => $this->driver->id,
        'status' => Order::STATUS_COMPLETED,
        'pickup_location' => '弘大',
        'dropoff_location' => '卡全部跑完结算',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
    ]);

    $this->getJson('/api/me/settlements')
        ->assertOk()
        ->assertJsonPath('data.data.0.pickup_location', '홍대')
        ->assertJsonPath('data.data.0.dropoff_location', '미정');
});
