<?php

use App\Models\Driver;
use App\Models\Order;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\OrderNotification;
use App\Services\MatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->driver = User::factory()->create([
        'id' => 2,
        'role' => User::ROLE_DRIVER,
    ]);

    $this->owner = User::factory()->create([
        'id' => 99,
        'role' => User::ROLE_OPERATOR,
    ]);

    // 매칭 스캔 전제 조건 — 온라인/매칭 켬은 각 테스트에서 API로 조작한다
    $this->driver->driver()->forceCreate([
        'user_id' => $this->driver->id,
        'status' => Driver::STATUS_OFFLINE,
        'match_enabled' => true,
    ]);

    Sanctum::actingAs($this->driver);
});

/**
 * 드라이버에게 쌓인 '매칭 운행 도착' 알림 수.
 */
function matchNotificationCount(User $driver, Order $order): int
{
    return $driver->notifications()
        ->where('type', OrderNotification::class)
        ->where('data->order_id', $order->id)
        ->where('data->title', '매칭 운행 도착')
        ->count();
}

/**
 * 드라이버에게 쌓인 '조건에 맞는 운행이 있어요' 요약 알림 수.
 */
function matchDigestCount(User $driver): int
{
    return $driver->notifications()
        ->where('type', OrderNotification::class)
        ->where('data->title', '조건에 맞는 운행이 있어요')
        ->count();
}

test('콜링 온라인 전환 시 조건에 맞는 운행이 있으면 요약 알림 1건만 온다', function () {
    $order = Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '인천공항 T2',
        'dropoff_location' => '강남',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
    ]);

    $this->driver->matchPreferences()->create([
        'name' => '인천공항 출발',
        'area' => '인천공항',
        'date_range' => 'today',
        'is_active' => true,
    ]);

    // 오프라인 → 온라인 전환 시점에 보류 매칭을 요약 알림(1건)으로 알린다
    $this->patchJson('/api/me/driver/status', ['status' => 'online'])->assertOk();

    expect(matchDigestCount($this->driver))->toBe(1)
        ->and(matchNotificationCount($this->driver, $order))->toBe(0);
});

test('매칭 설정을 활성으로 등록하면 열려 있는 매칭 운행을 요약 알림 1건으로 알린다', function () {
    $order = Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '인천공항 T1',
        'dropoff_location' => '명동',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
    ]);

    $this->patchJson('/api/me/driver/status', ['status' => 'online'])->assertOk();

    $this->postJson('/api/me/match-preferences', [
        'name' => '공항 콜',
        'area' => '인천공항',
        'date_range' => 'today',
        'is_active' => true,
    ])->assertCreated();

    expect(matchDigestCount($this->driver))->toBe(1)
        ->and(matchNotificationCount($this->driver, $order))->toBe(0);
});

test('요약 알림은 짧은 시간 안에 재스캔해도 중복으로 쌓이지 않는다', function () {
    $order = Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '인천공항 T2',
        'dropoff_location' => '강남',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
    ]);

    $this->driver->matchPreferences()->create([
        'name' => '인천공항 출발',
        'area' => '인천공항',
        'date_range' => 'today',
        'is_active' => true,
    ]);

    $this->patchJson('/api/me/driver/status', ['status' => 'online'])->assertOk();
    expect(matchDigestCount($this->driver))->toBe(1);

    // 온라인 유지 상태에서 매칭 스위치 재호출(재스캔) — 10분 안에는 같은 요약을 다시 보내지 않는다
    $this->patchJson('/api/me/driver/match', ['enabled' => true])->assertOk();

    expect(matchDigestCount($this->driver))->toBe(1)
        ->and(matchNotificationCount($this->driver, $order))->toBe(0);
});

test('조건에 맞지 않는 운행이면 요약 알림도 오지 않는다', function () {
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '부산 해운대',
        'dropoff_location' => '울산',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
    ]);

    $this->driver->matchPreferences()->create([
        'name' => '인천공항 출발',
        'area' => '인천공항',
        'date_range' => 'today',
        'is_active' => true,
    ]);

    $this->patchJson('/api/me/driver/status', ['status' => 'online'])->assertOk();

    expect(matchDigestCount($this->driver))->toBe(0);
});

test('운행 공개 시 조건에 맞는 기사에게 매칭 알림이 도착한다', function () {
    $this->driver->matchPreferences()->create([
        'name' => '인천공항 출발',
        'area' => '인천공항',
        'date_range' => 'today',
        'is_active' => true,
    ]);

    $this->patchJson('/api/me/driver/status', ['status' => 'online'])->assertOk();

    $order = Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_DRAFT,
        'pickup_location' => '인천공항 T2',
        'dropoff_location' => '강남',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
    ]);

    Sanctum::actingAs($this->owner);

    $this->postJson("/api/orders/{$order->id}/status", ['status' => 'published'])->assertOk();

    expect(matchNotificationCount($this->driver, $order))->toBe(1);
});

test('서비스 유형 조건에 맞는 운행만 매칭 알림이 온다', function () {
    $today = now('Asia/Seoul')->format('Y-m-d');

    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '인천공항 T2',
        'dropoff_location' => '강남',
        'service_type' => 'sending',
        'service_date' => $today,
    ]);

    // 픽업 조건을 걸면 샌딩 운행은 매칭되지 않아야 한다
    $this->patchJson('/api/me/driver/status', ['status' => 'online'])->assertOk();

    $this->postJson('/api/me/match-preferences', [
        'name' => '픽업만',
        'service_type' => 'pickup',
        'date_range' => 'today',
        'is_active' => true,
    ])->assertCreated();

    expect(matchDigestCount($this->driver))->toBe(0);
});

test('출발지/도착지 조건은 와일드카드와 국제공항 표기를 모두 잡는다', function () {
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '인천국제공항 T1',
        'dropoff_location' => '강남구 역삼동',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
    ]);

    // '인천%공항' 와일드카드 — '인천공항 T1/T2'·'인천국제공항' 모두 매칭
    $this->patchJson('/api/me/driver/status', ['status' => 'online'])->assertOk();

    $this->postJson('/api/me/match-preferences', [
        'name' => '공항→강남',
        'origin' => '인천%공항',
        'destination' => '강남',
        'date_range' => 'today',
        'is_active' => true,
    ])->assertCreated();

    $order = Order::first();

    expect(matchDigestCount($this->driver))->toBe(1)
        ->and(matchNotificationCount($this->driver, $order))->toBe(0);
});

test('차량 조건 — 기본 차량과 일치하는 운행만 매칭 알림이 온다', function () {
    $vehicle = Vehicle::create([
        'user_id' => $this->driver->id,
        'name' => '우리 차',
        'type' => '스타리아',
        'license_plate' => '12가3456',
        'is_default' => true,
    ]);

    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '인천공항 T2',
        'dropoff_location' => '강남',
        'vehicle_type' => '카니발',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
    ]);

    // 스타리아 차량을 지정하면 카니발 운행은 매칭되지 않아야 한다
    $this->patchJson('/api/me/driver/status', ['status' => 'online'])->assertOk();

    $this->postJson('/api/me/match-preferences', [
        'name' => '내 차로만',
        'vehicle_id' => $vehicle->id,
        'date_range' => 'today',
        'is_active' => true,
    ])->assertCreated();

    expect(matchDigestCount($this->driver))->toBe(0);
});

test('태그 조건 — 운행 태그와 하나라도 겹치면 요약 알림이 온다', function () {
    $order = Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '인천공항 T2',
        'dropoff_location' => '강남',
        'tags' => ['인천공항', '야간'],
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
    ]);

    $this->patchJson('/api/me/driver/status', ['status' => 'online'])->assertOk();

    $this->postJson('/api/me/match-preferences', [
        'name' => '공항 운행',
        'tags' => ['인천공항'],
        'date_range' => 'today',
        'is_active' => true,
    ])->assertCreated();

    expect(matchDigestCount($this->driver))->toBe(1)
        ->and(matchNotificationCount($this->driver, $order))->toBe(0);
});

test('태그 조건 — 운행 태그와 겹치지 않으면 매칭 알림이 오지 않는다', function () {
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '인천공항 T2',
        'dropoff_location' => '강남',
        'tags' => ['야간'],
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
    ]);

    $this->patchJson('/api/me/driver/status', ['status' => 'online'])->assertOk();

    $this->postJson('/api/me/match-preferences', [
        'name' => '강남만',
        'tags' => ['강남'],
        'date_range' => 'today',
        'is_active' => true,
    ])->assertCreated();

    expect(matchDigestCount($this->driver))->toBe(0);
});

test('태그 조건 — 태그가 없는 운행은 태그를 지정한 기사에게 매칭되지 않는다', function () {
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '인천공항 T2',
        'dropoff_location' => '강남',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
    ]);

    $this->patchJson('/api/me/driver/status', ['status' => 'online'])->assertOk();

    $this->postJson('/api/me/match-preferences', [
        'name' => '공항 운행',
        'tags' => ['인천공항'],
        'date_range' => 'today',
        'is_active' => true,
    ])->assertCreated();

    expect(matchDigestCount($this->driver))->toBe(0);
});

test('매칭 설정 저장 시 시간·지역·금액·차량 새 필드를 그대로 반환한다', function () {
    $vehicle = Vehicle::create([
        'user_id' => $this->driver->id,
        'name' => '기본 차',
        'type' => '스타리아',
        'license_plate' => '12가3456',
        'is_default' => true,
    ]);

    $this->patchJson('/api/me/driver/status', ['status' => 'online'])->assertOk();

    $this->postJson('/api/me/match-preferences', [
        'name' => '빠른 매칭',
        'service_type' => 'pickup',
        'origin' => '인천%공항',
        'destination' => '강남',
        'vehicle_id' => $vehicle->id,
        'date_range' => 'today',
        'is_active' => true,
    ])
        ->assertCreated()
        ->assertJsonPath('data.service_type', 'pickup')
        ->assertJsonPath('data.origin', '인천%공항')
        ->assertJsonPath('data.destination', '강남')
        ->assertJsonPath('data.vehicle_id', $vehicle->id);
});

test('공개 운행은 랭킹 상위 N명 기사에게만 자동 제안된다', function () {
    config(['matching.top_n' => 1]);

    $order = Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '인천공항 T2',
        'dropoff_location' => '강남',
        'vehicle_type' => '스타리아',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
    ]);

    // 두 기사 모두 매칭 설정 조건(인천공항)을 충족하지만 랭킹 점수가 다르다
    $top = User::factory()->create(['role' => User::ROLE_DRIVER]);
    $top->driver()->forceCreate([
        'user_id' => $top->id,
        'status' => Driver::STATUS_ONLINE,
        'match_enabled' => true,
    ]);
    $top->matchPreferences()->create([
        'name' => '공항 콜',
        'area' => '인천공항',
        'date_range' => 'today',
        'is_active' => true,
    ]);
    // 차량 조건 일치(+25)로 상위 점수를 만든다
    Vehicle::create([
        'user_id' => $top->id,
        'name' => '우리 차',
        'type' => '스타리아',
        'license_plate' => '11가1111',
        'is_default' => true,
    ]);

    $second = User::factory()->create(['role' => User::ROLE_DRIVER]);
    $second->driver()->forceCreate([
        'user_id' => $second->id,
        'status' => Driver::STATUS_ONLINE,
        'match_enabled' => true,
    ]);
    $second->matchPreferences()->create([
        'name' => '공항 콜',
        'area' => '인천공항',
        'date_range' => 'today',
        'is_active' => true,
    ]);

    $matched = app(MatchService::class)->matchForOrder($order);

    expect($matched)->toBe(1)
        ->and(matchNotificationCount($top, $order))->toBe(1)
        ->and(matchNotificationCount($second, $order))->toBe(0);
});
