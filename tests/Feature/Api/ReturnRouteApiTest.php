<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    // 왕복 체인 로직 검증에 집중 — 기본 선호도 필터(60%)는 끈다.
    config(['recommendation.min_match_score' => 0]);

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

test('복귀(연결) 운행 카드에도 조건 일치율과 추천 근거가 붙는다', function () {
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
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '13:00',
        'amount_value' => 70000,
    ]);

    $response = $this->getJson('/api/orders/return-routes');

    $response->assertOk()->assertJsonCount(1, 'data');

    $row = $response->json('data.0');

    // 동선/연결 근거(10점) 이상이면서 공항·연결 근거 문구가 카드에 붙는다
    expect($row['match_score'])->toBeInt()->toBeGreaterThanOrEqual(10);
    expect($row['match_reasons'])->toBeArray()->toContain('현재 운행과 동선이 좋음')->toContain('공항 운행');
});

test('공항 터미널 코드가 달라도 같은 공항이면 강력추천된다', function () {
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

    // 랜딩(공항 픽업) — 샌딩 시작(09:00) 후 30분~2시간 창(09:30~11:00)에 시작
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '인천공항 T1',
        'dropoff_location' => '명동',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '10:30',
    ]);

    $this->getJson('/api/orders/return-routes')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        // 같은 공항(인천공항)이면 터미널(T2↔T1)이 달라도 강력추천
        ->assertJsonPath('data.0.recommend_level', 'strong');
});

test('같은 공항이면 터미널이 달라도 모두 강력추천이다', function () {
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

    // 랜딩(공항 픽업) — 샌딩 시작(09:00) 후 30분~2시간 창(09:30~11:00)에 시작
    // 같은 터미널(T1)에서 출발 → 강력추천
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '인천공항 T1',
        'dropoff_location' => '명동',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '10:30',
    ]);

    // 다른 터미널(T2)에서 출발 → 같은 공항이므로 강력추천
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '인천공항 T2',
        'dropoff_location' => '명동',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '10:30',
    ]);

    $response = $this->getJson('/api/orders/return-routes');

    $response->assertOk()->assertJsonCount(2, 'data');

    expect($response->json('data.0.route'))->toBe('인천공항 T1 → 명동');
    expect($response->json('data.0.recommend_level'))->toBe('strong');
    expect($response->json('data.1.route'))->toBe('인천공항 T2 → 명동');
    expect($response->json('data.1.recommend_level'))->toBe('strong');
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

test('하차지와 다른 구에서 출발하는 연결은 추천되지 않는다', function () {
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

    // 다른 구(강남구)에서 출발 → 같은 서울이지만 구가 달라 추천 제외
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
        ->assertJsonCount(0, 'data');
});

test('같은 인천이어도 구가 다르면 연결되지 않는다', function () {
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

    // 같은 인천이지만 다른 구(부평)에서 출발 → 구가 달라 추천 제외
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
        ->assertJsonCount(0, 'data');
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

    // 연결1 — 서울 강남 → 인천공항 T1 (13:00) → 연결2(랜딩)는 시작 후 30분~2시간 창
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '인천공항 T1',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '13:00',
        'estimated_duration_minutes' => 60,
    ]);

    // 연결2 — 연결1 시작(13:00) 후 30분~2시간(13:30~15:00)에 시작하는 랜딩 (공운행 없이 연결)
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '인천공항 T2',
        'dropoff_location' => '서울 강남',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '14:00',
        'estimated_duration_minutes' => 70,
    ]);

    $response = $this->getJson('/api/orders/return-routes');

    $response->assertOk()->assertJsonCount(2, 'data');

    $data = $response->json('data');
    expect($data[1]['route'])->toBe('인천공항 T2 → 서울 강남');
    expect($data[1]['chain_leg'] ?? null)->toBe(3);
    expect($data[1]['chain_prev_id'] ?? null)->toBe($data[0]['id']);
});

test('연결2(랜딩)는 연결1 시작 후 30분~2시간 창에 시작해야 연결된다', function () {
    $this->travelTo(Carbon::parse('today 08:00 Asia/Seoul'));

    // 맡은 운행 — 랜딩 하차 11:00 → 연결1(샌딩) 창 13:00~14:00 (낮 2~3시간)
    Order::factory()->create([
        'user_id' => $this->driver->id,
        'status' => Order::STATUS_ACCEPTED,
        'pickup_location' => '인천공항 T1',
        'dropoff_location' => '서울 강남',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '09:00',
        'estimated_duration_minutes' => 60,
    ]);

    // 연결1(샌딩) — 13:00 시작 → 연결2(랜딩) 창 13:30~15:00 (시작 후 30분~2시간)
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '인천공항 T1',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '13:00',
        'estimated_duration_minutes' => 60,
    ]);

    // 연결2 후보 — 13:45 (시작 후 45분, 창 안) → 연결됨
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '인천공항 T2',
        'dropoff_location' => '서울 강남',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '13:45',
    ]);

    // 연결2 후보 — 15:30 (시작 후 2시간 30분, 창 밖) → 제외
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '인천공항 T1',
        'dropoff_location' => '인천 송도',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '15:30',
    ]);

    $response = $this->getJson('/api/orders/return-routes');

    $response->assertOk()->assertJsonCount(2, 'data');

    $data = $response->json('data');
    expect($data[1]['route'])->toBe('인천공항 T2 → 서울 강남');
    expect($data[1]['chain_leg'] ?? null)->toBe(3);
});

test('연결 체인이 연결4까지 이어진다', function () {
    $this->travelTo(Carbon::parse('today 08:00 Asia/Seoul'));

    // 맡은 운행 — 랜딩 하차 11:00 → 연결1 창 13:00~14:00 (낮 2~3시간)
    Order::factory()->create([
        'user_id' => $this->driver->id,
        'status' => Order::STATUS_ACCEPTED,
        'pickup_location' => '인천공항 T1',
        'dropoff_location' => '서울 강남',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '09:00',
        'estimated_duration_minutes' => 60,
    ]);

    // 연결1(샌딩) 13:00 → 연결2(랜딩) 창 13:30~15:00
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '인천공항 T1',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '13:00',
        'estimated_duration_minutes' => 60,
    ]);

    // 연결2(랜딩) 14:00 → 하차 16:00 → 연결3(샌딩) 창 18:00~19:00
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '인천공항 T2',
        'dropoff_location' => '서울 중구',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '14:00',
        'estimated_duration_minutes' => 60,
    ]);

    // 연결3(샌딩) 18:30 → 연결4(랜딩) 창 19:00~20:30
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 중구',
        'dropoff_location' => '인천공항 T2',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '18:30',
        'estimated_duration_minutes' => 60,
    ]);

    // 연결4(랜딩) 19:30 — 연결3 시작 후 1시간
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '인천공항 T1',
        'dropoff_location' => '서울 강남',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '19:30',
        'estimated_duration_minutes' => 70,
    ]);

    $response = $this->getJson('/api/orders/return-routes');

    $response->assertOk()->assertJsonCount(4, 'data');

    $data = $response->json('data');
    expect($data[0]['chain_leg'] ?? null)->toBe(2);
    expect($data[1]['chain_leg'] ?? null)->toBe(3);
    expect($data[1]['chain_prev_id'] ?? null)->toBe($data[0]['id']);
    expect($data[2]['chain_leg'] ?? null)->toBe(4);
    expect($data[2]['chain_prev_id'] ?? null)->toBe($data[1]['id']);
    expect($data[3]['chain_leg'] ?? null)->toBe(5);
    expect($data[3]['chain_prev_id'] ?? null)->toBe($data[2]['id']);
    expect($data[3]['route'])->toBe('인천공항 T1 → 서울 강남');
});

test('같은 구에서 출발하는 연결 운행만 추천된다', function () {
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

    // 다른 구(강남구)에서 출발 — 연결 창 안이지만 구가 달라 추천 제외
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남구',
        'dropoff_location' => '인천공항',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '13:30',
    ]);

    // 같은 구(중구)에서 출발 — 연결 창 안 → 추천
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
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.route', '서울 중구 → 인천공항')
        ->assertJsonPath('data.0.recommend_level', 'strong');
});

test('랜딩 후 도심→도심 픽업은 강력추천에서 제외된다 (양방향 왕복만)', function () {
    $this->travelTo(Carbon::parse('today 08:00 Asia/Seoul'));

    // 내 운행 — 랜딩(공항→도심), 하차지 서울 강남 → 다음은 샌딩(도심→공항)만 연결
    Order::factory()->create([
        'user_id' => $this->driver->id,
        'status' => Order::STATUS_ACCEPTED,
        'pickup_location' => '인천공항 T1',
        'dropoff_location' => '서울 강남',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '09:00',
        'estimated_duration_minutes' => 60,
    ]);

    // 샌딩(도심→공항) — 반대 방향(양방향)이라 강력추천
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '인천공항 T1',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '13:00',
    ]);

    // 픽업(도심→도심) — 같은 구(강남)지만 양방향이 아니므로 추천 제외
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '경기 성남시',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '13:00',
    ]);

    $this->getJson('/api/orders/return-routes')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.route', '서울 강남 → 인천공항 T1');
});

test('샌딩 후 랜딩(공항→도심)만 연결되고 랜딩 연속은 제외된다', function () {
    $this->travelTo(Carbon::parse('today 08:00 Asia/Seoul'));

    // 내 운행 — 샌딩(도심→공항), 하차지 인천공항 T1 → 다음은 랜딩만 연결
    Order::factory()->create([
        'user_id' => $this->driver->id,
        'status' => Order::STATUS_ACCEPTED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '인천공항 T1',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '09:00',
        'estimated_duration_minutes' => 60,
    ]);

    // 랜딩(공항→도심) — 반대 방향(양방향)이라 강력추천
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '인천공항 T1',
        'dropoff_location' => '서울 강남',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '10:30',
    ]);

    $this->getJson('/api/orders/return-routes')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.route', '인천공항 T1 → 서울 강남');
});

test('하차지와 같은 구에서 출발하는 서울 운행만 왕복 추천된다', function () {
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

    // 다른 구(강남구)에서 출발 → 구가 달라 추천 제외
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
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.route', '서울 마포구 → 인천공항')
        ->assertJsonPath('data.0.recommend_level', 'strong');
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

test('맡은 운행이 없어도 마켓에서 왕복(샌딩→랜딩) 짝을 강력추천으로 보여준다', function () {
    $this->travelTo(Carbon::parse('today 08:00 Asia/Seoul'));

    // 마켓 샌딩(도심→공항) — 왕복 첫 다리
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '인천공항 T1',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '12:00',
    ]);

    // 마켓 랜딩(공항→도심) — 같은 공항, 샌딩 시작(12:00) 후 30분~2시간 창(12:30~14:00)에 시작 → 왕복 짝
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '인천공항 T2',
        'dropoff_location' => '서울 강남',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '12:30',
    ]);

    $response = $this->getJson('/api/orders/recommendations');

    $response->assertOk();
    $data = $response->json('data');

    $strong = collect($data)->filter(fn (array $r) => ($r['recommend_level'] ?? null) === 'strong')->values();

    expect($strong)->toHaveCount(2);
    expect($strong[0]['route'])->toBe('서울 강남 → 인천공항 T1');
    expect($strong[0]['chain_leg'] ?? null)->toBe(2);
    expect($strong[1]['route'])->toBe('인천공항 T2 → 서울 강남');
    expect($strong[1]['chain_leg'] ?? null)->toBe(3);
    expect($strong[1]['chain_prev_id'] ?? null)->toBe($strong[0]['id']);
});

test('마켓 왕복 짝은 같은 공항에서 이어지는 랜딩만 묶는다 (다른 공항 제외)', function () {
    $this->travelTo(Carbon::parse('today 08:00 Asia/Seoul'));

    // 김포공항으로 가는 샌딩
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 마포구',
        'dropoff_location' => '김포공항 국내선',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '12:00',
    ]);

    // 인천공항에서 오는 랜딩 — 공항이 달라 왕복 짝으로 묶지 않는다
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '인천공항 T1',
        'dropoff_location' => '서울 마포구',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '12:30',
    ]);

    $this->getJson('/api/orders/recommendations')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('마켓 왕복 체인은 랜딩 후 3시간 뒤 다음 샌딩으로 이어진다', function () {
    $this->travelTo(Carbon::parse('today 08:00 Asia/Seoul'));

    // 앵커 샌딩 12:00
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '인천공항 T1',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '12:00',
    ]);

    // 랜딩 12:30 — 같은 공항(인천), 샌딩 시작 후 30분 → 연결
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '인천공항 T2',
        'dropoff_location' => '서울 강남',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '12:30',
    ]);

    // 다음 샌딩 12:45 — 랜딩(12:30) + 3시간(15:30) 이전이라 연결 안 됨
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '인천공항 T1',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '12:45',
    ]);

    // 다음 샌딩 15:30 — 랜딩 + 3시간 → 체인으로 연결
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '인천공항 T2',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '15:30',
    ]);

    $response = $this->getJson('/api/orders/recommendations');

    $response->assertOk();
    $data = $response->json('data');

    $strong = collect($data)->filter(fn (array $r) => ($r['recommend_level'] ?? null) === 'strong')->values();

    expect($strong)->toHaveCount(3);
    expect($strong[0]['route'])->toBe('서울 강남 → 인천공항 T1');
    expect($strong[0]['time'])->toBe('12:00');
    expect($strong[0]['chain_leg'] ?? null)->toBe(2);
    expect($strong[1]['route'])->toBe('인천공항 T2 → 서울 강남');
    expect($strong[1]['time'])->toBe('12:30');
    expect($strong[1]['chain_prev_id'] ?? null)->toBe($strong[0]['id']);
    expect($strong[2]['route'])->toBe('서울 강남 → 인천공항 T2');
    expect($strong[2]['time'])->toBe('15:30');
    expect($strong[2]['chain_prev_id'] ?? null)->toBe($strong[1]['id']);
});

test('매칭 설정 시간대에 맞는 마켓 왕복 체인은 추천1, 다른 시간대는 추천2로 나뉜다', function () {
    $this->travelTo(Carbon::parse('today 08:00 Asia/Seoul'));

    // 매칭 설정 — 오늘 12:00~14:00 (시간대 일치 여부로 추천 순위를 나눈다)
    $this->driver->matchPreferences()->create([
        'name' => '낮 시간대',
        'start_time' => '12:00',
        'end_time' => '14:00',
        'date_range' => 'today',
        'is_active' => true,
    ]);

    // 추천1(시간대 일치) 체인 — 앵커 샌딩 12:00 (12:00~14:00 안)
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '인천공항 T1',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '12:00',
    ]);

    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '인천공항 T2',
        'dropoff_location' => '서울 강남',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '12:30',
    ]);

    // 추천2(다른 시간대) 체인 — 앵커 샌딩 09:00 (시간대 밖)
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 중구',
        'dropoff_location' => '인천공항 T1',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '09:00',
    ]);

    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '인천공항 T1',
        'dropoff_location' => '서울 중구',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '09:30',
    ]);

    $response = $this->getJson('/api/orders/recommendations');

    $response->assertOk();
    $strong = collect($response->json('data'))
        ->filter(fn (array $r) => ($r['recommend_level'] ?? null) === 'strong')
        ->values();

    expect($strong)->toHaveCount(4);
    expect($strong[0]['time'])->toBe('12:00');
    expect($strong[0]['recommend_order'] ?? null)->toBe(1);
    expect($strong[1]['time'])->toBe('12:30');
    expect($strong[1]['recommend_order'] ?? null)->toBe(1);
    expect($strong[2]['time'])->toBe('09:00');
    expect($strong[2]['recommend_order'] ?? null)->toBe(2);
    expect($strong[3]['time'])->toBe('09:30');
    expect($strong[3]['recommend_order'] ?? null)->toBe(2);
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

test('랜딩 후 다음 샌딩은 랜딩 + 3시간 이후에만 연결된다', function () {
    $this->travelTo(Carbon::parse('today 08:00 Asia/Seoul'));

    // 랜딩 — 23:00 시작(항공기 도착) → 다음 샌딩은 23:00 + 3시간(02:00) 이후만 연결
    Order::factory()->create([
        'user_id' => $this->driver->id,
        'status' => Order::STATUS_ACCEPTED,
        'pickup_location' => '인천공항',
        'dropoff_location' => '서울 강남',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '23:00',
        'estimated_duration_minutes' => 60,
    ]);

    // 랜딩 후 2시간(01:00) → +3시간 이전이라 제외
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '인천공항',
        'service_date' => now('Asia/Seoul')->addDays(1)->format('Y-m-d'),
        'service_time' => '01:00',
    ]);

    // 랜딩 + 3시간(02:00) → 창 안 → 추천
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '인천공항',
        'service_date' => now('Asia/Seoul')->addDays(1)->format('Y-m-d'),
        'service_time' => '02:00',
    ]);

    // 랜딩 후 4시간 30분(03:30) → 창 안(3~6시간) → 추천
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
        ->assertJsonCount(2, 'data')
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
