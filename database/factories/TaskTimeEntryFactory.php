<?php

namespace Database\Factories;

use App\Enums\TimeEntrySource;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\TaskTimeEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskTimeEntry>
 */
class TaskTimeEntryFactory extends Factory
{
    /**
     * @var class-string<TaskTimeEntry>
     */
    protected $model = TaskTimeEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->subMinutes(fake()->numberBetween(15, 240));
        $end = (clone $start)->addMinutes(fake()->numberBetween(5, 60));

        return [
            'project_task_id' => ProjectTask::factory(),
            'project_id' => function (array $attributes): int {
                $task = ProjectTask::query()->find($attributes['project_task_id']);

                return $task?->project_id ?? Project::factory()->create()->id;
            },
            'user_id' => User::factory(),
            'started_at' => $start,
            'ended_at' => $end,
            'duration_seconds' => $start->diffInSeconds($end),
            'source' => TimeEntrySource::Timer,
            'notes' => null,
        ];
    }

    public function forTask(ProjectTask $task): static
    {
        return $this->state(fn (array $attributes): array => [
            'project_task_id' => $task->id,
            'project_id' => $task->project_id,
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $user->id,
        ]);
    }

    public function running(): static
    {
        return $this->state(fn (array $attributes): array => [
            'ended_at' => null,
            'duration_seconds' => null,
        ]);
    }

    public function manual(): static
    {
        return $this->state(fn (array $attributes): array => [
            'source' => TimeEntrySource::Manual,
        ]);
    }

    /**
     * @param  array{0: \DateTimeInterface, 1: \DateTimeInterface}  $range
     */
    public function between(\DateTimeInterface $start, \DateTimeInterface $end): static
    {
        return $this->state(fn (array $attributes): array => [
            'started_at' => $start,
            'ended_at' => $end,
            'duration_seconds' => max(0, $end->getTimestamp() - $start->getTimestamp()),
        ]);
    }
}
