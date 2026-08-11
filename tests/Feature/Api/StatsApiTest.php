<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['id' => 2]);
    Sanctum::actingAs($this->user);
});

test('stats orders returns daily series with revenue', function () {
    Order::factory()->create([
        'user_id' => $this->user->id,
        'status' => Order::STATUS_COMPLETED,
        'expected_revenue' => 50000,
        'created_at' => now(),
    ]);

    $response = $this->getJson('/api/stats/orders?days=7')->assertOk();

    expect($response->json('data.daily'))->toHaveCount(7)
        ->and($response->json('data.daily.6.revenue'))->toBe(50000)
        ->and($response->json('data.summary.total'))->toBe(1);
});

test('stats orders includes monthly series and status distribution', function () {
    Order::factory()->create([
        'user_id' => $this->user->id,
        'status' => Order::STATUS_COMPLETED,
        'expected_revenue' => 30000,
        'created_at' => now(),
    ]);

    Order::factory()->create([
        'user_id' => $this->user->id,
        'status' => Order::STATUS_CANCELLED,
        'created_at' => now(),
    ]);

    $response = $this->getJson('/api/stats/orders?days=30')->assertOk();

    expect($response->json('data.monthly'))->toHaveCount(6)
        ->and($response->json('data.monthly.5.revenue'))->toBe(30000);

    $distribution = collect($response->json('data.statusDistribution'));

    expect($distribution->firstWhere('status', Order::STATUS_COMPLETED)['count'])->toBe(1)
        ->and($distribution->firstWhere('status', Order::STATUS_CANCELLED)['count'])->toBe(1);
});

test('stats orders upcoming lists today service date', function () {
    Order::factory()->create([
        'user_id' => $this->user->id,
        'status' => Order::STATUS_ACCEPTED,
        'service_date' => now()->format('Y-m-d'),
        'pickup_location' => '인천공항',
        'dropoff_location' => '명동',
    ]);

    $response = $this->getJson('/api/stats/orders')->assertOk();

    expect($response->json('data.upcoming.today'))->toBe(1)
        ->and($response->json('data.upcoming.todayList.0.route'))->toBe('인천공항 → 명동');
});
