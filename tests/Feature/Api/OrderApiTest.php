<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->driver = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.create', 'order.status.update'],
    ]);

    $this->marketUser = User::factory()->create([
        'id' => 99,
    ]);

    Sanctum::actingAs($this->driver);
});

test('api order index returns claimable market orders from other users', function () {
    Order::factory()->create([
        'pickup_location' => '인천공항 T1',
        'dropoff_location' => '명동',
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->marketUser->id,
    ]);

    Order::factory()->create([
        'pickup_location' => '내오더출발',
        'dropoff_location' => '인천공항',
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->driver->id,
    ]);

    Order::factory()->create([
        'pickup_location' => '수락출발',
        'dropoff_location' => '인천공항',
        'status' => Order::STATUS_ACCEPTED,
        'user_id' => $this->marketUser->id,
    ]);

    $response = $this->getJson('/api/orders?scope=market')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.pagination.total', 1);

    expect(collect($response->json('data'))->pluck('route'))
        ->toContain('인천공항 T1 → 명동')
        ->not->toContain('내오더출발 → 인천공항')
        ->not->toContain('수락출발 → 인천공항');
});

test('api order index marks recently created orders as new', function () {
    Order::factory()->create([
        'pickup_location' => '신규출발',
        'dropoff_location' => '인천공항',
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->marketUser->id,
        'created_at' => now()->subMinutes(10),
    ]);

    Order::factory()->create([
        'pickup_location' => '이전출발',
        'dropoff_location' => '인천공항',
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->marketUser->id,
        'created_at' => now()->subHours(2),
    ]);

    $response = $this->getJson('/api/orders?scope=market')->assertOk();

    $rows = collect($response->json('data'))->keyBy('route');

    expect($rows['신규출발 → 인천공항']['isNew'])->toBeTrue();
    expect($rows['이전출발 → 인천공항']['isNew'])->toBeFalse();
});

test('api order index filters by service type and sorts by date', function () {
    Order::factory()->create([
        'service_type' => 'pickup',
        'service_date' => '2026-08-15',
        'service_time' => '10:00',
        'pickup_location' => '인천공항',
        'dropoff_location' => '명동',
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->marketUser->id,
    ]);

    Order::factory()->create([
        'service_type' => 'sending',
        'service_date' => '2026-08-14',
        'service_time' => '09:00',
        'pickup_location' => '강남',
        'dropoff_location' => '인천공항',
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->marketUser->id,
    ]);

    $response = $this->getJson('/api/orders?scope=market&service_type=pickup&sort=date')
        ->assertOk();

    $rows = collect($response->json('data'));

    expect($rows)->toHaveCount(1);
    expect($rows->first()['route'])->toBe('인천공항 → 명동');
});

test('api order index returns my draft orders tab', function () {
    Order::factory()->create([
        'status' => Order::STATUS_DRAFT,
        'user_id' => $this->driver->id,
        'pickup_location' => '초안출발',
        'dropoff_location' => '인천공항',
    ]);

    $response = $this->getJson('/api/orders?scope=mine&source=registered&tab=초안')
        ->assertOk();

    $rows = collect($response->json('data'));

    expect($rows)->toHaveCount(1);
    expect($rows->first()['route'])->toBe('초안출발 → 인천공항');
});

test('api order index returns my received orders with tab filter', function () {
    Order::factory()->create([
        'pickup_location' => '강남역',
        'dropoff_location' => '인천공항',
        'status' => Order::STATUS_ACCEPTED,
        'claimed_at' => now(),
        'user_id' => $this->driver->id,
    ]);

    Order::factory()->create([
        'pickup_location' => '홍대입구',
        'dropoff_location' => '김포공항',
        'status' => Order::STATUS_COMPLETED,
        'claimed_at' => now(),
        'user_id' => $this->driver->id,
    ]);

    Order::factory()->create([
        'pickup_location' => '남의출발',
        'dropoff_location' => '인천공항',
        'status' => Order::STATUS_ACCEPTED,
        'claimed_at' => now(),
        'user_id' => $this->marketUser->id,
    ]);

    $response = $this->getJson('/api/orders?scope=mine&tab=진행중')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    expect(collect($response->json('data'))->pluck('route'))
        ->toContain('강남역 → 인천공항')
        ->not->toContain('홍대입구 → 김포공항')
        ->not->toContain('남의출발 → 인천공항');

    $this->getJson('/api/orders?scope=mine&tab=완료')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('api order claim takes the market order into my received orders', function () {
    $order = Order::factory()->create([
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->marketUser->id,
    ]);

    $this->postJson("/api/orders/{$order->id}/claim")
        ->assertOk()
        ->assertJsonPath('data.status', Order::STATUS_ACCEPTED);

    expect($order->fresh()?->user_id)->toBe($this->driver->id);
    expect($order->fresh()?->status)->toBe(Order::STATUS_ACCEPTED);
    expect($order->fresh()?->claimed_at)->not->toBeNull();

    // 가져온 드라이버와 원 소유자 모두 알림을 받는다
    $claimNotification = $this->driver->notifications()->first();
    expect($claimNotification?->data['title'])->toBe('오더 가져오기 완료');
    expect($claimNotification?->data['order_id'])->toBe($order->id);

    $ownerNotification = $this->marketUser->notifications()->first();
    expect($ownerNotification?->data['title'])->toBe('오더 가져오기됨');
    expect($ownerNotification?->data['order_id'])->toBe($order->id);
});

test('api order claim rejects claiming my own order', function () {
    $order = Order::factory()->create([
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->driver->id,
    ]);

    $this->postJson("/api/orders/{$order->id}/claim")->assertForbidden();
});

test('api order transition follows the lifecycle rules', function () {
    $order = Order::factory()->create([
        'status' => Order::STATUS_ACCEPTED,
        'user_id' => $this->driver->id,
    ]);

    $this->postJson("/api/orders/{$order->id}/status", ['status' => Order::STATUS_DRIVING])
        ->assertOk()
        ->assertJsonPath('data.status', Order::STATUS_DRIVING);

    // 상태 변경 시 소유자에게 알림이 발송된다
    $notification = $this->driver->notifications()->first();
    expect($notification?->data['title'])->toBe('오더 상태 변경');
    expect($notification?->data['order_id'])->toBe($order->id);

    $this->postJson("/api/orders/{$order->id}/status", ['status' => Order::STATUS_DRAFT])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

test('api order options returns dropdown options', function () {
    $this->getJson('/api/options/orders')
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['statusOptions', 'serviceOptions', 'channelOptions', 'companyOptions'],
        ]);
});

test('api order store creates a draft order', function () {
    $response = $this->postJson('/api/orders', [
        'customer_name' => '등록손님',
        'vehicle_type' => '카니발',
        'service_type' => 'landing',
        'service_date' => '2026-08-20',
        'service_time' => '14:00',
        'service_datetime' => '2026-08-20 14:00:00',
        'pickup_location' => '인천공항 T2',
        'dropoff_location' => '서울 마포구',
        'flight_number' => 'KE123',
        'passenger_count' => 3,
        'expected_revenue' => 150000,
    ])
        ->assertStatus(201)
        ->assertJsonStructure(['data' => ['id', 'orderNumber', 'status']]);

    $order = Order::query()->findOrFail($response->json('data.id'));

    expect($order->customer_name)->toBe('등록손님');
    expect($order->service_type)->toBe('landing');
    expect($order->service_datetime)->toBe('2026-08-20 14:00:00');
    expect($order->status)->toBe(Order::STATUS_DRAFT);
    expect($order->user_id)->toBe($this->driver->id);
});

test('api order store accepts minimal fields without passenger count', function () {
    $response = $this->postJson('/api/orders', [
        'service_date' => '2026-08-20',
        'service_time' => '09:30',
        'service_datetime' => '2026-08-20 09:30:00',
        'pickup_location' => '강남구',
        'dropoff_location' => '인천',
    ])
        ->assertStatus(201);

    $order = Order::query()->findOrFail($response->json('data.id'));

    expect($order->passenger_count)->toBe(1);
    expect($order->service_datetime)->toBe('2026-08-20 09:30:00');
});

test('api order update modifies the order', function () {
    $order = Order::factory()->create([
        'customer_name' => '원래이름',
        'status' => Order::STATUS_DRAFT,
        'user_id' => $this->driver->id,
    ]);

    $this->patchJson("/api/orders/{$order->id}", [
        'customer_name' => '수정이름',
        'expected_revenue' => 200000,
    ])->assertOk();

    expect($order->fresh()?->customer_name)->toBe('수정이름');
    expect($order->fresh()?->expected_revenue)->toBe(200000);
});

test('api order batch store creates a set of orders', function () {
    $response = $this->postJson('/api/orders/batch', [
        'group_name' => '테스트 셋트',
        'orders' => [
            [
                'service_type' => 'pickup',
                'service_date' => '2026-08-20',
                'service_time' => '09:00',
                'service_datetime' => '2026-08-20 09:00:00',
                'pickup_location' => '인천',
                'dropoff_location' => '강남',
                'passenger_count' => 3,
                'expected_revenue' => 100000,
            ],
            [
                'service_type' => 'sending',
                'service_date' => '2026-08-22',
                'service_time' => '17:00',
                'service_datetime' => '2026-08-22 17:00:00',
                'pickup_location' => '강남',
                'dropoff_location' => '인천',
                'passenger_count' => 3,
                'expected_revenue' => 100000,
            ],
        ],
    ])
        ->assertStatus(201)
        ->assertJsonStructure(['data' => ['group_id', 'group_name', 'order_count']]);

    expect($response->json('data.order_count'))->toBe(2);

    $groupId = $response->json('data.group_id');
    $orderCount = Order::query()->where('group_id', $groupId)->count();

    expect($orderCount)->toBe(2);
});

test('api order stats returns summary and daily series', function () {
    Order::factory()->create([
        'status' => Order::STATUS_COMPLETED,
        'expected_revenue' => 100000,
        'user_id' => $this->driver->id,
    ]);
    Order::factory()->create([
        'status' => Order::STATUS_SETTLED,
        'expected_revenue' => 50000,
        'user_id' => $this->driver->id,
    ]);
    Order::factory()->create([
        'status' => Order::STATUS_PUBLISHED,
        'expected_revenue' => 30000,
        'user_id' => $this->driver->id,
    ]);

    $response = $this->getJson('/api/stats/orders?days=7')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'summary' => ['total', 'revenue', 'completed', 'settled', 'settlementPending', 'inProgress'],
                'daily' => [['date', 'count', 'revenue']],
                'statusDistribution' => [['status', 'label', 'count']],
            ],
        ]);

    $summary = $response->json('data.summary');

    expect($summary['total'])->toBe(3);
    expect($summary['completed'])->toBe(2);
    expect($summary['revenue'])->toBe(180000);
    expect($summary['settled'])->toBe(50000);
    expect($summary['settlementPending'])->toBe(100000);
});

test('api order duplicate creates a draft copy', function () {
    $order = Order::factory()->create([
        'status' => Order::STATUS_COMPLETED,
        'customer_name' => '복제원본',
        'pickup_location' => '인천공항',
        'dropoff_location' => '명동',
        'expected_revenue' => 90000,
        'user_id' => $this->driver->id,
    ]);

    $response = $this->postJson("/api/orders/{$order->id}/duplicate")
        ->assertStatus(201)
        ->assertJsonStructure(['data' => ['id']]);

    $copy = Order::find($response->json('data.id'));

    expect($copy->id)->not->toBe($order->id);
    expect($copy->status)->toBe(Order::STATUS_DRAFT);
    expect($copy->customer_name)->toBe('복제원본');
    expect($copy->pickup_location)->toBe('인천공항');
    expect($copy->expected_revenue)->toBe(90000);
    expect($copy->user_id)->toBe($this->driver->id);
});

test('api order batch settle settles only completed orders', function () {
    $completed = Order::factory()->create([
        'status' => Order::STATUS_COMPLETED,
        'user_id' => $this->driver->id,
    ]);
    $alreadySettled = Order::factory()->create([
        'status' => Order::STATUS_SETTLED,
        'user_id' => $this->driver->id,
    ]);
    $foreign = Order::factory()->create([
        'status' => Order::STATUS_COMPLETED,
        'user_id' => $this->marketUser->id,
    ]);

    $response = $this->postJson('/api/orders/batch-settle', [
        'ids' => [$completed->id, $alreadySettled->id, $foreign->id],
    ])->assertOk();

    expect($response->json('data.settled'))->toBe(1);
    expect($completed->fresh()->status)->toBe(Order::STATUS_SETTLED);
    expect($alreadySettled->fresh()->status)->toBe(Order::STATUS_SETTLED);
    expect($foreign->fresh()->status)->toBe(Order::STATUS_COMPLETED);
});

test('api order structure returns structured summary', function () {
    config()->set('services.order_ai.api_key', 'test-key');
    config()->set('services.order_ai.base_url', 'https://example.test/v1');
    config()->set('services.order_ai.model', 'test-model');

    Http::fake([
        'https://example.test/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'request_label' => '강릉 픽업',
                            'service_type' => 'pickup',
                            'pickup_location' => '서울 강남구',
                            'dropoff_location' => '강릉 정동진',
                            'vehicle_type' => '카니발',
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ],
            ],
        ]),
    ]);

    $this->postJson('/api/orders/structure', [
        'summary' => '8월 10일 오전 9시 강남에서 강릉 정동진 픽업 3명 카니발',
    ])
        ->assertOk()
        ->assertJsonPath('data.structured.service_type', '픽업')
        ->assertJsonPath('data.structured.pickup_location', '서울 강남구')
        ->assertJsonPath('data.structured.dropoff_location', '강릉 정동진');
});
