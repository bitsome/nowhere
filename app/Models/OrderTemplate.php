<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'name',
    'service_type',
    'vehicle_type',
    'pickup_location',
    'dropoff_location',
    'passenger_count',
    'expected_revenue',
    'flight_number',
    'reservation_company',
    'memo',
])]
class OrderTemplate extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
