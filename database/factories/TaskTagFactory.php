<?php

namespace Database\Factories;

use App\Enums\TaskTagColor;
use App\Enums\TaskTagShade;
use App\Models\TaskTag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskTag>
 */
class TaskTagFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'color' => fake()->randomElement(TaskTagColor::cases()),
            'shade' => TaskTagShade::Light,
            'sort_order' => fake()->numberBetween(0, 20),
        ];
    }
}
