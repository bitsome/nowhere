<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('order status follows the lifecycle forward through settlement', function () {
    $user = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.status.update'],
    ]);

    // 초안 ↔ 공개 왕복 — 등록자가 공개 후 다시 초안으로 되돌릴 수 있다
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'status' => Order::STATUS_DRAFT,
    ]);

    foreach ([Order::STATUS_PUBLISHED, Order::STATUS_DRAFT, Order::STATUS_PUBLISHED] as $status) {
        $this->actingAs($user)
            ->post(route('dashboard.business.order.status.transition', $order), [
                'status' => $status,
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        expect($order->fresh()?->status)->toBe($status);
    }

    // 수락(claim 승인) 이후 — 운행 → 완료 → 정산
    $accepted = Order::factory()->create([
        'user_id' => $user->id,
        'status' => Order::STATUS_ACCEPTED,
    ]);

    foreach ([Order::STATUS_DRIVING, Order::STATUS_COMPLETED, Order::STATUS_SETTLED] as $status) {
        $this->actingAs($user)
            ->post(route('dashboard.business.order.status.transition', $accepted), [
                'status' => $status,
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        expect($accepted->fresh()?->status)->toBe($status);
    }

    // 정산 이후에는 전환할 수 있는 상태가 없다.
    $this->actingAs($user)
        ->post(route('dashboard.business.order.status.transition', $accepted), [
            'status' => Order::STATUS_CANCELLED,
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($accepted->fresh()?->status)->toBe(Order::STATUS_SETTLED);
});

test('order rejects a status transition that skips lifecycle stages', function () {
    $user = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.status.update'],
    ]);

    $order = Order::factory()->create([
        'user_id' => $user->id,
        'status' => Order::STATUS_DRAFT,
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.business.order.status.transition', $order), [
            'status' => Order::STATUS_COMPLETED,
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($order->fresh()?->status)->toBe(Order::STATUS_DRAFT);
});

test('order cannot be published without required fields', function () {
    $user = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.status.update'],
    ]);

    $order = Order::factory()->create([
        'user_id' => $user->id,
        'status' => Order::STATUS_DRAFT,
        'pickup_location' => null,
        'dropoff_location' => null,
        'vehicle_type' => null,
        'service_type' => null,
        'service_date' => null,
        'service_time' => null,
        'expected_revenue' => null,
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.business.order.status.transition', $order), [
            'status' => Order::STATUS_PUBLISHED,
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($order->fresh()?->status)->toBe(Order::STATUS_DRAFT);
});

test('order can be cancelled from active lifecycle stages but not after settlement', function () {
    $user = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.status.update'],
    ]);

    $activeOrder = Order::factory()->create([
        'user_id' => $user->id,
        'status' => Order::STATUS_PUBLISHED,
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.business.order.status.transition', $activeOrder), [
            'status' => Order::STATUS_CANCELLED,
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect($activeOrder->fresh()?->status)->toBe(Order::STATUS_CANCELLED);
});

test('order status transition requires the order status update permission', function () {
    $user = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.create'],
    ]);

    $other = User::factory()->create([
        'id' => 3,
    ]);

    $order = Order::factory()->create([
        'user_id' => $other->id,
        'status' => Order::STATUS_PUBLISHED,
    ]);

    // 본인 운행이 아니고 상태 전환 권한도 없으면 거절된다
    $this->actingAs($user)
        ->post(route('dashboard.business.order.status.transition', $order), [
            'status' => Order::STATUS_CANCELLED,
        ])
        ->assertForbidden();

    expect($order->fresh()?->status)->toBe(Order::STATUS_PUBLISHED);
});

test('order detail page renders lifecycle status and available next transitions', function () {
    $user = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.create', 'order.status.update'],
    ]);

    $order = Order::factory()->create([
        'user_id' => $user->id,
        'status' => Order::STATUS_DRAFT,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.business.order.show', $order))
        ->assertSuccessful()
        ->assertSee('초안')
        ->assertSee('오더 상태')
        ->assertSee('공개')
        ->assertSee('취소')
        ->assertSee(route('dashboard.business.order.status.transition', $order), false);
});

test('driver can claim a published order from the market', function () {
    $driver = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.create'],
    ]);

    $owner = User::factory()->create([
        'id' => 3,
    ]);

    $order = Order::factory()->create([
        'user_id' => $owner->id,
        'status' => Order::STATUS_PUBLISHED,
    ]);

    $this->actingAs($driver)
        ->post(route('dashboard.business.order.claim', $order))
        ->assertRedirect(route('dashboard.business.order.show', $order))
        ->assertSessionHas('status');

    expect($order->fresh()?->user_id)->toBe($driver->id);
    expect($order->fresh()?->status)->toBe(Order::STATUS_ACCEPTED);
});

test('driver cannot claim their own order', function () {
    $driver = User::factory()->create([
        'id' => 2,
        'permissions' => ['order.create'],
    ]);

    $order = Order::factory()->create([
        'user_id' => $driver->id,
        'status' => Order::STATUS_PUBLISHED,
    ]);

    $this->actingAs($driver)
        ->post(route('dashboard.business.order.claim', $order))
        ->assertForbidden();

    expect($order->fresh()?->status)->toBe(Order::STATUS_PUBLISHED);
});
