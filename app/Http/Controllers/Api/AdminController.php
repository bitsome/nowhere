<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AutoOrderSetting;
use App\Models\Driver;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    /**
     * 운영 관리용 사용자 목록 (관리자/슈퍼 관리자 전용).
     *
     * @return JsonResponse{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function users(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request->user());

        $users = User::query()
            ->orderByDesc('created_at')
            ->paginate(20);

        // 완료/정산 운행 수 집계 (User에 orders 관계가 없어 직접 계산)
        $completedCounts = Order::query()
            ->whereIn('user_id', collect($users->items())->pluck('id'))
            ->whereIn('status', [Order::STATUS_COMPLETED, Order::STATUS_SETTLED])
            ->selectRaw('user_id, COUNT(*) as cnt')
            ->groupBy('user_id')
            ->pluck('cnt', 'user_id');

        return response()->json([
            'data' => $users->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_vehicle_verified' => (bool) $user->is_vehicle_verified,
                'is_license_verified' => (bool) $user->is_license_verified,
                'created_at' => $user->created_at?->format('Y-m-d'),
                'completed_count' => (int) ($completedCounts[$user->id] ?? 0),
            ]),
            'meta' => [
                'total' => $users->total(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    /**
     * 기사(Driver) 목록 + 가용 상태·오늘 통계 (관리자 전용).
     *
     * @return JsonResponse{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function drivers(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request->user());

        $users = User::query()
            ->where('role', User::ROLE_DRIVER)
            ->orderByDesc('created_at')
            ->paginate(20);

        $driverRows = Driver::query()
            ->whereIn('user_id', collect($users->items())->pluck('id'))
            ->get()
            ->keyBy('user_id');

        $today = now()->startOfDay();

        $todayStats = Order::query()
            ->whereIn('user_id', collect($users->items())->pluck('id'))
            ->whereIn('status', [Order::STATUS_COMPLETED, Order::STATUS_SETTLED])
            ->where('updated_at', '>=', $today)
            ->selectRaw('user_id, COUNT(*) as cnt, COALESCE(SUM(amount_value), 0) as income')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        return response()->json([
            'data' => $users->map(function (User $user) use ($driverRows, $todayStats) {
                $driver = $driverRows->get($user->id);
                $row = $todayStats->get($user->id);

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'is_vehicle_verified' => (bool) $user->is_vehicle_verified,
                    'is_license_verified' => (bool) $user->is_license_verified,
                    'status' => $driver?->status ?? Driver::STATUS_OFFLINE,
                    'status_label' => Driver::statusOptions()[$driver?->status ?? Driver::STATUS_OFFLINE] ?? '오프라인',
                    'status_updated_at' => $driver?->status_updated_at?->toIso8601String(),
                    'vehicle_count' => $user->vehicles()->count(),
                    'today_completed' => (int) ($row?->cnt ?? 0),
                    'today_income' => (int) ($row?->income ?? 0),
                ];
            }),
            'meta' => [
                'total' => $users->total(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    /**
     * 기사 상태 강제 전환 (휴게 지시 등) — 관리자 전용.
     */
    public function updateDriverStatus(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin($request->user());

        abort_unless($user->role === User::ROLE_DRIVER, 422, '드라이버만 상태를 변경할 수 있습니다.');

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Driver::statusOptions()))],
        ]);

        $driver = $user->driver()->firstOrCreate(['user_id' => $user->id], ['status' => Driver::STATUS_OFFLINE]);
        $now = now();

        // 온라인 시간 누적 로직은 DriverController와 동일하게 유지한다
        if ($driver->status === Driver::STATUS_ONLINE && $data['status'] !== Driver::STATUS_ONLINE && $driver->status_updated_at) {
            $driver->online_seconds = max(0, ((int) $driver->online_seconds) + (int) $driver->status_updated_at->diffInSeconds($now));
            $driver->online_date = $now->toDateString();
        }

        if ($data['status'] === Driver::STATUS_ONLINE && $driver->online_date?->toDateString() !== $now->toDateString()) {
            $driver->online_seconds = 0;
            $driver->online_date = $now->toDateString();
        }

        $driver->forceFill(['status' => $data['status'], 'status_updated_at' => $now])->save();

        return response()->json(['data' => ['status' => $driver->status, 'status_label' => Driver::statusOptions()[$driver->status]]]);
    }

    /**
     * 자동 운행 등록 설정 조회 (관리자 전용).
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function autoOrderSettings(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request->user());

        return response()->json(['data' => AutoOrderSetting::current()->toArray()]);
    }

    /**
     * 자동 운행 등록 설정 변경 — 활성화/비활성화·건수·등록 계정 (관리자 전용).
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function updateAutoOrderSettings(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request->user());

        $data = $request->validate([
            'is_active' => ['sometimes', 'boolean'],
            'min_count' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'max_count' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'owner_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ]);

        $setting = AutoOrderSetting::current();

        if (array_key_exists('min_count', $data) && array_key_exists('max_count', $data)) {
            $data['max_count'] = max($data['min_count'], $data['max_count']);
        }

        $setting->fill($data)->save();

        return response()->json(['data' => $setting->fresh()->toArray()]);
    }

    /**
     * 자동 등록된 운행 이력 — 최신순 50건 (관리자 전용).
     *
     * @return JsonResponse{data: array<int, array<string, mixed>>}
     */
    public function autoOrderHistory(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request->user());

        $rows = Order::query()
            ->where('auto_registered', true)
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'order_number', 'customer_name', 'pickup_location', 'dropoff_location', 'service_date', 'service_time', 'expected_revenue', 'status', 'created_at']);

        return response()->json([
            'data' => $rows->map(fn (Order $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'route' => trim(($order->pickup_location ?: '').' → '.($order->dropoff_location ?: '')),
                'service_date' => $order->service_date,
                'service_time' => $order->service_time,
                'expected_revenue' => $order->expected_revenue,
                'status' => $order->status,
                'status_label' => Order::statusOptions()[$order->status] ?? $order->status,
                'created_at' => $order->created_at?->format('Y-m-d H:i'),
            ]),
        ]);
    }

    /**
     * 자동 등록된 운행 삭제 — ids 또는 전체(all) (관리자 전용).
     *
     * @return JsonResponse{data: array<string, int>}
     */
    public function deleteAutoOrders(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request->user());

        $data = $request->validate([
            'ids' => ['sometimes', 'array'],
            'ids.*' => ['integer'],
            'all' => ['sometimes', 'boolean'],
        ]);

        $query = Order::query()->where('auto_registered', true);

        if ($request->boolean('all')) {
            $deleted = $query->delete();
        } else {
            $ids = $data['ids'] ?? [];
            $deleted = $ids ? $query->whereIn('id', $ids)->delete() : 0;
        }

        return response()->json(['data' => ['deleted' => $deleted]]);
    }

    private function authorizeAdmin(User $user): void
    {
        abort_unless(in_array($user->role, [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN], true), 403, '관리자만 접근할 수 있습니다.');
    }
}
