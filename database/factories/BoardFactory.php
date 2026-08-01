<?php

namespace Database\Factories;

use App\Models\Board;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Board>
 */
class BoardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(Board::typeOptions());

        return [
            'type' => $type,
            'title' => fake()->sentence(6),
            'content' => fake()->paragraphs(3, true),
            'user_id' => User::factory(),
            'status' => Board::STATUS_PUBLISHED,
            'is_notice' => $type === Board::TYPE_NOTICE,
            'is_private' => false,
            'view_count' => fake()->numberBetween(0, 120),
        ];
    }
}
