<?php

use App\Models\Order;
use App\Models\User;
use App\Support\Orders\ServiceTypeInferrer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * 묶음(셋트) 등록 — 한 번에 여러 운행을 하나의 그룹으로 만든다.
 *
 * 붙여넣기(N건 개별)와 달리 그룹으로 묶이므로, 공개 옵션도 같은 기준을 따르는지 확인한다.
 * 공개 요건을 못 채운 운행은 초안으로 남아 마켓에 노출되지 않아야 한다.
 */
function batchTestRegistrant(): User
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
function batchTestOrder(string $pickup, string $dropoff, ?int $amount = 90000): array
{
    return [
        'pickup_location' => $pickup,
        'dropoff_location' => $dropoff,
        'vehicle_type' => '카니발',
        // 구분은 노선이 정한다(공항 출발=픽업, 공항 도착=샌딩) — 어긋나면 유입 검증에서 거절된다
        'service_type' => ServiceTypeInferrer::infer($pickup, $dropoff) ?? 'pickup',
        'service_date' => '2026-10-01',
        'service_time' => '09:00',
        'expected_revenue' => $amount,
    ];
}

test('묶음 등록은 공개를 요청하지 않으면 전부 초안으로 남는다', function () {
    Sanctum::actingAs(batchTestRegistrant());

    $this->postJson('/api/orders/batch', [
        'group_name' => 'KLOOK 8월',
        'orders' => [
            batchTestOrder('인천공항 T1', '서울 강남'),
            batchTestOrder('명동', '인천공항 T1', 80000),
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('data.order_count', 2)
        ->assertJsonPath('data.published', 0)
        ->assertJsonPath('data.draft_ids', []);

    expect(Order::query()->where('status', Order::STATUS_DRAFT)->count())->toBe(2)
        // 그룹으로 묶인다 — 붙여넣기(N건 개별) 경로와 다른 점
        ->and(Order::query()->whereNotNull('group_id')->count())->toBe(2);
});

test('묶음 등록도 필수 정보가 찬 운행만 공개한다', function () {
    Sanctum::actingAs(batchTestRegistrant());

    $response = $this->postJson('/api/orders/batch', [
        'group_name' => 'KLOOK 8월',
        'publish' => true,
        'orders' => [
            batchTestOrder('인천공항 T1', '서울 강남'),
            // 출발지 누락 — 노선이 없으면 공개할 수 없으므로 초안으로 남아야 한다
            [...batchTestOrder('명동', '인천공항 T1', null), 'pickup_location' => null],
        ],
    ])->assertCreated();

    expect($response->json('data.published'))->toBe(1)
        ->and($response->json('data.draft_ids'))->toHaveCount(1)
        ->and(Order::query()->where('status', Order::STATUS_PUBLISHED)->count())->toBe(1)
        ->and(Order::query()->where('status', Order::STATUS_DRAFT)->count())->toBe(1);
});
