<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\MatchPreference;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Orders\LocationTokens;
use Illuminate\Support\Collection;

/**
 * 운행별 적합 기사 자동 매칭 랭킹 엔진.
 *
 * 공개/거래중 운행 하나에 대해, 지금 일할 수 있는 기사(온라인 + 매칭 켬 +
 * 활성 매칭 설정 보유)를 적합도 점수 순으로 정렬해 상위 N명을 돌려준다.
 * 관리자 검수·기사 추천 단계의 입력으로 사용한다. (docs/OPERATIONS.md '자동 매칭 점수')
 *
 * 배점(합계 100, config/matching.php):
 *   차량 25 / 거리 25 / 시간 20 / 동선 15 / 선호 10 / 평점 5
 *
 * 데이터 제약상 '거리'는 실제 좌표가 아니라 '현재 활동 권역' 근접으로 계산한다.
 * - 기사의 최근 운행(수락/운행중/완료/정산) 하차지 또는 자주 다니는 지역(이력 상위)과
 *   이 운행의 픽업지가 같은 구·동 권역이면 '가까운 위치'로 본다.
 * - GPS 위치 데이터가 도입되면 거리 항목을 실거리 기반으로 교체한다.
 */
class DriverMatchRanker
{
    public function __construct(
        private readonly MatchService $matchService,
    ) {}

    /**
     * 운행에 적합한 기사 목록을 점수 순으로 반환한다.
     *
     * @return array<int, array{
     *     user_id: int,
     *     name: string,
     *     rating: float,
     *     match_score: int,
     *     reasons: array<int, string>,
     *     breakdown: array<string, int>
     * }>
     */
    public function rank(Order $order, ?int $limit = null): array
    {
        $limit ??= (int) config('matching.top_n', 5);

        $rows = [];

        foreach ($this->candidateUsers($order) as $user) {
            $rows[] = $this->profileFor($user, $order);
        }

        // 점수 → 평점 → 최근 등록 순 정렬
        usort($rows, function (array $a, array $b): int {
            return $b['match_score'] <=> $a['match_score']
                ?: $b['rating'] <=> $a['rating']
                ?: $a['user_id'] <=> $b['user_id'];
        });

        return array_slice($rows, 0, $limit);
    }

    /**
     * 랭킹 후보 기사 — 온라인 + 매칭 켬 + 활성 매칭 설정 보유.
     * 이미 요청한 사람, 등록자 본인, 그리고 같은 날짜 진행 중 일정과
     * 시간이 실제로 겹치는 기사는 제외한다. (일정이 먼저 끝나 이어서
     * 픽업할 수 있는 기사는 남기고 동선 점수를 준다)
     *
     * @return Collection<int, User>
     */
    private function candidateUsers(Order $order): Collection
    {
        $sameDayRides = Order::query()
            ->whereIn('status', [Order::STATUS_ACCEPTED, Order::STATUS_DRIVING])
            ->where('service_date', $order->service_date)
            ->whereKeyNot($order->id)
            ->get(['id', 'user_id', 'service_time', 'estimated_duration_minutes']);

        $conflictedUserIds = $sameDayRides
            ->filter(fn (Order $ride): bool => $this->overlaps($order, $ride))
            ->pluck('user_id');

        $exclude = collect([
            $order->user_id,
            $order->claimant_user_id,
        ])->merge($conflictedUserIds)->filter()->unique()->all();

        return User::query()
            ->where('role', User::ROLE_DRIVER)
            ->whereNotIn('id', $exclude)
            ->whereHas('driver', function ($query) {
                $query->where('status', Driver::STATUS_ONLINE)
                    ->where('match_enabled', true);
            })
            ->whereHas('matchPreferences', function ($query) {
                $query->where('is_active', true);
            })
            ->get(['id', 'name']);
    }

    /**
     * 두 운행의 서비스 시간이 겹치는지. 시간/소요 미상이면 보수적으로 겹침으로 판단한다.
     */
    private function overlaps(Order $a, Order $b): bool
    {
        $startA = $this->startMinutes($a);
        $startB = $this->startMinutes($b);

        if ($startA === null || $startB === null) {
            return true;
        }

        $endA = $startA + $this->durationMinutes($a);
        $endB = $startB + $this->durationMinutes($b);

        return $startA < $endB && $startB < $endA;
    }

    private function startMinutes(Order $order): ?int
    {
        $time = trim((string) $order->service_time);

        if ($time === '' || ! str_contains($time, ':')) {
            return null;
        }

        [$hour, $minute] = array_map('intval', explode(':', $time));

        return ($hour * 60) + $minute;
    }

    private function durationMinutes(Order $order): int
    {
        return max(30, (int) ($order->estimated_duration_minutes ?: 60));
    }

    /**
     * 기사 하나에 대한 점수·근거 프로필.
     *
     * @return array{
     *     user_id: int,
     *     name: string,
     *     rating: float,
     *     match_score: int,
     *     reasons: array<int, string>,
     *     breakdown: array<string, int>
     * }
     */
    private function profileFor(User $user, Order $order): array
    {
        $weights = config('matching.weights', []);

        $preferences = $user->matchPreferences()
            ->where('is_active', true)
            ->get();

        $activeVehicle = Vehicle::activeVehicleFor($user->id);

        $rating = $this->driverRating($user->id);

        [$recentDropoff, $historyZones, $historyHours] = $this->activitySignals($user);

        $breakdown = [
            'vehicle' => $this->scoreVehicle($weights, $order, $activeVehicle),
            'distance' => $this->scoreDistance($weights, $order, $recentDropoff, $historyZones),
            'time' => $this->scoreTime($weights, $order, $preferences, $historyHours),
            'route' => $this->scoreRoute($weights, $order, $user),
            'preference' => $this->scorePreference($weights, $order, $preferences),
            'rating' => round($weights['rating'] * ($rating / 5)),
        ];

        $reasons = [];

        if ($breakdown['vehicle'] > 0) {
            $reasons[] = '차량 조건 일치';
        }

        if ($breakdown['distance'] > 0) {
            $reasons[] = '현재 위치와 가까움';
        }

        if ($breakdown['time'] > 0) {
            $reasons[] = '운행 시간대 맞음';
        }

        if ($breakdown['route'] > 0) {
            $reasons[] = '현재 일정과 동선이 좋음';
        }

        if ($breakdown['preference'] > 0) {
            $reasons[] = '매칭 설정 조건 충족';
        }

        $reasons[] = "평점 {$rating}";

        return [
            'user_id' => $user->id,
            'name' => $user->name,
            'rating' => round($rating, 1),
            'match_score' => min(100, array_sum($breakdown)),
            'reasons' => $reasons,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * 차량 항목 — 운행 차량 요건과 보유 차량이 일치하면 배점 전액.
     */
    private function scoreVehicle(array $weights, Order $order, ?Vehicle $activeVehicle): int
    {
        if ($activeVehicle === null) {
            return 0;
        }

        $orderVehicle = trim((string) $order->vehicle_type);
        $driverVehicle = trim((string) $activeVehicle->type);

        if ($orderVehicle === '' || $driverVehicle === '') {
            return 0;
        }

        $matched = mb_stripos($orderVehicle, $driverVehicle) !== false
            || mb_stripos($driverVehicle, $orderVehicle) !== false;

        return $matched ? (int) $weights['vehicle'] : 0;
    }

    /**
     * 거리 항목 — 픽업지가 '현재 활동 권역'(최근 하차지/자주 가는 지역)과 겹치면 배점 전액.
     *
     * @param  array<int, string>  $historyZones
     */
    private function scoreDistance(array $weights, Order $order, ?string $recentDropoff, array $historyZones): int
    {
        $zones = $historyZones;

        if (filled($recentDropoff)) {
            $zones = array_merge($zones, LocationTokens::tokens($recentDropoff));
        }

        if ($zones === []) {
            return 0;
        }

        return LocationTokens::touchesAnyZone((string) $order->pickup_location, array_unique($zones))
            ? (int) $weights['distance']
            : 0;
    }

    /**
     * 시간 항목 — 운행 시각이 매칭 설정 창 또는 운행 이력 시간대 안이면 배점 전액.
     *
     * @param  Collection<int, MatchPreference>  $preferences
     * @param  array<int, int>  $historyHours
     */
    private function scoreTime(array $weights, Order $order, Collection $preferences, array $historyHours): int
    {
        $time = trim((string) $order->service_time);

        if ($time === '') {
            return 0; // 시간 미정 운행은 시간 항목 판단 불가
        }

        foreach ($preferences as $preference) {
            if (filled($preference->start_time) && $this->matchService->timeMatches($preference, $order)) {
                return (int) $weights['time'];
            }
        }

        if (in_array((int) substr($time, 0, 2), $historyHours, true)) {
            return (int) $weights['time'];
        }

        return 0;
    }

    /**
     * 동선 항목 — 같은 날짜 진행 중(수락/운행중) 일정과 권역이 이어지면 배점 전액.
     * 앞연결: 진행 중 일정의 하차지 → 이 운행 픽업지 (도착하자마자 픽업)
     * 뒤연결: 이 운행 하차지 → 진행 중 일정의 픽업지 (내려주고 곧바로 다음 출발)
     */
    private function scoreRoute(array $weights, Order $order, User $user): int
    {
        $activeRides = Order::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [Order::STATUS_ACCEPTED, Order::STATUS_DRIVING])
            ->where('service_date', $order->service_date)
            ->whereKeyNot($order->id)
            ->get(['pickup_location', 'dropoff_location']);

        foreach ($activeRides as $ride) {
            if (LocationTokens::sharesZone((string) $ride->dropoff_location, (string) $order->pickup_location)
                || LocationTokens::sharesZone((string) $ride->pickup_location, (string) $order->dropoff_location)) {
                return (int) $weights['route'];
            }
        }

        return 0;
    }

    /**
     * 선호 항목 — 활성 매칭 설정이 실제 조건을 좁히고 있고(isMatch) 충족하면 배점 전액.
     *
     * @param  Collection<int, MatchPreference>  $preferences
     */
    private function scorePreference(array $weights, Order $order, Collection $preferences): int
    {
        foreach ($preferences as $preference) {
            if ($this->hasConstraints($preference) && $this->matchService->isMatch($preference, $order)) {
                return (int) $weights['preference'];
            }
        }

        return 0;
    }

    /**
     * 기사의 활동 신호 — 최근 하차지(위치 추정), 자주 가는 지역·시간대(이력 상위).
     *
     * @return array{0: string|null, 1: array<int, string>, 2: array<int, int>}
     */
    private function activitySignals(User $user): array
    {
        $orders = Order::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [
                Order::STATUS_ACCEPTED,
                Order::STATUS_DRIVING,
                Order::STATUS_COMPLETED,
                Order::STATUS_SETTLED,
            ])
            ->latest('service_date')
            ->limit(50)
            ->get(['service_date', 'service_time', 'pickup_location', 'dropoff_location']);

        $recentDropoff = $orders->first()?->dropoff_location ?? null;

        $areaCounts = [];
        $hourCounts = [];

        foreach ($orders as $order) {
            foreach ([$order->pickup_location, $order->dropoff_location] as $location) {
                foreach (LocationTokens::tokens((string) $location) as $token) {
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
            $recentDropoff,
            array_slice(array_keys($areaCounts), 0, 10),
            array_keys(array_filter($hourCounts, fn (int $count): bool => $count >= 2)),
        ];
    }

    /**
     * 설정이 실제로 조건을 좁히고 있는지 — 빈 설정(조건 없음)은 일치로 치지 않는다.
     */
    private function hasConstraints(MatchPreference $preference): bool
    {
        return filled(trim((string) $preference->area))
            || ! empty($preference->tags)
            || filled($preference->start_time)
            || filled($preference->date_range)
            || ! empty($preference->days)
            || filled(trim((string) $preference->origin))
            || filled(trim((string) $preference->destination))
            || filled($preference->service_type)
            || ($preference->min_revenue ?? 0) > 0
            || ($preference->max_passengers ?? 0) > 0
            || $preference->vehicle_id !== null;
    }

    /**
     * 기사 평균 평점 — 리뷰가 없으면 중립값(신규 기사 불이익 방지).
     */
    private function driverRating(int $userId): float
    {
        $avg = Review::query()
            ->where('reviewee_id', $userId)
            ->avg('rating');

        return $avg !== null ? round((float) $avg, 1) : (float) config('matching.neutral_rating', 3.0);
    }
}
