<?php

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * 초안 일괄 공개 커맨드 — 공개 요건을 충족한 초안만 마켓에 올린다.
 */
test('공개 요건을 충족한 초안만 공개하고 구분은 노선으로 추정한다', function () {
    $tomorrow = Carbon::tomorrow('Asia/Seoul')->toDateString();

    // 차종·요금이 없어도 노선과 일시가 있으면 공개 대상이다
    $ready = Order::factory()->create([
        'status' => Order::STATUS_DRAFT,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '인천공항 T1',
        'service_date' => $tomorrow,
        'service_time' => '09:00',
        'service_type' => null,
        'vehicle_type' => null,
        'expected_revenue' => null,
    ]);

    // 도착지 누락 — 노선이 없어 공개할 수 없다
    $missing = Order::factory()->create([
        'status' => Order::STATUS_DRAFT,
        'dropoff_location' => null,
        'service_date' => $tomorrow,
    ]);

    // 이미 지난 운행 — 공개해도 곧 만료되므로 건드리지 않는다
    $past = Order::factory()->create([
        'status' => Order::STATUS_DRAFT,
        'service_date' => Carbon::yesterday('Asia/Seoul')->toDateString(),
        'service_time' => '09:00',
    ]);

    $this->artisan('orders:publish-ready')->assertSuccessful();

    expect($ready->fresh()->status)->toBe(Order::STATUS_PUBLISHED)
        ->and($ready->fresh()->service_type)->toBe('sending')
        ->and($missing->fresh()->status)->toBe(Order::STATUS_DRAFT)
        ->and($past->fresh()->status)->toBe(Order::STATUS_DRAFT);
});

test('모의 실행은 상태를 바꾸지 않는다', function () {
    $order = Order::factory()->create([
        'status' => Order::STATUS_DRAFT,
        'pickup_location' => '서울 강남',
        'dropoff_location' => '인천공항 T1',
        'service_date' => Carbon::tomorrow('Asia/Seoul')->toDateString(),
        'service_time' => '09:00',
    ]);

    $this->artisan('orders:publish-ready --dry-run')->assertSuccessful();

    expect($order->fresh()->status)->toBe(Order::STATUS_DRAFT);
});
