<?php

use App\Models\Driver;
use App\Models\Order;
use App\Models\OrderClaim;
use App\Models\Settlement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * 직접 수행 — 등록자가 자기 공개 운행을 기사 모집 없이 본인이 수행한다.
 *
 * 정산 원장·목록 분류가 기존 가져오기(claim→승인) 흐름과 같은 기준을 쓰도록
 * 원 등록자(original_owner_id)와 신청 시각(claimed_at)을 기록하는 것이 핵심이다.
 */
beforeEach(function () {
    $this->owner = User::factory()->create([
        'role' => User::ROLE_CUSTOMER,
        'permissions' => ['order.create', 'order.status.update'],
    ]);

    $this->other = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
    $this->driver = User::factory()->create(['role' => User::ROLE_DRIVER]);

    Sanctum::actingAs($this->owner);
});

test('등록자는 자기 공개 운행을 직접 수행할 수 있다', function () {
    $order = Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'expected_revenue' => 90000,
        'claimed_at' => null,
        'original_owner_id' => null,
    ]);

    $this->postJson("/api/orders/{$order->id}/status", ['status' => Order::STATUS_ACCEPTED])
        ->assertOk()
        ->assertJsonPath('data.status', Order::STATUS_ACCEPTED);

    $fresh = $order->fresh();

    expect($fresh->status)->toBe(Order::STATUS_ACCEPTED)
        ->and($fresh->user_id)->toBe($this->owner->id)
        // 정산 원장·상호 리뷰가 가져오기 흐름과 같은 기준을 쓰도록 원 등록자를 기록한다
        ->and($fresh->original_owner_id)->toBe($this->owner->id)
        // '내 운행'·히스토리 분류가 claimed_at 기준이라 반드시 채워야 한다
        ->and($fresh->claimed_at)->not->toBeNull()
        ->and($fresh->approved_at)->not->toBeNull();

    // 기사 상태 자동 연동 — 직접 수행도 '운행 중'
    expect(Driver::query()->where('user_id', $this->owner->id)->value('status'))
        ->toBe(Driver::STATUS_ON_TRIP);
});

test('남의 공개 운행은 직접 수행할 수 없다', function () {
    $order = Order::factory()->create([
        'user_id' => $this->other->id,
        'status' => Order::STATUS_PUBLISHED,
    ]);

    $this->postJson("/api/orders/{$order->id}/status", ['status' => Order::STATUS_ACCEPTED])
        ->assertForbidden();

    expect($order->fresh()->status)->toBe(Order::STATUS_PUBLISHED);
});

test('기사가 가져오기 신청한 운행은 직접 수행으로 가로챌 수 없다', function () {
    $order = Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
    ]);

    OrderClaim::create([
        'order_id' => $order->id,
        'driver_id' => $this->driver->id,
        'status' => OrderClaim::STATUS_PENDING,
    ]);

    $this->postJson("/api/orders/{$order->id}/status", ['status' => Order::STATUS_ACCEPTED])
        ->assertStatus(409);

    expect($order->fresh()->status)->toBe(Order::STATUS_PUBLISHED)
        ->and($order->fresh()->original_owner_id)->toBeNull();
});

test('공개하지 않은 초안은 직접 수행할 수 없다', function () {
    $order = Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_DRAFT,
    ]);

    $this->postJson("/api/orders/{$order->id}/status", ['status' => Order::STATUS_ACCEPTED])
        ->assertStatus(422);

    expect($order->fresh()->status)->toBe(Order::STATUS_DRAFT);
});

test('직접 수행을 완료·정산하면 등록자와 수행자가 같은 원장이 만들어진다', function () {
    $order = Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'expected_revenue' => 90000,
        'claimed_at' => null,
        'original_owner_id' => null,
    ]);

    $this->postJson("/api/orders/{$order->id}/status", ['status' => Order::STATUS_ACCEPTED])->assertOk();
    $this->postJson("/api/orders/{$order->id}/status", ['status' => Order::STATUS_DRIVING])->assertOk();
    $this->postJson("/api/orders/{$order->id}/status", [
        'status' => Order::STATUS_COMPLETED,
        'actual_revenue' => 90000,
    ])->assertOk();
    $this->postJson("/api/orders/{$order->id}/status", ['status' => Order::STATUS_SETTLED])->assertOk();

    $settlement = Settlement::query()->where('order_id', $order->id)->first();

    expect($settlement)->not->toBeNull()
        ->and($settlement->driver_id)->toBe($this->owner->id)
        // 원 등록자가 기록되어 자기 수행 운행도 수금·출금 흐름에 그대로 올라탄다
        ->and($settlement->registrant_id)->toBe($this->owner->id)
        ->and($settlement->gross_amount)->toBe(90000)
        ->and($settlement->fee_amount)->toBe(4500)
        ->and($settlement->net_amount)->toBe(85500);
});

test('직접 수행한 운행은 마켓에서 내려가고 내 운행에 노출된다', function () {
    $order = Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'claimed_at' => null,
        'original_owner_id' => null,
    ]);

    $this->postJson("/api/orders/{$order->id}/status", ['status' => Order::STATUS_ACCEPTED])->assertOk();

    // 마켓에서 내려간다
    $this->getJson('/api/orders?scope=market')
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 0);

    // 내가 수행하는 운행으로 노출된다
    $this->getJson('/api/orders?scope=mine&source=received&tab=진행중')
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 1);
});
