<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Order;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * 기사 운영 — 가용 상태, 오늘 통계, 차량 관리.
 * #3(화면/UX)·#4(실시간·정책) 범위: 기사 본인만 상태 변경·차량 CRUD.
 */
class DriverController extends Controller
{
    private const SELF_STATUSES = [Driver::STATUS_OFFLINE, Driver::STATUS_ONLINE, Driver::STATUS_REST];

    /**
     * 내 기사 상태 조회.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $driver = $this->driverFor($user);

        return response()->json(['data' => $this->driverPayload($driver, $user)]);
    }

    /**
     * 내 기사 상태 변경 (온라인/오프라인/휴식).
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authorizeDriver($user);

        $data = $request->validate([
            'status' => ['required', Rule::in(self::SELF_STATUSES)],
        ]);

        $driver = $this->driverFor($user);
        $now = now();
        $previous = $driver->status;

        // 온라인 시간 누적 — 온라인을 벗어날 때 경과 초를 적립한다
        if ($previous === Driver::STATUS_ONLINE && $data['status'] !== Driver::STATUS_ONLINE) {
            $this->accumulateOnline($driver, $now);
        }

        // 온라인 진입 시 일 단위로 초기화
        if ($data['status'] === Driver::STATUS_ONLINE) {
            if ($driver->online_date?->toDateString() !== $now->toDateString()) {
                $driver->online_seconds = 0;
                $driver->online_date = $now->toDateString();
            }
        }

        $driver->forceFill([
            'status' => $data['status'],
            'status_updated_at' => $now,
        ])->save();

        return response()->json(['data' => $this->driverPayload($driver, $user)]);
    }

    /**
     * 오늘 통계 — 온라인 시간, 완료 운행 수, 수입, 진행 중 운행.
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
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

        return response()->json([
            'data' => [
                'online_seconds' => $this->onlineSeconds($driver),
                'status' => $driver->status,
                'status_label' => Driver::statusOptions()[$driver->status] ?? $driver->status,
                'today_completed' => $todayOrders->count(),
                'today_income' => (int) $todayOrders->sum('amount_value'),
                'active_count' => $active,
            ],
        ]);
    }

    /**
     * 내 차량 목록.
     */
    public function vehicles(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authorizeDriver($user);

        $vehicles = $user->vehicles()->orderByDesc('is_default')->orderBy('id')->get();

        return response()->json([
            'data' => $vehicles->map(fn (Vehicle $vehicle) => $this->vehiclePayload($vehicle)),
        ]);
    }

    /**
     * 차량 등록.
     */
    public function storeVehicle(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authorizeDriver($user);

        $data = $this->validatedVehicle($request);
        $isDefault = (bool) ($data['is_default'] ?? false);

        if ($isDefault) {
            $user->vehicles()->update(['is_default' => false]);
        }

        $vehicle = $user->vehicles()->create([...$data, 'is_default' => $isDefault]);

        return response()->json(['data' => $this->vehiclePayload($vehicle)], 201);
    }

    /**
     * 차량 수정.
     */
    public function updateVehicle(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorizeOwnership($request->user(), $vehicle);

        $data = $this->validatedVehicle($request);
        $isDefault = (bool) ($data['is_default'] ?? $vehicle->is_default);

        if ($isDefault && ! $vehicle->is_default) {
            $vehicle->user()->first()?->vehicles()->whereKeyNot($vehicle->id)->update(['is_default' => false]);
        }

        $vehicle->forceFill([...$data, 'is_default' => $isDefault])->save();

        return response()->json(['data' => $this->vehiclePayload($vehicle)]);
    }

    /**
     * 차량 삭제.
     */
    public function destroyVehicle(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorizeOwnership($request->user(), $vehicle);
        $vehicle->delete();

        return response()->json(['ok' => true]);
    }

    private function validatedVehicle(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'max:40'],
            'license_plate' => ['nullable', 'string', 'max:30'],
            'color' => ['nullable', 'string', 'max:30'],
            'capacity' => ['nullable', 'integer', 'min:0', 'max:99'],
            'luggage_capacity' => ['nullable', 'integer', 'min:0', 'max:99'],
            'insurance_expires_at' => ['nullable', 'date'],
            'photo_path' => ['nullable', 'string', 'max:255'],
            'is_default' => ['nullable', 'boolean'],
        ]);
    }

    private function authorizeDriver(User $user): void
    {
        abort_unless($user->role === User::ROLE_DRIVER, 403, '드라이버만 사용할 수 있습니다.');
    }

    private function authorizeOwnership(User $user, Vehicle $vehicle): void
    {
        abort_unless($vehicle->user_id === $user->id, 403, '본인 차량만 관리할 수 있습니다.');
    }

    private function driverFor(User $user): Driver
    {
        return $user->driver()->firstOrCreate(['user_id' => $user->id], ['status' => Driver::STATUS_OFFLINE]);
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

    /**
     * @return array<string, mixed>
     */
    private function driverPayload(Driver $driver, User $user): array
    {
        return [
            'user_id' => $user->id,
            'name' => $user->name,
            'status' => $driver->status,
            'status_label' => Driver::statusOptions()[$driver->status] ?? $driver->status,
            'status_updated_at' => $driver->status_updated_at?->toIso8601String(),
            'is_vehicle_verified' => (bool) $user->is_vehicle_verified,
            'is_license_verified' => (bool) $user->is_license_verified,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function vehiclePayload(Vehicle $vehicle): array
    {
        return [
            'id' => $vehicle->id,
            'name' => $vehicle->name,
            'type' => $vehicle->type,
            'license_plate' => $vehicle->license_plate,
            'color' => $vehicle->color,
            'capacity' => (int) $vehicle->capacity,
            'luggage_capacity' => (int) $vehicle->luggage_capacity,
            'insurance_expires_at' => $vehicle->insurance_expires_at?->toDateString(),
            'photo_path' => $vehicle->photo_path,
            'is_default' => (bool) $vehicle->is_default,
            'is_verified' => (bool) $vehicle->is_verified,
            'created_at' => $vehicle->created_at?->toDateString(),
        ];
    }
}
