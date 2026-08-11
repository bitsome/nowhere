<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id',
    'scheduled_time',
    'service_date',
    'service_month',
    'service_day',
    'service_weekday',
    'service_type',
    'location',
    'pickup_location',
    'dropoff_location',
    'flight_number',
    'passenger_count',
    'luggage_count',
    'amount_value',
    'amount_text',
])]
class OrderLineItem extends Model
{
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'passenger_count' => 'integer',
            'luggage_count' => 'integer',
            'amount_value' => 'integer',
        ];
    }
}
