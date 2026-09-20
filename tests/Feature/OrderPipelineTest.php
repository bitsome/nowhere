<?php

use App\Models\Order;
use App\Models\OrderIngestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    // 파이프라인 소유 계정 — config(orders.pipeline_owner_email) 기본값과 맞춘다
    $this->pipelineOwner = User::factory()->create([
        'email' => 'admin@example.com',
        'role' => User::ROLE_ADMIN,
        'name' => '파이프라인 운영',
    ]);

    // 위챗 모니터가 쓰는 발신 계정 (등록 권한만 가진 계정)
    User::factory()->create();
    $this->sender = User::factory()->create([
        'role' => User::ROLE_CUSTOMER,
        'permissions' => ['order.create'],
    ]);
});

/**
 * 모니터가 보내는 한 줄 — 送机 08:00.
 */
function pipelineSummary(): string
{
    return '送机8：00中区KE925';
}

/**
 * 유입 한 건(단일)의 payload — 원문이 실린다.
 *
 * @return array<string, mixed>
 */
function pipelineOrderPayload(): array
{
    return [
        'pickup_location' => '중구',
        'dropoff_location' => '공항',
        'service_type' => 'sending',
        'service_date' => now('Asia/Seoul')->addDay()->format('Y-m-d'),
        'service_time' => '08:00',
        'flight_number' => 'KE925',
        'original_summary' => pipelineSummary(),
    ];
}

test('유입으로 등록된 운행은 파이프라인 계정 소유로 남는다', function () {
    Sanctum::actingAs($this->sender);

    $this->postJson('/api/orders', pipelineOrderPayload())->assertCreated();

    $order = Order::query()->latest('id')->first();

    expect($order->user_id)->toBe($this->pipelineOwner->id)
        ->and($order->source)->toBe(Order::SOURCE_PIPELINE);
});

test('묶음 유입도 파이프라인 계정 소유로 남는다', function () {
    Sanctum::actingAs($this->sender);

    $this->postJson('/api/orders/batch', [
        'group_name' => '帮划客路 套出12',
        'orders' => [
            pipelineOrderPayload(),
            [
                'pickup_location' => '공항',
                'dropoff_location' => '중구',
                'service_type' => 'pickup',
                'service_date' => now('Asia/Seoul')->addDay()->format('Y-m-d'),
                'service_time' => '17:25',
                'flight_number' => 'KE622',
                'original_summary' => pipelineSummary(),
            ],
        ],
    ])->assertCreated()->assertJsonPath('data.order_count', 2);

    $orders = Order::query()->get();

    expect($orders)->toHaveCount(2)
        ->and($orders->every(fn (Order $order): bool => $order->user_id === $this->pipelineOwner->id))->toBeTrue()
        ->and($orders->every(fn (Order $order): bool => $order->source === Order::SOURCE_PIPELINE))->toBeTrue();
});

test('앱에서 직접 등록한 운행은 요청자 소유로 남는다', function () {
    Sanctum::actingAs($this->sender);

    $payload = pipelineOrderPayload();
    unset($payload['original_summary']);

    $this->postJson('/api/orders', $payload)->assertCreated();

    $order = Order::query()->latest('id')->first();

    expect($order->user_id)->toBe($this->sender->id)
        ->and($order->source)->toBe(Order::SOURCE_MANUAL);
});

test('관리자 지표에 오늘 유입 요약이 담긴다', function () {
    $today = now('Asia/Seoul')->format('Y-m-d');
    // 테스트 실행 시각과 무관하게 KST 하루 안/밖을 고정한다 (created_at 은 UTC 로 저장된다)
    $todayKst = now('Asia/Seoul')->startOfDay();
    $atToday = fn (int $hours): Carbon => $todayKst->copy()->addHours($hours)->utc();

    // 오늘 유입 3건 — 샌딩 2(08:00·09:00, 인천공항 출발) · 랜딩 1(17:25)
    Order::factory()->create([
        'user_id' => $this->pipelineOwner->id,
        'source' => Order::SOURCE_PIPELINE,
        'service_type' => 'sending',
        'service_time' => '08:00',
        'pickup_location' => '인천공항',
        'dropoff_location' => '강남',
        'created_at' => $atToday(3),
    ]);
    Order::factory()->create([
        'user_id' => $this->pipelineOwner->id,
        'source' => Order::SOURCE_PIPELINE,
        'service_type' => 'sending',
        'service_time' => '09:00',
        'pickup_location' => '인천공항',
        'dropoff_location' => '잠실',
        'created_at' => $atToday(3),
    ]);
    Order::factory()->create([
        'user_id' => $this->pipelineOwner->id,
        'source' => Order::SOURCE_PIPELINE,
        'service_type' => 'pickup',
        'service_time' => '17:25',
        'pickup_location' => '공항',
        'dropoff_location' => '명동',
        'created_at' => $atToday(3),
    ]);

    // 앱 직접 등록 건과 어제 유입 건은 오늘 유입 집계에서 빠진다
    Order::factory()->create([
        'user_id' => $this->sender->id,
        'source' => Order::SOURCE_MANUAL,
        'service_type' => 'sending',
        'created_at' => $atToday(3),
    ]);
    Order::factory()->create([
        'user_id' => $this->pipelineOwner->id,
        'source' => Order::SOURCE_PIPELINE,
        'service_type' => 'sending',
        'created_at' => $atToday(-3),
    ]);

    Sanctum::actingAs($this->pipelineOwner);

    $ingestion = $this->getJson('/api/admin/operations/metrics')
        ->assertOk()
        ->json('data.ingestion_today');

    expect($ingestion['date'])->toBe($today)
        ->and($ingestion['total'])->toBe(3)
        ->and(collect($ingestion['by_service_type'])->pluck('count', 'key')->all())
        ->toMatchArray(['sending' => 2, 'pickup' => 1, 'point' => 0])
        ->and(collect($ingestion['by_hour'])->pluck('count', 'label')->all())
        ->toMatchArray(['08시' => 1, '09시' => 1, '17시' => 1])
        ->and(collect($ingestion['pickup_regions'])->pluck('count', 'name')->all())
        ->toMatchArray(['인천공항' => 2, '공항' => 1]);
});

test('유입 기록을 근거로 기존 운행을 파이프라인 계정으로 옮긴다', function () {
    $order = Order::factory()->create([
        'user_id' => $this->sender->id,
        'source' => Order::SOURCE_MANUAL,
    ]);

    OrderIngestion::query()->create([
        'endpoint' => 'orders',
        'payload' => ['original_summary' => pipelineSummary()],
        'status' => OrderIngestion::STATUS_PROCESSED,
        'order_ids' => [$order->id],
    ]);

    $this->artisan('orders:sync-pipeline')->assertSuccessful();

    expect($order->fresh()->source)->toBe(Order::SOURCE_PIPELINE)
        ->and($order->fresh()->user_id)->toBe($this->pipelineOwner->id);
});

test('모의 실행은 값을 바꾸지 않는다', function () {
    $order = Order::factory()->create([
        'user_id' => $this->sender->id,
        'source' => Order::SOURCE_MANUAL,
    ]);

    OrderIngestion::query()->create([
        'endpoint' => 'orders',
        'payload' => ['original_summary' => pipelineSummary()],
        'status' => OrderIngestion::STATUS_PROCESSED,
        'order_ids' => [$order->id],
    ]);

    $this->artisan('orders:sync-pipeline --dry-run')->assertSuccessful();

    expect($order->fresh()->source)->toBe(Order::SOURCE_MANUAL)
        ->and($order->fresh()->user_id)->toBe($this->sender->id);
});

test('원문이 없는 앱 등록 건은 파이프라인으로 옮기지 않는다', function () {
    $order = Order::factory()->create([
        'user_id' => $this->sender->id,
        'source' => Order::SOURCE_MANUAL,
    ]);

    OrderIngestion::query()->create([
        'endpoint' => 'orders',
        'payload' => ['pickup_location' => '중구'],
        'status' => OrderIngestion::STATUS_PROCESSED,
        'order_ids' => [$order->id],
    ]);

    $this->artisan('orders:sync-pipeline')->assertSuccessful();

    expect($order->fresh()->source)->toBe(Order::SOURCE_MANUAL)
        ->and($order->fresh()->user_id)->toBe($this->sender->id);
});
