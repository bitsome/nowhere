<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use App\Services\MatchService;
use App\Support\Orders\OrderWorkspaceListBuilder;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * 운행 목록 조회 — 내 운행/마켓 스코프, 필터·정렬·매칭 필터·등록자 신뢰 정보를 한곳에서 처리한다.
 */
class OrderListService
{
    public function __construct(
        private readonly MatchService $matchService,
    ) {}

    /**
     * 서비스 지역 밖 시·도 키워드 — 서울/인천/경기만 취급하므로 그 외 지역명이
     * 명시된 운행은 추천에서 제외한다. ('경기 광주'처럼 시·도가 명시된 경우
     * 서울/인천/경기 판단이 우선이라 서비스 지역으로 처리된다.)
     */
    private const OUT_OF_SERVICE_REGION = [
        '부산', '대구', '광주', '대전', '울산', '세종',
        '강원', '충청', '전라', '경상', '경남', '경북',
        '제주',
    ];

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

            // 등록한 운행 — 카드에 대기 제안(오퍼) 건수 배지를 붙인다
            if ($request->string('source', '')->toString() === 'registered') {
                $rows = $this->attachPendingOffers($rows, collect($orders->items()));
            }
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
        } elseif ($source === 'all') {
            // 등록 + 받은 운행 모두 — 내가 등록했거나(original_owner 포함), 가져왔거나(claimant 포함) 한 운행
            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere('original_owner_id', $user->id)
                    ->orWhere('claimant_user_id', $user->id);
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

        // 탭별 상태 그룹 — 등록자 기준: 공개(등록·거래) / 진행중(배차~운행) / 정산(완료·정산)
        match ($tab) {
            '공개' => $query->whereIn('status', [Order::STATUS_PUBLISHED, Order::STATUS_TRADING]),
            '진행중' => $query->whereIn('status', [
                Order::STATUS_ACCEPTANCE_PENDING,
                Order::STATUS_ACCEPTED,
                Order::STATUS_DRIVING,
            ]),
            '정산' => $query->whereIn('status', [Order::STATUS_COMPLETED, Order::STATUS_SETTLED]),
            '초안' => $query->where('status', Order::STATUS_DRAFT),
            '취소' => $query->where('status', Order::STATUS_CANCELLED),
            default => $query->whereNotIn('status', [
                Order::STATUS_DRAFT,
                Order::STATUS_COMPLETED,
                Order::STATUS_SETTLED,
                Order::STATUS_CANCELLED,
            ]),
        };

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
        $cutoff = now('Asia/Seoul')->subHours(2);
        [$cutoffDate, $cutoffTime] = explode(' ', $cutoff->format('Y-m-d H:i'));

        $query->where(function ($sub) use ($cutoffDate, $cutoffTime) {
            $sub->where(function ($q) use ($cutoffDate, $cutoffTime) {
                $q->whereNotNull('service_date')
                    ->where('service_date', '!=', '')
                    ->whereNotNull('service_time')
                    ->where('service_time', '!=', '')
                    ->where(function ($dateQuery) use ($cutoffDate, $cutoffTime) {
                        // 날짜+시간 문자열 비교 대신 날짜·시간을 분리 비교 (SQLite/MySQL 공용)
                        $dateQuery->where('service_date', '>', $cutoffDate)
                            ->orWhere(function ($q2) use ($cutoffDate, $cutoffTime) {
                                $q2->where('service_date', $cutoffDate)
                                    ->where('service_time', '>=', $cutoffTime);
                            });
                    });
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
        $timeRange = $request->string('time_range')->toString();

        if (in_array($serviceType, ['pickup', 'sending', 'landing'], true)) {
            $query->where('service_type', $serviceType);
        }

        if ($date !== '') {
            // 날짜만(Y-m-d)이면 그 날짜 전체, 날짜시간(Y-m-d H:i)이면 해당 시각 이후 운행
            if (str_contains($date, ' ')) {
                [$dateOnly, $timeOnly] = explode(' ', $date);

                // 날짜+시간 문자열 비교 대신 날짜·시간을 분리 비교 (SQLite/MySQL 공용)
                $query->where(function ($q) use ($dateOnly, $timeOnly) {
                    $q->where('service_date', '>', $dateOnly)
                        ->orWhere(function ($q2) use ($dateOnly, $timeOnly) {
                            $q2->where('service_date', $dateOnly)
                                ->where('service_time', '>=', $timeOnly);
                        });
                });
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

        // 시간대 필터 — 오전(00:00~12:00)/오후(12:00~18:00)/야간(18:00~) (0패딩 HH:MM 문자열 비교로 SQLite/MySQL 공용)
        if (in_array($timeRange, ['morning', 'afternoon', 'night'], true)) {
            $query->whereNotNull('service_time')
                ->where('service_time', '!=', '')
                ->where(match ($timeRange) {
                    'morning' => fn ($q) => $q->where('service_time', '>=', '00:00')->where('service_time', '<', '12:00'),
                    'afternoon' => fn ($q) => $q->where('service_time', '>=', '12:00')->where('service_time', '<', '18:00'),
                    'night' => fn ($q) => $q->where('service_time', '>=', '18:00'),
                });
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

    /**
     * 연결 운행(연속 체인) 추천 — 내가 맡은 운행(수락/운행중)의 하차지에서 이어지는 마켓 운행을 찾는다.
     * (CJ 더운반/uber Freight의 리턴 로드 개념 — 하차 후 공차 이동을 줄이기 위한 우선 노출)
     *
     * 정합성 보완:
     * - 서비스 시각이 이미 충분히 지난 운행은 추천 근거에서 제외 (최근·예정 운행만 사용)
     * - 연결 운행은 랜딩 후 간격 창(낮 3~4시간 / 새벽·저녁·밤·야밤 2~3시간)에 시작하는 것만 추천
     * - 목적지 서울이면 다음 출발도 서울이어야 하고, 서울 내 구 단위는 달라도 연결한다
     * - 연결1(연결2)을 탄 뒤 차량이 도착하는 하차지에서 이어지는 다음 연결(연결3)까지 체인으로 추천한다
     *   (출발지 = 차량 위치, 공운행 방지)
     * - 현재 마켓 필터(시간대·날짜·노선·차량 등)를 함께 반영해 목록과 일관성 유지
     *
     * @return array<int, array<string, mixed>>
     */
    public function returnRoutes(Request $request, ?Carbon $maxStart = null, ?Carbon $minStart = null): array
    {
        $user = $request->user();

        // 내가 맡은 운행의 하차지 + 랜딩 시각 — 복귀 노선의 시작점 후보
        $tripSignals = Order::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [Order::STATUS_ACCEPTED, Order::STATUS_DRIVING])
            ->whereNotNull('dropoff_location')
            ->where('dropoff_location', '!=', '')
            ->get()
            ->filter(fn (Order $trip) => $this->isRelevantTrip($trip))
            ->map(fn (Order $trip) => [
                'dropoff' => (string) $trip->dropoff_location,
                'tokens' => $this->locationTokens($trip->dropoff_location),
                'landingAt' => $this->landingAt($trip),
            ])
            ->filter(fn (array $signal) => $this->inServiceRegion($signal['dropoff']))
            ->values();

        if ($tripSignals->isEmpty()) {
            return [];
        }

        // 마켓 후보 — 공개/거래중, 가져오기 요청 없음, 남의 운행, 서비스 시각이 지나지 않음
        $candidatesQuery = $this->marketCandidatesQuery($user, $maxStart, $minStart);

        // 현재 마켓 필터를 함께 적용 — 검색 중인 노선·날짜·시간대·차량에 맞는 복귀 운행만 추천
        $this->applyFilters($request, $candidatesQuery);

        $candidates = $candidatesQuery
            ->orderBy('service_date')
            ->orderBy('service_time')
            // 연결 창(랜딩 후 2~4시간)까지 밀집 데이터(~1,000건/일)에서도 충분히 미치도록 넉넉히 가져온다
            ->limit(2000)
            ->get()
            // 서비스 지역(서울/인천/경기) 안에서 시작·도착하는 운행만 추천한다.
            ->filter(fn (Order $order) => $this->inServiceRegion((string) $order->pickup_location)
                && $this->inServiceRegion((string) $order->dropoff_location));

        // 연결1 — 출발지가 내 하차지(차량 위치)와 연결되고, 랜딩 후 간격(낮 3~4시간/야간 2~3시간)에
        // 시작하는 샌딩(소요 30분~3시간)만 추천한다. (목적지 서울 → 출발 서울 규칙 포함)
        $leg2 = $candidates
            ->filter(fn (Order $order) => $tripSignals->contains(
                fn (array $signal) => $this->regionMatches($signal['dropoff'], (string) $order->pickup_location)
                    && $this->hasReasonableDuration($order)
                    && $this->startsWithinLandingGap($signal['landingAt'], $order),
            ))
            ->take(10);

        if ($leg2->isEmpty()) {
            return [];
        }

        // 연결2 — 연결1을 탄 뒤 차량이 도착하는 하차지에서 이어지는 다음 연결 (공운행 방지)
        $leg2Signals = $leg2->map(fn (Order $order) => [
            'dropoff' => (string) $order->dropoff_location,
            'landingAt' => $this->landingAt($order),
        ]);

        $leg3 = $candidates
            ->filter(fn (Order $order) => $leg2Signals->contains(
                fn (array $signal) => $this->regionMatches($signal['dropoff'], (string) $order->pickup_location)
                    && $this->hasReasonableDuration($order)
                    && $this->startsWithinLandingGap($signal['landingAt'], $order),
            ))
            // 연결1과 같은 운행은 제외 (출발지가 다르므로 실제로 겹치진 않지만 안전망)
            ->reject(fn (Order $order) => $leg2->contains('id', $order->id))
            ->values();

        // 연결1을 표시 순서(강력 우선)로 정렬 — 연결2는 화면에 보이는 연결1 뒤에 붙어야 체인이 이어진다
        $leg2Ordered = $this->sortStrongFirstOrders($leg2, $tripSignals);

        // 행 구성 — 연결1은 강력 우선, 연결2는 이어진 연결1 바로 뒤에 배치
        $leg2Rows = $this->returnRouteRows($leg2Ordered, $tripSignals);

        foreach ($leg2Rows as &$row) {
            $row['chain_leg'] = 2;
        }
        unset($row);

        // 연결2를 연결1별로 배정 — 표시 순서의 연결1마다 가장 빠른 이어지는 연결을 1건씩 붙인다.
        // (연결1이 여럿이어도 각자 자기 뒤의 연결2를 가져 연결 체인이 끊기지 않는다)
        $leg2ById = $leg2Ordered->keyBy('id');
        $leg3Prev = [];
        $leg3Used = [];

        foreach ($leg2Rows as $row) {
            $l2 = $leg2ById[$row['id']] ?? null;

            if ($l2 === null) {
                continue;
            }

            foreach ($leg3 as $order) {
                if (isset($leg3Used[$order->id])) {
                    continue;
                }

                if ($this->regionMatches((string) $l2->dropoff_location, (string) $order->pickup_location)
                    && $this->startsWithinLandingGap($this->landingAt($l2), $order)) {
                    $leg3Prev[$order->id] = $l2->id;
                    $leg3Used[$order->id] = true;

                    break;
                }
            }
        }

        $leg3Rows = $this->returnRouteRows($leg3, $leg2Signals);

        foreach ($leg3Rows as &$row) {
            $row['chain_leg'] = 3;
            $row['chain_prev_id'] = $leg3Prev[$row['id']] ?? null;
        }
        unset($row);

        // 최종 순서 — 연결1(같은 구 우선) 뒤에 그에 이어지는 연결2를 붙여 체인으로 만든다
        $ordered = [];

        foreach ($leg2Rows as $row) {
            $ordered[] = $row;

            foreach ($leg3Rows as $leg3Row) {
                if (($leg3Row['chain_prev_id'] ?? null) === $row['id']) {
                    $ordered[] = $leg3Row;
                }
            }
        }

        return $ordered;
    }

    /**
     * 연결 운행 행을 만든다 — 강력추천(같은 구·같은 공항 터미널) 우선 정렬과 등록자 신뢰 정보를 함께 붙인다.
     *
     * @param  Collection<int, Order>  $orders
     * @param  Collection<int, array{dropoff: string, landingAt?: Carbon|null}>  $signals
     * @return array<int, array<string, mixed>>
     */
    private function returnRouteRows(Collection $orders, Collection $signals): array
    {
        $orderItems = $orders->all();
        $rows = app(OrderWorkspaceListBuilder::class)->build($orders, null, 'date');

        // 강력추천 — 하차지와 출발지가 같은 구/같은 공항 터미널이면 strong, 그 외(같은 시/도)는 normal
        $strongIds = $orders
            ->filter(fn (Order $order) => $signals->contains(
                fn (array $signal) => $this->strongRegionMatch($signal['dropoff'], (string) $order->pickup_location),
            ))
            ->pluck('id')
            ->flip();

        foreach ($rows as &$row) {
            $row['recommend_reason'] = '연결 운행';
            $row['recommend_level'] = isset($strongIds[$row['id']]) ? 'strong' : 'normal';
        }
        unset($row);

        // 강력추천 우선 — 같은 등급 안에서는 날짜순이 유지되도록 안정 정렬
        usort($rows, function (array $a, array $b) use ($strongIds): int {
            $strongA = isset($strongIds[$a['id']]);
            $strongB = isset($strongIds[$b['id']]);

            if ($strongA === $strongB) {
                return 0;
            }

            return $strongA ? -1 : 1;
        });

        return $this->withOwnerTrust($rows, $orderItems);
    }

    /**
     * 연결 운행을 강력추천(같은 구·같은 공항 터미널) 우선으로 안정 정렬한다.
     * returnRouteRows()의 표시 순서와 연결2 배정 기준을 일치시키기 위해 사용한다.
     *
     * @param  Collection<int, Order>  $orders
     * @param  Collection<int, array{dropoff: string}>  $signals
     * @return Collection<int, Order>
     */
    private function sortStrongFirstOrders(Collection $orders, Collection $signals): Collection
    {
        $strongIds = $orders
            ->filter(fn (Order $order) => $signals->contains(
                fn (array $signal) => $this->strongRegionMatch($signal['dropoff'], (string) $order->pickup_location),
            ))
            ->pluck('id')
            ->flip();

        $byDate = $orders->sortBy([
            fn (Order $order) => (string) $order->service_date,
            fn (Order $order) => (string) $order->service_time,
        ]);

        return $byDate->sortBy(fn (Order $order) => isset($strongIds[$order->id]) ? 0 : 1);
    }

    /**
     * 홈 '추천일정' — 현재 운행(예약·일정)이 없어도 마켓 운행을 추천한다.
     *
     * 우선순위:
     * 1) 현재 맡은 운행(수락/운행중)이 있으면 연결 운행 추천을 그대로 사용한다.
     * 2) 일정이 없으면 '매칭 설정 + 운행 이력' 복합으로 추천한다.
     *    - 활성 매칭 설정의 지역·시간·날짜·최소수익 조건에 맞는 운행 (이유: '매칭 설정')
     *    - 최근 운행 이력에서 자주 다니는 출발/하차 지역에서 시작하는 운행 (이유: '자주 다니는 노선')
     *
     * @return array<int, array<string, mixed>>
     */
    public function recommendations(Request $request): array
    {
        $user = $request->user();

        // 추천은 오늘~내일 중심 — 지금부터 4시간 이내 시작하는 운행만 대상으로 한다.
        // (예: 오늘 23:00이면 내일 03:00까지 4시간 창)
        $now = now('Asia/Seoul');
        $maxStart = $now->copy()->addHours(4);

        // 1) 연결 운행 — 현재 맡은 운행 기준 (일정이 있는 경우 우선).
        //    다음 운행 시각은 '랜딩 + 간격 창'으로 정해지므로 4시간 창을 걸지 않는다.
        //    (2)의 매칭 설정·운행 이력 추천과 합쳐 홈 '강력추천'·'추천일정'에 함께 노출된다.
        $returnRows = $this->returnRoutes($request, null, $now);

        foreach ($returnRows as &$row) {
            $row['recommend_reason'] = '연결 운행';
        }
        unset($row);

        // 2) 매칭 설정 + 운행 이력 복합 추천 (연결 운행이 있어도 함께 계산해 노출한다)
        //    후보는 '지금부터'(과거 제외) 전부 — 매칭 설정 운행은 알람과 동일하게
        //    4시간 창 밖이어도 추천하고, 이력 기반 추천만 4시간 창 안으로 제한한다.
        //    샌딩→랜딩 소요시간은 30분~3시간 운행만 대상(기본 알고리즘).
        $candidates = $this->marketCandidatesQuery($user, null, $now)
            ->orderBy('service_date')
            ->orderBy('service_time')
            // 4시간 창 밖 매칭 설정 운행까지 포함하므로 밀집 데이터에서도 넉넉히 가져온다
            ->limit(2000)
            ->get()
            ->filter(fn (Order $order) => $this->hasReasonableDuration($order))
            // 서비스 지역(서울/인천/경기) 밖에서 시작·도착하는 운행은 추천에서 제외한다.
            ->filter(fn (Order $order) => $this->inServiceRegion((string) $order->pickup_location)
                && $this->inServiceRegion((string) $order->dropoff_location));

        if ($candidates->isEmpty()) {
            return [];
        }

        // 4시간 창 안 운행 — 이력(지역·시간) 신호의 대상
        $withinWindow = $candidates->filter(
            fn (Order $order) => $this->startsBeforeMax($order, $maxStart),
        );

        $matched = [];
        $reasons = [];

        // 2-1) 활성 매칭 설정 조건 일치 — 설정 우선 (4시간 창 무관, 알람 대상과 일치)
        //      매칭 시간대 안 운행 + 시간 중심 앞연결(시작 전 도착)·뒤연결(종료 후 출발)까지 포함
        $preferences = $user->matchPreferences()->where('is_active', true)->get();

        foreach ($candidates as $order) {
            foreach ($preferences as $preference) {
                if ($this->matchService->isMatch($preference, $order)
                    || $this->matchService->isFrontLink($preference, $order)
                    || $this->matchService->isBackLink($preference, $order)) {
                    $matched[$order->id] = $order;
                    $reasons[$order->id] = '매칭 설정';
                    break;
                }
            }
        }

        // 2-2) 운행 이력·가져오기 요청 이력 기반 — 설정 매칭이 없는 4시간 창 안 운행만 채운다
        //      (지역: 자주 다니는 노선 → 시간대: 자주 운행한 시간 순)
        $signals = $this->driverHistorySignals($user);

        if ($signals['areas'] !== []) {
            foreach ($withinWindow as $order) {
                if (isset($matched[$order->id])) {
                    continue;
                }

                if ($this->matchesAnyArea((string) $order->pickup_location, $signals['areas'])) {
                    $matched[$order->id] = $order;
                    $reasons[$order->id] = '자주 다니는 노선';
                }
            }
        }

        if ($signals['hours'] !== []) {
            foreach ($withinWindow as $order) {
                if (isset($matched[$order->id])) {
                    continue;
                }

                if ($order->service_time
                    && in_array((int) substr($order->service_time, 0, 2), $signals['hours'], true)) {
                    $matched[$order->id] = $order;
                    $reasons[$order->id] = '자주 운행한 시간';
                }
            }
        }

        $orderItems = array_values($matched);

        if ($orderItems === []) {
            $rows = [];
        } else {
            $rows = app(OrderWorkspaceListBuilder::class)->build(collect($orderItems), null, 'date');

            foreach ($rows as &$row) {
                $row['recommend_reason'] = $reasons[$row['id']] ?? null;
            }
            unset($row);

            // 홈에서 '가장 좋은 3개'를 먼저 보여줄 수 있도록 품질 순으로 정렬한다.
            // (같은 등급 안에서는 기존 날짜순이 유지되도록 안정 정렬)
            $reasonRank = [
                '연결 운행' => 1,
                '매칭 설정' => 2,
                '자주 다니는 노선' => 3,
                '자주 운행한 시간' => 4,
            ];

            usort($rows, function (array $a, array $b) use ($reasonRank): int {
                $rankA = $reasonRank[$a['recommend_reason'] ?? ''] ?? 9;
                $rankB = $reasonRank[$b['recommend_reason'] ?? ''] ?? 9;

                return $rankA <=> $rankB;
            });

            $rows = $this->withOwnerTrust($rows, $orderItems);
        }

        // 연결 운행(강력추천) + 매칭 설정·이력(추천일정) 합치기 — 중복 id 제거, 연결 운행 우선
        $seen = [];
        $merged = [];

        foreach (array_merge($returnRows, $rows) as $row) {
            if (isset($seen[$row['id']])) {
                continue;
            }
            $seen[$row['id']] = true;
            $merged[] = $row;
        }

        return $merged;
    }

    /**
     * 마켓 추천 후보 쿼리 — 공개/거래중, 가져오기 요청 없음, 남의 운행, 서비스 시각이 지나지 않은 운행.
     * maxStart를 주면 그 시각 이내에 시작하는 운행만, minStart를 주면 그 시각 이후 시작하는 운행만 남긴다.
     * (minStart 기본값: 2시간 전 — 마켓 왕복 목록은 최근 시작 운행도 허용하되, 추천은 '지금'부터만)
     */
    private function marketCandidatesQuery(User $user, ?Carbon $maxStart = null, ?Carbon $minStart = null): Builder
    {
        $cutoff = $minStart ?? now('Asia/Seoul')->subHours(2);
        [$cutoffDate, $cutoffTime] = explode(' ', $cutoff->format('Y-m-d H:i'));

        $query = Order::query()
            ->whereIn('status', [Order::STATUS_PUBLISHED, Order::STATUS_TRADING])
            ->whereNull('claimed_at')
            ->where('user_id', '!=', $user->id)
            ->whereNotNull('pickup_location')
            ->where('pickup_location', '!=', '')
            ->where(function ($sub) use ($cutoffDate, $cutoffTime) {
                $sub->where(function ($q) use ($cutoffDate, $cutoffTime) {
                    $q->whereNotNull('service_date')
                        ->where('service_date', '!=', '')
                        ->whereNotNull('service_time')
                        ->where('service_time', '!=', '')
                        ->where(function ($dateQuery) use ($cutoffDate, $cutoffTime) {
                            $dateQuery->where('service_date', '>', $cutoffDate)
                                ->orWhere(function ($q2) use ($cutoffDate, $cutoffTime) {
                                    $q2->where('service_date', $cutoffDate)
                                        ->where('service_time', '>=', $cutoffTime);
                                });
                        });
                })->orWhere(function ($q) {
                    $q->whereNull('service_date')
                        ->orWhere('service_date', '')
                        ->orWhereNull('service_time')
                        ->orWhere('service_time', '');
                });
            });

        // 추천 창 상한 — maxStart 이내에 시작하는 운행만 (시간 미정 운행은 유지)
        if ($maxStart !== null) {
            $maxDate = $maxStart->format('Y-m-d');
            $maxTime = $maxStart->format('H:i');

            $query->where(function ($sub) use ($maxDate, $maxTime) {
                $sub->where(function ($q) use ($maxDate, $maxTime) {
                    $q->whereNotNull('service_date')
                        ->where('service_date', '!=', '')
                        ->whereNotNull('service_time')
                        ->where('service_time', '!=', '')
                        ->where(function ($dateQuery) use ($maxDate, $maxTime) {
                            $dateQuery->where('service_date', '<', $maxDate)
                                ->orWhere(function ($q2) use ($maxDate, $maxTime) {
                                    $q2->where('service_date', $maxDate)
                                        ->where('service_time', '<=', $maxTime);
                                });
                        });
                })->orWhere(function ($q) {
                    $q->whereNull('service_date')
                        ->orWhere('service_date', '')
                        ->orWhereNull('service_time')
                        ->orWhere('service_time', '');
                });
            });
        }

        return $query;
    }

    /**
     * 운행 이력 + 가져오기 요청 이력에서 추천 신호를 뽑는다.
     * 지역(출발·하차 토큰 상위 10개)과 시간대(2회 이상 운행한 시각)를 함께 계산한다.
     *
     * @return array{areas: array<int, string>, hours: array<int, int>}
     */
    private function driverHistorySignals(User $user): array
    {
        $orders = Order::query()
            ->where(function ($query) use ($user) {
                // 내가 운행했거나 운행 중인 운행
                $query->where('user_id', $user->id)
                    ->whereIn('status', [
                        Order::STATUS_ACCEPTED,
                        Order::STATUS_DRIVING,
                        Order::STATUS_COMPLETED,
                        Order::STATUS_SETTLED,
                    ])
                    // 가져오기를 요청했지만 아직 승인 전인 운행
                    ->orWhere(function ($pending) use ($user) {
                        $pending->where('claimant_user_id', $user->id)
                            ->where('status', Order::STATUS_ACCEPTANCE_PENDING);
                    });
            })
            ->latest('service_date')
            ->limit(50)
            ->get();

        $areaCounts = [];
        $hourCounts = [];

        foreach ($orders as $order) {
            foreach ([$order->pickup_location, $order->dropoff_location] as $location) {
                foreach ($this->locationTokens((string) $location) as $token) {
                    $areaCounts[$token] = ($areaCounts[$token] ?? 0) + 1;
                }
            }

            if (filled($order->service_time)) {
                $hour = (int) substr($order->service_time, 0, 2);
                $hourCounts[$hour] = ($hourCounts[$hour] ?? 0) + 1;
            }
        }

        arsort($areaCounts);
        arsort($hourCounts);

        return [
            'areas' => array_slice(array_keys($areaCounts), 0, 10),
            'hours' => array_keys(array_filter($hourCounts, fn (int $count): bool => $count >= 2)),
        ];
    }

    /**
     * 출발지가 자주 다니는 지역과 겹치는지 확인한다.
     *
     * @param  array<int, string>  $frequentAreas
     */
    private function matchesAnyArea(string $location, array $frequentAreas): bool
    {
        foreach ($this->locationTokens($location) as $token) {
            if (in_array($token, $frequentAreas, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 왕복 추천 근거로 쓸 수 있는 운행인지 — 서비스 시각이 아직 지나지 않았는지 확인한다.
     * 일시 미정(날짜 없음) 운행은 근거로 유지하고, 시간 미정이면 날짜만으로 판단한다.
     */
    private function isRelevantTrip(Order $trip): bool
    {
        if (blank($trip->service_date)) {
            return true;
        }

        $today = now('Asia/Seoul')->format('Y-m-d');

        if (blank($trip->service_time)) {
            return $trip->service_date >= $today;
        }

        return Carbon::parse($trip->service_date.' '.$trip->service_time, 'Asia/Seoul')
            >= now('Asia/Seoul')->subHours(3);
    }

    /**
     * 실제 하차(차량이 비워지는) 시각 — 서비스 시작 + 랜딩 대기 + 소요시간. 시간 미정 운행은 null.
     *
     * 랜딩(공항 픽업) 운행은 service_time이 '항공기 도착 시각'이므로, 승객이
     * 입국심사·짐찾기로 나오는 데 걸리는 대기(설정: 평균 60분)를 먼저 더한다.
     */
    private function landingAt(Order $trip): ?Carbon
    {
        if (blank($trip->service_time)) {
            return null;
        }

        $carbon = Carbon::parse($trip->service_date.' '.$trip->service_time, 'Asia/Seoul');

        if ($this->isLandingTrip($trip)) {
            $carbon = $carbon->addMinutes((int) config('recommendation.landing_wait_minutes', 60));
        }

        return $carbon->addMinutes($trip->estimated_duration_minutes ?? 60);
    }

    /**
     * 랜딩(공항 픽업) 운행 여부 — service_type이 landing이거나 출발지가 공항이면 랜딩이다.
     */
    private function isLandingTrip(Order $order): bool
    {
        return $order->service_type === 'landing'
            || mb_stripos((string) $order->pickup_location, '공항') !== false;
    }

    /**
     * 샌딩→랜딩 소요시간이 기본 알고리즘(30분~2시간) 범위인지.
     * 소요시간 미정 운행은 판단을 보류한다(true).
     */
    private function hasReasonableDuration(Order $order): bool
    {
        $duration = $order->estimated_duration_minutes;

        if ($duration === null) {
            return true;
        }

        return $duration >= 30 && $duration <= 120;
    }

    /**
     * 후보 운행(샌딩)이 내 랜딩 후 간격 창(낮 2~3시간 / 야간 1~2시간)에 시작하는지.
     * 일시 불완전 운행은 시간 검증을 건너뛴다.
     */
    private function startsWithinLandingGap(?Carbon $landingAt, Order $order): bool
    {
        if ($landingAt === null) {
            return true;
        }

        if (blank($order->service_date) || blank($order->service_time)) {
            return true;
        }

        $start = Carbon::parse($order->service_date.' '.$order->service_time, 'Asia/Seoul');
        [$minHours, $maxHours] = $this->landingGapHours($landingAt);

        return $start->gte($landingAt->copy()->addHours($minHours))
            && $start->lte($landingAt->copy()->addHours($maxHours));
    }

    /**
     * 랜딩 후 다음 샌딩까지 간격(시간).
     * 2~3시간은 구간 이동시간(서울 내 30~60분, 공항 터미널 T1↔T2 약 15분) + 여유 1시간을
     * 포함한 기본값이고, 낮(09~17시)이 아닌 새벽·저녁·밤·야밤에는 이동이 빨라 1~2시간으로 타이트하게 잡는다.
     *
     * @return array{int, int} [최소시간, 최대시간]
     */
    private function landingGapHours(Carbon $landingAt): array
    {
        $hour = (int) $landingAt->format('G');
        $isDaytime = $hour >= 9 && $hour <= 16;

        return $isDaytime ? [2, 3] : [1, 2];
    }

    /**
     * 연속 운행 지역 매칭 — 같은 시/도(서울/인천/경기) 안이면 연결된다.
     * 구 단위가 달라도 시/도가 같으면 일반 추천으로 연결되고,
     * 같은 구·같은 공항 터미널은 강력추천(recommend_level=strong)으로 구분된다.
     * 예) 하차 '서울 중구' → 출발 '서울 강남구' (매칭), 하차 '인천 송도' → 출발 '인천 부평' (매칭)
     */
    private function regionMatches(string $dropoff, string $pickup): bool
    {
        if ($this->sameProvince($dropoff, $pickup)) {
            return true;
        }

        return array_intersect($this->locationTokens($pickup), $this->locationTokens($dropoff)) !== [];
    }

    /**
     * 같은 시/도(서울/인천/경기) 여부 — 서비스 지역 안에서는 시/도 단위로 연결을 허용한다.
     */
    private function sameProvince(string $a, string $b): bool
    {
        $normalize = fn (string $location): string => str_replace('특별시', '', $location);
        $aNormalized = $normalize($a);
        $bNormalized = $normalize($b);

        foreach (['서울', '인천', '경기'] as $province) {
            if (mb_stripos($aNormalized, $province) !== false && mb_stripos($bNormalized, $province) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * 강력추천 여부 — 하차지와 출발지가 '같은 구/상세 구역'이거나 '같은 공항 터미널'이면 강력추천.
     * - 같은 구: '서울 마포구' ↔ '서울 마포구' (구·동·읍·면 단위 토큰 일치)
     * - 같은 공항 터미널: 인천공항 T1↔T1, T2↔T2, 김포 국내선↔국내선, 국제선↔국제선
     * - 같은 시/도만 겹치거나(서울↔서울) 공항 단위만 겹치는(인천공항 T1↔T2) 연결은 일반 추천이다.
     */
    private function strongRegionMatch(string $dropoff, string $pickup): bool
    {
        $dropoffTerminal = $this->airportTerminal($dropoff);
        $pickupTerminal = $this->airportTerminal($pickup);

        // 양쪽 모두 터미널이 명시된 공항이면 터미널이 같아야 강력추천 (T1↔T2는 일반)
        if ($dropoffTerminal !== null && $pickupTerminal !== null) {
            return $dropoffTerminal === $pickupTerminal;
        }

        // 같은 구/상세 구역 — 구 단위 토큰이 겹쳐야 강력추천.
        // '인천공항' 같은 공항 단위 토큰만 겹치는 경우는 강력으로 보지 않는다.
        $overlap = array_intersect($this->locationTokens($pickup), $this->locationTokens($dropoff));

        foreach ($overlap as $token) {
            if (! str_ends_with($token, '공항')) {
                return true;
            }
        }

        return false;
    }

    /**
     * 위치 문자열의 공항 터미널 식별자 — '인천공항 T1' → '인천공항T1', '김포공항 국내선' → '김포공항국내선'.
     * 터미널·선이 명시되지 않은 위치는 null을 반환한다.
     */
    private function airportTerminal(string $location): ?string
    {
        if (preg_match('/([\p{Hangul}]*공항)\s*(T\d|국내선|국제선)/u', $location, $matches)) {
            return $matches[1].$matches[2];
        }

        return null;
    }

    /**
     * 서비스 지역(서울/인천/경기) 안 위치인지.
     * - 서울/인천/경기 시·도명이 명시되면 서비스 지역으로 본다.
     * - 그 외 시·도명이 명시되면 서비스 지역 밖으로 본다.
     * - 시·도명이 없는 상세 지명(판교, 잠실, 여의도 등)은 서울/경기 지역으로 간주한다.
     */
    private function inServiceRegion(string $location): bool
    {
        if (blank($location)) {
            return false;
        }

        $normalized = str_replace('특별시', '', $location);

        if (mb_stripos($normalized, '서울') !== false
            || mb_stripos($normalized, '인천') !== false
            || mb_stripos($normalized, '경기') !== false) {
            return true;
        }

        foreach (self::OUT_OF_SERVICE_REGION as $region) {
            if (mb_stripos($normalized, $region) !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * 후보 운행이 maxStart 이내에 시작하는지 — 시간 미정 운행은 상한 적용을 건너뛴다.
     */
    private function startsBeforeMax(Order $order, Carbon $max): bool
    {
        if (blank($order->service_date) || blank($order->service_time)) {
            return true;
        }

        return Carbon::parse($order->service_date.' '.$order->service_time, 'Asia/Seoul') <= $max;
    }

    /**
     * 위치 문자열을 지역 매칭용 토큰으로 분리한다.
     * - 공항 터미널 코드(T1/T2)와 방향 기호는 제거
     * - '강남구' → '강남'처럼 구/동/읍/면/리 접미사를 벗겨 상세 구역 단위로도 매칭
     * - 시/도 단위 토큰('서울', '경기' 등)은 너무 넓어 왕복 매칭에서 제외
     * 예: '서울 마포구' → ['마포', '마포구'], '인천공항 T2' → ['인천공항']
     *
     * @return array<int, string>
     */
    private function locationTokens(string $location): array
    {
        $normalized = preg_replace('/(\bT\d\b|국내선|국제선)/u', '', str_replace('국제', '', $location));
        $parts = preg_split('/[\s>→\-·,()（）\/]+/u', $normalized);

        $tokens = [];

        foreach ($parts as $part) {
            $part = trim($part);

            if (mb_strlen($part) < 2) {
                continue;
            }

            $tokens[] = mb_strtolower($part);

            // '강남구' → '강남' — 상세 구역만으로도 겹치게
            $stripped = preg_replace('/(구|동|읍|면|리)$/u', '', mb_strtolower($part));

            if (mb_strlen($stripped) >= 2) {
                $tokens[] = $stripped;
            }
        }

        $blocklist = [
            '서울', '서울특별시', '부산', '부산광역시', '인천', '인천광역시',
            '대구', '대전', '광주', '울산', '세종',
            '경기', '경기도', '강원', '강원도',
            '충북', '충청북도', '충남', '충청남도',
            '전북', '전라북도', '전남', '전라남도',
            '경북', '경상북도', '경남', '경상남도',
            '제주', '제주도',
        ];

        return array_values(array_unique(array_diff($tokens, $blocklist)));
    }

    /**
     * 등록한 운행 행에 대기 제안(오퍼) 건수를 붙인다 — 카드 배지용.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  Collection<int, Order>  $orderItems
     * @return array<int, array<string, mixed>>
     */
    private function attachPendingOffers(array $rows, Collection $orderItems): array
    {
        $counts = app(OrderOfferService::class)->pendingCountsByOrder($orderItems->pluck('id'));

        foreach ($rows as &$row) {
            if (($row['kind'] ?? '') === 'set') {
                // 셋트 — 그룹 내 운행들의 대기 제안 합산
                $row['pendingOffers'] = $orderItems
                    ->where('group_id', $row['id'] ?? null)
                    ->sum(fn (Order $order) => $counts[$order->id] ?? 0);
            } else {
                $row['pendingOffers'] = $counts[$row['id'] ?? null] ?? 0;
            }
        }
        unset($row);

        return $rows;
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
