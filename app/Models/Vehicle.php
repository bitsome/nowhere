<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 기사 차량 — users 테이블과 1:N.
 */
#[Fillable([
    'user_id',
    'name',
    'type',
    'license_plate',
    'color',
    'capacity',
    'luggage_capacity',
    'insurance_expires_at',
    'photo_path',
    'is_default',
    'is_verified',
])]
class Vehicle extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 드라이버가 사용 중인 차량 — 기본 차량 우선, 없으면 가장 먼저 등록된 차량.
     */
    public static function activeVehicleFor(int $userId): ?self
    {
        return static::query()
            ->where('user_id', $userId)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    /**
     * 카드·목록에 노출할 차량 요약.
     *
     * @return array<string, mixed>
     */
    public static function brief(?self $vehicle): ?array
    {
        if ($vehicle === null) {
            return null;
        }

        return [
            'name' => $vehicle->name,
            'type' => $vehicle->type,
            'license_plate' => $vehicle->license_plate,
            'color' => $vehicle->color,
            'capacity' => $vehicle->capacity,
            'luggage_capacity' => $vehicle->luggage_capacity,
            'is_verified' => $vehicle->is_verified,
        ];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'insurance_expires_at' => 'date',
            'is_default' => 'boolean',
            'is_verified' => 'boolean',
        ];
    }
}
