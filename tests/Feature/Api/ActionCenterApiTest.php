<?php

use App\Models\Order;
use App\Models\User;
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
});

test('가져오기 승인 대기 운행이 액션 센터 가져오기 승인에 나온다', function () {
    Sanctum::actingAs($this->driver);
    $this->postJson("/api/orders/{$this->order->id}/claim")->assertOk();

    Sanctum::actingAs($this->owner);

    $this->getJson('/api/actions')
        ->assertOk()
        ->assertJsonCount(1, 'data.claims')
        ->assertJsonPath('data.claims.0.id', $this->order->id)
        ->assertJsonPath('data.claims.0.claimant.name', $this->driver->name);
});

test('요금 제안 대기가 액션 센터 요금 제안에 나온다', function () {
    Sanctum::actingAs($this->driver);
    $this->postJson("/api/orders/{$this->order->id}/offers", ['amount' => 140000])->assertCreated();

    Sanctum::actingAs($this->owner);

    $this->getJson('/api/actions')
        ->assertOk()
        ->assertJsonCount(1, 'data.offers')
        ->assertJsonPath('data.offers.0.id', $this->order->id)
        ->assertJsonPath('data.offers.0.pending_count', 1)
        ->assertJsonPath('data.offers.0.offers.0.amount', 140000);
});

test('상대가 보낸 채팅 요청 카드가 액션 센터에 나온다', function () {
    Sanctum::actingAs($this->driver);
    $conv = $this->postJson('/api/chats', [
        'user_id' => $this->owner->id,
        'order_id' => $this->order->id,
    ])->assertCreated()->json('data');

    $this->postJson("/api/chats/{$conv['id']}/requests", [
        'type' => 'time_change',
        'payload' => ['to_time' => '14:30', 'note' => '일정 변경'],
    ])->assertCreated();

    Sanctum::actingAs($this->owner);

    $this->getJson('/api/actions')
        ->assertOk()
        ->assertJsonCount(1, 'data.chat_requests')
        ->assertJsonPath('data.chat_requests.0.type', 'time_change')
        ->assertJsonPath('data.chat_requests.0.title', '시간 변경 요청')
        ->assertJsonPath('data.chat_requests.0.sender_name', $this->driver->name)
        ->assertJsonPath('data.chat_requests.0.lines.0', '변경 시간: 14:30');
});

test('내가 보낸 채팅 요청은 내 액션 센터에 나오지 않는다', function () {
    Sanctum::actingAs($this->driver);
    $conv = $this->postJson('/api/chats', [
        'user_id' => $this->owner->id,
        'order_id' => $this->order->id,
    ])->assertCreated()->json('data');

    $this->postJson("/api/chats/{$conv['id']}/requests", [
        'type' => 'cancel',
        'payload' => ['reason' => '일정 취소'],
    ])->assertCreated();

    // 본인이 보낸 요청은 본인 액션 센터에서 제외된다
    $this->getJson('/api/actions')->assertOk()->assertJsonCount(0, 'data.chat_requests');

    // 상대(등록자) 액션 센터에는 보인다
    Sanctum::actingAs($this->owner);

    $this->getJson('/api/actions')
        ->assertOk()
        ->assertJsonCount(1, 'data.chat_requests')
        ->assertJsonPath('data.chat_requests.0.type', 'cancel');
});

test('대기 액션이 없으면 세 섹션이 모두 비어 있다', function () {
    Sanctum::actingAs($this->owner);

    $this->getJson('/api/actions')
        ->assertOk()
        ->assertJsonCount(0, 'data.claims')
        ->assertJsonCount(0, 'data.offers')
        ->assertJsonCount(0, 'data.chat_requests');
});
