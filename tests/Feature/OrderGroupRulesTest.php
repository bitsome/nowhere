<?php

use App\Models\Order;
use App\Services\OrderGroupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

/**
 * @return Collection<int, Order>
 */
function makeOrders(int $count): Collection
{
    return collect(range(1, $count))->map(fn () => Order::factory()->create());
}

beforeEach(function (): void {
    $this->service = app(OrderGroupService::class);
});

test('single orders can be grouped into a new set', function (): void {
    $orders = makeOrders(3);

    $group = $this->service->createGroup($orders->pluck('id')->all(), '아침 묶음');

    $this->assertDatabaseHas('order_groups', [
        'id' => $group->id,
        'name' => '아침 묶음',
        'type' => '셋트',
    ]);

    foreach ($orders as $order) {
        $this->assertSame($group->id, $order->refresh()->group_id);
    }

    $this->assertSame(3, $group->orders()->count());
});

test('a set requires at least two orders', function (): void {
    $order = Order::factory()->create();

    $this->service->createGroup([$order->id]);
})->throws(InvalidArgumentException::class, 'Set은 최소 2개의 Order가 필요합니다.');

test('an order already in a set cannot be added to another set', function (): void {
    [$orderA, $orderB, $orderC] = makeOrders(3)->all();
    $this->service->createGroup([$orderA->id, $orderB->id]);

    $this->service->createGroup([$orderA->id, $orderC->id]);
})->throws(InvalidArgumentException::class, '이미 다른 Set에 속한 Order는 포함할 수 없습니다.');

test('removing an order from a set makes it a single order again', function (): void {
    [$orderA, $orderB, $orderC] = makeOrders(3)->all();
    $group = $this->service->createGroup([$orderA->id, $orderB->id, $orderC->id]);

    $this->service->removeFromGroup($orderC);

    $this->assertNull($orderC->refresh()->group_id);
    $this->assertSame($group->id, $orderA->refresh()->group_id);
    $this->assertSame($group->id, $orderB->refresh()->group_id);
    $this->assertSame(2, $group->orders()->count());
});

test('a set is dissolved automatically when only one order remains', function (): void {
    [$orderA, $orderB] = makeOrders(2)->all();
    $group = $this->service->createGroup([$orderA->id, $orderB->id]);

    $this->service->removeFromGroup($orderB);

    $this->assertNull($orderA->refresh()->group_id);
    $this->assertNull($orderB->refresh()->group_id);
    $this->assertDatabaseMissing('order_groups', ['id' => $group->id]);
});

test('an order can be moved to another set', function (): void {
    [$orderA, $orderB, $orderX, $orderC, $orderD] = makeOrders(5)->all();
    $firstGroup = $this->service->createGroup([$orderA->id, $orderB->id, $orderX->id]);
    $secondGroup = $this->service->createGroup([$orderC->id, $orderD->id]);

    $this->service->moveToGroup($orderB, $secondGroup);

    $this->assertSame($secondGroup->id, $orderB->refresh()->group_id);
    $this->assertSame(2, $firstGroup->orders()->count());
    $this->assertSame(3, $secondGroup->orders()->count());
});

test('moving the last order dissolves the source set', function (): void {
    [$orderA, $orderB, $orderC, $orderD] = makeOrders(4)->all();
    $firstGroup = $this->service->createGroup([$orderA->id, $orderB->id]);
    $secondGroup = $this->service->createGroup([$orderC->id, $orderD->id]);

    $this->service->moveToGroup($orderB, $secondGroup);

    $this->assertDatabaseMissing('order_groups', ['id' => $firstGroup->id]);
    $this->assertNull($orderA->refresh()->group_id);
});

test('deleting an order recalculates its set', function (): void {
    [$orderA, $orderB, $orderC] = makeOrders(3)->all();
    $group = $this->service->createGroup([$orderA->id, $orderB->id, $orderC->id]);

    $orderC->refresh();
    $orderC->delete();
    $this->service->recalculateAfterDelete($orderC);

    $this->assertSame(2, $group->orders()->count());
    $this->assertDatabaseHas('order_groups', ['id' => $group->id]);
});

test('deleting an order that leaves one member dissolves the set', function (): void {
    [$orderA, $orderB] = makeOrders(2)->all();
    $group = $this->service->createGroup([$orderA->id, $orderB->id]);

    $orderB->refresh();
    $orderB->delete();
    $this->service->recalculateAfterDelete($orderB);

    $this->assertDatabaseMissing('order_groups', ['id' => $group->id]);
    $this->assertNull($orderA->refresh()->group_id);
});

test('an empty set is deleted automatically', function (): void {
    [$orderA, $orderB] = makeOrders(2)->all();
    $group = $this->service->createGroup([$orderA->id, $orderB->id]);

    $orderA->refresh();
    $orderA->delete();

    $orderB->refresh();
    $orderB->delete();

    $this->service->recalculateAfterDelete($orderB);

    $this->assertDatabaseMissing('order_groups', ['id' => $group->id]);
});
