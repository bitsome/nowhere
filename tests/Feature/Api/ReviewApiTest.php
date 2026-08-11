<?php

use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create(['id' => 2]);
    // claim(can:create)·상태 전환(can:transition) 권한 필요
    $this->performer = User::factory()->create([
        'id' => 99,
        'permissions' => ['order.create', 'order.status.update'],
    ]);

    // 등록자(owner) 등록 → 수행자(performer)가 claim → 운행·완료까지 진행시키는 헬퍼
    $this->makeCompletedOrder = function () {
        Sanctum::actingAs($this->owner);
        $order = Order::factory()->create([
            'user_id' => $this->owner->id,
            'status' => Order::STATUS_PUBLISHED,
        ]);

        Sanctum::actingAs($this->performer);
        $this->postJson("/api/orders/{$order->id}/claim")->assertOk();
        $this->postJson("/api/orders/{$order->id}/status", ['status' => 'driving'])->assertOk();
        $this->postJson("/api/orders/{$order->id}/status", ['status' => 'completed'])->assertOk();

        Sanctum::actingAs($this->owner);

        return $order;
    };
});

test('claim은 원 등록자를 original_owner_id로 기록한다', function () {
    Sanctum::actingAs($this->owner);
    $order = Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
    ]);

    Sanctum::actingAs($this->performer);
    $this->postJson("/api/orders/{$order->id}/claim")->assertOk();

    expect($order->fresh()->original_owner_id)->toBe($this->owner->id)
        ->and($order->fresh()->user_id)->toBe($this->performer->id);
});

test('완료 오더에서 등록자가 수행자에게 리뷰를 남길 수 있다', function () {
    $order = ($this->makeCompletedOrder)();

    $response = $this->postJson("/api/orders/{$order->id}/review", [
        'rating' => 5,
        'content' => '운행이 정말 깔끔했습니다.',
    ])->assertStatus(201);

    expect($response->json('data.rating'))->toBe(5)
        ->and($response->json('data.reviewer.id'))->toBe($this->owner->id);

    $this->assertDatabaseHas('reviews', [
        'order_id' => $order->id,
        'reviewer_id' => $this->owner->id,
        'reviewee_id' => $this->performer->id,
        'rating' => 5,
    ]);
});

test('운행이 끝나지 않은 오더에는 리뷰를 남길 수 없다', function () {
    Sanctum::actingAs($this->owner);
    $order = Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
    ]);

    $this->postJson("/api/orders/{$order->id}/review", [
        'rating' => 5,
        'content' => '아직 운행 전',
    ])->assertStatus(422);
});

test('한 오더에 같은 작성자의 중복 리뷰는 차단된다', function () {
    $order = ($this->makeCompletedOrder)();

    $this->postJson("/api/orders/{$order->id}/review", [
        'rating' => 5,
        'content' => '첫 리뷰',
    ])->assertStatus(201);

    $this->postJson("/api/orders/{$order->id}/review", [
        'rating' => 3,
        'content' => '두 번째 리뷰',
    ])->assertStatus(422);

    expect(Review::where('order_id', $order->id)->count())->toBe(1);
});

test('리뷰가 쌓이면 공개 프로필의 평점·실적 통계에 반영된다', function () {
    $order = ($this->makeCompletedOrder)();

    $this->postJson("/api/orders/{$order->id}/review", [
        'rating' => 4,
        'content' => '좋아요',
    ])->assertStatus(201);

    $profile = $this->getJson('/api/community/users/'.$this->performer->id)->assertOk();

    expect($profile->json('data.reviewSummary.count'))->toBe(1)
        ->and((float) $profile->json('data.reviewSummary.avg'))->toBe(4.0)
        ->and($profile->json('data.stats.completed_orders'))->toBe(1)
        ->and($profile->json('data.reviews.0.reviewer.name'))->toBe($this->owner->name);
});

test('마켓 목록에 등록자 신뢰 정보(평점·완료 수)가 포함된다', function () {
    $order = ($this->makeCompletedOrder)();

    $this->postJson("/api/orders/{$order->id}/review", [
        'rating' => 5,
        'content' => '최고',
    ])->assertStatus(201);

    // 다른 등록자의 공개 오더를 만들어 마켓에 노출시킨다
    $second = Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'created_at' => now()->subMinutes(30),
    ]);

    Sanctum::actingAs($this->performer);
    $response = $this->getJson('/api/orders?scope=market')->assertOk();

    $rows = collect($response->json('data'));
    $row = $rows->firstWhere('id', $second->id);

    expect($row['owner']['name'])->toBe($this->owner->name)
        ->and($row['owner']['completed_count'])->toBe(0);
});
