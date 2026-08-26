<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use App\Services\MatchService;
use App\Support\Orders\OrderWorkspaceListBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * 운행 목록 조회 — 내 운행/마켓 스코프, 필터·정렬·매칭 필터·등록자 신뢰 정보를 한곳에서 처리한다.
 */
class OrderListService
{
    public function __construct(
        private readonly MatchService $matchService,
    ) {}

    /**
     * 목록 조회 진입점.
     *
     * @return array{rows: array<int, array<string, mixed>>, pagination: array<string, mixed>}
     */
    public function index(Request $request): array
    {
        $query = $this->baseQuery($request);
        $this->applyFilters($request, $query);
        $this->applyMatchedFilter($request, $query);
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);
        $orders = $this->paginate($query, $request, $perPage);

        $rows = app(OrderWorkspaceListBuilder::class)->build(
            collect($orders->items()),
            null,
            $request->string('sort', 'latest')->toString(),
        );

        if ($request->string('scope', 'market')->toString() === 'market') {
            $rows = $this->decorateMarketRows($rows, $orders, $request->user());
        } else {
            // 내 운행(진행자) 관점 — 완료됐지만 아직 정산 전이면 '정산 진행중'으로 표시
            foreach ($rows as &$row) {
                if (($row['status'] ?? null) === Order::STATUS_COMPLETED) {
                    $row['statusLabel'] = '정산 진행중';
                }
            }
            unset($row);
        }

        return [
            'rows' => $rows,
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'last_page' => $orders->lastPage(),
            ],
        ];
    }

    /**
     * 스코프(내 운행/마켓)에 따른 기본 쿼리를 구성한다.
     */
    private function baseQuery(Request $request): Builder
    {
        $query = Order::query();
        $scope = $request->string('scope', 'market')->toString();
        $tab = $request->string('tab', '진행중')->toString();
        $source = $request->string('source')->toString();
        $user = $request->user();

        if ($scope === 'mine') {
            $this->applyMineScope($query, $user, $source, $tab);
        } else {
            $this->applyMarketScope($query, $user);
        }

        return $query;
    }

    private function applyMineScope(Builder $query, User $user, string $source, string $tab): void
    {
        if ($source === 'registered') {
            // 등록된 운행 — 직접 등록(아직 안 넘어감) + 내가 등록해 남에게 넘어간 운행(original_owner_id)
            // 가져오기 요청(승인 대기)도 함께 노출된다.
            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->where(function ($q2) {
                        $q2->whereNull('claimed_at')
                            ->orWhere('status', Order::STATUS_ACCEPTANCE_PENDING);
                    })
                    ->orWhere('original_owner_id', $user->id);
            });
        } else {
            // 받은 운행 = 내가 소유한 가져온 운행 + 내가 요청한 승인 대기
            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->whereNotNull('claimed_at')
                    ->where('status', '!=', Order::STATUS_ACCEPTANCE_PENDING)
                    ->orWhere('claimant_user_id', $user->id);
            });
        }

        if ($tab === '초안') {
            $query->where('status', Order::STATUS_DRAFT);
        } else {
            match ($tab) {
                '완료' => $query->whereIn('status', [Order::STATUS_COMPLETED, Order::STATUS_SETTLED]),
                '취소' => $query->where('status', Order::STATUS_CANCELLED),
                default => $query->whereNotIn('status', [
                    Order::STATUS_DRAFT,
                    Order::STATUS_COMPLETED,
                    Order::STATUS_SETTLED,
                    Order::STATUS_CANCELLED,
                ]),
            };
        }

        // 요청자(claimant) 이름을 함께 내려 주고, 승인 대기 운행은 항상 맨 위에 노출
        $query->with('claimant')
            ->orderByRaw("CASE WHEN status = '".Order::STATUS_ACCEPTANCE_PENDING."' THEN 0 ELSE 1 END");
    }

    private function applyMarketScope(Builder $query, User $user): void
    {
        // 가져오기 요청(승인 대기)이 걸린 운행은 다른 드라이버에게 노출하지 않는다
        $query->whereIn('status', [
            Order::STATUS_PUBLISHED,
            Order::STATUS_TRADING,
            Order::STATUS_ACCEPTANCE_PENDING,
        ])
            ->whereNull('claimed_at');

        // 서비스 날짜가 이미 지난 운행은 노출하지 않는다 (날짜 미정 운행은 유지, KST 기준)
        $query->where(function ($sub) {
            $sub->where('service_date', '>=', now('Asia/Seoul')->format('Y-m-d'))
                ->orWhereNull('service_date')
                ->orWhere('service_date', '');
        });

        // 서비스 시작 시각이 현재보다 2시간 넘게 지난 운행은 제외 (일시 불완전 운행은 유지)
        $cutoff = now('Asia/Seoul')->subHours(2)->format('Y-m-d H:i');

        $query->where(function ($sub) use ($cutoff) {
            $sub->where(function ($q) use ($cutoff) {
                $q->whereNotNull('service_date')
                    ->where('service_date', '!=', '')
                    ->whereNotNull('service_time')
                    ->where('service_time', '!=', '')
                    ->whereRaw("(service_date || ' ' || service_time) >= ?", [$cutoff]);
            })->orWhere(function ($q) {
                $q->whereNull('service_date')
                    ->orWhere('service_date', '')
                    ->orWhereNull('service_time')
                    ->orWhere('service_time', '');
            });
        });
    }

    /**
     * 공용 필터(서비스 타입/날짜/지역/차량/금액/인원/퀵 칩/검색)를 적용한다.
     */
    private function applyFilters(Request $request, Builder $query): void
    {
        $serviceType = $request->string('service_type')->toString();
        $date = $request->string('date')->toString();
        $departure = trim($request->string('departure')->toString());
        $arrival = trim($request->string('arrival')->toString());
        $vehicleType = $request->string('vehicle_type')->toString();
        $vehicleCapacity = $request->string('vehicle_capacity')->toString();
        $minAmount = $request->integer('min_amount', 0);
        $maxAmount = $request->integer('max_amount', 0);
        $minPassengers = $request->integer('min_passengers', 0);
        $search = trim($request->string('search')->toString());

        if (in_array($serviceType, ['pickup', 'sending', 'landing'], true)) {
            $query->where('service_type', $serviceType);
        }

        if ($date !== '') {
            // 날짜만(Y-m-d)이면 그 날짜 전체, 날짜시간(Y-m-d H:i)이면 해당 시각 이후 운행
            if (str_contains($date, ' ')) {
                $query->whereRaw("(service_date || ' ' || service_time) >= ?", [$date]);
            } else {
                $query->where('service_date', $date);
            }
        }

        if ($departure !== '') {
            $query->where('pickup_location', 'like', "%{$departure}%");
        }

        if ($arrival !== '') {
            $query->where('dropoff_location', 'like', "%{$arrival}%");
        }

        if ($vehicleType !== '') {
            $query->where('vehicle_type', 'like', "%{$vehicleType}%");
        }

        if ($vehicleCapacity !== '') {
            $query->where('vehicle_type', 'like', "%{$vehicleCapacity}%");
        }

        if ($minAmount > 0) {
            $query->where('expected_revenue', '>=', $minAmount);
        }

        if ($maxAmount > 0) {
            $query->where('expected_revenue', '<=', $maxAmount);
        }

        if ($minPassengers > 0) {
            $query->where('passenger_count', '>=', $minPassengers);
        }

        $this->applyQuickFilter($request, $query);
        $this->applySearch($query, $search, $request->string('scope', 'market')->toString());
    }

    private function applyQuickFilter(Request $request, Builder $query): void
    {
        $quick = $request->string('quick')->toString();

        if ($quick === 'amount') {
            return; // 금액순은 정렬로 처리
        }

        if (! in_array($quick, ['new', 'urgent', 'today', 'tomorrow', 'priority'], true)) {
            return;
        }

        $nowKst = now('Asia/Seoul');

        match ($quick) {
            'new' => $query->where('created_at', '>=', now()->subHours(2)),
            'urgent' => $query
                ->where('service_date', $nowKst->format('Y-m-d'))
                ->where('service_time', '>=', $nowKst->format('H:i'))
                ->where('service_time', '<=', $nowKst->copy()->addMinutes(120)->format('H:i')),
            'today' => $query->where('service_date', $nowKst->format('Y-m-d')),
            'tomorrow' => $query->where('service_date', $nowKst->copy()->addDay()->format('Y-m-d')),
            'priority' => $query->where('is_priority', true),
            default => null,
        };
    }

    private function applySearch(Builder $query, string $search, string $scope = 'market'): void
    {
        if ($search === '') {
            return;
        }

        if ($scope === 'market') {
            // 마켓 검색은 노선(출발/도착)만 매칭한다 — 고객명 등 개인정보는 검색하지 않는다
            $query->where(function ($sub) use ($search) {
                $sub->where('pickup_location', 'like', "%{$search}%")
                    ->orWhere('dropoff_location', 'like', "%{$search}%");
            });

            return;
        }

        $query->where(function ($sub) use ($search) {
            $sub->where('order_number', 'like', "%{$search}%")
                ->orWhere('customer_name', 'like', "%{$search}%")
                ->orWhere('pickup_location', 'like', "%{$search}%")
                ->orWhere('dropoff_location', 'like', "%{$search}%")
                ->orWhere('reservation_company', 'like', "%{$search}%");
        });
    }

    /**
     * 매칭 페이지 전용 — 활성 매칭 설정 조건에 맞는 운행만 남긴다.
     */
    private function applyMatchedFilter(Request $request, Builder $query): void
    {
        if (! $request->boolean('matched')) {
            return;
        }

        $preferences = $request->user()->matchPreferences()->where('is_active', true)->get();

        if ($preferences->isEmpty()) {
            $query->whereRaw('1 = 0');

            return;
        }

        $eligibleIds = (clone $query)
            ->get()
            ->filter(fn (Order $order) => $preferences->contains(
                fn ($preference) => $this->matchService->isEligible($request->user(), $preference, $order),
            ))
            ->pluck('id');

        $query->whereIn('id', $eligibleIds);
    }

    /**
     * 정렬 키를 결정한다 (quick=amount는 금액순으로 강제).
     */
    private function sortKey(Request $request): string
    {
        return $request->string('quick')->toString() === 'amount'
            ? 'amount'
            : $request->string('sort', 'latest')->toString();
    }

    private function paginate(Builder $query, Request $request, int $perPage): LengthAwarePaginator
    {
        return match ($this->sortKey($request)) {
            'date' => $query
                ->orderByRaw("CASE WHEN service_date IS NULL OR service_date = '' THEN 1 ELSE 0 END")
                ->orderBy('service_date')
                ->orderBy('service_time')
                ->paginate($perPage),
            'date_desc' => $query
                ->orderByRaw("CASE WHEN service_date IS NULL OR service_date = '' THEN 1 ELSE 0 END")
                ->orderByDesc('service_date')
                ->orderByDesc('service_time')
                ->paginate($perPage),
            'amount' => $query->orderByDesc('expected_revenue')->paginate($perPage),
            'amount_asc' => $query->orderBy('expected_revenue')->paginate($perPage),
            default => $query->latest()->paginate($perPage),
        };
    }

    /**
     * 마켓 응답 보강 — 등록자 신뢰 정보 + '나에게 매칭됨' 플래그.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function decorateMarketRows(array $rows, LengthAwarePaginator $orders, User $user): array
    {
        $rows = $this->withOwnerTrust($rows, $orders->items());

        return $this->withMatchFlag($rows, $orders->items(), $user);
    }

    /**
     * '나에게 매칭됨' 플래그 — 활성 매칭 설정 조건 + 가용성(온라인/시간 겹침)을 만족하는 운행.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, Order>  $orderItems
     * @return array<int, array<string, mixed>>
     */
    private function withMatchFlag(array $rows, array $orderItems, User $user): array
    {
        $preferences = $user->matchPreferences()->where('is_active', true)->get();

        if ($preferences->isEmpty()) {
            foreach ($rows as &$row) {
                $row['is_matched_to_me'] = false;
            }

            return $rows;
        }

        $ordersById = collect($orderItems)->keyBy('id');

        foreach ($rows as &$row) {
            $order = $ordersById[$row['id']] ?? null;
            $row['is_matched_to_me'] = $order !== null
                && $preferences->contains(fn ($preference) => $this->matchService->isEligible($user, $preference, $order));
        }

        return $rows;
    }

    /**
     * 등록자 신뢰 정보 — 이름/평점/리뷰 수/완료 운행 수.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, Order>  $orderItems
     * @return array<int, array<string, mixed>>
     */
    private function withOwnerTrust(array $rows, array $orderItems): array
    {
        $orders = collect($orderItems);
        $ownerIds = $orders->pluck('user_id')->filter()->unique();

        $names = User::query()->whereIn('id', $ownerIds)->pluck('name', 'id');

        $reviewStats = Review::query()
            ->whereIn('reviewee_id', $ownerIds)
            ->selectRaw('reviewee_id, COUNT(*) as cnt, AVG(rating) as avg')
            ->groupBy('reviewee_id')
            ->get()
            ->keyBy('reviewee_id');

        $completedCounts = Order::query()
            ->whereIn('user_id', $ownerIds)
            ->whereIn('status', [Order::STATUS_COMPLETED, Order::STATUS_SETTLED])
            ->selectRaw('user_id, COUNT(*) as cnt')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $ownerIdByOrderId = $orders->pluck('user_id', 'id');

        foreach ($rows as &$row) {
            $ownerId = $ownerIdByOrderId[$row['id']] ?? null;

            if ($ownerId === null) {
                $row['owner'] = null;

                continue;
            }

            $rating = $reviewStats[$ownerId] ?? null;
            $completed = $completedCounts[$ownerId] ?? null;

            $row['owner'] = [
                'id' => $ownerId,
                'name' => $names[$ownerId] ?? '',
                'rating' => $rating ? round((float) $rating->avg, 1) : 0,
                'review_count' => (int) ($rating->cnt ?? 0),
                'completed_count' => (int) ($completed->cnt ?? 0),
            ];
        }

        return $rows;
    }
}
