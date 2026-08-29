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

test('내가 맡은 운행의 하차지 근처에서 시작하는 마켓 운행이 왕복 추천된다', function () {
    $this->travelTo(Carbon::parse('today 08:00 Asia/Seoul'));

    // 내가 수락한 운행 — 하차지 '강남', 09:00 출발·소요 60분 → 10:30부터 복귀 가능
    Order::factory()->create([
        'user_id' => $this->driver->id,
        'status' => Order::STATUS_ACCEPTED,
        'pickup_location' => '인천공항 T2',
        'dropoff_location' => '서울 강남',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '09:00',
        'estimated_duration_minutes' => 60,
    ]);

    // 마켓 — '서울 강남'에서 출발하는 운행은 복귀 노선 후보
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '인천공항 T1',
        'service_date' => now('Asia/Seoul')->addDays(1)->format('Y-m-d'),
        'service_time' => '11:00',
    ]);

    // 마켓 — 무관한 지역에서 출발하는 운행은 추천되지 않는다
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '부산 해운대',
        'dropoff_location' => '울산',
        'service_date' => now('Asia/Seoul')->addDays(1)->format('Y-m-d'),
        'service_time' => '11:00',
    ]);

    $this->getJson('/api/orders/return-routes')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.route', '서울 강남 → 인천공항 T1');
});

test('공항 터미널 코드가 달라도 같은 공항이면 왕복 추천된다', function () {
    $this->travelTo(Carbon::parse('today 08:00 Asia/Seoul'));

    Order::factory()->create([
        'user_id' => $this->driver->id,
        'status' => Order::STATUS_ACCEPTED,
        'pickup_location' => '명동',
        'dropoff_location' => '인천공항 T2',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '09:00',
        'estimated_duration_minutes' => 60,
    ]);

    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '인천공항 T1',
        'dropoff_location' => '명동',
        'service_date' => now('Asia/Seoul')->addDays(1)->format('Y-m-d'),
        'service_time' => '11:00',
    ]);

    $this->getJson('/api/orders/return-routes')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('시 단위 토큰만으로 겹치는 운행은 왕복 추천되지 않는다', function () {
    $this->travelTo(Carbon::parse('today 08:00 Asia/Seoul'));

    // 하차지 '서울 마포구' — '서울' 시 단위만 겹치는 강남구 운행은 추천 제외
    Order::factory()->create([
        'user_id' => $this->driver->id,
        'status' => Order::STATUS_ACCEPTED,
        'pickup_location' => '인천공항 T2',
        'dropoff_location' => '서울 마포구',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '09:00',
        'estimated_duration_minutes' => 60,
    ]);

    // 마포구에서 출발하는 운행 → 추천
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 마포구',
        'dropoff_location' => '인천공항',
        'service_date' => now('Asia/Seoul')->addDays(1)->format('Y-m-d'),
        'service_time' => '11:00',
    ]);

    // 강남구에서 출발하는 운행 → 같은 시지만 구가 다르므로 추천 제외
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남구',
        'dropoff_location' => '인천공항',
        'service_date' => now('Asia/Seoul')->addDays(1)->format('Y-m-d'),
        'service_time' => '11:00',
    ]);

    $this->getJson('/api/orders/return-routes')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.route', '서울 마포구 → 인천공항');
});

test('구 접미사가 달라도 같은 상세 구역이면 왕복 추천된다', function () {
    $this->travelTo(Carbon::parse('today 08:00 Asia/Seoul'));

    // 하차지 '강남' ↔ 출발지 '서울 강남구' — 접미사 정규화로 매칭
    Order::factory()->create([
        'user_id' => $this->driver->id,
        'status' => Order::STATUS_ACCEPTED,
        'pickup_location' => '인천공항 T2',
        'dropoff_location' => '강남',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '09:00',
        'estimated_duration_minutes' => 60,
    ]);

    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남구',
        'dropoff_location' => '인천공항',
        'service_date' => now('Asia/Seoul')->addDays(1)->format('Y-m-d'),
        'service_time' => '11:00',
    ]);

    $this->getJson('/api/orders/return-routes')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('맡은 운행이 없으면 왕복 추천도 비어 있다', function () {
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '인천공항',
        'service_date' => now('Asia/Seoul')->addDays(1)->format('Y-m-d'),
    ]);

    $this->getJson('/api/orders/return-routes')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('서비스 시각이 지난 맡은 운행은 왕복 추천 근거에서 제외된다', function () {
    $this->travelTo(Carbon::parse('today 08:00 Asia/Seoul'));

    // 어제 서비스 시각이 지난 운행 — 근거로 쓰지 않는다
    Order::factory()->create([
        'user_id' => $this->driver->id,
        'status' => Order::STATUS_ACCEPTED,
        'pickup_location' => '인천공항',
        'dropoff_location' => '서울 강남',
        'service_date' => now('Asia/Seoul')->subDay()->format('Y-m-d'),
        'service_time' => '09:00',
    ]);

    // 하차지 근처에서 출발하는 마켓 운행이 있어도 추천되지 않는다
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '인천공항',
        'service_date' => now('Asia/Seoul')->addDays(1)->format('Y-m-d'),
        'service_time' => '11:00',
    ]);

    $this->getJson('/api/orders/return-routes')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('하차 예정 시각이 지나야 왕복 추천된다', function () {
    $this->travelTo(Carbon::parse('today 08:00 Asia/Seoul'));

    // 오늘 09:00 하차 예정, 소요 60분 → 하차 이후 10:30부터 복귀 가능
    Order::factory()->create([
        'user_id' => $this->driver->id,
        'status' => Order::STATUS_ACCEPTED,
        'pickup_location' => '인천공항',
        'dropoff_location' => '서울 강남',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '09:00',
        'estimated_duration_minutes' => 60,
    ]);

    // 09:30 출발 — 하차 전이라 추천 제외
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '인천공항',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '09:30',
    ]);

    // 11:00 출발 — 하차(10:30) 이후라 추천
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '인천공항',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '11:00',
    ]);

    $this->getJson('/api/orders/return-routes')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.route', '서울 강남 → 인천공항');
});

test('왕복 추천도 현재 마켓 필터를 반영한다', function () {
    $this->travelTo(Carbon::parse('today 08:00 Asia/Seoul'));

    Order::factory()->create([
        'user_id' => $this->driver->id,
        'status' => Order::STATUS_ACCEPTED,
        'pickup_location' => '인천공항',
        'dropoff_location' => '서울 강남',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '09:00',
        'estimated_duration_minutes' => 60,
    ]);

    $tomorrow = now('Asia/Seoul')->addDays(1)->format('Y-m-d');

    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '인천공항',
        'service_date' => $tomorrow,
        'service_time' => '11:00',
    ]);

    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '김포공항',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '11:00',
    ]);

    // 날짜 필터(내일)를 걸면 내일 운행만 추천된다
    $this->getJson('/api/orders/return-routes?date='.$tomorrow)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.route', '서울 강남 → 인천공항');

    // 시간대 필터(오전)를 걸면 오전 출발 운행만 남는다
    $this->getJson('/api/orders/return-routes?time_range=morning')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('이미 가져오기 요청이 걸린 운행과 내가 등록한 운행은 왕복 추천에서 제외된다', function () {
    Order::factory()->create([
        'user_id' => $this->driver->id,
        'status' => Order::STATUS_ACCEPTED,
        'pickup_location' => '인천공항',
        'dropoff_location' => '서울 강남',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
    ]);

    // 가져오기 요청(수락 대기) — 마켓 노출 대상에서 제외
    $claimant = User::factory()->create(['id' => 7, 'role' => User::ROLE_DRIVER]);

    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_ACCEPTANCE_PENDING,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '인천공항',
        'service_date' => now('Asia/Seoul')->addDays(1)->format('Y-m-d'),
        'claimant_user_id' => $claimant->id,
        'claimed_at' => now(),
    ]);

    // 내가 등록한 운행 — 추천 대상에서 제외
    $mine = Order::factory()->create([
        'user_id' => $this->driver->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '잠실',
        'service_date' => now('Asia/Seoul')->addDays(1)->format('Y-m-d'),
    ]);

    $this->getJson('/api/orders/return-routes')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
