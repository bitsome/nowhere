<?php

use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\OrderIngestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * 위챗 모니터가 보낸 중국어 운행 데이터 — 저장은 한국어로, 원본은 별도 보관.
 *
 * 모니터는 `广津`, `机场`, `小车` 처럼 중국어 표기를 그대로 보낸다. 저장 시점에 한국어로
 * 바꾸되, 변환·검증에 실패해도 원본은 order_ingestions 에 남아야 재처리가 가능하다.
 */
function ingestionTestRegistrant(): User
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
function ingestionTestPayload(): array
{
    return [
        // 送机(샌딩)는 출발지→공항 — 공항이 출발지 칸에 있으면 유입 검증에서 거절된다
        'pickup_location' => '广津',
        'dropoff_location' => '仁川T1',
        'vehicle_type' => '小车',
        'service_type' => 'sending',
        'service_date' => '2026-10-01',
        'service_time' => '09:00',
        'passenger_count' => 3,
        'expected_revenue' => 90000,
        'original_summary' => '3.30送机 仁川 3人 2行李 9万',
    ];
}

test('중국어 운행을 등록하면 한국어로 저장된다', function () {
    Sanctum::actingAs(ingestionTestRegistrant());

    $this->postJson('/api/orders', ingestionTestPayload())->assertCreated();

    $order = Order::query()->firstOrFail();

    // 送机(샌딩)는 출발지(广津)→공항(仁川T1) 순서로 보낸다 — 저장도 광진구→인천공항 제1터미널
    expect($order->pickup_location)->toBe('광진구')
        ->and($order->dropoff_location)->toBe('인천공항 제1터미널')
        ->and($order->vehicle_type)->toBe('소형 승용차(세단/SUV)');
});

test('등록 요청 원본을 변환 전 그대로 보관한다', function () {
    Sanctum::actingAs(ingestionTestRegistrant());

    $this->postJson('/api/orders', ingestionTestPayload())->assertCreated();

    $order = Order::query()->firstOrFail();
    $ingestion = OrderIngestion::query()->firstOrFail();

    expect($ingestion->endpoint)->toBe('orders')
        ->and($ingestion->status)->toBe(OrderIngestion::STATUS_PROCESSED)
        ->and($ingestion->order_ids)->toBe([$order->id])
        // 변환 전 원문이 남아 있어야 나중에 다시 볼 수 있다
        ->and($ingestion->payload['pickup_location'])->toBe('广津')
        ->and($ingestion->payload['dropoff_location'])->toBe('仁川T1')
        ->and($ingestion->payload['vehicle_type'])->toBe('小车')
        ->and($ingestion->payload['original_summary'])->toBe('3.30送机 仁川 3人 2行李 9万');
});

test('검증에 실패해도 원본은 남고 실패로 기록된다', function () {
    Sanctum::actingAs(ingestionTestRegistrant());

    $this->postJson('/api/orders', [
        'pickup_location' => '明洞',
        'service_type' => '샌딩',   // 허용값(pickup/sending/landing)이 아니다
    ])->assertStatus(422);

    $ingestion = OrderIngestion::query()->firstOrFail();

    expect($ingestion->status)->toBe(OrderIngestion::STATUS_FAILED)
        ->and($ingestion->error)->not->toBeNull()
        ->and($ingestion->payload['pickup_location'])->toBe('明洞')
        ->and(Order::query()->count())->toBe(0);
});

test('N건 일괄 등록도 원본을 보관하고 한국어로 저장한다', function () {
    Sanctum::actingAs(ingestionTestRegistrant());

    $this->postJson('/api/orders/bulk', [
        'orders' => [
            ['pickup_location' => '明洞', 'dropoff_location' => '机场', 'service_type' => 'sending', 'service_time' => '09:00'],
            ['pickup_location' => '江南', 'dropoff_location' => '金浦', 'service_type' => 'sending', 'service_time' => '11:30'],
        ],
    ])->assertCreated();

    $ingestion = OrderIngestion::query()->firstOrFail();

    expect($ingestion->endpoint)->toBe('orders/bulk')
        ->and($ingestion->status)->toBe(OrderIngestion::STATUS_PROCESSED)
        ->and($ingestion->order_ids)->toHaveCount(2)
        ->and($ingestion->payload['orders'][0]['pickup_location'])->toBe('明洞');

    // 두 행 모두 送机(샌딩) — 출발지 → 공항 순서 그대로 저장된다
    expect(Order::query()->orderBy('id')->pluck('pickup_location')->all())
        ->toBe(['명동', '강남구']);

    expect(Order::query()->orderBy('id')->pluck('dropoff_location')->all())
        ->toBe(['공항', '김포']);
});

test('묶음 등록도 유입 원문의 운영 지시를 태그로 남긴다', function () {
    Sanctum::actingAs(ingestionTestRegistrant());

    // 모니터가 묶음으로 보낸 문구 — `老外`(외국인 고객), `秒结`(바로결제)은 지명 칸이 아니라 문구에만 있다
    $this->postJson('/api/orders/batch', [
        'group_name' => '아침 묶음',
        'orders' => [
            [
                'pickup_location' => '明洞',
                'dropoff_location' => '仁川T1',
                'service_type' => 'sending',
                'service_date' => '2026-10-02',
                'service_time' => '09:00',
                'original_summary' => "9.30 送机 明洞→仁川\n老外 跑完秒结",
            ],
            [
                'pickup_location' => '仁川T1',
                'dropoff_location' => '明洞',
                'service_type' => 'pickup',
                'service_date' => '2026-10-02',
                'service_time' => '13:00',
                'original_summary' => "9.30 接机 仁川→明洞\n老外 跑完秒结",
            ],
        ],
    ])->assertCreated();

    expect(Order::query()->orderBy('id')->pluck('tags')->all())
        ->toBe([['외국인 고객', '바로결제'], ['외국인 고객', '바로결제']]);
});

test('유입 파서의 내부 태그(wechat-monitor·conf60)는 저장하지 않는다', function () {
    Sanctum::actingAs(ingestionTestRegistrant());

    $this->postJson('/api/orders', [
        ...ingestionTestPayload(),
        'tags' => ['wechat-monitor', 'conf60'],
    ])->assertCreated();

    expect(Order::query()->firstOrFail()->tags)->toBeNull();

    // 내부 태그가 아닌 값은 그대로 남긴다
    $this->postJson('/api/orders', [
        ...ingestionTestPayload(),
        'tags' => ['wechat-monitor', '호텔'],
    ])->assertCreated();

    expect(Order::query()->orderByDesc('id')->firstOrFail()->tags)->toBe(['호텔']);
});

test('곧 착륙·고객 나옴 문구는 운행을 긴급으로 올린다', function () {
    Sanctum::actingAs(ingestionTestRegistrant());

    $this->postJson('/api/orders', [
        ...ingestionTestPayload(),
        'original_summary' => '飞机马上降落 换一个下午送机',
    ])->assertCreated();

    $order = Order::query()->firstOrFail();

    expect($order->is_priority)->toBeTrue()
        ->and($order->tags)->toBe(['지금 착륙']);
});

test('도착지를 못 주우면 등록하지 않고 원본만 남긴다', function () {
    Sanctum::actingAs(ingestionTestRegistrant());

    $this->postJson('/api/orders', [
        ...ingestionTestPayload(),
        'dropoff_location' => null,
    ])->assertStatus(422);

    expect(Order::query()->count())->toBe(0);

    $ingestion = OrderIngestion::query()->firstOrFail();

    expect($ingestion->status)->toBe(OrderIngestion::STATUS_FAILED)
        ->and($ingestion->error)->toContain('도착지')
        // 원문은 남아 있어야 나중에 확인해 다시 보낼 수 있다
        ->and($ingestion->payload['pickup_location'])->toBe('广津');
});

test('시간을 못 주우면 등록하지 않는다', function () {
    Sanctum::actingAs(ingestionTestRegistrant());

    // 날짜만 있고 시각이 없는 원문 — 시각 없이는 배차 판단이 불가능하다
    $this->postJson('/api/orders', [
        ...ingestionTestPayload(),
        'service_time' => null,
    ])->assertStatus(422);

    expect(Order::query()->count())->toBe(0)
        ->and(OrderIngestion::query()->firstOrFail()->error)->toContain('시간');
});

test('시각은 통합 일시로 대체되지만, 시각 없이 채워진 자정 고정값은 시각으로 보지 않는다', function () {
    Sanctum::actingAs(ingestionTestRegistrant());

    // 통합 일시에 실제 시각이 있으면 시각이 주워진 것으로 본다
    $this->postJson('/api/orders', [
        ...ingestionTestPayload(),
        'service_time' => null,
        'service_datetime' => '2026-10-01 09:00:00',
    ])->assertCreated();

    // 등록 화면이 시각 없이 날짜만 넣으면 '00:00:00'을 채워 보내므로 이건 시각이 아니다
    $this->postJson('/api/orders', [
        ...ingestionTestPayload(),
        'service_time' => null,
        'service_datetime' => '2026-10-02 00:00:00',
    ])->assertStatus(422);

    expect(Order::query()->count())->toBe(1);
});

test('셋트는 한 다리라도 도착지·시간이 빠지면 통째로 받지 않는다', function () {
    Sanctum::actingAs(ingestionTestRegistrant());

    $leg = fn (string $dropoff, string $time): array => [
        'pickup_location' => '仁川T1',
        'dropoff_location' => $dropoff,
        'service_time' => $time,
    ];

    $this->postJson('/api/orders/batch', [
        'group_name' => '셋트',
        'orders' => [$leg('广津', '09:00'), $leg('', '11:00')],
    ])->assertStatus(422);

    expect(Order::query()->count())->toBe(0)
        ->and(OrderGroup::query()->count())->toBe(0);
});

test('N건 일괄 등록도 한 건이라도 빠지면 전체를 받지 않는다', function () {
    Sanctum::actingAs(ingestionTestRegistrant());

    $this->postJson('/api/orders/bulk', [
        'orders' => [
            ['pickup_location' => '明洞', 'dropoff_location' => '机场', 'service_time' => '09:00'],
            ['pickup_location' => '江南', 'dropoff_location' => '金浦'],
        ],
    ])->assertStatus(422);

    expect(Order::query()->count())->toBe(0);
});
