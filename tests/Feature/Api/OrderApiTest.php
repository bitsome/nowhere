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

test('api order index shows all market orders including my own, but claim is blocked', function () {
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

    // 본인 등록 운행도 마켓에 보여야 한다 — 그래야 관리자가 검증할 수 있다.
    $response = $this->getJson('/api/orders?scope=market')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.pagination.total', 2);

    expect(collect($response->json('data'))->pluck('route'))
        ->toContain('인천공항 T1 → 명동')
        ->toContain('내오더출발 → 인천공항')
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
    $dayAfter = now('Asia/Seoul')->addDay()->format('Y-m-d');
    $twoDays = now('Asia/Seoul')->addDays(2)->format('Y-m-d');

    Order::factory()->create([
        'service_type' => 'pickup',
        'service_date' => $twoDays,
        'service_time' => '10:00',
        'pickup_location' => '인천공항',
        'dropoff_location' => '명동',
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->marketUser->id,
    ]);

    Order::factory()->create([
        'service_type' => 'sending',
        'service_date' => $dayAfter,
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

test('api order index filters by quick tomorrow', function () {
    $today = now('Asia/Seoul')->format('Y-m-d');
    $tomorrow = now('Asia/Seoul')->addDay()->format('Y-m-d');

    Order::factory()->create([
        'service_date' => $today,
        'service_time' => '10:00',
        'pickup_location' => '오늘출발',
        'dropoff_location' => '인천공항',
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->marketUser->id,
    ]);

    Order::factory()->create([
        'service_date' => $tomorrow,
        'service_time' => '09:00',
        'pickup_location' => '내일출발',
        'dropoff_location' => '인천공항',
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->marketUser->id,
    ]);

    $response = $this->getJson('/api/orders?scope=market&quick=tomorrow')
        ->assertOk();

    $rows = collect($response->json('data'));

    expect($rows)->toHaveCount(1);
    expect($rows->first()['route'])->toBe('내일출발 → 인천공항');
});

test('api order index filters by service datetime onward', function () {
    $tomorrow = now('Asia/Seoul')->addDay()->format('Y-m-d');

    Order::factory()->create([
        'service_date' => $tomorrow,
        'service_time' => '10:00',
        'pickup_location' => '오전운행',
        'dropoff_location' => '인천공항',
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->marketUser->id,
    ]);

    Order::factory()->create([
        'service_date' => $tomorrow,
        'service_time' => '13:00',
        'pickup_location' => '오후운행',
        'dropoff_location' => '인천공항',
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->marketUser->id,
    ]);

    $response = $this->getJson('/api/orders?scope=market&date='.urlencode($tomorrow.' 12:00'))
        ->assertOk();

    $rows = collect($response->json('data'));

    expect($rows)->toHaveCount(1);
    expect($rows->first()['route'])->toBe('오후운행 → 인천공항');
});

test('api order index filters by departure and arrival', function () {
    $tomorrow = now('Asia/Seoul')->addDay()->format('Y-m-d');

    Order::factory()->create([
        'service_date' => $tomorrow,
        'service_time' => '10:00',
        'pickup_location' => '서울 강남구',
        'dropoff_location' => '인천공항 T1',
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->marketUser->id,
    ]);

    Order::factory()->create([
        'service_date' => $tomorrow,
        'service_time' => '11:00',
        'pickup_location' => '인천공항 T2',
        'dropoff_location' => '서울 중구',
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->marketUser->id,
    ]);

    Order::factory()->create([
        'service_date' => $tomorrow,
        'service_time' => '12:00',
        'pickup_location' => '수원',
        'dropoff_location' => '인천공항 T1',
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->marketUser->id,
    ]);

    // 출발지=서울 → 서울에서 출발하는 운행만
    $departure = $this->getJson('/api/orders?scope=market&departure='.urlencode('서울'))
        ->assertOk();

    $departureRows = collect($departure->json('data'));

    expect($departureRows)->toHaveCount(1);
    expect($departureRows->first()['route'])->toBe('서울 강남구 → 인천공항 T1');

    // 도착지=인천공항 T1 → 해당 터미널로 도착하는 운행만
    $arrival = $this->getJson('/api/orders?scope=market&arrival='.urlencode('인천공항 T1'))
        ->assertOk();

    $arrivalRows = collect($arrival->json('data'));

    expect($arrivalRows)->toHaveCount(2);
    expect($arrivalRows->pluck('route'))
        ->toContain('서울 강남구 → 인천공항 T1')
        ->toContain('수원 → 인천공항 T1');

    // 출발지=수원(경기도) → 구명 그대로 매칭
    $suwon = $this->getJson('/api/orders?scope=market&departure='.urlencode('수원'))
        ->assertOk();

    $suwonRows = collect($suwon->json('data'));

    expect($suwonRows)->toHaveCount(1);
    expect($suwonRows->first()['route'])->toBe('수원 → 인천공항 T1');
});

test('api order index filters by vehicle capacity', function () {
    $tomorrow = now('Asia/Seoul')->addDay()->format('Y-m-d');

    Order::factory()->create([
        'service_date' => $tomorrow,
        'service_time' => '10:00',
        'pickup_location' => '서울 강남구',
        'dropoff_location' => '인천공항',
        'vehicle_type' => '스타리아 9인승',
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->marketUser->id,
    ]);

    Order::factory()->create([
        'service_date' => $tomorrow,
        'service_time' => '11:00',
        'pickup_location' => '서울 마포구',
        'dropoff_location' => '인천공항',
        'vehicle_type' => '스타리아 7인승',
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->marketUser->id,
    ]);

    Order::factory()->create([
        'service_date' => $tomorrow,
        'service_time' => '12:00',
        'pickup_location' => '서울 중구',
        'dropoff_location' => '인천공항',
        'vehicle_type' => '카니발',
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->marketUser->id,
    ]);

    // 차종=스타리아 + 인승=9인승 → 해당 인승 운행만
    $nine = $this->getJson('/api/orders?scope=market&vehicle_type='.urlencode('스타리아').'&vehicle_capacity='.urlencode('9인승'))
        ->assertOk();

    $nineRows = collect($nine->json('data'));

    expect($nineRows)->toHaveCount(1);
    expect($nineRows->first()['route'])->toBe('서울 강남구 → 인천공항');

    // 인승만 7인승 → 정확히 7인승만 매칭 (9인승은 제외)
    $seven = $this->getJson('/api/orders?scope=market&vehicle_capacity='.urlencode('7인승'))
        ->assertOk();

    $sevenRows = collect($seven->json('data'));

    expect($sevenRows)->toHaveCount(1);
    expect($sevenRows->first()['route'])->toBe('서울 마포구 → 인천공항');
});

test('api order index market search matches route only, not customer name', function () {
    $tomorrow = now('Asia/Seoul')->addDay()->format('Y-m-d');

    Order::factory()->create([
        'service_date' => $tomorrow,
        'service_time' => '10:00',
        'customer_name' => '노선고객',
        'pickup_location' => '강남역',
        'dropoff_location' => '인천공항',
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->marketUser->id,
    ]);

    Order::factory()->create([
        'service_date' => $tomorrow,
        'service_time' => '11:00',
        'customer_name' => '노선고객',
        'pickup_location' => '서울역',
        'dropoff_location' => '김포공항',
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->marketUser->id,
    ]);

    // 노선(도착지) 검색 — 인천공항으로 가는 운행만 매칭
    $byDropoff = $this->getJson('/api/orders?scope=market&search='.urlencode('인천공항'))
        ->assertOk();

    $dropoffRows = collect($byDropoff->json('data'));

    expect($dropoffRows)->toHaveCount(1);
    expect($dropoffRows->first()['route'])->toBe('강남역 → 인천공항');

    // 마켓 검색은 고객명을 매칭하지 않는다 — '노선고객'으로는 검색되지 않는다
    $byCustomer = $this->getJson('/api/orders?scope=market&search='.urlencode('노선고객'))
        ->assertOk();

    expect(collect($byCustomer->json('data')))->toHaveCount(0);
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

test('api order claim requests the market order and notifies the owner', function () {
    $order = Order::factory()->create([
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->marketUser->id,
    ]);

    $this->postJson("/api/orders/{$order->id}/claim")
        ->assertOk()
        ->assertJsonPath('data.status', Order::STATUS_ACCEPTANCE_PENDING);

    // 요청 시점에는 소유자는 그대로, 요청자만 기록된다 (등록자 승인 후 넘어간다)
    expect($order->fresh()?->user_id)->toBe($this->marketUser->id);
    expect($order->fresh()?->status)->toBe(Order::STATUS_ACCEPTANCE_PENDING);
    expect($order->fresh()?->claimant_user_id)->toBe($this->driver->id);
    expect($order->fresh()?->claimed_at)->not->toBeNull();

    // 원 소유자에게 승인 요청 알림이 발송된다
    $ownerNotification = $this->marketUser->notifications()->first();
    expect($ownerNotification?->data['title'])->toBe('운행 가져오기 요청');
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
    expect($notification?->data['title'])->toBe('운행 상태 변경');
    expect($notification?->data['order_id'])->toBe($order->id);

    $this->postJson("/api/orders/{$order->id}/status", ['status' => Order::STATUS_DRAFT])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

test('api order cannot publish without required fields', function () {
    $order = Order::factory()->create([
        'status' => Order::STATUS_DRAFT,
        'user_id' => $this->driver->id,
        'pickup_location' => null,
        'dropoff_location' => null,
        'vehicle_type' => null,
        'service_type' => null,
        'service_date' => null,
        'service_time' => null,
        'expected_revenue' => null,
    ]);

    $this->postJson("/api/orders/{$order->id}/status", ['status' => Order::STATUS_PUBLISHED])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);

    expect($order->fresh()?->status)->toBe(Order::STATUS_DRAFT);
});

test('api order records ride start, completion timestamps and actual revenue', function () {
    $order = Order::factory()->create([
        'status' => Order::STATUS_ACCEPTED,
        'user_id' => $this->driver->id,
        'expected_revenue' => 100000,
    ]);

    // 운행중 전이 — 시작 시각 기록
    $this->postJson("/api/orders/{$order->id}/status", ['status' => Order::STATUS_DRIVING])
        ->assertOk();

    $driving = $order->fresh();

    expect($driving?->status)->toBe(Order::STATUS_DRIVING);
    expect($driving?->started_at)->not->toBeNull();

    // 완료 전이 — 완료 시각 + 실제 수익 기록
    $this->postJson("/api/orders/{$order->id}/status", [
        'status' => Order::STATUS_COMPLETED,
        'actual_revenue' => 95000,
    ])->assertOk();

    $completed = $order->fresh();

    expect($completed?->status)->toBe(Order::STATUS_COMPLETED);
    expect($completed?->started_at)->not->toBeNull();
    expect($completed?->completed_at)->not->toBeNull();
    expect($completed?->actual_revenue)->toBe(95000);
});

test('driver can withdraw a pending claim and the order returns to the market', function () {
    $order = Order::factory()->create([
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->marketUser->id,
    ]);

    // 가져오기 요청 → 수락 대기
    $this->postJson("/api/orders/{$order->id}/claim")
        ->assertOk();

    expect($order->fresh()?->status)->toBe(Order::STATUS_ACCEPTANCE_PENDING);

    // 요청자가 transition API로 published 전이 → 철회 처리
    $this->postJson("/api/orders/{$order->id}/status", ['status' => Order::STATUS_PUBLISHED])
        ->assertOk()
        ->assertJsonPath('data.status', Order::STATUS_PUBLISHED);

    $fresh = $order->fresh();

    expect($fresh?->status)->toBe(Order::STATUS_PUBLISHED);
    expect($fresh?->claimant_user_id)->toBeNull();
    expect($fresh?->claimed_at)->toBeNull();
});

test('registrant can approve a pending claim through the transition endpoint', function () {
    $order = Order::factory()->create([
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->marketUser->id,
    ]);

    $this->actingAs($this->driver)
        ->postJson("/api/orders/{$order->id}/claim")
        ->assertOk();

    // 등록자가 transition API로 accepted 전이 → claim 서비스가 승인 처리 (claimant 정리 포함)
    $this->actingAs($this->marketUser)
        ->postJson("/api/orders/{$order->id}/status", ['status' => Order::STATUS_ACCEPTED])
        ->assertOk()
        ->assertJsonPath('data.status', Order::STATUS_ACCEPTED);

    $fresh = $order->fresh();

    expect($fresh?->status)->toBe(Order::STATUS_ACCEPTED);
    expect($fresh?->user_id)->toBe($this->driver->id);
    expect($fresh?->claimant_user_id)->toBeNull();
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

test('only the registrant can settle a completed order taken over by a driver', function () {
    // 등록자(원 등록자)가 운행을 등록하고, 진행자(드라이버)가 수행해 완료한 상태
    // 두 사용자 모두 status.update 권한 없음 — 원 등록자 여부만으로 정산 권한이 결정돼야 한다
    $registrant = User::factory()->create(['id' => 2000, 'role' => 'Driver', 'permissions' => []]);
    $performer = User::factory()->create(['id' => 2001, 'role' => 'Driver', 'permissions' => []]);

    $order = Order::factory()->create([
        'user_id' => $performer->id,
        'original_owner_id' => $registrant->id,
        'status' => Order::STATUS_COMPLETED,
    ]);

    // 진행자는 정산 전이 불가 (정산 진행중으로 대기)
    $this->actingAs($performer)
        ->postJson("/api/orders/{$order->id}/status", ['status' => Order::STATUS_SETTLED])
        ->assertForbidden();

    // 등록자는 정산 전이 가능
    $this->actingAs($registrant)
        ->postJson("/api/orders/{$order->id}/status", ['status' => Order::STATUS_SETTLED])
        ->assertOk();

    expect($order->fresh()->status)->toBe(Order::STATUS_SETTLED);
});

test('batch settle only settles orders registered by the acting user', function () {
    $registrant = User::factory()->create(['id' => 102]);
    $performer = User::factory()->create(['id' => 103]);
    $otherRegistrant = User::factory()->create(['id' => 106]);

    // 등록자가 남에게 넘긴 후 완료된 운행
    $taken = Order::factory()->create([
        'user_id' => $performer->id,
        'original_owner_id' => $registrant->id,
        'status' => Order::STATUS_COMPLETED,
    ]);
    // 진행자가 수행한 다른 등록자의 운행
    $foreign = Order::factory()->create([
        'user_id' => $performer->id,
        'original_owner_id' => $otherRegistrant->id,
        'status' => Order::STATUS_COMPLETED,
    ]);

    // 진행자(batchSettle) — 자신이 수행했어도 등록자가 아니면 정산 대상이 아니다
    $this->actingAs($performer)
        ->postJson('/api/orders/batch-settle', ['ids' => [$taken->id, $foreign->id]])
        ->assertOk()
        ->assertJsonPath('data.settled', 0);

    expect($taken->fresh()->status)->toBe(Order::STATUS_COMPLETED);

    // 등록자 — 자신이 등록한 완료 운행만 정산된다
    $this->actingAs($registrant)
        ->postJson('/api/orders/batch-settle', ['ids' => [$taken->id, $foreign->id]])
        ->assertOk()
        ->assertJsonPath('data.settled', 1);

    expect($taken->fresh()->status)->toBe(Order::STATUS_SETTLED);
    expect($foreign->fresh()->status)->toBe(Order::STATUS_COMPLETED);
});

test('registered list includes taken-over orders and labels completed as settlement pending', function () {
    $registrant = User::factory()->create(['id' => 104]);
    $performer = User::factory()->create(['id' => 105]);

    // 등록자가 남에게 넘긴 후 완료된 운행 — 등록자 목록에 보여야 한다
    Order::factory()->create([
        'user_id' => $performer->id,
        'original_owner_id' => $registrant->id,
        'status' => Order::STATUS_COMPLETED,
    ]);

    $response = $this->actingAs($registrant)
        ->getJson('/api/orders?scope=mine&source=registered&tab=완료')
        ->assertOk();

    $rows = $response->json('data');
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['status'])->toBe(Order::STATUS_COMPLETED)
        ->and($rows[0]['statusLabel'])->toBe('정산 진행중');
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
