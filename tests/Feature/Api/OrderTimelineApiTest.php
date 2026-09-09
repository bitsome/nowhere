<?php

use App\Models\Order;
use App\Models\OrderClaim;
use App\Models\OrderEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->registrant = User::factory()->create(['name' => '등록자']);
    $this->driver = User::factory()->create(['name' => '수행기사', 'role' => User::ROLE_DRIVER]);
});

test('order status transitions are recorded on the timeline', function () {
    $order = Order::factory()->create([
        'user_id' => $this->driver->id,
        'original_owner_id' => $this->registrant->id,
        'status' => Order::STATUS_ACCEPTED,
    ]);

    Sanctum::actingAs($this->driver);

    $this->postJson("/api/orders/{$order->id}/status", ['status' => Order::STATUS_DRIVING])->assertOk();
    $this->postJson("/api/orders/{$order->id}/status", ['status' => Order::STATUS_COMPLETED])->assertOk();

    $statusEvents = OrderEvent::query()
        ->where('event', OrderEvent::EVENT_STATUS)
        ->orderBy('id')
        ->get();

    expect($statusEvents)->toHaveCount(2);
    expect($statusEvents[0]->from_status)->toBe(Order::STATUS_ACCEPTED);
    expect($statusEvents[0]->to_status)->toBe(Order::STATUS_DRIVING);
    expect($statusEvents[0]->user_id)->toBe($this->driver->id);
    expect($statusEvents[1]->to_status)->toBe(Order::STATUS_COMPLETED);
});

test('ride step advances are recorded on the timeline', function () {
    $order = Order::factory()->create([
        'user_id' => $this->driver->id,
        'original_owner_id' => $this->registrant->id,
        'status' => Order::STATUS_DRIVING,
        'ride_step' => Order::RIDE_STEP_START,
    ]);

    Sanctum::actingAs($this->driver);

    $this->postJson("/api/orders/{$order->id}/ride-step")->assertOk();

    $stepEvent = OrderEvent::query()
        ->where('event', OrderEvent::EVENT_RIDE_STEP)
        ->first();

    expect($stepEvent)->not->toBeNull();
    expect($stepEvent->from_status)->toBe(Order::RIDE_STEP_START);
    expect($stepEvent->to_status)->toBe(Order::RIDE_STEP_PICKUP_ARRIVED);
    expect($stepEvent->user_id)->toBe($this->driver->id);
});

test('cancellation is recorded with its reason', function () {
    $order = Order::factory()->create([
        'user_id' => $this->driver->id,
        'original_owner_id' => $this->registrant->id,
        'status' => Order::STATUS_ACCEPTED,
    ]);

    Sanctum::actingAs($this->driver);

    $this->postJson("/api/orders/{$order->id}/status", [
        'status' => Order::STATUS_CANCELLED,
        'cancel_reason' => '차량 사정',
    ])->assertOk();

    $cancelEvent = OrderEvent::query()
        ->where('event', OrderEvent::EVENT_STATUS)
        ->where('to_status', Order::STATUS_CANCELLED)
        ->first();

    expect($cancelEvent)->not->toBeNull();
    expect($cancelEvent->from_status)->toBe(Order::STATUS_ACCEPTED);
    expect($cancelEvent->note)->toBe('차량 사정');
    expect($order->fresh()?->cancel_reason)->toBe('차량 사정');
});

test('claim acceptance transition is recorded on the timeline', function () {
    $order = Order::factory()->create([
        'user_id' => $this->registrant->id,
        'status' => Order::STATUS_PUBLISHED,
    ]);

    // 기사가 가져오기 신청 → 수락 대기 기록
    Sanctum::actingAs($this->driver);

    $this->postJson("/api/orders/{$order->id}/claim")->assertOk();

    $claim = OrderClaim::query()->firstOrFail();

    // 등록자가 승인 → 예약(accepted) 기록
    Sanctum::actingAs($this->registrant);

    $this->postJson("/api/orders/{$order->id}/claim/{$claim->id}/approve")->assertOk();

    $events = OrderEvent::query()->orderBy('id')->get();

    expect($events)->toHaveCount(2);
    expect($events[0]->to_status)->toBe(Order::STATUS_ACCEPTANCE_PENDING);
    expect($events[1]->to_status)->toBe(Order::STATUS_ACCEPTED);
    expect($events[1]->user_id)->toBe($this->registrant->id);
});

test('order detail returns the timeline history', function () {
    $order = Order::factory()->create([
        'user_id' => $this->driver->id,
        'original_owner_id' => $this->registrant->id,
        'status' => Order::STATUS_ACCEPTED,
    ]);

    $order->orderEvents()->create([
        'user_id' => $this->driver->id,
        'event' => OrderEvent::EVENT_STATUS,
        'from_status' => Order::STATUS_PUBLISHED,
        'to_status' => Order::STATUS_ACCEPTED,
        'note' => '가져오기 승인',
    ]);

    Sanctum::actingAs($this->driver);

    $this->getJson("/api/orders/{$order->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data.timeline')
        ->assertJsonPath('data.timeline.0.to_status', Order::STATUS_ACCEPTED)
        ->assertJsonPath('data.timeline.0.user_name', '수행기사');
});
