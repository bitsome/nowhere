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

    // 내가 수락한 운행 — 하차지 '강남', 09:00 출발·소요 60분 → 랜딩 10:00
    Order::factory()->create([
        'user_id' => $this->driver->id,
        'status' => Order::STATUS_ACCEPTED,
        'pickup_location' => '인천공항 T2',
        'dropoff_location' => '서울 강남',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '09:00',
        'estimated_duration_minutes' => 60,
    ]);

    // 마켓 — '서울 강남'에서 출발하는 운행은 복귀 노선 후보 (랜딩 10:00 → 12:00~13:00 시작)
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '인천공항 T1',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '13:00',
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
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '13:00',
    ]);

    $this->getJson('/api/orders/return-routes')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        // 같은 공항이지만 터미널(T2↔T1)이 다르면 일반 추천
        ->assertJsonPath('data.0.recommend_level', 'normal');
});

test('같은 공항 터미널로 이어지면 강력추천이고 터미널이 다르면 일반 추천이다', function () {
    $this->travelTo(Carbon::parse('today 08:00 Asia/Seoul'));

    // 하차 '인천공항 T1' — 랜딩 10:00(낮) → 연결 창 12:00~13:00
    Order::factory()->create([
        'user_id' => $this->driver->id,
        'status' => Order::STATUS_ACCEPTED,
        'pickup_location' => '명동',
        'dropoff_location' => '인천공항 T1',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '09:00',
        'estimated_duration_minutes' => 60,
    ]);

    // 같은 터미널(T1)에서 출발 → 강력추천
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '인천공항 T1',
        'dropoff_location' => '명동',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '13:00',
    ]);

    // 다른 터미널(T2)에서 출발 → 같은 공항이지만 일반 추천
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '인천공항 T2',
        'dropoff_location' => '명동',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '13:00',
    ]);

    $response = $this->getJson('/api/orders/return-routes');

    $response->assertOk()->assertJsonCount(2, 'data');

    // 같은 터미널(T1)이 강력추천으로 앞에 배치된다
    expect($response->json('data.0.route'))->toBe('인천공항 T1 → 명동');
    expect($response->json('data.0.recommend_level'))->toBe('strong');
    expect($response->json('data.1.recommend_level'))->toBe('normal');
});

test('하차지와 같은 구에서 출발하면 강력추천(recommend_level=strong)이다', function () {
    $this->travelTo(Carbon::parse('today 08:00 Asia/Seoul'));

    // 하차 '서울 마포구' — 랜딩 10:00(낮) → 연결 창 12:00~13:00
    Order::factory()->create([
        'user_id' => $this->driver->id,
        'status' => Order::STATUS_ACCEPTED,
        'pickup_location' => '인천공항 T2',
        'dropoff_location' => '서울 마포구',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '09:00',
        'estimated_duration_minutes' => 60,
    ]);

    // 같은 구(마포구)에서 출발 → 강력추천
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 마포구',
        'dropoff_location' => '인천공항',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '13:00',
    ]);

    $this->getJson('/api/orders/return-routes')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.route', '서울 마포구 → 인천공항')
        ->assertJsonPath('data.0.recommend_level', 'strong');
});

test('서울 내 다른 구에서 출발하는 연결은 일반 추천(recommend_level=normal)이다', function () {
    $this->travelTo(Carbon::parse('today 08:00 Asia/Seoul'));

    // 하차 '서울 마포구' — 랜딩 10:00(낮) → 연결 창 12:00~13:00
    Order::factory()->create([
        'user_id' => $this->driver->id,
        'status' => Order::STATUS_ACCEPTED,
        'pickup_location' => '인천공항 T2',
        'dropoff_location' => '서울 마포구',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '09:00',
        'estimated_duration_minutes' => 60,
    ]);

    // 다른 구(강남구)에서 출발 → 같은 서울이지만 일반 추천
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남구',
        'dropoff_location' => '인천공항',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '13:00',
    ]);

    $this->getJson('/api/orders/return-routes')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.recommend_level', 'normal');
});

test('같은 인천이면 구가 달라도 일반 추천으로 연결된다', function () {
    $this->travelTo(Carbon::parse('today 08:00 Asia/Seoul'));

    // 하차 '인천 송도' — 랜딩 10:00(낮) → 연결 창 12:00~13:00
    Order::factory()->create([
        'user_id' => $this->driver->id,
        'status' => Order::STATUS_ACCEPTED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '인천 송도',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '09:00',
        'estimated_duration_minutes' => 60,
    ]);

    // 같은 인천이지만 다른 구(부평)에서 출발 → 같은 시/도 → 일반 추천
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '인천 부평',
        'dropoff_location' => '서울 강남',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '13:00',
    ]);

    $this->getJson('/api/orders/return-routes')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.route', '인천 부평 → 서울 강남')
        ->assertJsonPath('data.0.recommend_level', 'normal');
});

test('하차지가 서비스 지역(서울/인천/경기) 밖이면 왕복 추천 근거에서 제외된다', function () {
    $this->travelTo(Carbon::parse('today 08:00 Asia/Seoul'));

    // 하차지 '강원도 속초' — 서비스 지역 밖 → 복귀 근거로 쓰지 않는다
    Order::factory()->create([
        'user_id' => $this->driver->id,
        'status' => Order::STATUS_ACCEPTED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '강원도 속초',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '09:00',
        'estimated_duration_minutes' => 60,
    ]);

    // 하차지와 상세 지역(속초)이 겹치는 복귀 운행이 있어도 추천되지 않는다
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '속초',
        'dropoff_location' => '서울 강남',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '14:00',
    ]);

    $this->getJson('/api/orders/return-routes')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('밀집 데이터에서 연결 창 운행이 후보 상한(100건)에 잘리지 않고 추천된다', function () {
    $this->travelTo(Carbon::parse('today 08:00 Asia/Seoul'));

    // 하차 '서울 중구' — 랜딩 10:00(낮) → 연결 창 12:00~13:00
    Order::factory()->create([
        'user_id' => $this->driver->id,
        'status' => Order::STATUS_ACCEPTED,
        'pickup_location' => '인천공항 T1',
        'dropoff_location' => '서울 중구',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '09:00',
        'estimated_duration_minutes' => 60,
    ]);

    // 연결 창 이전에 시작하는 운행 100건 이상 — 예전 limit(100)으로는 창 안 운행이 잘렸던 상황
    for ($i = 0; $i < 120; $i++) {
        Order::factory()->create([
            'user_id' => $this->owner->id,
            'status' => Order::STATUS_PUBLISHED,
            'pickup_location' => '서울 강남구',
            'dropoff_location' => '인천공항',
            'service_date' => now('Asia/Seoul')->format('Y-m-d'),
            'service_time' => Carbon::createFromTime(10, 0, 0)->addMinutes($i)->format('H:i'),
        ]);
    }

    // 연결 창 안(13:00~14:00) — 같은 구(중구)에서 출발
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 중구',
        'dropoff_location' => '인천공항 T2',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '13:30',
    ]);

    $this->getJson('/api/orders/return-routes')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.route', '서울 중구 → 인천공항 T2')
        ->assertJsonPath('data.0.recommend_level', 'strong');
});

test('연결 운행 후 하차지에서 이어지는 다음 연결(연결2)도 함께 추천된다', function () {
    $this->travelTo(Carbon::parse('today 08:00 Asia/Seoul'));

    // 맡은 운행 — 랜딩 10:00(낮) → 연결1 창 12:00~13:00
    Order::factory()->create([
        'user_id' => $this->driver->id,
        'status' => Order::STATUS_ACCEPTED,
        'pickup_location' => '인천공항 T1',
        'dropoff_location' => '서울 강남',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '09:00',
        'estimated_duration_minutes' => 60,
    ]);

    // 연결1 — 서울 강남 → 인천공항 T1 (13:00, 랜딩 14:00) → 연결2 창 16:00~17:00
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '인천공항 T1',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '13:00',
        'estimated_duration_minutes' => 60,
    ]);

    // 연결2 — 연결1 하차지(인천공항)에서 이어지는 운행 (공운행 없이 연결)
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '인천공항 T2',
        'dropoff_location' => '서울 강남',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '16:30',
        'estimated_duration_minutes' => 70,
    ]);

    $response = $this->getJson('/api/orders/return-routes');

    $response->assertOk()->assertJsonCount(2, 'data');

    $data = $response->json('data');
    expect($data[1]['route'])->toBe('인천공항 T2 → 서울 강남');
    expect($data[1]['chain_leg'] ?? null)->toBe(3);
    expect($data[1]['chain_prev_id'] ?? null)->toBe($data[0]['id']);
});

test('같은 구에서 출발하는 연결 운행이 우선 추천된다', function () {
    $this->travelTo(Carbon::parse('today 08:00 Asia/Seoul'));

    // 하차지 '서울 중구' — 랜딩 10:00(낮) → 연결 창 12:00~13:00
    Order::factory()->create([
        'user_id' => $this->driver->id,
        'status' => Order::STATUS_ACCEPTED,
        'pickup_location' => '인천공항 T1',
        'dropoff_location' => '서울 중구',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '09:00',
        'estimated_duration_minutes' => 60,
    ]);

    // 다른 구(강남구)에서 출발 — 13:30 (연결 창 안)
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남구',
        'dropoff_location' => '인천공항',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '13:30',
    ]);

    // 같은 구(중구)에서 출발 — 13:00 (연결 창 안) → 같은 구라 앞에 배치
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 중구',
        'dropoff_location' => '인천공항',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '13:00',
    ]);

    $this->getJson('/api/orders/return-routes')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.route', '서울 중구 → 인천공항')
        ->assertJsonPath('data.0.recommend_level', 'strong')
        ->assertJsonPath('data.1.recommend_level', 'normal');
});

test('목적지가 서울이면 구가 달라도 다음 출발 서울 운행이 왕복 추천된다', function () {
    $this->travelTo(Carbon::parse('today 08:00 Asia/Seoul'));

    // 하차지 '서울 마포구' — 랜딩 10:00(낮) → 복귀 창 12:00~13:00
    Order::factory()->create([
        'user_id' => $this->driver->id,
        'status' => Order::STATUS_ACCEPTED,
        'pickup_location' => '인천공항 T2',
        'dropoff_location' => '서울 마포구',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '09:00',
        'estimated_duration_minutes' => 60,
    ]);

    // 같은 구(마포구)에서 출발 → 추천
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 마포구',
        'dropoff_location' => '인천공항',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '13:00',
    ]);

    // 다른 구(강남구)에서 출발 → 목적지·출발지가 모두 서울이므로 구가 달라도 추천
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남구',
        'dropoff_location' => '인천공항',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '13:00',
    ]);

    // 서울이 아닌 지역(부산)에서 출발 → 추천 제외
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '부산 해운대구',
        'dropoff_location' => '울산',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '13:00',
    ]);

    $this->getJson('/api/orders/return-routes')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.route', '서울 마포구 → 인천공항')
        ->assertJsonPath('data.0.recommend_level', 'strong')
        ->assertJsonPath('data.1.recommend_level', 'normal');
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
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '13:00',
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

    // 오늘 09:00 하차 예정, 소요 60분 → 랜딩 10:00 이후부터 복귀 가능
    Order::factory()->create([
        'user_id' => $this->driver->id,
        'status' => Order::STATUS_ACCEPTED,
        'pickup_location' => '인천공항',
        'dropoff_location' => '서울 강남',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '09:00',
        'estimated_duration_minutes' => 60,
    ]);

    // 09:30 출발 — 랜딩(10:00) 전이라 추천 제외
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '인천공항',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '09:30',
    ]);

    // 13:00 출발 — 랜딩(10:00) 후 3시간, 기본 알고리즘(낮 2~3시간) 범위 → 추천
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '인천공항',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '13:00',
    ]);

    $this->getJson('/api/orders/return-routes')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.route', '서울 강남 → 인천공항');
});

test('왕복 추천도 현재 마켓 필터를 반영한다', function () {
    $this->travelTo(Carbon::parse('today 08:00 Asia/Seoul'));

    // 랜딩 — 항공기 도착 07:00, 승객 퇴장 대기 1시간, 운행 1시간 → 실제 하차 09:00
    // → 복귀 창 11:00~12:00 (낮 2~3시간)
    Order::factory()->create([
        'user_id' => $this->driver->id,
        'status' => Order::STATUS_ACCEPTED,
        'pickup_location' => '인천공항',
        'dropoff_location' => '서울 강남',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '07:00',
        'estimated_duration_minutes' => 60,
    ]);

    // 복귀 창 안(11:00~12:00) 운행 2건 — 11:00 / 11:30 (둘 다 오전)
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '인천공항',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '11:00',
    ]);

    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '김포공항',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '11:30',
    ]);

    // 내일 같은 시각에 시작하는 운행 — 복귀 창(당일) 밖이라 추천 제외
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '성남',
        'service_date' => now('Asia/Seoul')->addDays(1)->format('Y-m-d'),
        'service_time' => '10:00',
    ]);

    // 날짜 필터(오늘) — 당일 복귀 창 안 운행만 남는다
    $this->getJson('/api/orders/return-routes?date='.now('Asia/Seoul')->format('Y-m-d'))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    // 시간대 필터 — 복귀 창이 오전이므로 오전 운행만 남는다
    $this->getJson('/api/orders/return-routes?time_range=morning')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->getJson('/api/orders/return-routes?time_range=afternoon')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('야간 시간대는 랜딩 후 1~2시간 창으로 타이트하게 추천한다', function () {
    $this->travelTo(Carbon::parse('today 08:00 Asia/Seoul'));

    // 랜딩 — 항공기 도착 23:00 + 승객 퇴장 대기 1시간 + 운행 1시간 → 실제 하차 다음날 01:00 (야간)
    // → 복귀 창 02:00~03:00 (1~2시간)
    Order::factory()->create([
        'user_id' => $this->driver->id,
        'status' => Order::STATUS_ACCEPTED,
        'pickup_location' => '인천공항',
        'dropoff_location' => '서울 강남',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '23:00',
        'estimated_duration_minutes' => 60,
    ]);

    // 랜딩 후 1시간(02:00) → 창 안 → 추천
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '인천공항',
        'service_date' => now('Asia/Seoul')->addDays(1)->format('Y-m-d'),
        'service_time' => '02:00',
    ]);

    // 랜딩 후 2시간 30분(03:30) → 야간 최대 2시간을 넘어 제외
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '김포공항',
        'service_date' => now('Asia/Seoul')->addDays(1)->format('Y-m-d'),
        'service_time' => '03:30',
    ]);

    $this->getJson('/api/orders/return-routes')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.route', '서울 강남 → 인천공항');
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
