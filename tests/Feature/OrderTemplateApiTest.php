<?php

use App\Models\OrderTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('user can store and list their order templates', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/order-templates', [
        'name' => '강남→인천공항',
        'service_type' => 'pickup',
        'vehicle_type' => '카니발',
        'pickup_location' => '서울 강남구',
        'dropoff_location' => '인천공항 T1',
        'passenger_count' => 3,
        'expected_revenue' => 80000,
    ])
        ->assertStatus(201)
        ->assertJsonPath('data.name', '강남→인천공항')
        ->assertJsonPath('data.pickup_location', '서울 강남구');

    $this->getJson('/api/order-templates')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', '강남→인천공항');
});

test('order template store requires a name', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/order-templates', [
        'pickup_location' => '서울 강남구',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('user can only delete their own templates', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $template = OrderTemplate::query()->create([
        'user_id' => $owner->id,
        'name' => '내 템플릿',
    ]);

    // 남의 템플릿은 삭제 불가
    Sanctum::actingAs($other);

    $this->deleteJson("/api/order-templates/{$template->id}")->assertForbidden();
    expect(OrderTemplate::query()->count())->toBe(1);

    // 본인 템플릿은 삭제 가능
    Sanctum::actingAs($owner);

    $this->deleteJson("/api/order-templates/{$template->id}")
        ->assertOk()
        ->assertJsonPath('data.deleted', $template->id);

    expect(OrderTemplate::query()->count())->toBe(0);
});

test('order templates are scoped per user', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    OrderTemplate::query()->create([
        'user_id' => $other->id,
        'name' => '남의 템플릿',
    ]);

    Sanctum::actingAs($owner);

    $this->getJson('/api/order-templates')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
