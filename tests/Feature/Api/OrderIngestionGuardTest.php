<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * 유입 규칙 검사 — 편명 숫자를 시각으로 읽거나 방향이 서비스 구분과 어긋난 행은 받지 않는다.
 *
 * 값을 고쳐서 받지 않는다. 어긋난 행은 사유와 함께 거절해 유입 기록에 남기고,
 * 그 기록으로 파서를 고친다.
 */
function ingestionGuardRegistrant(): User
{
    // id=1 은 루트 특례(전체 권한)를 받는다 — 먼저 채워 실제 권한 검사를 받게 한다
    User::factory()->create();

    return User::factory()->create([
        'role' => User::ROLE_CUSTOMER,
        'permissions' => ['order.create'],
    ]);
}

/**
 * 모니터가 보낸 문구 — 送机 08:00(KE925)과 接机 17:25(KE622) 두 다리가 한 줄씩 들어 있다.
 */
function ingestionGuardSummary(): string
{
    return "🇰🇷墨雨2 在韩车友群\n[1개] 帮划客路  套出12 🌾\n  9.13      新卡起\n送机8：00中区KE925\n接机17：25中区KE622";
}

test('규칙에 맞는 묶음은 그대로 받는다', function () {
    Sanctum::actingAs(ingestionGuardRegistrant());

    $this->postJson('/api/orders/batch', [
        'group_name' => '帮划客路 套出12',
        'orders' => [
            [
                'pickup_location' => '중구',
                'dropoff_location' => '공항',
                'service_type' => 'sending',
                'service_date' => '2026-09-13',
                'service_time' => '08:00',
                'flight_number' => 'KE925',
                'original_summary' => ingestionGuardSummary(),
            ],
            [
                'pickup_location' => '공항',
                'dropoff_location' => '중구',
                'service_type' => 'pickup',
                'service_date' => '2026-09-13',
                'service_time' => '17:25',
                'flight_number' => 'KE622',
                'original_summary' => ingestionGuardSummary(),
            ],
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('data.order_count', 2);

    expect(Order::query()->count())->toBe(2);
});

test('편명 숫자를 시각으로 읽은 행은 사유와 함께 거절한다', function () {
    Sanctum::actingAs(ingestionGuardRegistrant());

    $response = $this->postJson('/api/orders/batch', [
        'group_name' => '帮划客路 套出12',
        'orders' => [
            [
                'pickup_location' => '중구',
                'dropoff_location' => '공항',
                'service_type' => 'sending',
                'service_date' => '2026-09-13',
                'service_time' => '08:00',
                'original_summary' => ingestionGuardSummary(),
            ],
            [
                // 모니터가 KE925 를 시각으로 읽어 만든 행 — 실제로는 없는 운행이다
                'pickup_location' => '공항',
                'dropoff_location' => '중구',
                'service_type' => 'pickup',
                'service_date' => '2026-09-13',
                'service_time' => '09:25',
                'original_summary' => ingestionGuardSummary(),
            ],
        ],
    ])->assertStatus(422);

    expect($response->json('errors')['orders.1.service_time'][0])
        ->toBe('2번째 운행 — 편명 KE925 의 숫자를 시각 09:25 으로 읽었습니다 — 문구에 적힌 실제 시각을 써야 합니다. 원문을 확인해 다시 보내 주세요.')
        // 규칙에 맞는 첫 행도 함께 거절된다 — 원문 전체를 다시 보내야 파서가 고칠 수 있다
        ->and($response->json('errors'))->toHaveCount(1);

    expect(Order::query()->count())->toBe(0);
});

test('접기(接机)인데 공항이 도착지 칸에 있으면 거절한다', function () {
    Sanctum::actingAs(ingestionGuardRegistrant());

    $this->postJson('/api/orders', [
        'pickup_location' => '明洞',
        'dropoff_location' => '仁川T1',
        'service_type' => 'pickup',
        'service_date' => '2026-10-01',
        'service_time' => '14:00',
    ])
        ->assertStatus(422)
        ->assertJsonPath('errors.service_time.0', '接机(픽업)는 공항에서 출발합니다 — 공항(仁川T1)이 도착지 칸에 있습니다. 원문을 확인해 다시 보내 주세요.');

    expect(Order::query()->count())->toBe(0);
});

test('접기(接机)인데 목적지가 출발지 칸에 있으면 거절한다', function () {
    Sanctum::actingAs(ingestionGuardRegistrant());

    // 接机 표시를 잃어 구분이 비고, 목적지가 출발지 칸에 들어온 행
    $this->postJson('/api/orders', [
        'pickup_location' => '중구',
        'dropoff_location' => null,
        'service_date' => '2026-09-13',
        'service_time' => '17:25',
        'flight_number' => 'KE622',
        'original_summary' => ingestionGuardSummary(),
    ])
        ->assertStatus(422)
        ->assertJsonPath('errors.service_time.0', '接机(픽업)는 공항에서 출발합니다 — 목적지(중구)가 출발지 칸에 있습니다. 원문을 확인해 다시 보내 주세요.');
});

test('샌딩(送机)인데 공항이 출발지 칸에 있으면 거절한다', function () {
    Sanctum::actingAs(ingestionGuardRegistrant());

    $this->postJson('/api/orders', [
        'pickup_location' => '인천공항 T1',
        'dropoff_location' => '명동',
        'service_type' => 'sending',
        'service_date' => '2026-10-01',
        'service_time' => '14:00',
    ])
        ->assertStatus(422)
        ->assertJsonPath('errors.service_time.0', '送机(샌딩)는 공항으로 갑니다 — 공항(인천공항 T1)이 출발지 칸에 있습니다. 원문을 확인해 다시 보내 주세요.');
});

test('지명 칸에 필드 라벨이 들어오면 거절한다', function () {
    Sanctum::actingAs(ingestionGuardRegistrant());

    // 예약 시스템 문구 — 파서가 라벨(地址)을 값으로 밀어 넣은 행
    $this->postJson('/api/orders', [
        'pickup_location' => '地址',
        'dropoff_location' => '中庭首尔钟路酒店',
        'service_date' => '2026-09-16',
        'service_time' => '16:00',
        'original_summary' => '日期：9.16 接机 航班号：OZ364 仁川T2 时间：1600 地址：中庭首尔钟路酒店 人数、行李：6人6行李',
    ])
        ->assertStatus(422)
        ->assertJsonPath('errors.service_time.0', '지명 칸에 값이 아니라 라벨(地址)이 들어 있습니다. 원문을 확인해 다시 보내 주세요.');

    expect(Order::query()->count())->toBe(0);
});

test('유입 원문의 금액을 날짜로 읽어 상한을 넘긴 행은 거절한다', function () {
    Sanctum::actingAs(ingestionGuardRegistrant());

    // 원문의 `5.5🌾`(금액 5.5만)를 5월 5일로 읽은 행 — 공개 상한(30일)을 넘는다
    $far = now('Asia/Seoul')->addDays(40)->format('Y-m-d');

    $this->postJson('/api/orders', [
        'pickup_location' => '인천공항',
        'dropoff_location' => '명동',
        'service_date' => $far,
        'service_time' => '14:05',
        'original_summary' => '14:05 接机 明洞 小车 V5，5.5🌾',
    ])
        ->assertStatus(422)
        ->assertJsonPath('errors.service_time.0', '운행 날짜 '.$far.' 가 오늘부터 30일을 넘습니다 — 원문의 금액을 날짜로 읽지 않았는지 확인해야 합니다. 원문을 확인해 다시 보내 주세요.');

    expect(Order::query()->count())->toBe(0);
});

test('원문이 없는 등록 행은 먼 날짜라도 날짜 규칙에 걸리지 않는다', function () {
    Sanctum::actingAs(ingestionGuardRegistrant());

    // 사람이 등록 화면에서 먼 날짜를 초안으로 잡아 두는 것은 막지 않는다
    $far = now('Asia/Seoul')->addDays(40)->format('Y-m-d');

    $this->postJson('/api/orders', [
        'pickup_location' => '명동',
        'dropoff_location' => '인천공항',
        'service_date' => $far,
        'service_time' => '14:05',
    ])->assertCreated();

    expect(Order::query()->count())->toBe(1);
});
