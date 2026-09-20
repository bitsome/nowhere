<?php

use App\Models\Order;
use App\Models\OrderIngestion;
use App\Models\OrderTerm;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * 내장 사전 소급 적용 커맨드 — 사전에 추가한 표기를 이미 저장된 운행·용어에 반영한다.
 */
test('내장 사전 표기를 기존 운행·일정·미매핑 용어에 소급 적용한다', function () {
    $order = Order::factory()->create([
        'pickup_location' => '釜山',
        'dropoff_location' => '机场',
        'vehicle_type' => '九卡',
    ]);
    $order->lineItems()->create(['dropoff_location' => '文鹤']);

    OrderTerm::query()->create([
        'field' => OrderTerm::FIELD_PICKUP,
        'term' => '釜山',
        'status' => OrderTerm::STATUS_PENDING,
        'occurrences' => 1,
    ]);

    $this->artisan('orders:apply-terms')->assertSuccessful();

    expect($order->fresh()->pickup_location)->toBe('부산시')
        ->and($order->fresh()->vehicle_type)->toBe('카니발 9인승')
        ->and($order->lineItems()->firstOrFail()->dropoff_location)->toBe('문학');

    $term = OrderTerm::query()->where('term', '釜山')->firstOrFail();

    expect($term->status)->toBe(OrderTerm::STATUS_MAPPED)
        ->and($term->mapped_to)->toBe('부산시');
});

test('태그 표기는 운행에 태그를 붙이고 미매핑 용어는 무시한다', function () {
    $order = Order::factory()->create([
        'pickup_location' => '帮划客路',
        'tags' => ['wechat-monitor', '호텔'],
    ]);

    OrderTerm::query()->create([
        'field' => OrderTerm::FIELD_PICKUP,
        'term' => '帮划客路',
        'status' => OrderTerm::STATUS_PENDING,
        'occurrences' => 2,
    ]);

    $this->artisan('orders:apply-terms')->assertSuccessful();

    expect($order->fresh()->tags)->toBe(['호텔', '클록스텝진행'])
        // 태그 표기는 지명이 아니므로 값은 그대로 둔다
        ->and($order->fresh()->pickup_location)->toBe('帮划客路');

    $locationRow = OrderTerm::query()
        ->where('field', OrderTerm::FIELD_PICKUP)
        ->where('term', '帮划客路')
        ->firstOrFail();

    expect($locationRow->status)->toBe(OrderTerm::STATUS_IGNORED)
        ->and($locationRow->mapped_to)->toBeNull();

    // 태그 표기는 '태그' 분야 행으로 등록된다
    $tagRow = OrderTerm::query()
        ->where('field', OrderTerm::FIELD_TAG)
        ->where('term', '帮划客路')
        ->firstOrFail();

    expect($tagRow->status)->toBe(OrderTerm::STATUS_MAPPED)
        ->and($tagRow->mapped_to)->toBe('클록스텝진행');
});

test('긴급 표기가 붙은 운행은 긴급으로 올린다', function () {
    $order = Order::factory()->create([
        'pickup_location' => '공항',
        'dropoff_location' => '명동',
        'is_priority' => false,
        'tags' => ['wechat-monitor', '고객 나옴'],
    ]);

    $this->artisan('orders:apply-terms')->assertSuccessful();

    expect($order->fresh()->is_priority)->toBeTrue()
        // 태그는 그대로 남는다 — 왜 긴급인지 기사가 볼 수 있어야 한다
        ->and($order->fresh()->tags)->toBe(['고객 나옴']);
});

test('지명 칸에 없는 태그 표기도 유입 원문에서 찾아 붙인다', function () {
    $order = Order::factory()->create([
        'pickup_location' => '공항',
        'dropoff_location' => '명동',
        'tags' => ['wechat-monitor'],
    ]);

    // 원문에만 있는 운영 지시 — 지명 칸에는 남지 않는다
    OrderIngestion::query()->create([
        'endpoint' => 'orders',
        'status' => OrderIngestion::STATUS_PROCESSED,
        'order_ids' => [$order->id],
        'payload' => ['original_summary' => "월입 500만 러리군\n17:35 接机-明洞 ✈KE408\n老外 跑完秒结"],
    ]);

    $this->artisan('orders:apply-terms')->assertSuccessful();

    expect($order->fresh()->tags)->toBe(['외국인 고객', '바로결제']);
});
