<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_number' => Order::generateOrderNumber(),
            'reservation_company' => fake()->randomElement(Order::reservationCompanyOptions()),
            'customer_name' => fake()->name(),
            'reservation_channel' => fake()->randomElement(array_keys(Order::reservationChannelOptions())),
            'passenger_count' => fake()->numberBetween(1, 8),
            'pickup_location' => fake()->city().' '.fake()->streetName(),
            'dropoff_location' => fake()->city().' '.fake()->streetName(),
            'flight_number' => strtoupper(fake()->lexify('??')).fake()->numberBetween(100, 9999),
            'scheduled_at' => now()->addHours(fake()->numberBetween(1, 48)),
            'order_type' => fake()->randomElement(array_keys(Order::orderTypeOptions())),
            'estimated_duration_minutes' => fake()->numberBetween(20, 120),
            'distance_km' => fake()->randomFloat(1, 3, 80),
            'expected_revenue' => fake()->numberBetween(12000, 180000),
            'status' => fake()->randomElement(array_keys(Order::statusOptions())),
            'user_id' => User::factory(),
        ];
    }
}
