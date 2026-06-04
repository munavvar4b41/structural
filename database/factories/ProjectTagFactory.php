<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectTag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectTag>
 */
class ProjectTagFactory extends Factory
{
    protected $model = ProjectTag::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => fake()->unique()->slug(2),
        ];
    }
}
