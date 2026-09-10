<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    // 추천 로직(어떤 운행이 추천되는지) 검증에 집중 — 기본 선호도 필터(60%)는 끈다.
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

test('일정이 없어도 활성 매칭 설정 조건에 맞는 운행을 추천한다', function () {
    $this->travelTo(Carbon::parse('today 08:00 Asia/Seoul'));

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
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '11:00',
    ]);

    $this->getJson('/api/orders/recommendations')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.recommend_reason', '매칭 설정');
});

test('매칭 설정 조건 운행은 4시간 창 밖(자정 넘김)이어도 추천된다', function () {
    // 오늘 23:00 → 추천 창은 내일 03:00까지 (4시간)
    $this->travelTo(Carbon::parse('today 23:00 Asia/Seoul'));

    $this->driver->matchPreferences()->create([
        'name' => '심야 시간대',
        'area' => '서울 강남',
        'start_time' => '00:00',
        'end_time' => '05:00',
        'is_active' => true,
    ]);

    // 내일 02:30 — 자정을 넘겼고 4시간 창 안 → 추천됨
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '잠실',
        'service_date' => now('Asia/Seoul')->addDay()->format('Y-m-d'),
        'service_time' => '02:30',
    ]);

    // 내일 04:30 — 4시간 창(03:00) 밖이지만 매칭 설정 조건에 맞아 알람과 동일하게 추천됨
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '판교',
        'service_date' => now('Asia/Seoul')->addDay()->format('Y-m-d'),
        'service_time' => '04:30',
    ]);

    $this->getJson('/api/orders/recommendations')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.recommend_reason', '매칭 설정');
});

test('매칭 설정이 없으면 자주 다니는 노선(운행 이력) 기준으로 추천한다', function () {
    $this->travelTo(Carbon::parse('today 08:00 Asia/Seoul'));

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
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '11:00',
    ]);

    $this->getJson('/api/orders/recommendations')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.recommend_reason', '자주 다니는 노선');
});

test('운행했던 시간대의 운행을 추천한다', function () {
    $this->travelTo(Carbon::parse('today 08:00 Asia/Seoul'));

    // 최근 완료 운행 2건이 같은 시간대(09시) — 시간 패턴
    Order::factory()->create([
        'user_id' => $this->driver->id,
        'status' => Order::STATUS_COMPLETED,
        'pickup_location' => '인천공항',
        'dropoff_location' => '서울 강남',
        'service_date' => now('Asia/Seoul')->subDays(3)->format('Y-m-d'),
        'service_time' => '09:00',
    ]);

    Order::factory()->create([
        'user_id' => $this->driver->id,
        'status' => Order::STATUS_COMPLETED,
        'pickup_location' => '김포공항',
        'dropoff_location' => '여의도',
        'service_date' => now('Asia/Seoul')->subDays(1)->format('Y-m-d'),
        'service_time' => '09:30',
    ]);

    // 마켓 운행 — 지역은 겹치지 않지만 같은 시간대(09시) (서비스 지역 안)
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '수원',
        'dropoff_location' => '판교',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '09:10',
    ]);

    $this->getJson('/api/orders/recommendations')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.recommend_reason', '자주 운행한 시간');
});

test('추천은 품질 순(매칭 설정 > 자주 다니는 노선 > 자주 운행한 시간)으로 정렬된다', function () {
    $this->travelTo(Carbon::parse('today 08:00 Asia/Seoul'));

    // 운행 이력 — 09시 2회 (시간 신호) + '강남' 하차 (지역 신호)
    Order::factory()->create([
        'user_id' => $this->driver->id,
        'status' => Order::STATUS_COMPLETED,
        'pickup_location' => '인천공항',
        'dropoff_location' => '서울 강남',
        'service_date' => now('Asia/Seoul')->subDays(3)->format('Y-m-d'),
        'service_time' => '09:00',
    ]);

    Order::factory()->create([
        'user_id' => $this->driver->id,
        'status' => Order::STATUS_COMPLETED,
        'pickup_location' => '김포공항',
        'dropoff_location' => '여의도',
        'service_date' => now('Asia/Seoul')->subDays(1)->format('Y-m-d'),
        'service_time' => '09:30',
    ]);

    // 매칭 설정 — 판교 08:00~08:30
    $this->driver->matchPreferences()->create([
        'name' => '판교 출근',
        'area' => '판교',
        'start_time' => '08:00',
        'end_time' => '08:30',
        'is_active' => true,
    ]);

    // 매칭 설정 일치 (rank 2)
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '판교',
        'dropoff_location' => '정자',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '08:10',
    ]);

    // 지역 일치 (rank 3)
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '잠실',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '09:00',
    ]);

    // 시간 일치 (rank 4) — 서비스 지역 안에서 지역 불일치
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '수원',
        'dropoff_location' => '정자',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '09:05',
    ]);

    $this->getJson('/api/orders/recommendations')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.recommend_reason', '매칭 설정')
        ->assertJsonPath('data.1.recommend_reason', '자주 다니는 노선')
        ->assertJsonPath('data.2.recommend_reason', '자주 운행한 시간');
});

test('가져오기 요청(승인 대기) 이력의 지역 운행을 추천한다', function () {
    $this->travelTo(Carbon::parse('today 08:00 Asia/Seoul'));

    // 내가 가져오기를 요청한 운행 (아직 승인 대기) — 지역 신호
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_ACCEPTANCE_PENDING,
        'claimant_user_id' => $this->driver->id,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '인천공항',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '09:00',
    ]);

    // 같은 지역의 다른 마켓 운행
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '잠실',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '10:00',
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

    // 하차(10:00) 후 2~3시간 사이 시작하는 복귀 운행 (당일 13:00)
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '인천공항 T1',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '13:00',
    ]);

    $this->getJson('/api/orders/recommendations')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.recommend_reason', '연결 운행');
});

test('연결 운행과 매칭 설정 추천이 함께 반환된다', function () {
    $this->travelTo(Carbon::parse('today 08:00 Asia/Seoul'));

    // 맡은 운행 — 랜딩 10:00(낮) → 연결 운행 창 12:00~13:00
    Order::factory()->create([
        'user_id' => $this->driver->id,
        'status' => Order::STATUS_ACCEPTED,
        'pickup_location' => '인천공항 T2',
        'dropoff_location' => '서울 강남',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '09:00',
        'estimated_duration_minutes' => 60,
    ]);

    // 매칭 설정 — 서울 강남 선호 (시간 무관)
    $this->driver->matchPreferences()->create([
        'name' => '강남 선호',
        'area' => '서울 강남',
        'is_active' => true,
    ]);

    // 연결 운행 후보 — 랜딩 후 3시간(13:00) 시작
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '인천공항 T1',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '13:00',
    ]);

    // 매칭 설정 후보 — 4시간 창 안(11:00)이지만 연결 운행 창(12:00~) 밖
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '잠실',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '11:00',
    ]);

    $reasons = collect($this->getJson('/api/orders/recommendations')->json('data'))
        ->pluck('recommend_reason');

    expect($reasons)
        ->toContain('연결 운행')
        ->toContain('매칭 설정');
});

test('매칭 시간 중심 앞연결·뒤연결 운행도 추천된다', function () {
    $this->travelTo(Carbon::parse('today 08:00 Asia/Seoul'));

    // 매칭 설정 — 인천공항 22:00~03:00 (자정 넘김)
    $this->driver->matchPreferences()->create([
        'name' => '인천공항 심야',
        'area' => '인천공항',
        'start_time' => '22:00',
        'end_time' => '03:00',
        'date_range' => 'today_tomorrow',
        'is_active' => true,
    ]);

    // 앞연결 — 오늘 20:00 출발·랜딩 21:00 (시작 22:00 전 3시간 안) → 매칭 지역 도착
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '인천공항 T1',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '20:00',
        'estimated_duration_minutes' => 60,
    ]);

    // 뒤연결 — 내일 03:30 출발 (종료 03:00 후 3시간 안) → 매칭 지역에서 출발
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '인천공항 T2',
        'dropoff_location' => '서울 강남',
        'service_date' => now('Asia/Seoul')->addDay()->format('Y-m-d'),
        'service_time' => '03:30',
    ]);

    $response = $this->getJson('/api/orders/recommendations');

    $routes = collect($response->json('data'))->pluck('route');
    $reasons = collect($response->json('data'))->pluck('recommend_reason');

    expect($routes)->toContain('서울 강남 → 인천공항 T1')    // 앞연결
        ->toContain('인천공항 T2 → 서울 강남');               // 뒤연결
    expect($reasons)->toContain('매칭 설정');
});

test('이력 기반 추천은 4시간 창 안 운행만 대상이다', function () {
    $this->travelTo(Carbon::parse('today 08:00 Asia/Seoul'));

    // 최근 완료 운행 — 하차지 '서울 강남' (자주 다니는 지역)
    Order::factory()->create([
        'user_id' => $this->driver->id,
        'status' => Order::STATUS_COMPLETED,
        'pickup_location' => '인천공항',
        'dropoff_location' => '서울 강남',
        'service_date' => now('Asia/Seoul')->subDays(2)->format('Y-m-d'),
        'service_time' => '09:00',
    ]);

    // 11:00 시작 — 지금(08:00)부터 4시간(12:00) 이내 → 추천됨
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '잠실',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '11:00',
    ]);

    // 내일 14:00 시작 — 같은 지역이지만 4시간 창 초과 → 제외
    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '판교',
        'service_date' => now('Asia/Seoul')->addDay()->format('Y-m-d'),
        'service_time' => '14:00',
    ]);

    $this->getJson('/api/orders/recommendations')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.recommend_reason', '자주 다니는 노선');
});

test('일정·설정·이력이 모두 없으면 추천이 비어 있다', function () {
    $this->travelTo(Carbon::parse('today 08:00 Asia/Seoul'));

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

test('선호도(조건 일치율) 60% 미만 운행은 추천에서 제외된다', function () {
    config(['recommendation.min_match_score' => 60]);
    $this->travelTo(Carbon::parse('today 08:00 Asia/Seoul'));

    // 지역만 설정한 약한 매칭 — 점수 45%(설정 25 + 지역 20), 차량 미등록이라 60% 미만
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
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '11:00',
    ]);

    $this->getJson('/api/orders/recommendations')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('선호도 60% 이상 운행만 추천에 남는다', function () {
    config(['recommendation.min_match_score' => 60]);
    $this->travelTo(Carbon::parse('today 08:00 Asia/Seoul'));

    // 조건이 모두 설정된 매칭 — 점수 70%(설정 25 + 지역 20 + 시간 15 + 금액 10)
    $this->driver->matchPreferences()->create([
        'name' => '강남 선호',
        'area' => '서울 강남',
        'start_time' => '09:00',
        'end_time' => '18:00',
        'min_revenue' => 100000,
        'is_active' => true,
    ]);

    Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '인천공항',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '11:00',
        'expected_revenue' => 150000,
    ]);

    $this->getJson('/api/orders/recommendations')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.match_score', 70);
});

test('진행 중 일정과 겹치면 시간 여유 근거가 빠지고, 같은 점수면 여유 있는 운행이 먼저 온다', function () {
    $this->travelTo(Carbon::parse('today 08:00 Asia/Seoul'));

    // 운행 이력 — '강남' 하차 2회 + 09시 2회 (지역·시간 신호)
    Order::factory()->create([
        'user_id' => $this->driver->id,
        'status' => Order::STATUS_COMPLETED,
        'pickup_location' => '인천공항',
        'dropoff_location' => '서울 강남',
        'service_date' => now('Asia/Seoul')->subDays(2)->format('Y-m-d'),
        'service_time' => '09:00',
    ]);
    Order::factory()->create([
        'user_id' => $this->driver->id,
        'status' => Order::STATUS_COMPLETED,
        'pickup_location' => '인천공항',
        'dropoff_location' => '서울 강남',
        'service_date' => now('Asia/Seoul')->subDays(1)->format('Y-m-d'),
        'service_time' => '09:30',
    ]);

    // 확정된 내 일정 — 오늘 11:00 (11:00~12:30)
    Order::factory()->create([
        'user_id' => $this->driver->id,
        'status' => Order::STATUS_ACCEPTED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '인천공항',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '11:00',
        'estimated_duration_minutes' => 90,
    ]);

    // 추천 후보 2건 — 같은 점수(자주 다니는 노선 20)지만 시간 여유가 다른 운행
    $free = Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '잠실',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '10:00', // 내 일정(11:00)과 안 겹침 → 여유 있음
    ]);
    $busy = Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '판교',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => '11:30', // 내 일정(11:00~12:30)과 겹침 → 여유 없음
    ]);

    $rows = $this->getJson('/api/orders/recommendations')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->json('data');

    // 같은 조건 일치율이면 '시간 여유 충분' 근거가 있는 운행을 먼저 보여준다 (개인화·실행 가능성 우선)
    $this->assertSame($free->id, $rows[0]['id']);
    $this->assertSame($busy->id, $rows[1]['id']);
    expect($rows[0]['match_reasons'])->toContain('시간 여유 충분');
    expect($rows[1]['match_reasons'])->not->toContain('시간 여유 충분');
});
