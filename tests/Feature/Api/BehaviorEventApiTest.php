<?php

use App\Models\BehaviorEvent;
use App\Models\Order;
use App\Models\OrderClaim;
use App\Models\User;
use App\Services\Order\OrderClaimService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    // 등록자(운행 등록) — 별도 권한 불필요
    $this->owner = User::factory()->create(['id' => 2]);

    // 수행 기사 — claim·상태 전환 권한 필요
    $this->driver = User::factory()->create([
        'id' => 99,
        'role' => User::ROLE_DRIVER,
        'permissions' => ['order.create', 'order.status.update'],
    ]);
});

test('claim 신청 시 기사 행동 로그(claim)가 기록된다', function () {
    Sanctum::actingAs($this->owner);
    $order = Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
    ]);

    Sanctum::actingAs($this->driver);
    $this->postJson("/api/orders/{$order->id}/claim")->assertOk();

    $log = BehaviorEvent::query()
        ->where('event', BehaviorEvent::EVENT_CLAIM)
        ->first();

    expect($log)->not->toBeNull();
    expect($log->user_id)->toBe($this->driver->id);
    expect($log->order_id)->toBe($order->id);
});

test('신청→승인→완료 흐름을 끝내면 ride_completed 로그가 남는다', function () {
    Sanctum::actingAs($this->owner);
    $order = Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
    ]);

    Sanctum::actingAs($this->driver);
    $this->postJson("/api/orders/{$order->id}/claim")->assertOk();

    $claim = OrderClaim::query()->where('order_id', $order->id)->pending()->first();

    Sanctum::actingAs($this->owner);
    $this->postJson("/api/orders/{$order->id}/claim/{$claim->id}/approve")->assertOk();

    Sanctum::actingAs($this->driver);
    $this->postJson("/api/orders/{$order->id}/status", ['status' => Order::STATUS_DRIVING])->assertOk();
    $this->postJson("/api/orders/{$order->id}/status", ['status' => Order::STATUS_COMPLETED])->assertOk();

    $log = BehaviorEvent::query()
        ->where('event', BehaviorEvent::EVENT_RIDE_COMPLETED)
        ->first();

    expect($log)->not->toBeNull();
    expect($log->user_id)->toBe($this->driver->id);
    expect($log->order_id)->toBe($order->id);
    expect($log->meta['role'])->toBe(User::ROLE_DRIVER);
});

test('등록자가 신청을 거절하면 해당 기사에게 claim_rejected 로그가 남는다', function () {
    Sanctum::actingAs($this->owner);
    $order = Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
    ]);

    Sanctum::actingAs($this->driver);
    $this->postJson("/api/orders/{$order->id}/claim")->assertOk();

    $claim = OrderClaim::query()->where('order_id', $order->id)->pending()->first();

    Sanctum::actingAs($this->owner);
    $this->postJson("/api/orders/{$order->id}/claim/{$claim->id}/reject")->assertOk();

    $log = BehaviorEvent::query()
        ->where('event', BehaviorEvent::EVENT_CLAIM_REJECTED)
        ->first();

    expect($log)->not->toBeNull();
    expect($log->user_id)->toBe($this->driver->id);
    expect($log->order_id)->toBe($order->id);
});

test('기사가 신청을 직접 철회하면 claim_withdrawn(수동) 로그가 남는다', function () {
    Sanctum::actingAs($this->owner);
    $order = Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
    ]);

    Sanctum::actingAs($this->driver);
    $this->postJson("/api/orders/{$order->id}/claim")->assertOk();
    $this->postJson("/api/orders/{$order->id}/claim/withdraw")->assertOk();

    $log = BehaviorEvent::query()
        ->where('event', BehaviorEvent::EVENT_CLAIM_WITHDRAWN)
        ->first();

    expect($log)->not->toBeNull();
    expect($log->user_id)->toBe($this->driver->id);
    expect($log->meta['cause'])->toBe('manual');
});

test('30분 미승인 자동 만료 시 신청 기사에게 claim_withdrawn(자동) 로그가 남는다', function () {
    Sanctum::actingAs($this->owner);
    $order = Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_ACCEPTANCE_PENDING,
        'claimed_at' => now()->subSeconds(OrderClaimService::CLAIM_EXPIRE_SECONDS + 60),
        'claimant_user_id' => $this->driver->id,
    ]);

    OrderClaim::create([
        'order_id' => $order->id,
        'driver_id' => $this->driver->id,
        'status' => OrderClaim::STATUS_PENDING,
    ]);

    $this->artisan('orders:expire-claims')->assertSuccessful();

    $log = BehaviorEvent::query()
        ->where('event', BehaviorEvent::EVENT_CLAIM_WITHDRAWN)
        ->first();

    expect($log)->not->toBeNull();
    expect($log->user_id)->toBe($this->driver->id);
    expect($log->meta['cause'])->toBe('auto');
});

test('확정 운행 취소 시 ride_cancelled 로그가 남는다', function () {
    $order = Order::factory()->create([
        'user_id' => $this->driver->id,
        'original_owner_id' => $this->owner->id,
        'status' => Order::STATUS_ACCEPTED,
    ]);

    Sanctum::actingAs($this->driver);
    $this->postJson("/api/orders/{$order->id}/status", [
        'status' => Order::STATUS_CANCELLED,
        'cancel_reason' => '차량 사정',
    ])->assertOk();

    $log = BehaviorEvent::query()
        ->where('event', BehaviorEvent::EVENT_RIDE_CANCELLED)
        ->first();

    expect($log)->not->toBeNull();
    expect($log->user_id)->toBe($this->driver->id);
    expect($log->order_id)->toBe($order->id);
    expect($log->meta['cancel_reason'])->toBe('차량 사정');
});

test('행동 로그 저장은 로그인한 사용자만 가능하다', function () {
    $this->postJson('/api/behavior-events', [
        'events' => [['event' => 'impression', 'order_id' => 1]],
    ])->assertStatus(401);
});

test('impression·click 노출 신호를 일괄 저장한다', function () {
    Sanctum::actingAs($this->driver);

    $response = $this->postJson('/api/behavior-events', [
        'events' => [
            ['event' => 'impression', 'order_id' => 10, 'meta' => ['scope' => 'home', 'section' => 'single', 'rank' => 1]],
            ['event' => 'click', 'order_id' => 11, 'meta' => ['scope' => 'market']],
            ['event' => 'impression'],
        ],
    ])->assertOk();

    expect($response->json('stored'))->toBe(3);
    expect(BehaviorEvent::query()->count())->toBe(3);

    $homeImpression = BehaviorEvent::query()->where('event', 'impression')->where('order_id', 10)->first();
    expect($homeImpression->user_id)->toBe($this->driver->id);
    expect($homeImpression->meta['scope'])->toBe('home');
});

test('허용되지 않은 이벤트 종류는 거부된다', function () {
    Sanctum::actingAs($this->driver);

    $this->postJson('/api/behavior-events', [
        'events' => [['event' => 'hack', 'order_id' => 1]],
    ])->assertStatus(422);

    expect(BehaviorEvent::query()->count())->toBe(0);
});

test('한 번에 100건을 초과하는 신호는 거부된다', function () {
    Sanctum::actingAs($this->driver);

    $rows = collect(range(1, 101))->map(fn ($i) => ['event' => 'impression', 'order_id' => $i])->all();

    $this->postJson('/api/behavior-events', ['events' => $rows])->assertStatus(422);

    expect(BehaviorEvent::query()->count())->toBe(0);
});
