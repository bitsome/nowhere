<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('my orders page requires authentication', function () {
    $this->get(route('my-orders'))
        ->assertRedirect(route('login'));
});

test('my orders page shows tabs and order cards for authenticated users', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    Order::factory()->create([
        'customer_name' => '내오더손님',
        'vehicle_type' => '카니발',
        'service_type' => 'pickup',
        'service_date' => '2026-08-10',
        'service_time' => '09:00',
        'pickup_location' => '인천공항 T1',
        'dropoff_location' => '명동',
        'expected_revenue' => 120000,
        'status' => Order::STATUS_ACCEPTED,
        'claimed_at' => now(),
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('my-orders'))
        ->assertSuccessful()
        ->assertSee('내가 받은 오더')
        ->assertSee('진행중')
        ->assertSee('완료')
        ->assertSee('취소')
        ->assertSee('data-order-tabs', false)
        ->assertSee('data-order-card-list', false)
        ->assertSee('data-order-card-variant="line"', false)
        ->assertSee('data-order-rows', false)
        ->assertSee('인천공항 T1 → 명동')
        ->assertSee('120,000원');
});

test('my orders page filters orders by tab status and shows only my orders', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    $other = User::factory()->create([
        'id' => 99,
    ]);

    Order::factory()->create([
        'customer_name' => '진행중손님',
        'pickup_location' => '진행중출발',
        'dropoff_location' => '인천공항',
        'status' => Order::STATUS_ACCEPTED,
        'claimed_at' => now(),
        'user_id' => $user->id,
    ]);

    Order::factory()->create([
        'customer_name' => '완료손님',
        'pickup_location' => '완료출발',
        'dropoff_location' => '인천공항',
        'status' => Order::STATUS_COMPLETED,
        'claimed_at' => now(),
        'user_id' => $user->id,
    ]);

    Order::factory()->create([
        'customer_name' => '취소손님',
        'pickup_location' => '취소출발',
        'dropoff_location' => '인천공항',
        'status' => Order::STATUS_CANCELLED,
        'claimed_at' => now(),
        'user_id' => $user->id,
    ]);

    Order::factory()->create([
        'customer_name' => '남의손님',
        'pickup_location' => '남의출발',
        'dropoff_location' => '인천공항',
        'status' => Order::STATUS_ACCEPTED,
        'claimed_at' => now(),
        'user_id' => $other->id,
    ]);

    $this->actingAs($user)
        ->get(route('my-orders', ['tab' => '진행중']))
        ->assertSuccessful()
        ->assertSee('진행중출발')
        ->assertDontSee('완료출발')
        ->assertDontSee('취소출발')
        ->assertDontSee('남의출발');

    $this->actingAs($user)
        ->get(route('my-orders', ['tab' => '완료']))
        ->assertSuccessful()
        ->assertSee('완료출발')
        ->assertDontSee('진행중출발');

    $this->actingAs($user)
        ->get(route('my-orders', ['tab' => '취소']))
        ->assertSuccessful()
        ->assertSee('취소출발')
        ->assertDontSee('진행중출발');
});
