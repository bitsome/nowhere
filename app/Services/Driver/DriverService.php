<?php

namespace App\Services\Driver;

use App\Models\Driver;
use App\Models\Order;
use App\Models\User;
use App\Services\MatchService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * 기사 운영 — 가용 상태 변경(온라인 시간 누적), 매칭 스위치, 오늘 통계를 담당한다.
 */
class DriverService
{
    private const SELF_STATUSES = [Driver::STATUS_OFFLINE, Driver::STATUS_ONLINE, Driver::STATUS_REST];

    public function __construct(
        private readonly MatchService $matchService,
    ) {}

    public function statuses(): array
    {
        return self::SELF_STATUSES;
    }

    public function driverFor(User $user): Driver
    {
        return $user->driver()->firstOrCreate(['user_id' => $user->id], ['status' => Driver::STATUS_OFFLINE]);
    }

    /**
     * 기사 가용 상태 변경 — 온라인 시간을 일 단위로 누적/초기화한다.
     */
    public function changeStatus(User $user, string $status): Driver
    {
        abort_unless($user->role === User::ROLE_DRIVER, 403, '드라이버만 사용할 수 있습니다.');

        $driver = $this->driverFor($user);
        $now = now();
        $previous = $driver->status;

        // 온라인을 벗어날 때 경과 초를 적립한다
        if ($previous === Driver::STATUS_ONLINE && $status !== Driver::STATUS_ONLINE) {
            $this->accumulateOnline($driver, $now);
        }

        // 온라인 진입 시 일 단위로 초기화
        if ($status === Driver::STATUS_ONLINE) {
            if ($driver->online_date?->toDateString() !== $now->toDateString()) {
                $driver->online_seconds = 0;
                $driver->online_date = $now->toDateString();
            }
        }

        $driver->forceFill([
            'status' => $status,
            'status_updated_at' => $now,
        ])->save();

        // 콜링 조건(온라인 + 매칭 켬)을 갖춘 순간 현재 열려 있는 매칭 운행을 놓치지 않도록 알림
        if ($status === Driver::STATUS_ONLINE && $driver->match_enabled) {
            $this->matchService->matchForDriver($user);
        }

        return $driver;
    }

    /**
     * 자동 매칭(콜링) 시작/중지.
     */
    public function setMatchEnabled(User $user, bool $enabled): Driver
    {
        abort_unless($user->role === User::ROLE_DRIVER, 403, '드라이버만 사용할 수 있습니다.');

        $driver = $this->driverFor($user);
        $driver->forceFill(['match_enabled' => $enabled])->save();

        // 매칭을 켠 순간 이미 열려 있는 매칭 운행을 알림 (온라인 상태일 때만)
        if ($enabled && $driver->status === Driver::STATUS_ONLINE) {
            $this->matchService->matchForDriver($user);
        }

        return $driver;
    }

    /**
     * 오늘 통계 — 온라인 시간, 완료 운행 수, 수입, 진행 중 운행.
     *
     * @return array<string, mixed>
     */
    public function stats(User $user): array
    {
        $driver = $this->driverFor($user);
        $today = now()->startOfDay();

        $todayOrders = Order::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [Order::STATUS_COMPLETED, Order::STATUS_SETTLED])
            ->where('updated_at', '>=', $today)
            ->get(['id', 'amount_value']);

        $active = Order::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [Order::STATUS_ACCEPTED, Order::STATUS_DRIVING])
            ->count();

        return [
            'online_seconds' => $this->onlineSeconds($driver),
            'status' => $driver->status,
            'status_label' => Driver::statusOptions()[$driver->status] ?? $driver->status,
            'today_completed' => $todayOrders->count(),
            'today_income' => (int) $todayOrders->sum('amount_value'),
            'active_count' => $active,
        ];
    }

    /**
     * 정산 내역 — 기간별 내가 완료한 운행 목록과 합계.
     * 정산 대상은 내가 수행한(소유한) 운행이며 service_date 기준으로 집계한다.
     *
     * @return array<string, mixed>
     */
    public function settlements(Request $request): array
    {
        $from = (string) $request->input('from', '');
        $to = (string) $request->input('to', '');

        $query = Order::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('status', [Order::STATUS_COMPLETED, Order::STATUS_SETTLED]);

        if ($from !== '') {
            $query->where('service_date', '>=', $from);
        }

        if ($to !== '') {
            $query->where('service_date', '<=', $to);
        }

        $orders = $query
            ->orderByDesc('service_date')
            ->orderByDesc('service_time')
            ->limit(200)
            ->get();

        $data = $orders->map(fn (Order $order) => [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'service_date' => $order->service_date,
            'service_time' => $order->service_time,
            'pickup_location' => $order->pickup_location,
            'dropoff_location' => $order->dropoff_location,
            'amount' => (int) ($order->expected_revenue ?? $order->amount_value ?? 0),
            'status' => $order->status,
            'status_label' => match ($order->status) {
                // 완료(정산 전) → '정산 대기중', 정산 완료 → '정산 완료' (진행자 화면 기준)
                Order::STATUS_COMPLETED => '정산 대기중',
                Order::STATUS_SETTLED => '정산 완료',
                default => Order::statusOptions()[$order->status] ?? $order->status,
            },
        ]);

        return [
            'data' => $data->values(),
            'summary' => [
                'count' => $data->count(),
                'total_amount' => (int) $data->sum('amount'),
                'from' => $from,
                'to' => $to,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(Driver $driver, User $user): array
    {
        return [
            'user_id' => $user->id,
            'name' => $user->name,
            'status' => $driver->status,
            'status_label' => Driver::statusOptions()[$driver->status] ?? $driver->status,
            'status_updated_at' => $driver->status_updated_at?->toIso8601String(),
            'match_enabled' => (bool) $driver->match_enabled,
            'is_vehicle_verified' => (bool) $user->is_vehicle_verified,
            'is_license_verified' => (bool) $user->is_license_verified,
        ];
    }

    private function accumulateOnline(Driver $driver, Carbon $now): void
    {
        $base = $driver->status_updated_at;

        if ($base === null) {
            return;
        }

        $elapsed = $base->diffInSeconds($now);
        $driver->online_seconds = max(0, ($driver->online_seconds ?? 0) + (int) $elapsed);
        $driver->online_date = $now->toDateString();
    }

    private function onlineSeconds(Driver $driver): int
    {
        $seconds = (int) ($driver->online_seconds ?? 0);

        if ($driver->status === Driver::STATUS_ONLINE && $driver->status_updated_at) {
            $seconds += (int) $driver->status_updated_at->diffInSeconds(now());
        }

        return $seconds;
    }
}
