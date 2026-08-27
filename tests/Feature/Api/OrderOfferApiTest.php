<?php

use App\Models\Driver;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\OrderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create([
        'id' => 99,
        'role' => User::ROLE_OPERATOR,
    ]);

    $this->driver = User::factory()->create([
        'id' => 2,
        'role' => User::ROLE_DRIVER,
    ]);

    $this->order = Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '인천공항 T2',
        'dropoff_location' => '강남',
        'expected_revenue' => 150000,
    ]);

    Sanctum::actingAs($this->driver);
});

test('드라이버가 공개 운행에 요금 제안을 보내면 등록자에게 알림이 간다', function () {
    $this->postJson("/api/orders/{$this->order->id}/offers", [
        'amount' => 140000,
        'message' => '요금 협의 가능합니다',
    ])->assertCreated()
        ->assertJsonPath('data.amount', 140000)
        ->assertJsonPath('data.status', 'pending');

    expect(OrderOffer::where('order_id', $this->order->id)->count())->toBe(1);

    expect($this->owner->notifications()
        ->where('type', OrderNotification::class)
        ->where('data->order_id', $this->order->id)
        ->where('data->title', '요금 제안 도착')
        ->count())->toBe(1);
});

test('같은 운행에 중복 제안은 거부된다', function () {
    $this->postJson("/api/orders/{$this->order->id}/offers", ['amount' => 140000])->assertCreated();

    $this->postJson("/api/orders/{$this->order->id}/offers", ['amount' => 130000])->assertStatus(422);
});

test('본인 운행에는 제안할 수 없다', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson("/api/orders/{$this->order->id}/offers", ['amount' => 140000])->assertStatus(403);
});

test('기사가 아닌 사용자는 요금 제안을 보낼 수 없다', function () {
    $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);
    Sanctum::actingAs($operator);

    $this->postJson("/api/orders/{$this->order->id}/offers", ['amount' => 140000])->assertStatus(403);
});

test('등록자는 제안 목록에서 기사 이름과 금액을 보고 연락처는 노출되지 않는다', function () {
    $this->postJson("/api/orders/{$this->order->id}/offers", [
        'amount' => 140000,
        'message' => '오전 출발 가능',
    ])->assertCreated();

    Sanctum::actingAs($this->owner);

    $this->getJson("/api/orders/{$this->order->id}/offers")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.driver.name', $this->driver->name)
        ->assertJsonPath('data.0.amount', 140000)
        ->assertJsonPath('data.0.driver.vehicle', null)
        ->assertJsonMissingPath('data.0.driver.phone')
        ->assertJsonMissingPath('data.0.driver.email');
});

test('제안 목록과 받은 편지함에 기사가 등록한 차량이 포함된다', function () {
    Vehicle::create([
        'user_id' => $this->driver->id,
        'name' => '내 카니발',
        'type' => '카니발',
        'license_plate' => '12가3456',
        'is_default' => true,
    ]);

    $this->postJson("/api/orders/{$this->order->id}/offers", ['amount' => 140000])->assertCreated();

    Sanctum::actingAs($this->owner);

    $this->getJson("/api/orders/{$this->order->id}/offers")
        ->assertOk()
        ->assertJsonPath('data.0.driver.vehicle.license_plate', '12가3456');

    $this->getJson('/api/offers/inbox')
        ->assertOk()
        ->assertJsonPath('data.0.offers.0.driver.vehicle.name', '내 카니발');
});

test('등록자가 제안을 수락하면 운행이 기사에게 넘어가고 다른 제안은 거절된다', function () {
    $otherDriver = User::factory()->create(['id' => 3, 'role' => User::ROLE_DRIVER]);

    $offerA = $this->postJson("/api/orders/{$this->order->id}/offers", ['amount' => 140000])->json('data');

    Sanctum::actingAs($otherDriver);
    $this->postJson("/api/orders/{$this->order->id}/offers", ['amount' => 130000])->assertCreated();

    Sanctum::actingAs($this->owner);

    $this->postJson("/api/orders/{$this->order->id}/offers/{$offerA['id']}/accept")
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted')
        ->assertJsonPath('data.accepted_amount', 140000);

    $this->order->refresh();

    expect($this->order->status)->toBe(Order::STATUS_ACCEPTED);
    expect($this->order->user_id)->toBe($this->driver->id);
    expect($this->order->original_owner_id)->toBe($this->owner->id);

    // 딜 성사 — 수락한 제안 금액이 운행 계약 금액이 된다 (등록 금액 15만 대신 14만)
    expect($this->order->expected_revenue)->toBe(140000);
    expect($this->order->amount_value)->toBe(140000);

    expect(OrderOffer::find($offerA['id'])->status)->toBe(OrderOffer::STATUS_ACCEPTED);
    expect(OrderOffer::where('order_id', $this->order->id)
        ->where('status', OrderOffer::STATUS_REJECTED)->count())->toBe(1);

    // 수락된 기사는 운행 중 상태 + XP + 알림
    expect($this->driver->driver()->first()->status)->toBe(Driver::STATUS_ON_TRIP);
    expect($this->driver->refresh()->xp)->toBe(20);
    expect($this->driver->notifications()
        ->where('type', OrderNotification::class)
        ->where('data->title', '요금 제안 수락됨')->count())->toBe(1);
});

test('등록자가 제안을 거절하면 제안만 거절되고 운행은 마켓에 남는다', function () {
    $offer = $this->postJson("/api/orders/{$this->order->id}/offers", ['amount' => 140000])->json('data');

    Sanctum::actingAs($this->owner);

    $this->postJson("/api/orders/{$this->order->id}/offers/{$offer['id']}/reject")->assertOk();

    expect(OrderOffer::find($offer['id'])->status)->toBe(OrderOffer::STATUS_REJECTED);
    expect($this->order->refresh()->status)->toBe(Order::STATUS_PUBLISHED);
});

test('드라이버가 본인 대기 제안을 철회할 수 있다', function () {
    $offer = $this->postJson("/api/orders/{$this->order->id}/offers", ['amount' => 140000])->json('data');

    $this->deleteJson("/api/orders/{$this->order->id}/offers/{$offer['id']}")->assertOk();

    expect(OrderOffer::find($offer['id'])->status)->toBe(OrderOffer::STATUS_CANCELLED);
});

test('운행 등록자가 아닌 사용자는 제안을 수락할 수 없다', function () {
    $offer = $this->postJson("/api/orders/{$this->order->id}/offers", ['amount' => 140000])->json('data');

    $intruder = User::factory()->create(['role' => User::ROLE_OPERATOR]);
    Sanctum::actingAs($intruder);

    $this->postJson("/api/orders/{$this->order->id}/offers/{$offer['id']}/accept")->assertStatus(403);
});

test('가져오기 요청이 걸리면 남아 있는 대기 제안이 자동 정리된다', function () {
    $this->postJson("/api/orders/{$this->order->id}/offers", ['amount' => 140000])->assertCreated();

    $claimant = User::factory()->create(['id' => 4, 'role' => User::ROLE_DRIVER]);
    Sanctum::actingAs($claimant);

    $this->postJson("/api/orders/{$this->order->id}/claim")->assertOk();

    expect(OrderOffer::where('order_id', $this->order->id)
        ->where('status', OrderOffer::STATUS_CANCELLED)->count())->toBe(1);
});

test('제안 도착 알림에 제안 정보(offer_id·금액)가 담기고 알림 API에도 노출된다', function () {
    $this->postJson("/api/orders/{$this->order->id}/offers", ['amount' => 140000])->assertCreated();

    $notification = $this->owner->notifications()
        ->where('type', OrderNotification::class)
        ->where('data->title', '요금 제안 도착')
        ->first();

    expect($notification->data['offer_id'])->toBeInt();
    expect($notification->data['offer_amount'])->toBe(140000);

    Sanctum::actingAs($this->owner);

    $this->getJson('/api/notifications')
        ->assertOk()
        ->assertJsonPath('data.0.offer_id', $notification->data['offer_id'])
        ->assertJsonPath('data.0.offer_amount', 140000);
});

test('등록자는 제안 받은 편지함에서 내 운행의 대기 제안을 금액순으로 확인한다', function () {
    $otherDriver = User::factory()->create(['id' => 3, 'role' => User::ROLE_DRIVER]);

    $this->postJson("/api/orders/{$this->order->id}/offers", ['amount' => 140000])->assertCreated();

    Sanctum::actingAs($otherDriver);
    $this->postJson("/api/orders/{$this->order->id}/offers", ['amount' => 145000])->assertCreated();

    Sanctum::actingAs($this->owner);

    $this->getJson('/api/offers/inbox')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->order->id)
        ->assertJsonPath('data.0.pending_count', 2)
        ->assertJsonCount(2, 'data.0.offers')
        ->assertJsonPath('data.0.offers.0.amount', 145000)
        ->assertJsonPath('data.0.offers.0.driver.name', $otherDriver->name)
        ->assertJsonMissingPath('data.0.offers.0.driver.phone')
        ->assertJsonMissingPath('data.0.offers.0.driver.email');
});

test('대기 제안이 없는 등록자는 받은 편지함이 비어 있다', function () {
    Sanctum::actingAs($this->owner);

    $this->getJson('/api/offers/inbox')->assertOk()->assertJsonCount(0, 'data');
});

test('내 등록 운행 목록에 대기 제안 건수 배지가 포함된다', function () {
    $otherDriver = User::factory()->create(['id' => 3, 'role' => User::ROLE_DRIVER]);

    $this->postJson("/api/orders/{$this->order->id}/offers", ['amount' => 140000])->assertCreated();

    Sanctum::actingAs($otherDriver);
    $this->postJson("/api/orders/{$this->order->id}/offers", ['amount' => 145000])->assertCreated();

    Sanctum::actingAs($this->owner);

    $this->getJson('/api/orders?scope=mine&source=registered&tab='.urlencode('공개'))
        ->assertOk()
        ->assertJsonPath('data.0.pendingOffers', 2);
});
