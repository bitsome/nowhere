<?php

use App\Models\Driver;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderNotification;
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

test('콜링 온라인 전환 시 조건에 맞는 공개 운행이 매칭 알림으로 도착한다', function () {
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

    // 오프라인 → 온라인 전환 시점에 보류 매칭을 되돌려 받는다
    $this->patchJson('/api/me/driver/status', ['status' => 'online'])->assertOk();

    expect(matchNotificationCount($this->driver, $order))->toBe(1);
});

test('매칭 설정을 활성으로 등록하면 현재 열려 있는 매칭 운행을 즉시 알린다', function () {
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

    expect(matchNotificationCount($this->driver, $order))->toBe(1);
});

test('이미 알림을 받은 운행은 재스캔해도 중복 매칭 알림이 없어야 한다', function () {
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
    expect(matchNotificationCount($this->driver, $order))->toBe(1);

    // 온라인 유지 상태에서 매칭 스위치 재호출(재스캔) — 중복 없이 그대로
    $this->patchJson('/api/me/driver/match', ['enabled' => true])->assertOk();

    expect(matchNotificationCount($this->driver, $order))->toBe(1);
});

test('조건에 맞지 않는 운행은 매칭 알림이 오지 않는다', function () {
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

    expect($this->driver->notifications()
        ->where('type', OrderNotification::class)
        ->where('data->title', '매칭 운행 도착')
        ->count())->toBe(0);
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
