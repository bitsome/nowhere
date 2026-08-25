<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\MatchPreference;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderNotification;
use Illuminate\Support\Carbon;

/**
 * 자동 매칭 엔진.
 *
 * 운행이 공개되면(마켓 노출) 각 기사의 활성 매칭 설정을 검사해
 * 조건에 맞는 기사에게 '제안' 알림을 보낸다. 확정은 기사가 수락할 때 이뤄진다.
 * 가용성 조건: 기사 온라인 + 같은 날짜에 진행 중 운행 없음.
 */
class MatchService
{
    /**
     * 공개된 운행을 조건에 맞는 기사에게 매칭 제안(알림)한다.
     *
     * @return int 매칭 제안을 보낸 기사 수
     */
    public function matchForOrder(Order $order): int
    {
        if (! in_array($order->status, [
            Order::STATUS_PUBLISHED,
            Order::STATUS_TRADING,
        ], true)) {
            return 0;
        }

        // 가져오기 요청(승인 대기)이 걸린 운행은 매칭 대상에서 제외
        if ($order->claimant_user_id !== null) {
            return 0;
        }

        $preferences = MatchPreference::query()
            ->where('is_active', true)
            ->with('user')
            ->get();

        $matched = 0;

        foreach ($preferences as $preference) {
            $user = $preference->user;

            if ($user === null || $user->role !== User::ROLE_DRIVER) {
                continue;
            }

            if (! $this->isEligible($user, $preference, $order)) {
                continue;
            }

            $revenueText = $order->expected_revenue > 0 ? " (예상 {$order->expected_revenue}원)" : '';

            $user->notify(new OrderNotification(
                '매칭 운행 도착',
                "{$order->customer_name}님의 {$order->rideSummary()} 운행이 설정 조건에 맞아 매칭되었습니다{$revenueText}. 수락하시겠어요?",
                $order->id,
            ));

            $matched++;
        }

        return $matched;
    }

    /**
     * 기사가 해당 운행의 매칭 후보가 되는지 확인 (설정 일치 + 가용성).
     */
    public function isEligible(User $user, MatchPreference $preference, Order $order): bool
    {
        if ($user->role !== User::ROLE_DRIVER) {
            return false;
        }

        $driver = $user->driver()->first();

        if ($driver === null || $driver->status !== Driver::STATUS_ONLINE) {
            return false;
        }

        if (! $driver->match_enabled) {
            return false;
        }

        if (! $this->isMatch($preference, $order)) {
            return false;
        }

        if ($this->hasTimeOverlap($user->id, $order)) {
            return false;
        }

        return true;
    }

    /**
     * 설정 조건(지역/시간대/요일/최소수익)과 운행이 일치하는지.
     */
    public function isMatch(MatchPreference $preference, Order $order): bool
    {
        return $this->dateMatches($preference, $order)
            && $this->areaMatches($preference, $order)
            && $this->timeMatches($preference, $order)
            && $this->dayMatches($preference, $order)
            && $this->passengerMatches($preference, $order)
            && $this->revenueMatches($preference, $order);
    }

    /**
     * 최대 인원 조건 — 설정한 최대 인원 이하 운행만 매칭 (비우면 전체).
     */
    private function passengerMatches(MatchPreference $preference, Order $order): bool
    {
        $maxPassengers = $preference->max_passengers;

        if (! $maxPassengers || $maxPassengers <= 0) {
            return true;
        }

        $passengerCount = (int) ($order->passenger_count ?: 0);

        return $passengerCount <= $maxPassengers;
    }

    /**
     * 날짜 범위 조건 — 오늘/내일/오늘+내일로 좁힐 수 있다 (비우면 전체).
     */
    private function dateMatches(MatchPreference $preference, Order $order): bool
    {
        $range = $preference->date_range;

        if (! $range || ! $order->service_date) {
            return true;
        }

        $today = now('Asia/Seoul')->format('Y-m-d');
        $tomorrow = now('Asia/Seoul')->addDay()->format('Y-m-d');

        return match ($range) {
            'today' => $order->service_date === $today,
            'tomorrow' => $order->service_date === $tomorrow,
            'today_tomorrow' => in_array($order->service_date, [$today, $tomorrow], true),
            default => true,
        };
    }

    private function areaMatches(MatchPreference $preference, Order $order): bool
    {
        $area = trim((string) $preference->area);

        if ($area === '') {
            return true;
        }

        // 공항 표기 차이 정규화 — '인천국제공항'도 '인천공항' 설정과 매칭되도록
        $normalize = fn (string $s): string => str_replace('국제', '', $s);

        $area = $normalize($area);
        $pickup = $normalize((string) $order->pickup_location);
        $dropoff = $normalize((string) $order->dropoff_location);

        // 픽업 또는 하차가 설정 지역에 있으면 매칭 (공항 콜의 경우 왕복 모두 해당)
        return mb_stripos($pickup, $area) !== false || mb_stripos($dropoff, $area) !== false;
    }

    private function timeMatches(MatchPreference $preference, Order $order): bool
    {
        $start = $preference->start_time;
        $end = $preference->end_time;

        if (! $start || ! $end) {
            return true;
        }

        $time = $order->service_time
            ?: ($order->service_datetime ? Carbon::parse($order->service_datetime)->format('H:i') : '');

        if ($time === '') {
            return true; // 시간 미정 운행은 시간 조건 통과
        }

        if ($start <= $end) {
            return $time >= $start && $time <= $end;
        }

        // 야간 시간대 (예: 22:00 ~ 06:00)
        return $time >= $start || $time <= $end;
    }

    private function dayMatches(MatchPreference $preference, Order $order): bool
    {
        $days = $preference->days;

        if (empty($days) || ! $order->service_date) {
            return true;
        }

        $serviceDate = Carbon::parse($order->service_date);

        // 자정을 넘는 야간 시간대(예: 09:00~03:00)의 새벽 구간(00:00~종료)은 전날 요일로 판정
        $start = $preference->start_time;
        $end = $preference->end_time;
        $time = $order->service_time
            ?: ($order->service_datetime ? Carbon::parse($order->service_datetime)->format('H:i') : '');

        if ($start && $end && $start > $end && $time !== '' && $time <= $end) {
            $serviceDate = $serviceDate->subDay();
        }

        $dayOfWeek = $serviceDate->dayOfWeekIso; // 1=월 .. 7=일

        return in_array($dayOfWeek, $days, true);
    }

    private function revenueMatches(MatchPreference $preference, Order $order): bool
    {
        if ($preference->min_revenue <= 0) {
            return true;
        }

        $revenue = (int) ($order->expected_revenue ?: $order->amount_value ?: 0);

        return $revenue >= $preference->min_revenue;
    }

    /**
     * 같은 날짜에 진행 중(수락/운행중) 운행이 있으면 제외한다.
     */
    private function hasTimeOverlap(int $userId, Order $order): bool
    {
        if (! $order->service_date) {
            return false;
        }

        return Order::query()
            ->where('user_id', $userId)
            ->whereIn('status', [Order::STATUS_ACCEPTED, Order::STATUS_DRIVING])
            ->where('service_date', $order->service_date)
            ->whereKeyNot($order->id)
            ->exists();
    }
}
