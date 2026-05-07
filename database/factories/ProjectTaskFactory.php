<?php

namespace Database\Factories;

use App\Enums\ProjectTaskStatus;
use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectTask>
 */
class ProjectTaskFactory extends Factory
{
    /**
     * @var class-string<ProjectTask>
     */
    protected $model = ProjectTask::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'project_requirement_id' => null,
            'parent_project_task_id' => null,
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'status' => fake()->randomElement(ProjectTaskStatus::cases()),
            'assignee_user_id' => null,
            'created_by_user_id' => User::factory(),
            'estimated_minutes' => fake()->optional(0.6)->randomElement([15, 30, 60, 120, 240]),
        ];
    }

    public function forProject(Project $project): static
    {
        return $this->state(fn (array $attributes): array => [
            'project_id' => $project->id,
        ]);
    }

    public function withRequirement(ProjectRequirement $requirement): static
    {
        return $this->state(fn (array $attributes): array => [
            'project_id' => $requirement->project_id,
            'project_requirement_id' => $requirement->id,
        ]);
    }

    public function childOf(ProjectTask $parent): static
    {
        return $this->state(fn (array $attributes): array => [
            'project_id' => $parent->project_id,
            'project_requirement_id' => $parent->project_requirement_id,
            'parent_project_task_id' => $parent->id,
        ]);
    }
}
