<?php

namespace Database\Factories;

use App\Enums\TaskPriorityColor;
use App\Enums\TaskPriorityShade;
use App\Models\TaskPriority;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskPriority>
 */
class TaskPriorityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'color' => fake()->randomElement(TaskPriorityColor::cases()),
            'shade' => TaskPriorityShade::Light,
            'sort_order' => fake()->numberBetween(0, 20),
        ];
    }
}
