<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * N건 일괄 등록 — 붙여넣은 문구 한 건에 운행이 여러 개 섞여 있을 때 쓴다.
 *
 * 셋트로 묶지 않고 각각 독립 운행으로 만드는 것이 핵심이다. 셋트는 추천·매칭에서
 * 여러 일정을 한 묶음으로 다루므로, 서로 무관한 운행을 묶으면 추천이 왜곡된다.
 */
function bulkTestRegistrant(): User
{
    // id=1 은 루트 특례(전체 권한)를 받는다 — 먼저 채워 실제 권한 검사를 받게 한다
    User::factory()->create();

    return User::factory()->create([
        'role' => User::ROLE_CUSTOMER,
        'permissions' => ['order.create'],
    ]);
}

/**
 * @return array<string, mixed>
 */
function bulkTestOrder(string $pickup, string $dropoff, ?int $amount = 90000): array
{
    return [
        'pickup_location' => $pickup,
        'dropoff_location' => $dropoff,
        'vehicle_type' => '카니발',
        'service_type' => 'pickup',
        'service_date' => '2026-10-01',
        'service_time' => '09:00',
        'passenger_count' => 3,
        'expected_revenue' => $amount,
    ];
}

test('여러 운행을 한 번에 등록하면 각각 독립 운행이 된다', function () {
    $user = bulkTestRegistrant();
    Sanctum::actingAs($user);

    $this->postJson('/api/orders/bulk', [
        'orders' => [
            bulkTestOrder('인천공항 T1', '서울 강남'),
            bulkTestOrder('명동', '인천공항 T1', 80000),
        ],
    ])
        ->assertCreated()
        ->assertJsonCount(2, 'data.order_ids')
        // 공개를 요청하지 않으면 초안으로만 만든다
        ->assertJsonPath('data.published', 0)
        ->assertJsonPath('data.draft_ids', []);

    expect(Order::query()->count())->toBe(2)
        // 셋트로 묶지 않는다 — 마켓에서 각각 따로 가져가야 한다
        ->and(Order::query()->whereNotNull('group_id')->count())->toBe(0)
        ->and(Order::query()->where('group_type', '단일')->count())->toBe(2)
        ->and(Order::query()->where('user_id', $user->id)->count())->toBe(2);
});

test('공개를 요청하면 필수 정보가 찬 운행만 공개한다', function () {
    Sanctum::actingAs(bulkTestRegistrant());

    $response = $this->postJson('/api/orders/bulk', [
        'publish' => true,
        'orders' => [
            bulkTestOrder('인천공항 T1', '서울 강남'),
            // 금액 누락 — 마켓 공개 요건을 못 채우므로 초안으로 남아야 한다
            bulkTestOrder('명동', '인천공항 T1', null),
        ],
    ])->assertCreated();

    expect($response->json('data.published'))->toBe(1)
        ->and($response->json('data.draft_ids'))->toHaveCount(1)
        ->and(Order::query()->where('status', Order::STATUS_PUBLISHED)->count())->toBe(1)
        ->and(Order::query()->where('status', Order::STATUS_DRAFT)->count())->toBe(1);
});

test('공개된 운행은 마켓에서 각각 따로 보인다', function () {
    $user = bulkTestRegistrant();
    Sanctum::actingAs($user);

    $this->postJson('/api/orders/bulk', [
        'publish' => true,
        'orders' => [
            bulkTestOrder('인천공항 T1', '서울 강남'),
            bulkTestOrder('명동', '인천공항 T1', 80000),
        ],
    ])->assertCreated();

    // 등록자 본인에게는 마켓이 아니라 '내 마켓(등록)' 목록으로 보인다
    $this->getJson('/api/orders?scope=mine&source=registered&tab=공개')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('기사 역할은 일괄 등록할 수 없다', function () {
    // 권한을 명시한다 — 팩토리 기본 permissions 는 무작위 role 로 계산되어 흔들린다
    User::factory()->create();
    Sanctum::actingAs(User::factory()->create([
        'role' => User::ROLE_DRIVER,
        'permissions' => [],
    ]));

    $this->postJson('/api/orders/bulk', [
        'orders' => [bulkTestOrder('인천공항 T1', '서울 강남')],
    ])->assertForbidden();
});

test('운행 목록이 비면 검증에 실패한다', function () {
    Sanctum::actingAs(bulkTestRegistrant());

    $this->postJson('/api/orders/bulk', ['orders' => []])->assertStatus(422);
});
