<?php

namespace App\Support\Orders;

use App\Models\Order;
use App\Models\OrderGroup;
use Illuminate\Support\Collection;

/**
 * 오더 워크스페이스 목록의 공용 데이터 계약을 만든다.
 *
 * Single/Set을 묶고, 시간순으로 통합 정렬한 뒤,
 * Blade와 Vue, API 응답이 그대로 사용하는 행 목록을 반환한다.
 */
class OrderWorkspaceListBuilder
{
    public function __construct(
        private readonly OrderListRowBuilder $rowBuilder,
    ) {}

    /**
     * @param  Collection<int, Order>  $orders
     * @param  Collection<int, OrderGroup>|null  $groups
     * @param  string  $sort  latest(등록순) | date(서비스순) | amount | amount_asc
     * @return array<int, array<string, mixed>>
     */
    public function build(Collection $orders, ?Collection $groups = null, string $sort = 'date'): array
    {
        [$sets, $singles] = $this->partitionByGroup($orders);

        $groups = $this->resolveGroups($groups, array_keys($sets));

        $rows = [];

        foreach ($sets as $groupId => $setOrders) {
            $rows[] = $this->buildSetRow($groupId, collect($setOrders), $groups);
        }

        foreach ($singles as $order) {
            $rows[] = $this->rowBuilder->build($order);
        }

        return $this->sortRows($rows, $sort);
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return array{0: array<int, array<int, Order>>, 1: array<int, Order>}
     */
    private function partitionByGroup(Collection $orders): array
    {
        $sets = [];
        $singles = [];

        foreach ($orders as $order) {
            if ($order->group_id !== null) {
                $sets[$order->group_id][] = $order;
            } else {
                $singles[] = $order;
            }
        }

        return [$sets, $singles];
    }

    /**
     * @param  Collection<int, OrderGroup>|null  $groups
     * @param  array<int, int|string>  $groupIds
     * @return Collection<int, OrderGroup>
     */
    private function resolveGroups(?Collection $groups, array $groupIds): Collection
    {
        if ($groups !== null) {
            return $groups->keyBy('id');
        }

        return OrderGroup::query()
            ->whereIn('id', $groupIds)
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  Collection<int, Order>  $setOrders
     * @param  Collection<int, OrderGroup>  $groups
     * @return array<string, mixed>
     */
    private function buildSetRow(int $groupId, Collection $setOrders, Collection $groups): array
    {
        $sortedOrders = $setOrders
            ->sortBy(fn (Order $order) => ($order->service_date ?: '').' '.($order->service_time ?: ''))
            ->values();

        $firstOrder = $sortedOrders->first();

        $memberRows = $this->rowBuilder->buildMany($sortedOrders);

        $statusLabels = collect($memberRows)->pluck('statusLabel')->filter()->unique()->values();

        // 셋트 내 오더들의 상태 목록 (중복 제거, 색상은 프론트에서 매핑)
        $statuses = $sortedOrders->pluck('status')->filter()->unique()->values()->all();
        $singleStatus = count($statuses) === 1 ? $statuses[0] : 'mixed';

        // 셋트 내 하나라도 등록 1시간 이내면 N 배지
        $isNew = $sortedOrders->contains(fn (Order $order) => $order->created_at !== null && $order->created_at->gte(now()->subHours(2)));

        return [
            'key' => 'set-'.$groupId,
            'kind' => 'set',
            'id' => $groupId,
            'firstOrderId' => $firstOrder?->id,
            'name' => $groups[$groupId]->name ?? 'SET',
            'count' => $sortedOrders->count(),
            'status' => $singleStatus,
            'statusLabel' => $statusLabels->count() === 1
                ? (string) $statusLabels->first()
                : '-',
            'isNew' => $isNew,
            'isUrgent' => $sortedOrders->contains(fn (Order $order) => $this->rowBuilder->isUrgent($order)),
            'isToday' => $this->isOnDate($firstOrder, 'today'),
            'isTomorrow' => $this->isOnDate($firstOrder, 'tomorrow'),
            'showUrl' => $firstOrder !== null ? route('dashboard.business.order.show', $firstOrder) : '',
            'routes' => $sortedOrders
                ->map(fn (Order $order) => [
                    'route' => ($order->pickup_location ?: '-').' → '.($order->dropoff_location ?: '-'),
                    'time' => $this->rowBuilder->formatTime($order),
                    'date' => $this->rowBuilder->formatDate($order),
                    'id' => $order->id,
                    'serviceLabel' => $this->rowBuilder->serviceLabel($order),
                    'vehicle' => $order->vehicle_type ?: '',
                    'passengerCount' => $order->passenger_count ?: 0,
                ])
                ->values()
                ->all(),
            'pickupDateTime' => $firstOrder !== null ? $this->rowBuilder->formatPickupDateTime($firstOrder) : '-',
            'date' => $firstOrder !== null ? $this->rowBuilder->formatDate($firstOrder) : '-',
            'totalAmount' => $this->buildTotalAmount($sortedOrders),
            'passengerCount' => $sortedOrders->sum(fn (Order $order) => (int) ($order->passenger_count ?? 0)),
            'orders' => $memberRows,
            'sortDate' => $firstOrder?->service_date ?: '',
            'sortTime' => $firstOrder?->service_time ?: '',
            'sortCreatedAt' => $sortedOrders
                ->max(fn (Order $order) => $order->created_at?->toISOString() ?? '') ?? '',
            'amountValue' => (int) $sortedOrders->sum(fn (Order $order) => (int) ($order->expected_revenue ?? $order->amount_value ?? 0)),
        ];
    }

    /**
     * @param  Collection<int, Order>  $orders
     */
    private function buildTotalAmount(Collection $orders): string
    {
        $total = $orders->sum(fn (Order $order) => (int) ($order->expected_revenue ?? $order->amount_value ?? 0));

        return $total > 0 ? number_format($total).'원' : '-';
    }

    /**
     * 셋트 첫 오더의 운행일이 오늘/내일인지 판단.
     *
     * @param  Order|null  $order
     * @param  'today'|'tomorrow'  $which
     */
    private function isOnDate(?Order $order, string $which): bool
    {
        if (! $order?->service_date) {
            return false;
        }

        $method = 'is'.ucfirst($which);

        return \Carbon\Carbon::parse($order->service_date)->{$method}();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function sortRows(array $rows, string $sort): array
    {
        $sorted = collect($rows);

        return match ($sort) {
            'date' => $sorted
                ->sortBy(fn (array $row) => ($row['sortDate'] ?? '').' '.($row['sortTime'] ?? '').' '.($row['key'] ?? ''))
                ->values()
                ->all(),
            'amount' => $sorted
                ->sortByDesc(fn (array $row) => (int) ($row['amountValue'] ?? 0))
                ->values()
                ->all(),
            'amount_asc' => $sorted
                ->sortBy(fn (array $row) => (int) ($row['amountValue'] ?? 0))
                ->values()
                ->all(),
            // 등록순 (기본)
            default => $sorted
                ->sortByDesc(fn (array $row) => $row['sortCreatedAt'] ?? '')
                ->values()
                ->all(),
        };
    }
}
