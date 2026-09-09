<?php

use App\Models\Order;
use App\Models\OrderClaim;
use App\Models\OrderEvent;
use App\Models\Settlement;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->driver = User::factory()->create([
        'id' => 2,
        'role' => User::ROLE_DRIVER,
        'name' => '홍기사',
    ]);

    $this->customer = User::factory()->create([
        'id' => 99,
        'role' => User::ROLE_CUSTOMER,
        'name' => '김등록',
    ]);
});

// ── 가져오기 요청 자동 만료 (orders:expire-claims) ──

test('claim auto expire returns expired orders to market', function () {
    $order = Order::factory()->create([
        'status' => Order::STATUS_ACCEPTANCE_PENDING,
        'user_id' => $this->customer->id,
        'original_owner_id' => $this->customer->id,
        'claimed_at' => now()->subMinutes(31),
        'pickup_location' => '인천공항 T1',
        'dropoff_location' => '강남',
    ]);

    OrderClaim::create([
        'order_id' => $order->id,
        'driver_id' => $this->driver->id,
        'status' => OrderClaim::STATUS_PENDING,
    ]);

    $this->artisan('orders:expire-claims')->assertSuccessful();

    expect($order->fresh()?->status)->toBe(Order::STATUS_PUBLISHED);
    expect($order->fresh()?->claimed_at)->toBeNull();
    expect($order->fresh()?->claimant_user_id)->toBeNull();

    $claim = OrderClaim::query()->where('order_id', $order->id)->firstOrFail();
    expect($claim->status)->toBe(OrderClaim::STATUS_WITHDRAWN);

    // 등록자에게 만료 알림 + 운행 타임라인 기록
    expect($this->customer->notifications()->where('data->title', '가져오기 요청 만료')->count())->toBe(1);
    expect(OrderEvent::query()->where('order_id', $order->id)->where('event', 'claim_auto_expired')->exists())->toBeTrue();
});

test('claim auto expire keeps orders within window', function () {
    $order = Order::factory()->create([
        'status' => Order::STATUS_ACCEPTANCE_PENDING,
        'user_id' => $this->customer->id,
        'original_owner_id' => $this->customer->id,
        'claimed_at' => now()->subMinutes(5),
    ]);

    $this->artisan('orders:expire-claims')->assertSuccessful();

    expect($order->fresh()?->status)->toBe(Order::STATUS_ACCEPTANCE_PENDING);
});

test('claim auto expire never touches held orders', function () {
    $order = Order::factory()->create([
        'status' => Order::STATUS_ACCEPTANCE_PENDING,
        'user_id' => $this->customer->id,
        'original_owner_id' => $this->customer->id,
        'claimed_at' => now()->subMinutes(31),
        'admin_hold' => true,
    ]);

    $this->artisan('orders:expire-claims')->assertSuccessful();

    expect($order->fresh()?->status)->toBe(Order::STATUS_ACCEPTANCE_PENDING);
});

// ── 완료 운행 자동 정산 (orders:auto-settle) ──

test('completed order is auto settled after grace period', function () {
    $order = Order::factory()->create([
        'status' => Order::STATUS_COMPLETED,
        'user_id' => $this->driver->id,
        'original_owner_id' => $this->customer->id,
        'completed_at' => now()->subHours(25),
        'pickup_location' => '인천공항 T1',
        'dropoff_location' => '명동',
    ]);

    $this->artisan('orders:auto-settle')->assertSuccessful();

    expect($order->fresh()?->status)->toBe(Order::STATUS_SETTLED);

    $settlement = Settlement::query()->where('order_id', $order->id)->first();
    expect($settlement)->not->toBeNull();
    expect($settlement?->driver_id)->toBe($this->driver->id);
    expect($settlement?->registrant_id)->toBe($this->customer->id);

    // 당사자(수행 기사·등록자)에게 각각 정산 알림
    expect($this->driver->notifications()->where('data->title', '정산 완료')->count())->toBe(1);
    expect($this->customer->notifications()->where('data->title', '자동 정산 완료')->count())->toBe(1);
});

test('completed order inside grace period is not settled', function () {
    $order = Order::factory()->create([
        'status' => Order::STATUS_COMPLETED,
        'user_id' => $this->driver->id,
        'original_owner_id' => $this->customer->id,
        'completed_at' => now()->subHours(1),
    ]);

    $this->artisan('orders:auto-settle')->assertSuccessful();

    expect($order->fresh()?->status)->toBe(Order::STATUS_COMPLETED);
    expect(Settlement::query()->where('order_id', $order->id)->exists())->toBeFalse();
});

test('held completed order is never auto settled', function () {
    $order = Order::factory()->create([
        'status' => Order::STATUS_COMPLETED,
        'user_id' => $this->driver->id,
        'original_owner_id' => $this->customer->id,
        'completed_at' => now()->subHours(25),
        'admin_hold' => true,
    ]);

    $this->artisan('orders:auto-settle')->assertSuccessful();

    expect($order->fresh()?->status)->toBe(Order::STATUS_COMPLETED);
});

// ── 알림 피로도 — 같은 (제목·운행) 알림 중복 방지 ──

test('notification service skips duplicate within window', function () {
    $service = app(NotificationService::class);

    $order = Order::factory()->create([
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $this->customer->id,
        'pickup_location' => '인천공항',
        'dropoff_location' => '강남',
    ]);

    expect($service->notifyOnce($this->customer, '중복 테스트', $order->id, '첫 알림'))->toBeTrue();
    expect($service->notifyOnce($this->customer, '중복 테스트', $order->id, '두 번째 알림'))->toBeFalse();

    expect($this->customer->notifications()->where('data->title', '중복 테스트')->count())->toBe(1);
});
