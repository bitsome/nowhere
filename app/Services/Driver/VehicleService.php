<?php

namespace App\Services\Driver;

use App\Models\User;
use App\Models\Vehicle;

/**
 * 기사 차량 관리 — 목록/등록/수정/삭제(대표 차량 처리)와 직렬화를 담당한다.
 */
class VehicleService
{
    /**
     * 내 차량 목록 (대표 차량 우선).
     *
     * @return array<int, array<string, mixed>>
     */
    public function list(User $user): array
    {
        return $user->vehicles()
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get()
            ->map(fn (Vehicle $vehicle) => $this->payload($vehicle))
            ->values()
            ->all();
    }

    /**
     * 차량 등록.
     *
     * @param  array<string, mixed>  $data  검증된 페이로드
     */
    public function store(User $user, array $data): Vehicle
    {
        $this->authorizeDriver($user);

        $isDefault = (bool) ($data['is_default'] ?? false);

        if ($isDefault) {
            $user->vehicles()->update(['is_default' => false]);
        }

        return $user->vehicles()->create([...$data, 'is_default' => $isDefault]);
    }

    /**
     * 차량 수정.
     *
     * @param  array<string, mixed>  $data  검증된 페이로드
     */
    public function update(User $user, Vehicle $vehicle, array $data): Vehicle
    {
        $this->authorizeOwnership($user, $vehicle);

        $isDefault = (bool) ($data['is_default'] ?? $vehicle->is_default);

        if ($isDefault && ! $vehicle->is_default) {
            $vehicle->user()->first()?->vehicles()->whereKeyNot($vehicle->id)->update(['is_default' => false]);
        }

        $vehicle->forceFill([...$data, 'is_default' => $isDefault])->save();

        return $vehicle;
    }

    public function destroy(User $user, Vehicle $vehicle): void
    {
        $this->authorizeOwnership($user, $vehicle);
        $vehicle->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(Vehicle $vehicle): array
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

    private function authorizeDriver(User $user): void
    {
        abort_unless($user->role === User::ROLE_DRIVER, 403, '드라이버만 사용할 수 있습니다.');
    }

    private function authorizeOwnership(User $user, Vehicle $vehicle): void
    {
        abort_unless($vehicle->user_id === $user->id, 403, '본인 차량만 관리할 수 있습니다.');
    }
}
