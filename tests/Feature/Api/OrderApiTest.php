<?php

use App\Models\Order;
use App\Models\OrderClaim;
use App\Models\Review;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->driver = User::factory()->create([
        'id' => 2,
        'role' => User::ROLE_DRIVER,
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

test('api order index filters by time range', function () {
    $tomorrow = now('Asia/Seoul')->addDay()->format('Y-m-d');

    Order::factory()->create([
        'service_date' => $tomorrow,
        'service_time' => '09:30',
        'pickup_location' => '서울 강남구',
        'dropoff_location' => '인천공항',
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->marketUser->id,
    ]);

    Order::factory()->create([
        'service_date' => $tomorrow,
        'service_time' => '14:00',
        'pickup_location' => '서울 마포구',
        'dropoff_location' => '인천공항',
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->marketUser->id,
    ]);

    Order::factory()->create([
        'service_date' => $tomorrow,
        'service_time' => '20:30',
        'pickup_location' => '서울 중구',
        'dropoff_location' => '인천공항',
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->marketUser->id,
    ]);

    // 오전 — 12:00 전 운행만
    $morning = $this->getJson('/api/orders?scope=market&time_range=morning')->assertOk();
    expect(collect($morning->json('data'))->pluck('route'))->toHaveCount(1);
    expect($morning->json('data.0.route'))->toBe('서울 강남구 → 인천공항');

    // 오후 — 12:00 ~ 18:00 사이 운행만
    $afternoon = $this->getJson('/api/orders?scope=market&time_range=afternoon')->assertOk();
    expect(collect($afternoon->json('data'))->pluck('route'))->toHaveCount(1);
    expect($afternoon->json('data.0.route'))->toBe('서울 마포구 → 인천공항');

    // 야간 — 18:00 이후 운행만
    $night = $this->getJson('/api/orders?scope=market&time_range=night')->assertOk();
    expect(collect($night->json('data'))->pluck('route'))->toHaveCount(1);
    expect($night->json('data.0.route'))->toBe('서울 중구 → 인천공항');
});

test('api order index market search matches route only, not customer name', function () {
    $tomorrow = now('Asia/Seoul')->addDay()->format('Y-m-d');

    $firstOrder = Order::factory()->create([
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

    // 태그가 달린 운행 — 태그 검색 전용 (노선은 앞 검색어와 겹치지 않게)
    Order::factory()->create([
        'service_date' => $tomorrow,
        'service_time' => '12:00',
        'customer_name' => '노선고객',
        'pickup_location' => '판교역',
        'dropoff_location' => '안양',
        'tags' => ['출장 이동', '새벽 운행'],
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

    // 마켓 검색은 주문번호도 매칭한다 — 해당 운행만 찾는다
    $byOrderNumber = $this->getJson('/api/orders?scope=market&search='.urlencode($firstOrder->order_number))
        ->assertOk();

    $orderNumberRows = collect($byOrderNumber->json('data'));

    expect($orderNumberRows)->toHaveCount(1);
    expect($orderNumberRows->first()['orderNumber'])->toBe($firstOrder->order_number);

    // 태그 검색 — 태그에 달린 단어로 해당 운행만 찾는다 (노선·고객명과 무관)
    $byTag = $this->getJson('/api/orders?scope=market&search='.urlencode('새벽 운행'))
        ->assertOk();

    $tagRows = collect($byTag->json('data'));

    expect($tagRows)->toHaveCount(1);
    expect($tagRows->first()['route'])->toBe('판교역 → 안양');
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

test('진행중 탭은 상태 세분화 필터(전체/수락/운행중)로 나눌 수 있다', function () {
    Order::factory()->create([
        'pickup_location' => '수락출발',
        'dropoff_location' => '인천공항',
        'status' => Order::STATUS_ACCEPTED,
        'claimed_at' => now(),
        'user_id' => $this->driver->id,
    ]);

    Order::factory()->create([
        'pickup_location' => '운행출발',
        'dropoff_location' => '김포공항',
        'status' => Order::STATUS_DRIVING,
        'claimed_at' => now(),
        'user_id' => $this->driver->id,
    ]);

    // 전체 — 수락·운행중 모두 노출
    $this->getJson('/api/orders?scope=mine&source=all&tab='.urlencode('진행중'))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    // 수락만
    $response = $this->getJson('/api/orders?scope=mine&source=all&tab='.urlencode('진행중').'&progress_status=accepted')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    expect(collect($response->json('data'))->pluck('route'))
        ->toContain('수락출발 → 인천공항')
        ->not->toContain('운행출발 → 김포공항');

    // 운행중만
    $response = $this->getJson('/api/orders?scope=mine&source=all&tab='.urlencode('진행중').'&progress_status=driving')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    expect(collect($response->json('data'))->pluck('route'))
        ->toContain('운행출발 → 김포공항')
        ->not->toContain('수락출발 → 인천공항');
});

test('api order index history source returns only finished received orders', function () {
    Order::factory()->create([
        'pickup_location' => '완료출발',
        'dropoff_location' => '인천공항',
        'status' => Order::STATUS_COMPLETED,
        'claimed_at' => now(),
        'user_id' => $this->driver->id,
    ]);

    Order::factory()->create([
        'pickup_location' => '정산출발',
        'dropoff_location' => '김포공항',
        'status' => Order::STATUS_SETTLED,
        'claimed_at' => now(),
        'user_id' => $this->driver->id,
    ]);

    Order::factory()->create([
        'pickup_location' => '취소출발',
        'dropoff_location' => '김포공항',
        'status' => Order::STATUS_CANCELLED,
        'claimed_at' => now(),
        'user_id' => $this->driver->id,
    ]);

    // 아직 끝나지 않은 운행 — 히스토리에 나오면 안 된다
    Order::factory()->create([
        'pickup_location' => '예약출발',
        'dropoff_location' => '인천공항',
        'status' => Order::STATUS_ACCEPTED,
        'claimed_at' => now(),
        'user_id' => $this->driver->id,
    ]);

    Order::factory()->create([
        'pickup_location' => '남의완료',
        'dropoff_location' => '인천공항',
        'status' => Order::STATUS_COMPLETED,
        'claimed_at' => now(),
        'user_id' => $this->marketUser->id,
    ]);

    // 전체 — 완료/정산/취소만
    $response = $this->getJson('/api/orders?scope=mine&source=history&tab='.urlencode('전체'))
        ->assertOk()
        ->assertJsonCount(3, 'data');

    expect(collect($response->json('data'))->pluck('route'))
        ->toContain('완료출발 → 인천공항')
        ->toContain('정산출발 → 김포공항')
        ->toContain('취소출발 → 김포공항')
        ->not->toContain('예약출발 → 인천공항')
        ->not->toContain('남의완료 → 인천공항');

    // 완료 / 정산완료 / 취소 탭 — 각각 1건씩
    $this->getJson('/api/orders?scope=mine&source=history&tab='.urlencode('완료'))
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson('/api/orders?scope=mine&source=history&tab='.urlencode('정산완료'))
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson('/api/orders?scope=mine&source=history&tab='.urlencode('취소'))
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

test('여러 운행을 일괄로 가져오기 요청하면 모두 요청된다', function () {
    $orders = collect();

    foreach (range(1, 3) as $i) {
        $orders->push(Order::factory()->create([
            'status' => Order::STATUS_PUBLISHED,
            'user_id' => $this->marketUser->id,
        ]));
    }

    $this->postJson('/api/orders/batch-claim', [
        'order_ids' => $orders->pluck('id')->all(),
    ])
        ->assertOk()
        ->assertJsonPath('summary.requested', 3)
        ->assertJsonPath('summary.succeeded', 3)
        ->assertJsonPath('summary.failed', 0);

    foreach ($orders as $order) {
        expect($order->fresh()?->claimant_user_id)->toBe($this->driver->id);
    }

    // 같은 일괄 요청으로 보낸 운행끼리는 같은 그룹 키를 공유한다 (요청보냄에서 한 그룹으로 묶기 위함)
    $batchIds = $orders->map(fn ($order) => $order->fresh()?->claim_batch_id)->unique();
    expect($batchIds)->toHaveCount(1);
    expect($batchIds->first())->not->toBeNull();

    // 요청보냄 목록 응답에도 같은 그룹 키가 함께 내려온다 (프론트 그룹핑용)
    $response = $this->getJson('/api/orders?scope=mine&source=all&tab='.urlencode('요청').'&request_category=sent')
        ->assertOk()
        ->assertJsonCount(3, 'data');

    $keys = collect($response->json('data'))->pluck('claimBatchId')->unique();
    expect($keys)->toHaveCount(1);
    expect($keys->first())->toBe($batchIds->first());
});

test('일괄 가져오기 요청은 내 운행만 실패하고 승인 대기 운행에는 추가 신청한다', function () {
    $claimable = Order::factory()->create([
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->marketUser->id,
    ]);

    $mine = Order::factory()->create([
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->driver->id,
    ]);

    $alreadyClaimed = Order::factory()->create([
        'status' => Order::STATUS_ACCEPTANCE_PENDING,
        'user_id' => $this->marketUser->id,
        'claimant_user_id' => User::factory()->create(['id' => 55, 'role' => User::ROLE_DRIVER])->id,
        'claimed_at' => now(),
    ]);

    // 55번 기사의 대기 중인 신청 기록도 함께 남긴다
    OrderClaim::create([
        'order_id' => $alreadyClaimed->id,
        'driver_id' => 55,
        'status' => OrderClaim::STATUS_PENDING,
    ]);

    $this->postJson('/api/orders/batch-claim', [
        'order_ids' => [$claimable->id, $mine->id, $alreadyClaimed->id],
    ])
        ->assertOk()
        ->assertJsonPath('summary.requested', 3)
        // 여러 명 동시 신청이므로 승인 대기 운행에도 추가 신청이 성공한다
        ->assertJsonPath('summary.succeeded', 2)
        ->assertJsonPath('summary.failed', 1)
        ->assertJsonPath('data.0.ok', true)
        ->assertJsonPath('data.1.ok', false)
        ->assertJsonPath('data.2.ok', true);

    expect($claimable->fresh()?->claimant_user_id)->toBe($this->driver->id);
    expect($mine->fresh()?->claimant_user_id)->toBeNull();
    // 승인 대기 운행에는 55번·드라이버 두 명의 신청이 쌓인다 (대표는 가장 최근 신청자)
    expect($alreadyClaimed->fresh()?->claimant_user_id)->toBe($this->driver->id);
    expect($alreadyClaimed->pendingClaims()->count())->toBe(2);
});

test('기사가 아닌 사용자의 일괄 가져오기 요청은 거부된다', function () {
    $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);

    $orderIds = collect(range(1, 2))->map(fn () => Order::factory()->create([
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $operator->id,
    ])->id);

    Sanctum::actingAs($operator);

    $this->postJson('/api/orders/batch-claim', [
        'order_ids' => $orderIds->all(),
    ])->assertForbidden();
});

test('가져오기 요청(승인 대기)은 진행중이 아닌 요청 탭의 요청보냄에 나온다', function () {
    $order = Order::factory()->create([
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->marketUser->id,
    ]);

    $this->postJson("/api/orders/{$order->id}/claim")->assertOk();

    // 진행중 탭에는 승인 대기가 빠진다
    $this->getJson('/api/orders?scope=mine&source=all&tab='.urlencode('진행중'))
        ->assertOk()
        ->assertJsonCount(0, 'data');

    // 요청 탭의 요청보냄(내가 보낸)에는 보인다
    $this->getJson('/api/orders?scope=mine&source=all&tab='.urlencode('요청').'&request_category=sent')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $order->id)
        // 개별(일괄 아님) 요청은 그룹 키가 없다
        ->assertJsonPath('data.0.claimBatchId', null);

    // 요청받음에는 보이지 않는다
    $this->getJson('/api/orders?scope=mine&source=all&tab='.urlencode('요청').'&request_category=received')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('등록자의 운행에 가져오기 요청이 오면 요청 탭의 요청받음에 나온다', function () {
    $order = Order::factory()->create([
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->marketUser->id,
    ]);

    $this->postJson("/api/orders/{$order->id}/claim")->assertOk();

    // 등록자 관점 — 요청받음에 보이고, 요청보냄에는 없다
    Sanctum::actingAs($this->marketUser);

    $this->getJson('/api/orders?scope=mine&source=all&tab='.urlencode('요청').'&request_category=received')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $order->id);

    $this->getJson('/api/orders?scope=mine&source=all&tab='.urlencode('요청').'&request_category=sent')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    // 등록자의 진행중 탭에도 승인 대기가 빠진다
    $this->getJson('/api/orders?scope=mine&source=all&tab='.urlencode('진행중'))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('요청 후 30분이 지난 보낸 요청은 만료로 표시된다', function () {
    $expired = Order::factory()->create([
        'status' => Order::STATUS_ACCEPTANCE_PENDING,
        'user_id' => $this->marketUser->id,
        'claimant_user_id' => $this->driver->id,
        'claimed_at' => now()->subMinutes(31),
    ]);

    OrderClaim::create([
        'order_id' => $expired->id,
        'driver_id' => $this->driver->id,
        'status' => OrderClaim::STATUS_PENDING,
    ]);

    $fresh = Order::factory()->create([
        'status' => Order::STATUS_ACCEPTANCE_PENDING,
        'user_id' => $this->marketUser->id,
        'claimant_user_id' => $this->driver->id,
        'claimed_at' => now(),
    ]);

    OrderClaim::create([
        'order_id' => $fresh->id,
        'driver_id' => $this->driver->id,
        'status' => OrderClaim::STATUS_PENDING,
    ]);

    $response = $this->getJson('/api/orders?scope=mine&source=all&tab='.urlencode('요청').'&request_category=sent')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $byId = collect($response->json('data'))->keyBy('id');

    expect($byId[$expired->id]['claimExpired'])->toBeTrue();
    expect($byId[$fresh->id]['claimExpired'])->toBeFalse();
    expect($byId[$fresh->id]['claimedAt'])->not->toBeNull();
});

test('만료된 가져오기 요청은 승인할 수 없다', function () {
    $order = Order::factory()->create([
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->marketUser->id,
    ]);

    $this->postJson("/api/orders/{$order->id}/claim")->assertOk();

    $claim = OrderClaim::query()->where('order_id', $order->id)->pending()->first();
    expect($claim)->not->toBeNull();

    // 30분이 지나도록 요청 시각을 과거로 되돌린다
    $order->forceFill(['claimed_at' => now()->subMinutes(31)])->save();

    Sanctum::actingAs($this->marketUser);

    $this->postJson("/api/orders/{$order->id}/claim/{$claim->id}/approve")
        ->assertStatus(409)
        ->assertJsonPath('message', '요청이 만료되어 자동 거절되었습니다.');

    // 요청자는 그대로 남아 있다 (자동 거절 표시만 되고 상태는 보존)
    expect($order->fresh()?->claimant_user_id)->toBe($this->driver->id);
});

test('액션 센터의 가져오기 요청에 기사가 사용하는 차량이 포함된다', function () {
    Vehicle::create([
        'user_id' => $this->driver->id,
        'name' => '내 카니발',
        'type' => '카니발',
        'license_plate' => '12가3456',
        'is_default' => true,
    ]);

    $order = Order::factory()->create([
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->marketUser->id,
    ]);

    $this->postJson("/api/orders/{$order->id}/claim")->assertOk();

    Sanctum::actingAs($this->marketUser);

    $this->getJson('/api/actions')
        ->assertOk()
        ->assertJsonCount(1, 'data.claims')
        ->assertJsonPath('data.claims.0.claimant.name', $this->driver->name)
        ->assertJsonPath('data.claims.0.claimant.vehicle.license_plate', '12가3456');
});

test('운행 상세의 가져오기 요청에 기사 정보(평점·차량)가 함께 포함된다', function () {
    Vehicle::create([
        'user_id' => $this->driver->id,
        'name' => '내 카니발',
        'type' => '카니발',
        'license_plate' => '12가3456',
        'is_default' => true,
    ]);

    $order = Order::factory()->create([
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->marketUser->id,
    ]);

    // 기사 평점 소스 — 운행과 무관한 리뷰로 평점·리뷰 수가 내려오는지 확인
    Review::create([
        'order_id' => $order->id,
        'reviewer_id' => $this->marketUser->id,
        'reviewee_id' => $this->driver->id,
        'rating' => 5,
        'content' => '좋아요',
    ]);

    $this->postJson("/api/orders/{$order->id}/claim")->assertOk();

    Sanctum::actingAs($this->driver);

    $this->getJson("/api/orders/{$order->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data.claims')
        ->assertJsonPath('data.claims.0.claim_id', fn ($value) => is_int($value))
        ->assertJsonPath('data.claims.0.driver_id', $this->driver->id)
        ->assertJsonPath('data.claims.0.driver_name', $this->driver->name)
        ->assertJsonPath('data.claims.0.rating', 5)
        ->assertJsonPath('data.claims.0.review_count', 1)
        ->assertJsonPath('data.claims.0.vehicle.license_plate', '12가3456');
});

test('api order claim rejects claiming my own order', function () {
    $order = Order::factory()->create([
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->driver->id,
    ]);

    $this->postJson("/api/orders/{$order->id}/claim")->assertForbidden();
});

test('같은 운행에 두 번째 드라이버도 가져오기 요청을 추가할 수 있다', function () {
    $order = Order::factory()->create([
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->marketUser->id,
    ]);

    // 첫 번째 드라이버가 가져오기 요청
    $this->postJson("/api/orders/{$order->id}/claim")->assertOk();
    expect($order->fresh()?->claimant_user_id)->toBe($this->driver->id);

    // 두 번째 드라이버도 같은 운행에 신청할 수 있다 (여러 명 동시 신청)
    $otherDriver = User::factory()->create(['role' => User::ROLE_DRIVER]);
    Sanctum::actingAs($otherDriver);

    $this->postJson("/api/orders/{$order->id}/claim")->assertOk();

    // 신청 건은 두 건이고, 대표(가장 최근) 신청자는 두 번째 드라이버다
    expect($order->fresh()?->pendingClaims()->count())->toBe(2);
    expect($order->fresh()?->claimant_user_id)->toBe($otherDriver->id);

    // 등록자가 두 번째 드라이버를 승인하면 첫 번째 드라이버의 신청은 자동 거절된다
    $latestClaim = $order->fresh()->pendingClaims()->where('driver_id', $otherDriver->id)->first();

    Sanctum::actingAs($this->marketUser);
    $this->postJson("/api/orders/{$order->id}/claim/{$latestClaim->id}/approve")->assertOk();

    expect($order->fresh()?->status)->toBe(Order::STATUS_ACCEPTED);
    expect($order->fresh()?->user_id)->toBe($otherDriver->id);
    expect(OrderClaim::query()->where('order_id', $order->id)->where('driver_id', $this->driver->id)->first()?->status)
        ->toBe(OrderClaim::STATUS_REJECTED);
});

test('거절당한 드라이버는 30초 내에 같은 운행에 다시 신청할 수 없다', function () {
    $order = Order::factory()->create([
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->marketUser->id,
    ]);

    // 드라이버가 신청 → 등록자가 거절
    $this->postJson("/api/orders/{$order->id}/claim")->assertOk();

    $claim = OrderClaim::query()->where('order_id', $order->id)->pending()->first();
    expect($claim)->not->toBeNull();

    Sanctum::actingAs($this->marketUser);
    $this->postJson("/api/orders/{$order->id}/claim/{$claim->id}/reject")->assertOk();

    // 같은 드라이버가 곧바로 재신청 → 30초 잠금
    Sanctum::actingAs($this->driver);
    $this->postJson("/api/orders/{$order->id}/claim")->assertStatus(429);

    // 30초가 지나면 다시 신청 가능
    Carbon::setTestNow(now()->addSeconds(31));
    $this->postJson("/api/orders/{$order->id}/claim")->assertOk();

    Carbon::setTestNow();
});

test('등록자가 신청 거절 시 사유가 기록되고 기사 알림에 전달된다', function () {
    $order = Order::factory()->create([
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->marketUser->id,
    ]);

    Sanctum::actingAs($this->driver);
    $this->postJson("/api/orders/{$order->id}/claim")->assertOk();

    $claim = OrderClaim::query()->where('order_id', $order->id)->pending()->first();
    expect($claim)->not->toBeNull();

    Sanctum::actingAs($this->marketUser);
    $this->postJson("/api/orders/{$order->id}/claim/{$claim->id}/reject", [
        'reason' => '운행 조건이 맞지 않아요',
    ])->assertOk();

    expect($claim->fresh()?->status)->toBe(OrderClaim::STATUS_REJECTED);
    expect($claim->fresh()?->reject_reason)->toBe('운행 조건이 맞지 않아요');

    $notification = $this->driver->notifications()
        ->where('data->title', '가져오기 요청 거절됨')->first();

    expect($notification?->data['message'] ?? '')->toContain('운행 조건이 맞지 않아요');
});

test('철회한 드라이버도 30초 내에 같은 운행에 다시 신청할 수 없다', function () {
    $order = Order::factory()->create([
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->marketUser->id,
    ]);

    // 드라이버가 신청 → 스스로 철회 (published 복귀)
    $this->postJson("/api/orders/{$order->id}/claim")->assertOk();
    $this->postJson("/api/orders/{$order->id}/status", ['status' => Order::STATUS_PUBLISHED])->assertOk();

    // 같은 드라이버가 곧바로 재신청 → 30초 잠금
    $this->postJson("/api/orders/{$order->id}/claim")->assertStatus(429);

    // 30초가 지나면 다시 신청 가능
    Carbon::setTestNow(now()->addSeconds(31));
    $this->postJson("/api/orders/{$order->id}/claim")->assertOk();

    Carbon::setTestNow();
});

test('기사가 아닌 사용자는 가져오기 요청을 보낼 수 없다', function () {
    $order = Order::factory()->create([
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->marketUser->id,
    ]);

    $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);
    Sanctum::actingAs($operator);

    $this->postJson("/api/orders/{$order->id}/claim")->assertStatus(403);
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
    // 운행중 전환 시 시작 단계(운행시작)가 함께 기록된다
    expect($driving?->ride_step)->toBe(Order::RIDE_STEP_START);
    // 시작 단계의 기록 시각도 함께 남는다
    expect($driving?->ride_step_times)->toHaveKey(Order::RIDE_STEP_START);

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

test('운행중 단계는 기사 본인이 순서대로 진행하고 도착지 도착 시 완료된다', function () {
    $order = Order::factory()->create([
        'status' => Order::STATUS_DRIVING,
        'user_id' => $this->driver->id,
    ]);

    $expectedSteps = Order::rideStepSequence();

    // 운행시작 → 픽업 도착 → 승객 도착 → 출발 → 이동중 순으로 진행
    foreach (array_slice($expectedSteps, 0, -1) as $step) {
        $this->postJson("/api/orders/{$order->id}/ride-step")
            ->assertOk()
            ->assertJsonPath('data.ride_step', $step);

        $fresh = $order->fresh();

        expect($fresh?->ride_step)->toBe($step);
        // 각 단계가 기록된 시각이 스테퍼 추적용으로 함께 남는다
        expect($fresh?->ride_step_times)->toHaveKey($step);
    }

    // 목적지 도착 = 완료 — 마지막 단계(도착지 도착) 기록과 함께 운행이 자동 완료된다
    $this->postJson("/api/orders/{$order->id}/ride-step")
        ->assertOk()
        ->assertJsonPath('data.ride_step', Order::RIDE_STEP_ARRIVED)
        ->assertJsonPath('data.status', Order::STATUS_COMPLETED);

    expect($order->fresh()?->ride_step)->toBe(Order::RIDE_STEP_ARRIVED);
    expect($order->fresh()?->status)->toBe(Order::STATUS_COMPLETED);

    // 완료된 운행에서는 더 이상 단계를 진행할 수 없다
    $this->postJson("/api/orders/{$order->id}/ride-step")
        ->assertStatus(403);

    expect($order->fresh()?->ride_step)->toBe(Order::RIDE_STEP_ARRIVED);
});

test('운행중이 아닌 운행의 단계 진행은 거부된다', function () {
    $order = Order::factory()->create([
        'status' => Order::STATUS_ACCEPTED,
        'user_id' => $this->driver->id,
    ]);

    $this->postJson("/api/orders/{$order->id}/ride-step")->assertStatus(403);

    expect($order->fresh()?->ride_step)->toBeNull();
});

test('운행 수행자가 아닌 사용자의 단계 진행은 거부된다', function () {
    // 남의 운행(수행자 = marketUser)의 단계를 driver가 진행하려 하면 거부
    $order = Order::factory()->create([
        'status' => Order::STATUS_DRIVING,
        'user_id' => $this->marketUser->id,
    ]);

    $this->postJson("/api/orders/{$order->id}/ride-step")->assertStatus(403);

    expect($order->fresh()?->ride_step)->toBeNull();
});

test('내 운행 목록 행에 운행중 단계(rideStep)가 노출된다', function () {
    Order::factory()->create([
        'status' => Order::STATUS_DRIVING,
        'user_id' => $this->driver->id,
        'ride_step' => Order::RIDE_STEP_MOVING,
        'claimed_at' => now(),
    ]);

    $this->getJson('/api/orders?scope=mine&source=all&tab='.urlencode('진행중'))
        ->assertOk()
        ->assertJsonPath('data.0.rideStep', Order::RIDE_STEP_MOVING)
        ->assertJsonPath('data.0.userId', $this->driver->id);
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

test('가져오기 요청을 요청자가 휴지통에서 철회하면 운행이 마켓으로 돌아간다', function () {
    $order = Order::factory()->create([
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->marketUser->id,
    ]);

    $this->actingAs($this->driver)
        ->postJson("/api/orders/{$order->id}/claim")
        ->assertOk();

    // 요청자(드라이버)가 전용 철회 API로 삭제
    $this->actingAs($this->driver)
        ->postJson("/api/orders/{$order->id}/claim/withdraw")
        ->assertOk()
        ->assertJsonPath('data.status', Order::STATUS_PUBLISHED);

    $fresh = $order->fresh();

    expect($fresh?->status)->toBe(Order::STATUS_PUBLISHED);
    expect($fresh?->claimant_user_id)->toBeNull();
    expect($fresh?->claimed_at)->toBeNull();

    // 철회한 드라이버는 30초 동안 같은 운행에 다시 신청할 수 없다
    $this->actingAs($this->driver)
        ->postJson("/api/orders/{$order->id}/claim")
        ->assertStatus(429);
});

test('요청하지 않은 드라이버는 다른 드라이버의 요청을 철회할 수 없다', function () {
    $order = Order::factory()->create([
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->marketUser->id,
    ]);

    $this->actingAs($this->driver)
        ->postJson("/api/orders/{$order->id}/claim")
        ->assertOk();

    $otherDriver = User::factory()->create(['role' => User::ROLE_DRIVER]);

    $this->actingAs($otherDriver)
        ->postJson("/api/orders/{$order->id}/claim/withdraw")
        ->assertStatus(403);

    expect($order->fresh()?->status)->toBe(Order::STATUS_ACCEPTANCE_PENDING);
});

test('claim summary returns the current status and claim time of sent requests', function () {
    // order_number가 시간 기반 시퀀스라 한 번에 여러 건 생성하면 충돌하므로 하나씩 만든다
    $orders = collect();

    for ($i = 0; $i < 3; $i++) {
        $orders->push(Order::factory()->create([
            'status' => Order::STATUS_PUBLISHED,
            'user_id' => $this->marketUser->id,
        ]));
    }

    // 홈의 일괄요청과 동일하게 한 번에 묶어 요청
    $this->actingAs($this->driver)
        ->postJson('/api/orders/batch-claim', [
            'order_ids' => $orders->pluck('id')->all(),
        ])
        ->assertOk();

    // 첫 번째는 승인, 두 번째는 거절, 세 번째는 수락 대기로 남긴다
    $this->actingAs($this->marketUser)
        ->postJson("/api/orders/{$orders[0]->id}/status", ['status' => Order::STATUS_ACCEPTED])
        ->assertOk();

    $this->actingAs($this->marketUser)
        ->postJson("/api/orders/{$orders[1]->id}/status", ['status' => Order::STATUS_PUBLISHED])
        ->assertOk();

    // 요약 — 홈 일괄요청중 카드가 승인/거절 개수와 남은 시간을 계산하는 데 쓰는 응답
    $response = $this->actingAs($this->driver)
        ->postJson('/api/orders/claims/summary', [
            'order_ids' => $orders->pluck('id')->all(),
        ])
        ->assertOk()
        ->assertJsonCount(3, 'data');

    $byId = collect($response->json('data'))->keyBy('id');

    expect($byId[$orders[0]->id]['status'])->toBe(Order::STATUS_ACCEPTED);
    expect($byId[$orders[1]->id]['status'])->toBe(Order::STATUS_PUBLISHED);
    expect($byId[$orders[2]->id]['status'])->toBe(Order::STATUS_ACCEPTANCE_PENDING);
    expect($byId[$orders[2]->id]['claimedAt'])->not->toBeNull();
    expect($byId[$orders[2]->id]['claimBatchId'])->toBeString();
});

test('만료된 요청은 withdraw-expired로 자동 철회되어 바로 다시 요청할 수 있다', function () {
    $order = Order::factory()->create([
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->marketUser->id,
    ]);

    $this->actingAs($this->driver)
        ->postJson("/api/orders/{$order->id}/claim")
        ->assertOk();

    // 30분이 지나 요청이 만료되도록 시각을 되돌린다
    $order->forceFill(['claimed_at' => now()->subMinutes(31)])->save();

    // 만료된 요청 자동 철회 → 운행이 마켓으로 돌아간다
    $this->actingAs($this->driver)
        ->postJson("/api/orders/{$order->id}/claim/withdraw-expired")
        ->assertOk()
        ->assertJsonPath('data.status', Order::STATUS_PUBLISHED);

    $fresh = $order->fresh();

    expect($fresh?->status)->toBe(Order::STATUS_PUBLISHED);
    expect($fresh?->claimant_user_id)->toBeNull();
    expect($fresh?->claimed_at)->toBeNull();

    // 재신청 잠금을 걸지 않아 바로 다시 요청할 수 있다
    $this->actingAs($this->driver)
        ->postJson("/api/orders/{$order->id}/claim")
        ->assertOk()
        ->assertJsonPath('data.status', Order::STATUS_ACCEPTANCE_PENDING);
});

test('만료되지 않은 요청은 withdraw-expired로 철회할 수 없다', function () {
    $order = Order::factory()->create([
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->marketUser->id,
    ]);

    $this->actingAs($this->driver)
        ->postJson("/api/orders/{$order->id}/claim")
        ->assertOk();

    $this->actingAs($this->driver)
        ->postJson("/api/orders/{$order->id}/claim/withdraw-expired")
        ->assertStatus(409);

    expect($order->fresh()?->status)->toBe(Order::STATUS_ACCEPTANCE_PENDING);
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
    // 승인 시점 기록 — 진행중 목록의 '승인받은 시간'에 사용
    expect($fresh?->approved_at)->not->toBeNull();
});

test('driver can request more order details from the registrant', function () {
    $order = Order::factory()->create([
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->marketUser->id,
    ]);

    // 사유·메모를 함께 보내면 등록자 알림에 요청 내용이 담긴다
    $this->postJson("/api/orders/{$order->id}/request-details", [
        'reason' => '승객 연락처가 필요해요',
        'message' => '탑승 시간을 확인하고 싶습니다.',
    ])->assertOk()
        ->assertJsonPath('data.ok', true);

    $notification = $this->marketUser->notifications()->first();
    expect($notification?->data['title'])->toBe('상세 정보 요청');
    expect($notification?->data['order_id'])->toBe($order->id);
    expect($notification?->data['message'])->toContain('승객 연락처가 필요해요');
    expect($notification?->data['message'])->toContain('탑승 시간을 확인하고 싶습니다.');
});

test('registrant cannot request details on their own order', function () {
    $order = Order::factory()->create([
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->marketUser->id,
    ]);

    $this->actingAs($this->marketUser)
        ->postJson("/api/orders/{$order->id}/request-details")
        ->assertStatus(422);

    expect($this->marketUser->notifications()->count())->toBe(0);
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

test('api order store keeps draft when publish is not requested', function () {
    $response = $this->postJson('/api/orders', [
        'pickup_location' => '인천공항 T1',
        'dropoff_location' => '서울 강남',
        'vehicle_type' => '카니발',
        'service_type' => 'pickup',
        'service_date' => '2026-10-01',
        'service_time' => '09:00',
        'expected_revenue' => 90000,
    ])->assertCreated();

    expect($response->json('data.published'))->toBe(0)
        ->and($response->json('data.draft_ids'))->toBe([])
        ->and(Order::query()->findOrFail($response->json('data.id'))->status)->toBe(Order::STATUS_DRAFT);
});

test('api order store publishes an order that meets the market requirements', function () {
    $response = $this->postJson('/api/orders', [
        'pickup_location' => '인천공항 T1',
        'dropoff_location' => '서울 강남',
        'vehicle_type' => '카니발',
        'service_type' => 'pickup',
        'service_date' => '2026-10-01',
        'service_time' => '09:00',
        'expected_revenue' => 90000,
        'publish' => true,
    ])->assertCreated();

    expect($response->json('data.published'))->toBe(1)
        ->and($response->json('data.draft_ids'))->toBe([])
        ->and(Order::query()->findOrFail($response->json('data.id'))->status)->toBe(Order::STATUS_PUBLISHED);
});

test('api order store keeps draft when publish is requested but requirements are missing', function () {
    // 출발지 누락 — 노선이 없으면 마켓에 노출할 수 없으므로 공개를 요청해도 초안으로 남아야 한다
    $response = $this->postJson('/api/orders', [
        'dropoff_location' => '서울 강남',
        'vehicle_type' => '카니발',
        'service_type' => 'pickup',
        'service_date' => '2026-10-01',
        'service_time' => '09:00',
        'publish' => true,
    ])->assertCreated();

    $orderId = $response->json('data.id');

    expect($response->json('data.published'))->toBe(0)
        ->and($response->json('data.draft_ids'))->toBe([$orderId])
        ->and(Order::query()->findOrFail($orderId)->status)->toBe(Order::STATUS_DRAFT);
});

test('api order store publishes even when vehicle and revenue are not stated', function () {
    // 차종·요금을 쓰지 않는 유입 원문이 많으므로, 미지정이어도 공개를 허용한다 (요금은 기사 제안으로 이어진다)
    $response = $this->postJson('/api/orders', [
        'pickup_location' => '인천공항 T1',
        'dropoff_location' => '서울 강남',
        'service_date' => '2026-10-01',
        'service_time' => '09:00',
        'publish' => true,
    ])->assertCreated();

    expect($response->json('data.published'))->toBe(1)
        ->and(Order::query()->findOrFail($response->json('data.id'))->status)->toBe(Order::STATUS_PUBLISHED);
});

test('api order store infers service type from the route when it is not stated', function () {
    // 구분 표기가 없으면 공항이 어느 쪽인지로 픽업/샌딩/시내를 정한다
    $pickup = $this->postJson('/api/orders', [
        'pickup_location' => '인천공항 T1',
        'dropoff_location' => '서울 강남',
        'service_time' => '09:00',
    ])->assertCreated();
    $sending = $this->postJson('/api/orders', [
        'pickup_location' => '서울 강남',
        'dropoff_location' => '인천공항 T1',
        'service_time' => '09:00',
    ])->assertCreated();
    $point = $this->postJson('/api/orders', [
        'pickup_location' => '서울 강남',
        'dropoff_location' => '서울 잠실',
        'service_time' => '09:00',
    ])->assertCreated();

    expect(Order::query()->findOrFail($pickup->json('data.id'))->service_type)->toBe('pickup')
        ->and(Order::query()->findOrFail($sending->json('data.id'))->service_type)->toBe('sending')
        ->and(Order::query()->findOrFail($point->json('data.id'))->service_type)->toBe('point');
});

test('요금 협의 운행은 완료 시 실제 수익을 입력해야 정산할 수 있다', function () {
    $order = Order::factory()->create([
        'status' => Order::STATUS_DRIVING,
        'user_id' => $this->driver->id,
        'expected_revenue' => null,
        'amount_value' => null,
        'actual_revenue' => null,
    ]);

    // 계약 금액도 실제 수익도 없으면 완료할 수 없다 — 정산 원장이 0원으로 마감되는 것을 막는다
    $this->postJson("/api/orders/{$order->id}/status", ['status' => Order::STATUS_COMPLETED])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['actual_revenue']);

    expect($order->fresh()->status)->toBe(Order::STATUS_DRIVING);

    $this->postJson("/api/orders/{$order->id}/status", [
        'status' => Order::STATUS_COMPLETED,
        'actual_revenue' => 90000,
    ])->assertOk();

    expect($order->fresh()->status)->toBe(Order::STATUS_COMPLETED)
        ->and($order->fresh()->actual_revenue)->toBe(90000);
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

test('가져온 운행(진행자)은 등록자가 아니므로 운행 정보를 수정할 수 없다', function () {
    $order = Order::factory()->create([
        'status' => Order::STATUS_DRAFT,
        'user_id' => $this->driver->id,               // 수행자(가져온 기사)
        'original_owner_id' => $this->marketUser->id, // 원 등록자
    ]);

    // 기사(수행자)가 수정을 시도하면 거부되고, 등록자는 수정할 수 있다
    $originalName = $order->customer_name;

    $this->patchJson("/api/orders/{$order->id}", ['customer_name' => '변경시도'])
        ->assertForbidden();

    expect($order->fresh()?->customer_name)->toBe($originalName);

    Sanctum::actingAs($this->marketUser);

    $this->patchJson("/api/orders/{$order->id}", ['customer_name' => '등록자수정'])
        ->assertOk();

    expect($order->fresh()?->customer_name)->toBe('등록자수정');
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

    // 진행자는 정산 전이 불가 (완료가 마지막 — 정산 대기중으로 대기)
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
        ->getJson('/api/orders?scope=mine&source=registered&tab=정산')
        ->assertOk();

    $rows = $response->json('data');
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['status'])->toBe(Order::STATUS_COMPLETED)
        ->and($rows[0]['statusLabel'])->toBe('정산 대기중');
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
