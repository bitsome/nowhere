<?php

namespace App\Services\Order;

use App\Models\MatchPreference;
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
     * 왕복 노선 추천 — 내가 맡은 운행(수락/운행중)의 하차지 근처에서 시작하는 마켓 운행을 찾는다.
     * (CJ 더운반/uber Freight의 리턴 로드 개념 — 하차 후 공차 이동을 줄이기 위한 우선 노출)
     *
     * 정합성 보완:
     * - 서비스 시각이 이미 충분히 지난 운행은 추천 근거에서 제외 (최근·예정 운행만 사용)
     * - 복귀 운행은 하차 예정 시각(소요시간+버퍼) 이후에 시작하는 것만 추천
     * - 현재 마켓 필터(시간대·날짜·노선·차량 등)를 함께 반영해 목록과 일관성 유지
     *
     * @return array<int, array<string, mixed>>
     */
    public function returnRoutes(Request $request): array
    {
        $user = $request->user();

        // 내가 맡은 운행의 하차지 + 하차 이후 가능 시각 — 복귀 노선의 시작점 후보
        $tripSignals = Order::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [Order::STATUS_ACCEPTED, Order::STATUS_DRIVING])
            ->whereNotNull('dropoff_location')
            ->where('dropoff_location', '!=', '')
            ->get()
            ->filter(fn (Order $trip) => $this->isRelevantTrip($trip))
            ->map(fn (Order $trip) => [
                'tokens' => $this->locationTokens($trip->dropoff_location),
                'availableAfter' => $this->availableAfter($trip),
            ])
            ->filter(fn (array $signal) => $signal['tokens'] !== [])
            ->values();

        if ($tripSignals->isEmpty()) {
            return [];
        }

        // 마켓 후보 — 공개/거래중, 가져오기 요청 없음, 남의 운행, 서비스 시각이 지나지 않음
        $candidatesQuery = $this->marketCandidatesQuery($user);

        // 현재 마켓 필터를 함께 적용 — 검색 중인 노선·날짜·시간대·차량에 맞는 복귀 운행만 추천
        $this->applyFilters($request, $candidatesQuery);

        $candidates = $candidatesQuery
            ->orderBy('service_date')
            ->orderBy('service_time')
            ->limit(100)
            ->get();

        // 출발지가 내 하차지와 지역(토큰)이 겹치고, 하차 이후 가능한 시각에 시작하는 운행만 추천
        $matched = $candidates
            ->filter(fn (Order $order) => $tripSignals->contains(
                fn (array $signal) => array_intersect($this->locationTokens($order->pickup_location), $signal['tokens']) !== []
                    && $this->isTimeFeasible($signal['availableAfter'], $order),
            ))
            ->take(10);

        if ($matched->isEmpty()) {
            return [];
        }

        $rows = app(OrderWorkspaceListBuilder::class)->build($matched, null, 'date');

        return $this->withOwnerTrust($rows, $matched->all());
    }

    /**
     * 홈 '추천일정' — 현재 운행(예약·일정)이 없어도 마켓 운행을 추천한다.
     *
     * 우선순위:
     * 1) 현재 맡은 운행(수락/운행중)이 있으면 왕복 노선 추천을 그대로 사용한다.
     * 2) 일정이 없으면 '매칭 설정 + 운행 이력' 복합으로 추천한다.
     *    - 활성 매칭 설정의 지역·시간·날짜·최소수익 조건에 맞는 운행 (이유: '매칭 설정')
     *    - 최근 운행 이력에서 자주 다니는 출발/하차 지역에서 시작하는 운행 (이유: '자주 다니는 노선')
     *
     * @return array<int, array<string, mixed>>
     */
    public function recommendations(Request $request): array
    {
        // 1) 왕복 노선 — 현재 맡은 운행 기준 (일정이 있는 경우 우선)
        $returnRoutes = $this->returnRoutes($request);

        if ($returnRoutes !== []) {
            foreach ($returnRoutes as &$row) {
                $row['recommend_reason'] = '왕복 노선';
            }
            unset($row);

            return $returnRoutes;
        }

        // 2) 일정이 없으면 매칭 설정 + 운행 이력 복합 추천
        $user = $request->user();
        $candidates = $this->marketCandidatesQuery($user)
            ->orderBy('service_date')
            ->orderBy('service_time')
            ->limit(100)
            ->get();

        if ($candidates->isEmpty()) {
            return [];
        }

        $matched = [];
        $reasons = [];

        // 2-1) 활성 매칭 설정 조건 일치 — 설정 우선
        $preferences = $user->matchPreferences()->where('is_active', true)->get();

        foreach ($candidates as $order) {
            if ($preferences->contains(fn (MatchPreference $preference) => $this->matchService->isMatch($preference, $order))) {
                $matched[$order->id] = $order;
                $reasons[$order->id] = '매칭 설정';
            }
        }

        // 2-2) 운행 이력의 자주 다니는 지역 기준 — 설정 매칭이 없는 운행만 채운다
        $frequentAreas = $this->frequentDriverAreas($user);

        if ($frequentAreas !== []) {
            foreach ($candidates as $order) {
                if (isset($matched[$order->id])) {
                    continue;
                }

                if ($this->matchesAnyArea((string) $order->pickup_location, $frequentAreas)) {
                    $matched[$order->id] = $order;
                    $reasons[$order->id] = '자주 다니는 노선';
                }
            }
        }

        if ($matched === []) {
            return [];
        }

        $orderItems = array_values($matched);
        $rows = app(OrderWorkspaceListBuilder::class)->build(collect($orderItems), null, 'date');

        foreach ($rows as &$row) {
            $row['recommend_reason'] = $reasons[$row['id']] ?? null;
        }
        unset($row);

        return $this->withOwnerTrust($rows, $orderItems);
    }

    /**
     * 마켓 추천 후보 쿼리 — 공개/거래중, 가져오기 요청 없음, 남의 운행, 서비스 시각이 지나지 않은 운행.
     */
    private function marketCandidatesQuery(User $user): Builder
    {
        $cutoff = now('Asia/Seoul')->subHours(2);
        [$cutoffDate, $cutoffTime] = explode(' ', $cutoff->format('Y-m-d H:i'));

        return Order::query()
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
    }

    /**
     * 최근 운행 이력에서 자주 다니는 지역 토큰 (출발·하차 합산, 상위 10개).
     *
     * @return array<int, string>
     */
    private function frequentDriverAreas(User $user): array
    {
        $counts = [];

        $trips = Order::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [
                Order::STATUS_ACCEPTED,
                Order::STATUS_DRIVING,
                Order::STATUS_COMPLETED,
                Order::STATUS_SETTLED,
            ])
            ->latest('service_date')
            ->limit(50)
            ->get();

        foreach ($trips as $trip) {
            foreach ([$trip->pickup_location, $trip->dropoff_location] as $location) {
                foreach ($this->locationTokens((string) $location) as $token) {
                    $counts[$token] = ($counts[$token] ?? 0) + 1;
                }
            }
        }

        arsort($counts);

        return array_slice(array_keys($counts), 0, 10);
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
     * 해당 운행을 마친 뒤 복귀 운행을 시작할 수 있는 시각 (소요시간 + 30분 버퍼).
     * 시간 미정 운행은 시간 제약 없음(null)으로 처리한다.
     */
    private function availableAfter(Order $trip): ?Carbon
    {
        if (blank($trip->service_time)) {
            return null;
        }

        return Carbon::parse($trip->service_date.' '.$trip->service_time, 'Asia/Seoul')
            ->addMinutes(($trip->estimated_duration_minutes ?? 60) + 30);
    }

    /**
     * 후보 운행이 내 하차 이후 시각에 시작하는지 — 일시 불완전 운행은 시간 검증을 건너뛴다.
     */
    private function isTimeFeasible(?Carbon $availableAfter, Order $order): bool
    {
        if ($availableAfter === null) {
            return true;
        }

        if (blank($order->service_date) || blank($order->service_time)) {
            return true;
        }

        return Carbon::parse($order->service_date.' '.$order->service_time, 'Asia/Seoul') >= $availableAfter;
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
        $normalized = preg_replace('/\bT\d\b/i', '', str_replace('국제', '', $location));
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
