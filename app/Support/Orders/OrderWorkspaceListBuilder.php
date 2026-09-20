<?php

namespace App\Support\Orders;

use App\Models\Order;
use App\Models\OrderGroup;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * 운행 워크스페이스 목록의 공용 데이터 계약을 만든다.
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
            // 운행이 하나뿐인 묶음은 셋트가 아니라 일반 카드로 보여준다 — 셋트 표기가 판단을 흐린다
            if (count($setOrders) === 1) {
                $singles[] = $setOrders[0];

                continue;
            }

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

        // 셋트 내 운행들의 상태 목록 (중복 제거, 색상은 프론트에서 매핑)
        $statuses = $sortedOrders->pluck('status')->filter()->unique()->values()->all();
        $singleStatus = count($statuses) === 1 ? $statuses[0] : 'mixed';

        // 셋트 내 하나라도 등록 1시간 이내면 N 배지
        $isNew = $sortedOrders->contains(fn (Order $order) => $order->created_at !== null && $order->created_at->gte(now()->subHours(2)));

        // 앱에서 지은 이름이 없으면 첫 다리 노선 요약을 만들어 쓴다 — 카드에서는 바로 아래 일정과 중복이라 감춘다
        $storedName = $this->storedSetName($groups[$groupId]->name ?? null);

        return [
            'key' => 'set-'.$groupId,
            'kind' => 'set',
            'id' => $groupId,
            'firstOrderId' => $firstOrder?->id,
            'name' => $storedName ?? $this->generatedSetName($sortedOrders),
            'isNameGenerated' => $storedName === null,
            'count' => $sortedOrders->count(),
            'status' => $singleStatus,
            'statusLabel' => $statusLabels->count() === 1
                ? (string) $statusLabels->first()
                : '-',
            'isNew' => $isNew,
            'isUrgent' => $sortedOrders->contains(fn (Order $order) => $this->rowBuilder->isUrgent($order)),
            'isPriority' => $sortedOrders->contains(fn (Order $order) => $this->rowBuilder->isPriority($order)),
            'isToday' => $this->isOnDate($firstOrder, 'today'),
            'isTomorrow' => $this->isOnDate($firstOrder, 'tomorrow'),
            'routes' => $sortedOrders
                ->map(fn (Order $order) => [
                    'route' => $this->rowBuilder->routeLabel($order),
                    'time' => $this->rowBuilder->formatTime($order),
                    'date' => $this->rowBuilder->formatDate($order),
                    'id' => $order->id,
                    'serviceLabel' => $this->rowBuilder->serviceLabel($order),
                    // 값이 없으면 카드에서 차량 줄 자체를 숨기므로 빈 문자열을 유지한다
                    'vehicle' => trim((string) $order->vehicle_type) === ''
                        ? ''
                        : ChineseTextNormalizer::displayVehicle($order->vehicle_type),
                    'passengerCount' => $order->passenger_count ?: 0,
                    'luggageCount' => $order->luggage_count ?: 0,
                    'flightNumber' => $order->flight_number ?: '',
                    // 다리별 대략 거리(km) — 단일 카드와 같은 기준
                    'distanceKm' => LocationDistance::km($order->pickup_location, $order->dropoff_location),
                    'sortDate' => $order->service_date ?: '',
                ])
                ->values()
                ->all(),
            'pickupDateTime' => $firstOrder !== null ? $this->rowBuilder->formatPickupDateTime($firstOrder) : '-',
            'date' => $firstOrder !== null ? $this->rowBuilder->formatDate($firstOrder) : '-',
            'totalAmount' => $this->buildTotalAmount($sortedOrders),
            // 셋트 인원은 합계가 아니라 가장 많은 다리 기준 — 다리마다 같은 일행이 나눠 타는 경우를 합치면 부풀려진다
            'passengerCount' => (int) $sortedOrders->max(fn (Order $order) => (int) ($order->passenger_count ?? 0)),
            // 셋트 태그 — 다리들의 태그를 합쳐 중복을 없앤다 (단일 행과 같은 자리에서 그대로 읽는다)
            // 유입 파서가 붙이는 내부 태그는 여기서도 걷어낸다
            'tags' => $sortedOrders
                ->flatMap(fn (Order $order) => ChineseTextNormalizer::withoutInternalTags($order->tags))
                ->unique()
                ->values()
                ->all(),
            'orders' => $memberRows,
            'sortDate' => $firstOrder?->service_date ?: '',
            'sortTime' => $firstOrder?->service_time ?: '',
            'sortCreatedAt' => $sortedOrders
                ->max(fn (Order $order) => $order->created_at?->toISOString() ?? '') ?? '',
            'amountValue' => (int) $sortedOrders->sum(fn (Order $order) => (int) ($order->expected_revenue ?? $order->amount_value ?? 0)),
        ];
    }

    /**
     * 앱에서 직접 지은 이름 — 비어 있거나 한자(漢字)가 섞였으면 없는 것으로 본다.
     *
     * 유입 원문(중국어 요약문)이 그대로 노출되지 않도록 한자 이름은 쓰지 않는다.
     * (유입 원문에는 모니터가 붙인 '[2개]' 접두사가 있어 한글 포함 여부로는 구분되지 않는다)
     */
    private function storedSetName(?string $storedName): ?string
    {
        $name = trim((string) $storedName);

        return $name !== '' && ! $this->hasHanzi($name) ? $name : null;
    }

    /**
     * 이름이 없을 때 쓸 대표 노선 요약.
     *
     * 카드에서는 바로 아래 일정 목록과 중복이라 감추고(`isNameGenerated`), 홈 히어로처럼
     * 한 줄로 셋트를 가리켜야 하는 곳에서만 쓴다.
     *
     * @param  Collection<int, Order>  $orders  서비스 일시 순으로 정렬된 셋트 운행
     */
    private function generatedSetName(Collection $orders): string
    {
        // 위치 값 자체에 한자가 남아 있는 경우가 있어(유입 원문 잡음), 한자가 없는 첫 다리를 대표로 삼는다.
        $representative = $orders->first(
            fn (Order $order): bool => ! $this->hasHanzi($order->pickup_location) && ! $this->hasHanzi($order->dropoff_location),
        );

        if ($representative === null) {
            return '셋트 운행';
        }

        $from = trim((string) $representative->pickup_location);
        $to = trim((string) $representative->dropoff_location);

        if ($from === '' && $to === '') {
            return '셋트 운행';
        }

        $route = ($from !== '' ? $from : '미정').' → '.($to !== '' ? $to : '미정');
        $rest = $orders->count() - 1;

        return $rest > 0 ? $route.' 외 '.$rest.'건' : $route;
    }

    /**
     * 한자(漢字) 포함 여부 — 유입 원문·오염된 위치 값 판별용.
     */
    private function hasHanzi(?string $value): bool
    {
        return $value !== null && preg_match('/\p{Han}/u', $value) === 1;
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
     * 셋트 첫 운행의 운행일이 오늘/내일인지 판단.
     *
     * @param  'today'|'tomorrow'  $which
     */
    private function isOnDate(?Order $order, string $which): bool
    {
        if (! $order?->service_date) {
            return false;
        }

        $method = 'is'.ucfirst($which);

        return Carbon::parse($order->service_date)->{$method}();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function sortRows(array $rows, string $sort): array
    {
        $sorted = collect($rows);

        $result = match ($sort) {
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

        // 가져오기 승인 대기 운행은 항상 맨 위에 노출 (등록자가 승인해야 하므로)
        $pending = [];
        $rest = [];

        foreach ($result as $row) {
            if (($row['status'] ?? null) === Order::STATUS_ACCEPTANCE_PENDING) {
                $pending[] = $row;
            } else {
                $rest[] = $row;
            }
        }

        return [...$pending, ...$rest];
    }
}
