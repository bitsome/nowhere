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
