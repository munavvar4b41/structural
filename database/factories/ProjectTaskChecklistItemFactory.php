<?php

namespace Database\Factories;

use App\Models\ProjectTask;
use App\Models\ProjectTaskChecklistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectTaskChecklistItem>
 */
class ProjectTaskChecklistItemFactory extends Factory
{
    /**
     * @var class-string<ProjectTaskChecklistItem>
     */
    protected $model = ProjectTaskChecklistItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_task_id' => ProjectTask::factory(),
            'title' => fake()->sentence(3),
            'is_completed' => false,
        ];
    }

    public function forTask(ProjectTask $task): static
    {
        return $this->state(fn (array $attributes): array => [
            'project_task_id' => $task->id,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_completed' => true,
        ]);
    }
}
