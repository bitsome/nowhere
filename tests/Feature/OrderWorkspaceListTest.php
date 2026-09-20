<?php

use App\Models\Order;
use App\Models\OrderGroup;
use App\Support\Orders\OrderWorkspaceListBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * 목록 카드 구성 — 운행이 하나뿐인 묶음은 셋트가 아니라 일반 카드로 보여준다.
 */
test('운행이 둘 이상인 묶음은 셋트 카드 한 장으로 묶는다', function () {
    $group = OrderGroup::query()->create(['name' => '아침 묶음', 'type' => '셋트']);

    $orders = collect([
        Order::factory()->create(['group_id' => $group->id]),
        Order::factory()->create(['group_id' => $group->id]),
    ]);

    $rows = app(OrderWorkspaceListBuilder::class)->build($orders);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['kind'])->toBe('set')
        ->and($rows[0]['count'])->toBe(2);
});

test('운행이 하나뿐인 묶음은 일반 카드로 보여준다', function () {
    $group = OrderGroup::query()->create(['name' => '한 건 묶음', 'type' => '셋트']);
    $order = Order::factory()->create(['group_id' => $group->id]);

    $rows = app(OrderWorkspaceListBuilder::class)->build(collect([$order]));

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['kind'])->toBe('single')
        ->and($rows[0]['id'])->toBe($order->id);
});

test('셋트 카드의 인원은 합계가 아니라 가장 많은 다리 기준이다', function () {
    $group = OrderGroup::query()->create(['name' => '인원 확인', 'type' => '셋트']);

    $orders = collect([
        Order::factory()->create(['group_id' => $group->id, 'passenger_count' => 2]),
        Order::factory()->create(['group_id' => $group->id, 'passenger_count' => 5]),
        Order::factory()->create(['group_id' => $group->id, 'passenger_count' => 3]),
    ]);

    $rows = app(OrderWorkspaceListBuilder::class)->build($orders);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['passengerCount'])->toBe(5);
});

test('셋트 카드의 태그는 다리 태그를 합쳐 중복 없이 보여준다', function () {
    $group = OrderGroup::query()->create(['name' => '태그 확인', 'type' => '셋트']);

    $orders = collect([
        Order::factory()->create([
            'group_id' => $group->id,
            'service_date' => '2026-09-20',
            'service_time' => '10:00',
            'tags' => ['바로결제', '호텔'],
        ]),
        Order::factory()->create([
            'group_id' => $group->id,
            'service_date' => '2026-09-20',
            'service_time' => '14:00',
            'tags' => ['호텔', '짐 없음'],
        ]),
    ]);

    $rows = app(OrderWorkspaceListBuilder::class)->build($orders);

    expect($rows[0]['tags'])->toBe(['바로결제', '호텔', '짐 없음']);
});

test('카드 태그에서 유입 파서 내부 태그는 뺀다', function () {
    $single = Order::factory()->create(['tags' => ['wechat-monitor', '호텔']]);

    $group = OrderGroup::query()->create(['name' => '내부 태그', 'type' => '셋트']);

    $legs = collect([
        Order::factory()->create([
            'group_id' => $group->id,
            'service_date' => '2026-09-20',
            'service_time' => '10:00',
            'tags' => ['wechat-monitor', '바로결제'],
        ]),
        Order::factory()->create([
            'group_id' => $group->id,
            'service_date' => '2026-09-20',
            'service_time' => '14:00',
            'tags' => ['conf60'],
        ]),
    ]);

    $builder = app(OrderWorkspaceListBuilder::class);

    $singleRows = $builder->build(collect([$single]));
    $setRows = $builder->build($legs);

    expect($singleRows[0]['tags'])->toBe(['호텔'])
        ->and($setRows[0]['tags'])->toBe(['바로결제']);
});

test('이름이 없는 묶음은 첫 다리 노선 요약을 만들고 자동 생성으로 표시한다', function () {
    $group = OrderGroup::query()->create(['name' => '', 'type' => '셋트']);

    $orders = collect([
        Order::factory()->create([
            'group_id' => $group->id,
            'pickup_location' => '명동',
            'dropoff_location' => '인천공항 T1',
            'service_date' => '2026-10-01',
            'service_time' => '09:00',
        ]),
        Order::factory()->create([
            'group_id' => $group->id,
            'pickup_location' => '인천공항 T1',
            'dropoff_location' => '강남',
            'service_date' => '2026-10-01',
            'service_time' => '13:00',
        ]),
    ]);

    $rows = app(OrderWorkspaceListBuilder::class)->build($orders);

    // 카드에서는 아래 일정과 중복이라 감추고, 홈 히어로처럼 한 줄로 가리켜야 하는 곳에서만 쓴다
    expect($rows[0]['isNameGenerated'])->toBeTrue()
        ->and($rows[0]['name'])->toBe('명동 → 인천공항 T1 외 1건');
});

test('앱에서 지은 묶음 이름은 그대로 쓰고 자동 생성으로 보지 않는다', function () {
    $group = OrderGroup::query()->create(['name' => 'KLOOK 8월', 'type' => '셋트']);

    $orders = collect([
        Order::factory()->create(['group_id' => $group->id]),
        Order::factory()->create(['group_id' => $group->id]),
    ]);

    $rows = app(OrderWorkspaceListBuilder::class)->build($orders);

    expect($rows[0]['isNameGenerated'])->toBeFalse()
        ->and($rows[0]['name'])->toBe('KLOOK 8월');
});

test('한 다리라도 긴급이면 셋트 카드도 긴급으로 표시한다', function () {
    $group = OrderGroup::query()->create(['name' => '긴급 확인', 'type' => '셋트']);

    $calm = collect([
        Order::factory()->create(['group_id' => $group->id, 'is_priority' => false]),
        Order::factory()->create(['group_id' => $group->id, 'is_priority' => false]),
    ]);

    expect(app(OrderWorkspaceListBuilder::class)->build($calm)[0]['isPriority'])->toBeFalse();

    $urgent = collect([
        Order::factory()->create(['group_id' => $group->id, 'is_priority' => false]),
        Order::factory()->create(['group_id' => $group->id, 'is_priority' => true]),
    ]);

    expect(app(OrderWorkspaceListBuilder::class)->build($urgent)[0]['isPriority'])->toBeTrue();
});

test('카드 행에 출발지·도착지 대략 거리를 함께 내려준다', function () {
    $order = Order::factory()->create([
        'pickup_location' => '인천공항',
        'dropoff_location' => '명동',
    ]);

    $rows = app(OrderWorkspaceListBuilder::class)->build(collect([$order]));

    expect($rows[0]['kind'])->toBe('single')
        ->and($rows[0]['distanceKm'])->toBeGreaterThan(52)
        ->toBeLessThan(65);
});

test('셋트 카드는 다리별 거리를 내려주고 좌표를 모르는 지명은 비운다', function () {
    $group = OrderGroup::query()->create(['name' => '거리 확인', 'type' => '셋트']);

    $orders = collect([
        Order::factory()->create([
            'group_id' => $group->id,
            'pickup_location' => '김포공항',
            'dropoff_location' => '명동',
            'service_date' => '2026-09-20',
            'service_time' => '09:00',
        ]),
        // 좌표를 모르는 지명 — 거리 없이 노선만 보여준다
        Order::factory()->create([
            'group_id' => $group->id,
            'pickup_location' => '미정',
            'dropoff_location' => '명동',
            'service_date' => '2026-09-20',
            'service_time' => '13:00',
        ]),
    ]);

    $routes = app(OrderWorkspaceListBuilder::class)->build($orders)[0]['routes'];

    expect($routes[0]['distanceKm'])->toBeGreaterThan(16)
        ->toBeLessThan(26)
        ->and($routes[1]['distanceKm'])->toBeNull();
});
