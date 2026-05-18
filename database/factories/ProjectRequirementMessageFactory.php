<?php

namespace Database\Factories;

use App\Models\ProjectRequirement;
use App\Models\ProjectRequirementMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectRequirementMessage>
 */
class ProjectRequirementMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_requirement_id' => ProjectRequirement::factory(),
            'user_id' => User::first()->id,
            'body' => fake()->sentences(2, true),
        ];
    }
}
