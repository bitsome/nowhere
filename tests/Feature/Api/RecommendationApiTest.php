<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->driver = User::factory()->create([
        'id' => 2,
        'role' => User::ROLE_DRIVER,
    ]);

    $this->owner = User::factory()->create([
        'id' => 99,
        'role' => User::ROLE_OPERATOR,
    ]);

    Sanctum::actingAs($this->driver);
});

test('일정이 없어도 활성 매칭 설정 조건에 맞는 운행을 추천한다', function () {
    $this->driver->matchPreferences()->create([
        'name' => '강남 선호',
        'area' => '서울 강남',
        'is_active' => true,
    ]);

    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '인천공항',
        'service_date' => now('Asia/Seoul')->addDays(1)->format('Y-m-d'),
        'service_time' => '11:00',
    ]);

    $this->getJson('/api/orders/recommendations')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.recommend_reason', '매칭 설정');
});

test('매칭 설정이 없으면 자주 다니는 노선(운행 이력) 기준으로 추천한다', function () {
    // 최근 완료한 운행 — 하차지 '서울 강남' (자주 다니는 지역)
    Order::factory()->create([
        'user_id' => $this->driver->id,
        'status' => Order::STATUS_COMPLETED,
        'pickup_location' => '인천공항',
        'dropoff_location' => '서울 강남',
        'service_date' => now('Asia/Seoul')->subDays(2)->format('Y-m-d'),
        'service_time' => '09:00',
    ]);

    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '잠실',
        'service_date' => now('Asia/Seoul')->addDays(1)->format('Y-m-d'),
        'service_time' => '11:00',
    ]);

    $this->getJson('/api/orders/recommendations')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.recommend_reason', '자주 다니는 노선');
});

test('현재 맡은 운행이 있으면 왕복 노선 추천을 우선한다', function () {
    $this->travelTo(Carbon::parse('today 08:00 Asia/Seoul'));

    Order::factory()->create([
        'user_id' => $this->driver->id,
        'status' => Order::STATUS_ACCEPTED,
        'pickup_location' => '인천공항 T2',
        'dropoff_location' => '서울 강남',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '09:00',
        'estimated_duration_minutes' => 60,
    ]);

    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '인천공항 T1',
        'service_date' => now('Asia/Seoul')->addDays(1)->format('Y-m-d'),
        'service_time' => '11:00',
    ]);

    $this->getJson('/api/orders/recommendations')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.recommend_reason', '왕복 노선');
});

test('일정·설정·이력이 모두 없으면 추천이 비어 있다', function () {
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '부산 해운대',
        'dropoff_location' => '울산',
        'service_date' => now('Asia/Seoul')->addDays(1)->format('Y-m-d'),
        'service_time' => '11:00',
    ]);

    $this->getJson('/api/orders/recommendations')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
