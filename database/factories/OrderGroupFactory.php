<?php

namespace Database\Factories;

use App\Models\OrderGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderGroup>
 */
class OrderGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->optional()->words(2, true),
            'type' => '셋트',
        ];
    }
}
