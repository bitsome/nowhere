<?php

use App\Models\Driver;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * 랭킹 후보가 되는 기사 — 온라인 + 매칭 켬 + 활성 매칭 설정은 각 테스트에서 붙인다.
 */
function matchingRankDriver(array $attributes = []): User
{
    $user = User::factory()->create(array_merge(['role' => User::ROLE_DRIVER], $attributes));

    $user->driver()->forceCreate([
        'user_id' => $user->id,
        'status' => Driver::STATUS_ONLINE,
        'match_enabled' => true,
    ]);

    return $user;
}

beforeEach(function () {
    config(['matching.top_n' => 5]);

    $this->owner = User::factory()->create([
        'role' => User::ROLE_OPERATOR,
    ]);

    $this->admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);
});

test('온라인·매칭 켬·활성 설정 기사만 랭킹 후보가 되고, 시간이 겹치는 진행 중 일정은 제외된다', function () {
    $date = now('Asia/Seoul')->format('Y-m-d');

    $order = Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남구',
        'dropoff_location' => '인천공항 T1',
        'vehicle_type' => '스타리아',
        'service_date' => $date,
        'service_time' => '11:00',
        'estimated_duration_minutes' => 60,
    ]);

    // 1) 정상 후보 — 차량 일치·최근 활동 권역 겹침·시간대/선호 조건 충족
    $good = matchingRankDriver();
    $good->matchPreferences()->create([
        'name' => '강남 공항 콜',
        'area' => '강남',
        'date_range' => 'today',
        'start_time' => '06:00',
        'end_time' => '22:00',
        'is_active' => true,
    ]);
    $good->vehicles()->create(['name' => '스타리아', 'type' => '스타리아', 'is_default' => true]);
    Order::factory()->create([
        'user_id' => $good->id,
        'status' => Order::STATUS_COMPLETED,
        'pickup_location' => '잠실',
        'dropoff_location' => '서울 강남구',
        'service_date' => $date,
        'service_time' => '08:00',
        'estimated_duration_minutes' => 60,
    ]);

    // 2) 오프라인 — 제외
    $offline = User::factory()->create(['role' => User::ROLE_DRIVER]);
    $offline->driver()->forceCreate([
        'user_id' => $offline->id,
        'status' => Driver::STATUS_OFFLINE,
        'match_enabled' => true,
    ]);
    $offline->matchPreferences()->create([
        'name' => '강남 콜',
        'area' => '강남',
        'date_range' => 'today',
        'is_active' => true,
    ]);

    // 3) 활성 매칭 설정 없음 — 제외
    $noPref = matchingRankDriver();

    // 4) 같은 날짜 진행 중 일정(10:30~11:30)과 시간 겹침 — 제외
    $busy = matchingRankDriver();
    $busy->matchPreferences()->create([
        'name' => '강남 콜',
        'area' => '강남',
        'date_range' => 'today',
        'is_active' => true,
    ]);
    Order::factory()->create([
        'user_id' => $busy->id,
        'status' => Order::STATUS_ACCEPTED,
        'pickup_location' => '판교',
        'dropoff_location' => '분당',
        'service_date' => $date,
        'service_time' => '10:30',
        'estimated_duration_minutes' => 60,
    ]);

    Sanctum::actingAs($this->admin);

    $this->getJson("/api/admin/orders/{$order->id}/matching-drivers")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.user_id', $good->id)
        ->assertJsonPath('data.0.match_score', 83)
        ->assertJsonPath('data.0.breakdown.vehicle', 25)
        ->assertJsonPath('data.0.breakdown.distance', 25)
        ->assertJsonPath('data.0.breakdown.time', 20)
        ->assertJsonPath('data.0.breakdown.preference', 10)
        ->assertJsonPath('data.0.breakdown.rating', 3)
        ->assertJsonPath('data.0.reasons.0', '차량 조건 일치')
        ->assertJsonPath('data.0.reasons.3', '매칭 설정 조건 충족');
});

test('동선 연결과 평점이 반영되어 높은 점수의 기사가 먼저 랭킹된다', function () {
    $date = now('Asia/Seoul')->format('Y-m-d');

    $order = Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남구',
        'dropoff_location' => '인천공항 T1',
        'vehicle_type' => '스타리아',
        'service_date' => $date,
        'service_time' => '13:00',
        'estimated_duration_minutes' => 60,
    ]);

    // 기사 A — 동선 연결(10:00 하차 강남구) + 평점 5 → 만점
    $driverA = matchingRankDriver();
    $driverA->matchPreferences()->create([
        'name' => '강남 선호',
        'area' => '강남',
        'date_range' => 'today',
        'start_time' => '06:00',
        'end_time' => '22:00',
        'is_active' => true,
    ]);
    $driverA->vehicles()->create(['name' => '스타리아', 'type' => '스타리아', 'is_default' => true]);

    Order::factory()->create([
        'user_id' => $driverA->id,
        'status' => Order::STATUS_ACCEPTED,
        'pickup_location' => '잠실',
        'dropoff_location' => '서울 강남구',
        'service_date' => $date,
        'service_time' => '10:00',
        'estimated_duration_minutes' => 60,
    ]);

    // 리뷰 평점 5 — 오더 FK가 필요해 완료 운행을 리뷰 대상으로 사용
    $completed = Order::factory()->create([
        'user_id' => $driverA->id,
        'status' => Order::STATUS_COMPLETED,
        'pickup_location' => '마포',
        'dropoff_location' => '여의도',
        'service_date' => $date,
        'service_time' => '07:00',
        'estimated_duration_minutes' => 60,
    ]);

    Review::create([
        'order_id' => $completed->id,
        'reviewer_id' => $this->owner->id,
        'reviewee_id' => $driverA->id,
        'rating' => 5,
        'content' => '만족',
    ]);

    // 기사 B — 차량 불일치(그랜저)·활동 이력 없음 → 약점수
    $driverB = matchingRankDriver();
    $driverB->matchPreferences()->create([
        'name' => '강남 선호',
        'area' => '강남',
        'start_time' => '06:00',
        'end_time' => '22:00',
        'is_active' => true,
    ]);
    $driverB->vehicles()->create(['name' => '그랜저', 'type' => '그랜저', 'is_default' => true]);

    Sanctum::actingAs($this->admin);

    $response = $this->getJson("/api/admin/orders/{$order->id}/matching-drivers")
        ->assertOk();

    $data = $response->json('data');

    expect($data[0]['user_id'])->toBe($driverA->id)
        ->and($data[0]['match_score'])->toBe(100)
        ->and($data[0]['breakdown']['route'])->toBe(15)
        ->and($data[0]['breakdown']['rating'])->toBe(5)
        ->and($data[0]['reasons'])->toContain('현재 일정과 동선이 좋음')
        ->and($data[1]['user_id'])->toBe($driverB->id)
        ->and($data[1]['match_score'])->toBe(33);
});

test('자동 매칭 랭킹 API는 관리자만 조회할 수 있다', function () {
    $date = now('Asia/Seoul')->format('Y-m-d');

    $order = Order::factory()->create([
        'user_id' => $this->owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '서울 강남구',
        'dropoff_location' => '인천공항 T1',
        'vehicle_type' => '스타리아',
        'service_date' => $date,
        'service_time' => '13:00',
    ]);

    // 기사 역할 — 403
    $driver = User::factory()->create(['role' => User::ROLE_DRIVER]);
    Sanctum::actingAs($driver);

    $this->getJson("/api/admin/orders/{$order->id}/matching-drivers")->assertForbidden();

    // 관리자 — 200 + 메타(배점)
    Sanctum::actingAs($this->admin);

    $this->getJson("/api/admin/orders/{$order->id}/matching-drivers")
        ->assertOk()
        ->assertJsonPath('meta.weights.vehicle', 25)
        ->assertJsonPath('meta.weights.rating', 5);
});
