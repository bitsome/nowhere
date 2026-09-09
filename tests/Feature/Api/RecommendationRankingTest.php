<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    // 추천 정렬(조건 일치율) 검증에 집중 — 기본 선호도 필터(60%)는 끈다.
    config(['recommendation.min_match_score' => 0]);

    $this->driver = User::factory()->create([
        'role' => User::ROLE_DRIVER,
    ]);

    $this->owner = User::factory()->create([
        'role' => User::ROLE_OPERATOR,
    ]);

    Sanctum::actingAs($this->driver);
});

test('홈 단일 추천은 조건 일치율(match_score)이 높은 운행부터 정렬된다', function () {
    $this->travelTo(Carbon::parse('today 08:00 Asia/Seoul'));

    $date = now('Asia/Seoul')->format('Y-m-d');

    // 기사 차량 — '스타리아' 운행만 차량 조건이 채워진다
    $this->driver->vehicles()->create([
        'name' => '스타리아',
        'type' => '스타리아',
        'is_default' => true,
    ]);

    // 시간대만 설정 — 두 운행 모두 설정 일치(+25)·시간대(+15), 차량(+20) 여부로 점수가 갈린다
    $this->driver->matchPreferences()->create([
        'name' => '주간 운행',
        'start_time' => '06:00',
        'end_time' => '22:00',
        'is_active' => true,
    ]);

    $higher = Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남구',
        'dropoff_location' => '인천공항 T1',
        'vehicle_type' => '스타리아',
        'service_date' => $date,
        'service_time' => '10:00',
    ]);

    $lower = Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '판교',
        'dropoff_location' => '인천공항 T1',
        'vehicle_type' => '그랜저',
        'service_date' => $date,
        'service_time' => '11:00',
    ]);

    $this->getJson('/api/orders/recommendations')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $higher->id)
        ->assertJsonPath('data.0.match_score', 60)
        ->assertJsonPath('data.1.id', $lower->id)
        ->assertJsonPath('data.1.match_score', 40);
});
