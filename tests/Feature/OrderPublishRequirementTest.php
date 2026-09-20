<?php

use App\Models\Order;
use App\Models\User;
use App\Services\Order\OrderTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * 등록자로 공개 전이를 시도하는 클로저 — 공개 요건에 걸리면 ValidationException 을 던진다.
 */
function publishAttempt(Order $order): Closure
{
    return fn (): mixed => app(OrderTransitionService::class)->transition(
        User::query()->findOrFail($order->user_id),
        $order,
        Order::STATUS_PUBLISHED,
    );
}

test('노선과 날짜가 정상이면 공개한다', function () {
    $order = Order::factory()->create([
        'status' => Order::STATUS_DRAFT,
        'pickup_location' => '강남구',
        'dropoff_location' => '인천공항 제1터미널',
        'service_date' => now('Asia/Seoul')->addDay()->toDateString(),
    ]);

    (publishAttempt($order))();

    expect($order->fresh()->status)->toBe(Order::STATUS_PUBLISHED);
});

test('지명 사전에서 읽지 못한 운행은 공개하지 않는다', function () {
    $order = Order::factory()->create([
        'status' => Order::STATUS_DRAFT,
        'pickup_location' => '帮划客路',
        'dropoff_location' => '공항',
        'service_date' => now('Asia/Seoul')->addDay()->toDateString(),
    ]);

    expect($order->publishRequirementError())->toContain('읽지 못한 지명');
    expect(publishAttempt($order))->toThrow(ValidationException::class);

    expect($order->fresh()->status)->toBe(Order::STATUS_DRAFT);
});

test('지난 날짜로는 공개하지 않는다', function () {
    $order = Order::factory()->create([
        'status' => Order::STATUS_DRAFT,
        'pickup_location' => '강남구',
        'dropoff_location' => '공항',
        'service_date' => now('Asia/Seoul')->subDay()->toDateString(),
    ]);

    expect($order->publishRequirementError())->toContain('지난 날짜');
    expect(publishAttempt($order))->toThrow(ValidationException::class);
});

test('상한을 넘긴 먼 날짜로는 공개하지 않는다', function () {
    // 원문의 소수 금액(12.5🌾)을 날짜로 잘못 읽으면 몇 달 뒤 날짜로 저장된다
    $order = Order::factory()->create([
        'status' => Order::STATUS_DRAFT,
        'pickup_location' => '강남구',
        'dropoff_location' => '공항',
        'service_date' => now('Asia/Seoul')->addDays(31)->toDateString(),
    ]);

    expect($order->publishRequirementError())->toContain('30일보다 뒤');
    expect(publishAttempt($order))->toThrow(ValidationException::class);
});

test('여러 문제가 있으면 한 번에 안내한다', function () {
    $order = Order::factory()->create([
        'status' => Order::STATUS_DRAFT,
        'pickup_location' => '套出',
        'dropoff_location' => '공항',
        'service_date' => now('Asia/Seoul')->subDay()->toDateString(),
    ]);

    $error = $order->publishRequirementError();

    expect($error)->toContain('읽지 못한 지명')
        ->and($error)->toContain('지난 날짜');
});
