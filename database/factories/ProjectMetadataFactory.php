<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectMetadata;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectMetadata>
 */
class ProjectMetadataFactory extends Factory
{
    protected $model = ProjectMetadata::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'key' => strtolower(fake()->unique()->word()),
            'value' => fake()->word(),
        ];
    }
}
