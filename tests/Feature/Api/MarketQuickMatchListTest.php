<?php

use App\Models\Driver;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    // '주간(06~20) 선택 + 지금은 밤(21시)' 시나리오 고정 — 오늘 주간은 이미 끝났다
    $this->travelTo(Carbon::parse('2026-09-09 21:00 Asia/Seoul'));

    $this->driver = User::factory()->create([
        'id' => 2,
        'role' => User::ROLE_DRIVER,
    ]);

    $this->owner = User::factory()->create([
        'id' => 99,
        'role' => User::ROLE_OPERATOR,
    ]);

    // 매칭 조건 판정 전제 — 온라인 + 매칭 켬
    $this->driver->driver()->forceCreate([
        'user_id' => $this->driver->id,
        'status' => Driver::STATUS_ONLINE,
        'match_enabled' => true,
    ]);

    // 주간(06~20) + 인천공항 조건 — 날짜 범위는 전체(내일/모레 모두 비교 대상)
    $this->driver->matchPreferences()->create([
        'name' => '주간',
        'start_time' => '06:00',
        'end_time' => '20:00',
        'area' => '인천공항',
        'date_range' => null,
        'vehicle_id' => null,
        'is_active' => true,
    ]);

    Sanctum::actingAs($this->driver);
});

function marketQuickOrder(int $ownerId, string $date, string $time): Order
{
    return Order::factory()->create([
        'user_id' => $ownerId,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '인천공항 T1',
        'dropoff_location' => '강남',
        'service_date' => $date,
        'service_time' => $time,
    ]);
}

test('빠른매칭 — 주간 선택 + 지금이 밤이면 시작된 오늘 운행은 빠지고 내일 주간부터 시간순으로 나온다', function () {
    // 등록순(최신 등록 우선)이었다면 맨 나중에 만든 '모레'가 제일 먼저 보여야 하지만,
    // 빠른매칭은 서비스 시각이 가까운 순서라 '내일 06:10'이 먼저 와야 한다.
    $dayAfter = marketQuickOrder($this->owner->id, '2026-09-11', '06:10'); // 모레 (먼저 등록)
    $tomorrowEarly = marketQuickOrder($this->owner->id, '2026-09-10', '06:10'); // 내일 새벽
    $tomorrowLate = marketQuickOrder($this->owner->id, '2026-09-10', '13:00'); // 내일 오후
    $todayStarted = marketQuickOrder($this->owner->id, '2026-09-09', '19:30'); // 오늘 주간 — 이미 시작됨(제외 대상)

    $response = $this->getJson('/api/orders?scope=market&matched=1')->assertOk();

    // 가장 가까운 서비스 시각순 (모레가 먼저 등록됐어도 내일이 먼저)
    expect(collect($response->json('data'))->pluck('id')->all())
        ->toBe([$tomorrowEarly->id, $tomorrowLate->id, $dayAfter->id]);

    // 시작된 오늘 운행은 빠른매칭 결과에서 빠진다
    expect(collect($response->json('data'))->pluck('id'))->not->toContain($todayStarted->id);

    expect($response->json('meta.pagination.total'))->toBe(3);
});

test('빠른매칭이 아닌 일반 마켓은 시작 직후(2시간 내) 운행을 그대로 보여준다', function () {
    $todayStarted = marketQuickOrder($this->owner->id, '2026-09-09', '19:30'); // 1시간 30분 전 시작

    $this->getJson('/api/orders?scope=market')
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 1);

    expect(collect($this->getJson('/api/orders?scope=market')->json('data'))->pluck('id'))
        ->toContain($todayStarted->id);
});
