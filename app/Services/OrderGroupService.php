<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderGroup;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OrderGroupService
{
    /**
     * Single Order들을 선택해 새 Set을 생성한다. (Single → Set)
     *
     * @param  array<int, int>  $orderIds
     */
    public function createGroup(array $orderIds, ?string $name = null, string $type = '셋트'): OrderGroup
    {
        $orders = Order::query()->whereKey($orderIds)->get();

        if ($orders->count() < 2) {
            throw new InvalidArgumentException('Set은 최소 2개의 Order가 필요합니다.');
        }

        if ($orders->contains(fn (Order $order): bool => $order->group_id !== null)) {
            throw new InvalidArgumentException('이미 다른 Set에 속한 Order는 포함할 수 없습니다.');
        }

        return DB::transaction(function () use ($orders, $name, $type): OrderGroup {
            $group = OrderGroup::create([
                'name' => $name,
                'type' => $type,
            ]);

            $orders->each(function (Order $order) use ($group): void {
                $order->update(['group_id' => $group->id]);
            });

            return $group;
        });
    }

    /**
     * Set에서 특정 Order를 제거해 Single Order로 되돌린다. (Set → Single)
     * 남은 Order가 1개 이하가 되면 Set을 자동으로 해제한다. (Rule 1, 2)
     */
    public function removeFromGroup(Order $order): void
    {
        $order->refresh();
        $groupId = $order->group_id;

        if ($groupId === null) {
            return;
        }

        DB::transaction(function () use ($order, $groupId): void {
            $order->update(['group_id' => null]);

            $group = OrderGroup::find($groupId);

            if ($group !== null) {
                $this->dissolveIfNeeded($group);
            }
        });
    }

    /**
     * Order를 다른 Set으로 이동한다. (Set → Set)
     * 원래 Set에 남은 Order가 1개 이하가 되면 자동으로 해제한다. (Rule 1, 2)
     */
    public function moveToGroup(Order $order, OrderGroup $targetGroup): void
    {
        $order->refresh();

        if ($order->group_id === $targetGroup->id) {
            return;
        }

        DB::transaction(function () use ($order, $targetGroup): void {
            $previousGroupId = $order->group_id;

            $order->update(['group_id' => $targetGroup->id]);

            if ($previousGroupId !== null && $previousGroupId !== $targetGroup->id) {
                $previousGroup = OrderGroup::find($previousGroupId);

                if ($previousGroup !== null) {
                    $this->dissolveIfNeeded($previousGroup);
                }
            }
        });
    }

    /**
     * Order 삭제 후 해당 Set을 다시 계산한다. (Rule 3)
     * 남은 Order가 1개 이하가 되면 Set을 자동으로 해제한다.
     *
     * 삭제 전에 조회한 Order 인스턴스를 넘겨야 정확한 group_id를 읽을 수 있다.
     */
    public function recalculateAfterDelete(Order $order): void
    {
        $groupId = $order->group_id;

        if ($groupId === null) {
            return;
        }

        $group = OrderGroup::find($groupId);

        if ($group !== null) {
            $this->dissolveIfNeeded($group);
        }
    }

    /**
     * Set에 Order가 1개 이하만 남으면 해제하고 남은 Order를 Single로 되돌린다. (Rule 1, 2)
     */
    private function dissolveIfNeeded(OrderGroup $group): void
    {
        if ($group->orders()->count() > 1) {
            return;
        }

        $group->orders()->update(['group_id' => null]);
        $group->delete();
    }
}
