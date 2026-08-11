<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('market page requires authentication', function () {
    $this->get(route('market'))
        ->assertRedirect(route('login'));
});

test('market page shows only claimable orders from other users', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    $marketUser = User::factory()->create([
        'id' => 99,
    ]);

    Order::factory()->create([
        'customer_name' => '마켓손님',
        'vehicle_type' => '스타리아 9인승',
        'service_type' => 'pickup',
        'service_date' => '2026-08-10',
        'service_time' => '09:00',
        'pickup_location' => '인천공항 T1',
        'dropoff_location' => '명동',
        'expected_revenue' => 120000,
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $marketUser->id,
    ]);

    // 내 오더는 마켓에 보이지 않는다.
    Order::factory()->create([
        'customer_name' => '내오더손님',
        'pickup_location' => '내오더출발',
        'dropoff_location' => '인천공항',
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $user->id,
    ]);

    // 가져올 수 없는 상태(수락)는 마켓에 보이지 않는다.
    Order::factory()->create([
        'customer_name' => '수락손님',
        'pickup_location' => '수락출발',
        'dropoff_location' => '인천공항',
        'status' => Order::STATUS_ACCEPTED,
        'user_id' => $marketUser->id,
    ]);

    $this->actingAs($user)
        ->get(route('market'))
        ->assertSuccessful()
        ->assertSee('오더 마켓')
        ->assertSee('data-order-card-list', false)
        ->assertSee('data-order-rows', false)
        ->assertSee('"orderNumber":', false)
        ->assertSee('인천공항 T1 → 명동')
        ->assertSee('120,000원')
        ->assertDontSee('내오더출발')
        ->assertDontSee('수락출발');
});

test('market page filters claimable orders by search query', function () {
    $user = User::factory()->create([
        'id' => 2,
    ]);

    $marketUser = User::factory()->create([
        'id' => 99,
    ]);

    Order::factory()->create([
        'customer_name' => '김마켓',
        'pickup_location' => '명동',
        'dropoff_location' => '인천공항',
        'status' => Order::STATUS_TRADING,
        'user_id' => $marketUser->id,
    ]);

    Order::factory()->create([
        'customer_name' => '이마켓',
        'pickup_location' => '잠실',
        'dropoff_location' => '김포공항',
        'status' => Order::STATUS_PUBLISHED,
        'user_id' => $marketUser->id,
    ]);

    $this->actingAs($user)
        ->get(route('market', ['search' => '김마켓']))
        ->assertSuccessful()
        ->assertSee('명동')
        ->assertDontSee('잠실');
});
