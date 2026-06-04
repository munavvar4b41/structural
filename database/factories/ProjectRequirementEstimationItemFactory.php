<?php

namespace Database\Factories;

use App\Models\ProjectRequirementEstimation;
use App\Models\ProjectRequirementEstimationItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectRequirementEstimationItem>
 */
class ProjectRequirementEstimationItemFactory extends Factory
{
    protected $model = ProjectRequirementEstimationItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_requirement_estimation_id' => ProjectRequirementEstimation::factory(),
            'parent_estimation_item_id' => null,
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'estimated_minutes' => fake()->numberBetween(15, 480),
            'sort_order' => 0,
            'transferred_project_task_id' => null,
        ];
    }
}
