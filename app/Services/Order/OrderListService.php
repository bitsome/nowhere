<?php

namespace App\Services\Order;

use App\Models\MatchPreference;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\MatchService;
use App\Support\Orders\LocationTokens;
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
            $rows = $this->attachMatchScores($rows, collect($orders->items())->keyBy('id'), $request->user());
        } else {
            // 내 운행(진행자) 관점 — 완료됐지만 아직 정산 전이면 '정산 대기중'으로 표시
            foreach ($rows as &$row) {
                if (($row['status'] ?? null) === Order::STATUS_COMPLETED) {
                    $row['statusLabel'] = '정산 대기중';
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
            $requestCategory = $request->string('request_category', 'received')->toString();
            $this->applyMineScope($query, $user, $source, $tab, $request, $requestCategory);
        } else {
            $this->applyMarketScope($query, $user);
        }

        return $query;
    }

    private function applyMineScope(Builder $query, User $user, string $source, string $tab, Request $request, string $requestCategory = 'received'): void
    {
        // 요청 탭 — 가져오기 요청(승인 대기) 전용. 보낸(내가 요청)/받은(내 운행에 요청) 방향만 나눈다.
        if ($tab === '요청') {
            $query->where('status', Order::STATUS_ACCEPTANCE_PENDING)
                ->with('claimant');

            if ($requestCategory === 'sent') {
                $query->whereHas('pendingClaims', fn ($q) => $q->where('driver_id', $user->id));
            } else {
                $query->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                        ->orWhere('original_owner_id', $user->id);
                });
            }

            return;
        }

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
            // 등록 + 받은 운행 모두 — 내가 등록했거나(original_owner 포함), 가져왔거나(신청자 포함) 한 운행
            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere('original_owner_id', $user->id)
                    ->orWhereHas('pendingClaims', fn ($sub) => $sub->where('driver_id', $user->id));
            });
        } elseif ($source === 'history') {
            // 히스토리 — 내가 수행한 운행 중 완전히 끝난 것만 (완료/정산완료/취소)
            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->whereNotNull('claimed_at')
                    ->where('status', '!=', Order::STATUS_ACCEPTANCE_PENDING)
                    ->orWhereHas('pendingClaims', fn ($sub) => $sub->where('driver_id', $user->id));
            })->whereIn('status', [
                Order::STATUS_COMPLETED,
                Order::STATUS_SETTLED,
                Order::STATUS_CANCELLED,
            ]);
        } else {
            // 받은 운행 = 내가 소유한 가져온 운행 + 내가 요청한 승인 대기
            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->whereNotNull('claimed_at')
                    ->where('status', '!=', Order::STATUS_ACCEPTANCE_PENDING)
                    ->orWhereHas('pendingClaims', fn ($sub) => $sub->where('driver_id', $user->id));
            });
        }

        // 탭별 상태 그룹 — 등록자 기준: 공개(등록·거래) / 진행중(배차~운행) / 정산(완료·정산)
        match ($tab) {
            '전체' => $query->whereNotIn('status', [Order::STATUS_DRAFT]),
            '공개' => $query->whereIn('status', [Order::STATUS_PUBLISHED, Order::STATUS_TRADING]),
            '진행중' => $query->whereIn('status', [
                Order::STATUS_ACCEPTED,
                Order::STATUS_DRIVING,
            ]),
            '예약' => $query->where('status', Order::STATUS_ACCEPTED),
            '운행중' => $query->where('status', Order::STATUS_DRIVING),
            '정산' => $query->whereIn('status', [Order::STATUS_COMPLETED, Order::STATUS_SETTLED]),
            '정산완료' => $query->where('status', Order::STATUS_SETTLED),
            '완료' => $query->where('status', Order::STATUS_COMPLETED),
            '초안' => $query->where('status', Order::STATUS_DRAFT),
            '취소' => $query->where('status', Order::STATUS_CANCELLED),
            default => $query->whereNotIn('status', [
                Order::STATUS_DRAFT,
                Order::STATUS_COMPLETED,
                Order::STATUS_SETTLED,
                Order::STATUS_CANCELLED,
            ]),
        };

        // 진행중 탭 — 상태 세분화 (전체/수락/운행중). 전체(all)면 그룹 상태 그대로 유지한다.
        if ($tab === '진행중') {
            $progressStatus = $request->string('progress_status')->toString();

            if (in_array($progressStatus, [Order::STATUS_ACCEPTED, Order::STATUS_DRIVING], true)) {
                $query->where('status', $progressStatus);
            }
        }

        // 요청자(claimant) 이름을 함께 내려 주고, 승인 대기 운행은 항상 맨 위에 노출
        $query->with('claimant')
            ->orderByRaw("CASE WHEN status = '".Order::STATUS_ACCEPTANCE_PENDING."' THEN 0 ELSE 1 END");
    }

    private function applyMarketScope(Builder $query, User $user): void
    {
        // 승인 대기(가져오기 신청) 운행도 아직 다른 드라이버가 신청할 수 있으므로 마켓에 노출한다.
        // 다만 내가 이미 신청한 운행은 마켓에서 제외한다 (내 마켓 '보낸 요청' 탭으로 이동).
        $query->whereIn('status', [
            Order::STATUS_PUBLISHED,
            Order::STATUS_TRADING,
            Order::STATUS_ACCEPTANCE_PENDING,
        ])
            // 관리자 개입(B-2) — 숨김(마켓 제외)·보류(진행 동결) 운행은 다른 기사에게 노출하지 않는다
            ->where('is_hidden', false)
            ->where('admin_hold', false)
            ->whereDoesntHave('pendingClaims', fn ($sub) => $sub->where('driver_id', $user->id));

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
            // 마켓 검색은 노선(출발/도착)과 주문번호만 매칭한다 — 고객명 등 개인정보는 검색하지 않는다
            $query->where(function ($sub) use ($search) {
                $sub->where('order_number', 'like', "%{$search}%")
                    ->orWhere('pickup_location', 'like', "%{$search}%")
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
     * - 연결 운행은 직전 운행 간격 창(샌딩→랜딩 30분~2시간 / 랜딩→샌딩 낮 2~3시간·야간 1~2시간)에 시작하는 것만 추천
     * - 목적지 서울이면 다음 출발도 서울이어야 하고, 서울 내 구 단위는 달라도 연결한다
     * - 연결1을 탄 뒤 차량이 도착하는 하차지에서 이어지는 다음 연결(연결2~연결4)까지 체인으로 추천한다
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
                'trip' => $trip,
                'dropoff' => (string) $trip->dropoff_location,
                'tokens' => $this->locationTokens($trip->dropoff_location),
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

        // 연결 체인 — 연결1(leg2)부터 연결4(leg5)까지, 직전 운행의 하차지에서 이어지는
        // 운행만 이어붙인다. (출발지 = 차량 위치, 공운행 방지)
        $usedIds = [];
        $levels = [];

        // 연결1 — 출발지가 내 하차지(차량 위치)와 연결되고, 직전 운행과 반대 방향(양방향 왕복)이며,
        // 간격 창에 시작하는 운행만 추천한다.
        $leg2 = $candidates
            ->filter(fn (Order $order) => $tripSignals->contains(
                fn (array $signal) => $this->regionMatches($signal['dropoff'], (string) $order->pickup_location)
                    && $this->isBidirectionalPair($signal['trip'], $order)
                    && $this->hasReasonableDuration($order)
                    && $this->startsWithinGap($signal['trip'], $order),
            ))
            ->take(10);

        if ($leg2->isEmpty()) {
            return [];
        }

        // 연결1을 표시 순서(강력 우선)로 정렬 — 뒤 연결들은 화면에 보이는 순서에 맞춰 붙어야 체인이 이어진다
        $leg2Ordered = $this->sortStrongFirstOrders($leg2, $tripSignals);
        $usedIds += $leg2Ordered->pluck('id')->flip()->all();

        $levels[] = [
            'orders' => $leg2Ordered,
            'signals' => $tripSignals,
            'rows' => $this->returnRouteRows($leg2Ordered, $tripSignals),
            'prevMap' => [],
        ];

        // 연결2~연결4 (leg3~leg5) — 직전 연결의 하차지에서 이어지는 다음 연결을 1건씩 붙인다
        for ($leg = 3; $leg <= 5; $leg++) {
            $prevLevel = $levels[count($levels) - 1];
            $prevOrders = $prevLevel['orders'];

            $next = $candidates
                ->filter(fn (Order $order) => $prevOrders->contains(
                    fn (Order $prevOrder) => $this->regionMatches((string) $prevOrder->dropoff_location, (string) $order->pickup_location)
                        && $this->isBidirectionalPair($prevOrder, $order)
                        && $this->hasReasonableDuration($order)
                        && $this->startsWithinGap($prevOrder, $order),
                ))
                ->reject(fn (Order $order) => isset($usedIds[$order->id]))
                ->values();

            if ($next->isEmpty()) {
                break;
            }

            // 표시 순서의 직전 연결마다 가장 빠른 이어지는 연결을 1건씩 배정한다.
            // (연결이 여럿이어도 각자 자기 뒤의 연결을 가져 연결 체인이 끊기지 않는다)
            $prevMap = [];
            $nextUsed = [];

            foreach ($prevOrders as $prevOrder) {
                foreach ($next as $order) {
                    if (isset($nextUsed[$order->id])) {
                        continue;
                    }

                    if ($this->regionMatches((string) $prevOrder->dropoff_location, (string) $order->pickup_location)
                        && $this->isBidirectionalPair($prevOrder, $order)
                        && $this->startsWithinGap($prevOrder, $order)) {
                        $prevMap[$order->id] = $prevOrder->id;
                        $nextUsed[$order->id] = true;

                        break;
                    }
                }
            }

            // 이어진 직전 연결이 없는 운행은 제외 (고립 연결 방지)
            $next = $next->filter(fn (Order $order) => isset($prevMap[$order->id]))->values();

            if ($next->isEmpty()) {
                break;
            }

            $usedIds += $next->pluck('id')->flip()->all();

            $nextSignals = $prevOrders->map(fn (Order $order) => [
                'dropoff' => (string) $order->dropoff_location,
            ]);

            $nextRows = $this->returnRouteRows($next, $nextSignals);

            foreach ($nextRows as &$row) {
                $row['chain_prev_id'] = $prevMap[$row['id']] ?? null;
            }
            unset($row);

            $levels[] = [
                'orders' => $next,
                'signals' => $nextSignals,
                'rows' => $nextRows,
                'prevMap' => $prevMap,
            ];
        }

        // 최종 순서 — 연결1(강력 우선) 뒤에 그에 이어지는 연결2→연결3→연결4를 붙여 체인으로 만든다
        $ordered = [];

        foreach ($levels[0]['rows'] as $row) {
            $row['chain_leg'] = 2;
            $ordered[] = $row;
            $prevId = $row['id'];

            for ($l = 1; $l < count($levels); $l++) {
                $nextRow = null;

                foreach ($levels[$l]['rows'] as $candidate) {
                    if (($candidate['chain_prev_id'] ?? null) === $prevId) {
                        $nextRow = $candidate;

                        break;
                    }
                }

                if ($nextRow === null) {
                    break;
                }

                $nextRow['chain_leg'] = $l + 2;
                $ordered[] = $nextRow;
                $prevId = $nextRow['id'];
            }
        }

        // 추천 근거 + 조건 일치율 — 복귀(연결) 운행 카드에도 '왜 이 운행인가'를 보여준다
        return $this->attachMatchScores($ordered, $candidates->keyBy('id'), $user);
    }

    /**
     * 마켓 왕복 체인 — 맡은 운행이 없어도, 마켓에서 샌딩(도심→공항)을 시작점으로
     * 랜딩(공항→도심)→샌딩→랜딩…으로 이어지는 왕복 체인을 만들어 강력추천으로 보여준다.
     * - 샌딩 → 랜딩: 같은 공항, 샌딩 시작 후 30분~2시간
     * - 랜딩 → 샌딩: 같은 구, 랜딩 시작 후 3시간~6시간 (랜딩 + 3시간 이후)
     * 각 다리에 recommend_level=strong을 붙이고, 활성 매칭 설정 시간대에 맞는 체인은
     * recommend_order=1(추천1), 다른 시간대 체인은 recommend_order=2(추천2)로 나눈다.
     *
     * @return array<int, array<string, mixed>>
     */
    private function marketReturnPairs(Request $request): array
    {
        $user = $request->user();
        $now = now('Asia/Seoul');

        $candidates = $this->marketCandidatesQuery($user, null, $now)
            ->orderBy('service_date')
            ->orderBy('service_time')
            ->limit(2000)
            ->get()
            ->filter(fn (Order $order) => $this->inServiceRegion((string) $order->pickup_location)
                && $this->inServiceRegion((string) $order->dropoff_location))
            ->values();

        if ($candidates->isEmpty()) {
            return [];
        }

        $sendings = $candidates->filter(fn (Order $order) => $this->isSendingTrip($order))->values();

        if ($sendings->isEmpty()) {
            return [];
        }

        // 활성 매칭 설정 — 설정 시간대에 맞는 체인을 '추천1', 다른 시간대 체인은 '추천2'로 나눈다
        $preferences = $user->matchPreferences()->where('is_active', true)->get();

        $matchesPreference = function (Order $order) use ($preferences): bool {
            foreach ($preferences as $preference) {
                if ($this->matchService->isMatch($preference, $order)) {
                    return true;
                }
            }

            return false;
        };

        // 추천1(시간대 일치) 후보를 먼저, 그다음 추천2 후보를 만든다
        if ($preferences->isNotEmpty()) {
            $anchors = $sendings
                ->filter(fn (Order $order) => $matchesPreference($order))
                ->concat($sendings->filter(fn (Order $order) => ! $matchesPreference($order)))
                ->values();
        } else {
            $anchors = $sendings;
        }

        $chains = [];
        $usedOrderIds = [];

        foreach ($anchors as $anchor) {
            // 홈 노출 최대 3개 그룹 — 추천1(시간대 일치)부터 채운다
            if (count($chains) >= 3) {
                break;
            }

            if (isset($usedOrderIds[$anchor->id])) {
                continue;
            }

            $chain = $this->buildMarketChain($candidates, $anchor, $usedOrderIds);

            // 왕복(최소 2다리: 샌딩+랜딩)이 되어야만 추천한다 — 복귀 없이 나가는 샌딩 단독은 추천하지 않는다
            if (count($chain) < 2) {
                continue;
            }

            $chains[] = [
                'orders' => $chain,
                // 매칭 설정이 없거나 시간대가 일치하면 추천1, 설정이 있는데 다른 시간대면 추천2
                'rank' => ($preferences->isNotEmpty() && ! $matchesPreference($chain[0])) ? 2 : 1,
            ];
        }

        if ($chains === []) {
            return [];
        }

        $chainOrders = collect();
        $orderItems = [];

        foreach ($chains as $chain) {
            foreach ($chain['orders'] as $order) {
                $chainOrders->push($order);
                $orderItems[] = $order;
            }
        }

        $rowsById = collect(app(OrderWorkspaceListBuilder::class)->build($chainOrders, null, 'date'))->keyBy('id');

        $rows = [];

        foreach ($chains as $chain) {
            $prevId = null;

            foreach ($chain['orders'] as $index => $order) {
                $row = $rowsById[$order->id] ?? null;

                if ($row === null) {
                    continue;
                }

                $row['recommend_reason'] = '연결 운행';
                $row['recommend_level'] = 'strong';
                $row['recommend_order'] = $chain['rank'];
                $row['chain_leg'] = 2 + $index;

                if ($prevId !== null) {
                    $row['chain_prev_id'] = $prevId;
                }

                $rows[] = $row;
                $prevId = $order->id;
            }
        }

        return $this->withOwnerTrust($rows, $orderItems);
    }

    /**
     * 마켓 왕복 체인 생성 — 앵커 샌딩(도심→공항)부터 같은 공항 랜딩, 같은 구 샌딩…으로
     * 최대 5다리까지 이어붙인다. (출발지 = 차량 위치, 공운행 방지)
     *
     * @param  Collection<int, Order>  $candidates
     * @param  array<string, bool>  $usedOrderIds
     * @return array<int, Order>
     */
    private function buildMarketChain(Collection $candidates, Order $anchor, array &$usedOrderIds): array
    {
        $orders = [$anchor];
        $usedOrderIds[$anchor->id] = true;
        $prev = $anchor;

        for ($leg = 2; $leg <= 5; $leg++) {
            $next = $candidates->first(function (Order $order) use ($prev, &$usedOrderIds) {
                if (isset($usedOrderIds[$order->id])) {
                    return false;
                }

                return $this->regionMatches((string) $prev->dropoff_location, (string) $order->pickup_location)
                    && $this->isBidirectionalPair($prev, $order)
                    && $this->hasReasonableDuration($order)
                    && $this->startsWithinGap($prev, $order);
            });

            if ($next === null) {
                break;
            }

            $usedOrderIds[$next->id] = true;
            $orders[] = $next;
            $prev = $next;
        }

        return $orders;
    }

    /**
     * 연결 운행 행을 만든다 — 강력추천(같은 구·같은 공항 터미널) 우선 정렬과 등록자 신뢰 정보를 함께 붙인다.
     *
     * @param  Collection<int, Order>  $orders
     * @param  Collection<int, array{dropoff: string, tokens?: array<int, string>}>  $signals
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

        // 맡은 운행이 없으면 마켓에서 샌딩↔랜딩 왕복 짝을 찾아 강력추천으로 보여준다.
        // (일정이 없어도 강력추천은 항상 양방향 왕복만 노출하도록 보장)
        if ($returnRows === []) {
            $returnRows = $this->marketReturnPairs($request);
        }

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

        // 셋트 운행 — 한 다리라도 매칭되면 셋트 전체(나머지 일정)를 추천에 포함시켜 묶음 카드로 보여준다
        $matchedSetGroupIds = collect($orderItems)->pluck('group_id')->filter()->unique()->all();

        if ($matchedSetGroupIds !== []) {
            $extraSetOrders = Order::query()
                ->whereIn('group_id', $matchedSetGroupIds)
                ->whereIn('status', [Order::STATUS_PUBLISHED, Order::STATUS_TRADING])
                ->whereNotIn('id', collect($orderItems)->pluck('id'))
                ->get()
                ->all();

            $orderItems = array_merge($orderItems, $extraSetOrders);
        }

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

        // 추천 근거 + 조건 일치율 점수 부여 — 홈 카드의 '조건 N%'와 ✓ 체크리스트용
        $orderMap = Order::query()->whereIn('id', collect($merged)->pluck('id'))->get()->keyBy('id');

        $merged = $this->attachMatchScores($merged, $orderMap, $user);

        // 선호도(조건 일치율)가 기본 기준 미만인 운행은 추천에서 제외한다 — 낮은 후보로 판단을 늘리지 않는다.
        $minScore = (int) config('recommendation.min_match_score', 60);

        $rows = collect($merged)
            ->filter(function (array $row) use ($minScore): bool {
                if (($row['kind'] ?? '') === 'set') {
                    return true; // 셋트 그룹은 개별 점수 없이 유지
                }

                return ($row['match_score'] ?? 0) >= $minScore;
            })
            ->values()
            ->all();

        // 홈 랭킹 — 연결 운행(체인)은 다리 순서를 유지하고,
        // 개별 추천은 조건 일치율(match_score)이 높은 운행부터 보여준다.
        // (같은 점수 안에서는 기존 순서 유지 — 안정 정렬)
        $chainRows = [];
        $singleRows = [];
        $setRows = [];

        foreach ($rows as $row) {
            if (($row['kind'] ?? '') === 'set') {
                $setRows[] = $row;
            } elseif (($row['recommend_reason'] ?? '') === '연결 운행') {
                $chainRows[] = $row;
            } else {
                $singleRows[] = $row;
            }
        }

        usort($singleRows, fn (array $a, array $b): int => ($b['match_score'] ?? 0) <=> ($a['match_score'] ?? 0));

        return array_merge($chainRows, $singleRows, $setRows);
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
            // 관리자 개입(B-2) — 숨김·보류 운행은 추천 후보에서 제외
            ->where('is_hidden', false)
            ->where('admin_hold', false)
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
     * 랜딩(공항 픽업) 운행 여부 — 출발지가 공항이면 랜딩이다.
     * (service_type은 데이터에 따라 부정확할 수 있어 방향(출발지)만으로 판단한다)
     */
    private function isLandingTrip(Order $order): bool
    {
        return mb_stripos((string) $order->pickup_location, '공항') !== false;
    }

    /**
     * 샌딩(도심→공항) 운행 여부 — 도착지가 공항이면 샌딩이다.
     */
    private function isSendingTrip(Order $order): bool
    {
        return mb_stripos((string) $order->dropoff_location, '공항') !== false;
    }

    /**
     * 양방향(왕복) 연결 여부 — 강력추천은 반드시 공항 왕복이어야 한다.
     * 샌딩(도심→공항) 뒤에는 랜딩(공항→도심), 랜딩 뒤에는 샌딩만 연결하고,
     * 도심↔도심 픽업처럼 방향이 이어지지 않는 운행은 강력추천에서 제외한다.
     */
    private function isBidirectionalPair(Order $prev, Order $next): bool
    {
        return ($this->isSendingTrip($prev) && $this->isLandingTrip($next))
            || ($this->isLandingTrip($prev) && $this->isSendingTrip($next));
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
     * 후보 운행이 직전 운행의 간격 창에 시작하는지 판정한다.
     * - 후보가 랜딩(공항 픽업)이면 '샌딩→랜딩' 규칙: 직전 운행 시작 후 30분~2시간(설정).
     *   승객 퇴장 대기(landing_wait_minutes)가 샌딩 소요를 흡수하므로 타이트하게 연결할 수 있다.
     * - 그 외(랜딩→샌딩)는 '랜딩→샌딩' 규칙: 직전 랜딩 시작 후 3시간~6시간 창.
     */
    private function startsWithinGap(Order $prevTrip, Order $order): bool
    {
        $start = $this->orderStart($order);

        if ($start === null) {
            return true;
        }

        $prevStart = $this->orderStart($prevTrip);

        if ($prevStart === null) {
            return true;
        }

        if ($this->isLandingTrip($order)) {
            [$minMinutes, $maxMinutes] = $this->sendLandingGapMinutes();

            return $start->gte($prevStart->copy()->addMinutes($minMinutes))
                && $start->lte($prevStart->copy()->addMinutes($maxMinutes));
        }

        // 랜딩(공항→도심) 후 다음 샌딩(도심→공항) — 랜딩 시작(service_time) 기준 3시간 이후에만 연결한다.
        // (사용자 규칙: '14:30 랜딩 → 다음 운행은 14:30 + 3시간 이후')
        [$minHours, $maxHours] = $this->landingSendGapHours();

        return $start->gte($prevStart->copy()->addHours($minHours))
            && $start->lte($prevStart->copy()->addHours($maxHours));
    }

    /**
     * 후보 운행(샌딩)이 랜딩 후 간격 창에 시작하는지.
     * 일시 불완전 운행은 시간 검증을 건너뛴다.
     */
    private function orderStart(Order $order): ?Carbon
    {
        if (blank($order->service_date) || blank($order->service_time)) {
            return null;
        }

        return Carbon::parse($order->service_date.' '.$order->service_time, 'Asia/Seoul');
    }

    /**
     * 랜딩 후 다음 샌딩 간격(시간) — 랜딩 시작(service_time) 기준 3시간 이후에 연결한다.
     * (운행 소요 + 이동 + 휴식 여유를 합친 값. 사용자 규칙: '랜딩 + 3시간 이후')
     *
     * @return array{int, int} [최소시간, 최대시간]
     */
    private function landingSendGapHours(): array
    {
        $gap = config('recommendation.landing_send_gap_hours', ['min' => 3, 'max' => 6]);

        return [(int) ($gap['min'] ?? 3), (int) ($gap['max'] ?? 6)];
    }

    /**
     * 샌딩→랜딩 간격(분) — 랜딩 시작(항공기 도착)이 직전 샌딩 시작 후 몇 분 이내인지.
     *
     * @return array{int, int} [최소분, 최대분]
     */
    private function sendLandingGapMinutes(): array
    {
        $gap = config('recommendation.send_landing_gap_minutes', ['min' => 30, 'max' => 120]);

        return [(int) ($gap['min'] ?? 30), (int) ($gap['max'] ?? 120)];
    }

    /**
     * 연속 운행 지역 매칭 — '가까운 위치' 기준으로만 연결한다.
     * - 같은 구/동/읍/면(상세 구역 토큰 일치) → 강력추천
     * - 같은 공항(터미널·선 무관, 인천공항 T1↔T2·김포 국내선↔국제선 포함) → 강력추천
     * 같은 시/도(서울/인천/경기)만으로는 연결하지 않는다. (경기 도내 먼 시/군 연결 방지)
     * 예) 하차 '서울 중구' → 출발 '서울 중구' (연결), 하차 '인천공항 T1' → 출발 '인천공항 T2' (연결)
     */
    private function regionMatches(string $dropoff, string $pickup): bool
    {
        return $this->strongRegionMatch($dropoff, $pickup);
    }

    /**
     * 같은 공항(터미널 무관) 여부 — '인천공항' ↔ '인천공항 T1', '김포공항 국내선' ↔ '김포공항' 등.
     */
    private function sameAirport(string $a, string $b): bool
    {
        foreach (['인천공항', '김포공항'] as $airport) {
            if (mb_stripos($a, $airport) !== false && mb_stripos($b, $airport) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * 강력추천 여부 — 하차지와 출발지가 '같은 구/상세 구역'이거나 '같은 공항'이면 강력추천.
     * - 같은 구: '서울 마포구' ↔ '서울 마포구' (구·동·읍·면 단위 토큰 일치)
     * - 같은 공항: 인천공항 T1↔T1·T1↔T2, 김포 국내선↔국내선·국내선↔국제선 (터미널·선 무관)
     */
    private function strongRegionMatch(string $dropoff, string $pickup): bool
    {
        // 같은 공항(터미널·선 무관)이면 강력추천
        if ($this->sameAirport($dropoff, $pickup)) {
            return true;
        }

        // 같은 구/상세 구역 — 구 단위 토큰이 겹치면 강력추천.
        // ('인천공항' 같은 공항 단위 토큰만 겹치는 경우는 위에서 이미 처리된다.)
        $overlap = array_intersect($this->locationTokens($pickup), $this->locationTokens($dropoff));

        foreach ($overlap as $token) {
            if (! str_ends_with($token, '공항')) {
                return true;
            }
        }

        return false;
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
        return LocationTokens::tokens($location);
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

    /**
     * 추천 근거 + 조건 일치율 점수 — 홈/마켓 카드의 '조건 N%'와 ✓ 체크리스트.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  Collection<int, Order>  $orderMap  keyBy('id')된 운행 모델
     * @return array<int, array<string, mixed>>
     */
    private function attachMatchScores(array $rows, Collection $orderMap, User $user): array
    {
        $preferences = $user->matchPreferences()->where('is_active', true)->get();
        $signals = $this->driverHistorySignals($user);
        $activeVehicle = Vehicle::activeVehicleFor($user->id);

        foreach ($rows as &$row) {
            if (($row['kind'] ?? '') === 'set') {
                continue; // 셋트 그룹은 개별 운행 점수 생략
            }

            $order = $orderMap[$row['id']] ?? null;

            if ($order === null) {
                continue;
            }

            $isChain = ($row['recommend_reason'] ?? null) === '연결 운행';
            $profile = $this->matchProfile($user, $order, $preferences, $signals, $activeVehicle, $isChain);

            $row['match_score'] = $profile['score'];
            $row['match_reasons'] = $profile['reasons'];
        }
        unset($row);

        return $rows;
    }

    /**
     * 운행 하나에 대한 조건 일치율 점수와 추천 근거 체크리스트를 계산한다.
     *
     * 점수 구성(합계 100):
     * - 매칭 설정 완전 일치(또는 앞·뒤 연결) 25점
     * - 차량 조건 일치 20점
     * - 태그/지역 조건(선호 태그/선호 지역/자주 다니는 노선) 20점
     * - 시간대 조건(선호 시간대/자주 운행한 시간대) 15점
     * - 금액 조건(최소 금액 이상) 10점
     * - 동선/연결(다음 운행과 이어짐) 10점
     *
     * @param  Collection<int, MatchPreference>  $preferences
     * @param  array{areas: array<int, string>, hours: array<int, int>}  $signals
     * @return array{score: int, reasons: array<int, string>}
     */
    private function matchProfile(
        User $user,
        Order $order,
        Collection $preferences,
        array $signals,
        ?Vehicle $activeVehicle,
        bool $isChain = false,
    ): array {
        $score = 0;
        $reasons = [];

        // 1) 매칭 설정 완전 일치(또는 앞·뒤 연결) — 조건이 실제로 설정된 경우만
        foreach ($preferences as $preference) {
            if ($this->hasConstraints($preference)
                && ($this->matchService->isMatch($preference, $order)
                    || $this->matchService->isFrontLink($preference, $order)
                    || $this->matchService->isBackLink($preference, $order))) {
                $score += 25;
                $reasons[] = '매칭 설정에 맞는 운행';
                break;
            }
        }

        // 2) 차량 조건 — 내 차량과 운행 차량이 일치
        if ($activeVehicle !== null && $this->vehicleMatches($activeVehicle, $order)) {
            $score += 20;
            $reasons[] = '차량 조건 일치';
        }

        // 3) 태그/지역 조건 — 선호 태그(설정) 우선, 선호 지역(구 설정), 자주 다니는 노선(이력) 차선
        $areaMatched = false;

        foreach ($preferences as $preference) {
            if (! empty($preference->tags) && $this->matchService->tagsMatch($preference, $order)) {
                $score += 20;
                $reasons[] = '선호 태그 운행';
                $areaMatched = true;
                break;
            }
        }

        if (! $areaMatched) {
            foreach ($preferences as $preference) {
                if (filled(trim((string) $preference->area)) && $this->matchService->areaMatches($preference, $order)) {
                    $score += 20;
                    $reasons[] = '선호 지역 운행';
                    $areaMatched = true;
                    break;
                }
            }
        }

        if (! $areaMatched && $this->matchesAnyArea((string) $order->pickup_location, $signals['areas'])) {
            $score += 20;
            $reasons[] = '자주 다니는 노선';
        }

        // 4) 시간대 조건 — 선호 시간대(설정) 우선, 자주 운행한 시간대(이력) 차선
        $timeMatched = false;

        foreach ($preferences as $preference) {
            if (filled($preference->start_time) && $this->matchService->timeMatches($preference, $order)) {
                $score += 15;
                $reasons[] = '선호 시간대 운행';
                $timeMatched = true;
                break;
            }
        }

        if (! $timeMatched && $order->service_time) {
            $hour = (int) substr($order->service_time, 0, 2);

            if (in_array($hour, $signals['hours'], true)) {
                $score += 15;
                $reasons[] = '자주 운행한 시간대';
            }
        }

        // 5) 금액 조건 — 최소 금액 이상 (설정된 경우만)
        foreach ($preferences as $preference) {
            if ($preference->min_revenue > 0 && $this->matchService->revenueMatches($preference, $order)) {
                $score += 10;
                $reasons[] = '최소 금액 이상';
                break;
            }
        }

        // 6) 동선/연결 — 연결 운행 체인 (다음 운행과 이어짐)
        if ($isChain) {
            $score += 10;
            $reasons[] = '현재 운행과 동선이 좋음';
        }

        // 공항 운행 — 점수 없이 근거만
        if (mb_stripos((string) $order->pickup_location, '공항') !== false
            || mb_stripos((string) $order->dropoff_location, '공항') !== false) {
            $reasons[] = '공항 운행';
        }

        return ['score' => min(100, $score), 'reasons' => $reasons];
    }

    /**
     * 설정이 실제로 조건을 좁히고 있는지 — 비어 있는 설정(조건 없음)은 일치로 치지 않는다.
     */
    private function hasConstraints(MatchPreference $preference): bool
    {
        return filled(trim((string) $preference->area))
            || ! empty($preference->tags)
            || filled($preference->start_time)
            || $preference->min_revenue > 0
            || filled($preference->date_range)
            || ! empty($preference->days)
            || ($preference->max_passengers ?? 0) > 0;
    }

    /**
     * 내 차량과 운행 차량이 일치하는지 — 종류명 부분 일치(예: '스타리아' vs '스타리아 9인승').
     */
    private function vehicleMatches(Vehicle $vehicle, Order $order): bool
    {
        $orderVehicle = trim((string) $order->vehicle_type);
        $driverVehicle = trim((string) $vehicle->type);

        if ($orderVehicle === '' || $driverVehicle === '') {
            return false;
        }

        return mb_stripos($orderVehicle, $driverVehicle) !== false
            || mb_stripos($driverVehicle, $orderVehicle) !== false;
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
