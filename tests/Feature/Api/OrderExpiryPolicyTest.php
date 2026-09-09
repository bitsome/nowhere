<?php

use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\OrderOffer;
use App\Models\User;
use App\Services\Order\OrderTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-09-01 08:00 Asia/Seoul'));

    $this->driver = User::factory()->create(['role' => User::ROLE_DRIVER, 'name' => '김기사']);
    $this->customer = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'name' => '홍등록']);

    Sanctum::actingAs($this->driver);
});

function publishedOrderFor(User $owner, string $time, array $extra = []): Order
{
    return Order::factory()->create(array_merge([
        'user_id' => $owner->id,
        'status' => Order::STATUS_PUBLISHED,
        'pickup_location' => '인천공항 T1',
        'dropoff_location' => '강남',
        'service_date' => now('Asia/Seoul')->format('Y-m-d'),
        'service_time' => $time,
        'amount_value' => 80000,
    ], $extra));
}

test('시작 시각 + 유예가 지난 대기 요금 제안은 자동 철회되고 기사에게 만료 알림이 간다', function () {
    $pastOrder = publishedOrderFor($this->customer, '05:00'); // 08:00 기준 유예(2h) 경과
    $futureOrder = publishedOrderFor($this->customer, '12:00');

    $this->postJson("/api/orders/{$pastOrder->id}/offers", ['amount' => 65000, 'message' => '제안합니다.'])->assertCreated();
    $this->postJson("/api/orders/{$futureOrder->id}/offers", ['amount' => 70000, 'message' => '제안합니다.'])->assertCreated();

    Artisan::call('orders:expire-offers');

    $expired = OrderOffer::query()->where('order_id', $pastOrder->id)->firstOrFail();
    $kept = OrderOffer::query()->where('order_id', $futureOrder->id)->firstOrFail();

    expect($expired->status)->toBe(OrderOffer::STATUS_CANCELLED);
    expect($kept->status)->toBe(OrderOffer::STATUS_PENDING);

    expect($this->driver->notifications()->where('data->title', '요금 제안 만료')->count())->toBe(1);
    expect($this->driver->notifications()->where('data->title', '요금 제안 만료')->first()->data['message'])->toContain('시작 시각이 지나');
});

test('이미 취소된 운행에 남은 대기 제안도 자동으로 정리된다', function () {
    $order = publishedOrderFor($this->customer, '12:00');

    $this->postJson("/api/orders/{$order->id}/offers", ['amount' => 65000, 'message' => '제안합니다.'])->assertCreated();

    // 등록자가 직접 취소 — 현재 흐름은 대기 제안을 정리하지 않고 남긴다
    app(OrderTransitionService::class)->transition($this->customer, $order, Order::STATUS_CANCELLED, '등록자 취소');

    expect($order->fresh()?->offers()->where('status', OrderOffer::STATUS_PENDING)->count())->toBe(1);

    Artisan::call('orders:expire-offers');

    expect($order->fresh()?->offers()->where('status', OrderOffer::STATUS_PENDING)->count())->toBe(0);
    expect($this->driver->notifications()->where('data->title', '요금 제안 만료')->count())->toBe(1);
    expect($this->driver->notifications()->where('data->title', '요금 제안 만료')->first()->data['message'])->toContain('취소되어');
});

test('시작 시각 후 유예가 지난 미매칭 공개 운행은 자동 취소되고 등록자·제안 기사에게 알림이 간다', function () {
    $order = publishedOrderFor($this->customer, '05:00'); // 유예(2h) 경과

    $this->postJson("/api/orders/{$order->id}/offers", ['amount' => 65000, 'message' => '제안합니다.'])->assertCreated();

    Artisan::call('orders:close-published');

    $closed = $order->fresh();

    expect($closed?->status)->toBe(Order::STATUS_CANCELLED);
    expect($closed?->cancel_reason)->toContain('매칭되지 않아');

    // 취소 사유가 타임라인에 함께 남는다
    $event = OrderEvent::query()->where('order_id', $order->id)->where('event', 'status')->latest('id')->first();

    expect($event?->from_status)->toBe(Order::STATUS_PUBLISHED);
    expect($event?->to_status)->toBe(Order::STATUS_CANCELLED);
    expect($event?->note)->toContain('매칭되지 않아');

    // 등록자 + 제안 기사 알림
    expect($this->customer->notifications()->where('data->title', '운행 자동 취소')->count())->toBe(1);
    expect($this->driver->notifications()->where('data->title', '요금 제안 만료')->count())->toBe(1);
    expect($order->fresh()?->offers()->where('status', OrderOffer::STATUS_PENDING)->count())->toBe(0);
});

test('미래 시각 공개 운행과 관리자 보류 운행은 자동 취소되지 않는다', function () {
    publishedOrderFor($this->customer, '12:00'); // 아직 유효
    publishedOrderFor($this->customer, '05:00', ['admin_hold' => true]); // 관리자 보류는 시스템이 건드리지 않음
    publishedOrderFor($this->customer, '05:00', ['is_hidden' => true]); // 관리자 숨김도 유지

    Artisan::call('orders:close-published');

    expect(Order::query()->where('status', Order::STATUS_PUBLISHED)->count())->toBe(3);
    expect(Order::query()->where('status', Order::STATUS_CANCELLED)->count())->toBe(0);
});
