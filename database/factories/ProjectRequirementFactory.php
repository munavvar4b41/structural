<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectRequirement>
 */
class ProjectRequirementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'created_by_user_id' => User::first()->id,
            'responsible_user_id' => null,
            'reviewer_user_id' => null,
            'title' => fake()->sentence(4),
            'description' => (string) json_encode([
                'type' => 'doc',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            ['type' => 'text', 'text' => fake()->paragraph()],
                        ],
                    ],
                ],
            ]),
            'reviewed_at' => null,
            'review_understanding' => null,
            'understanding_confirmed_at' => null,
            'understanding_confirmed_by_user_id' => null,
        ];
    }
}
