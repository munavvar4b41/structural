<?php

namespace Database\Factories;

use App\Enums\WorkloadPeriod;
use App\Models\CaseStudy;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CaseStudy>
 */
class CaseStudyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'project_task_id' => null,
            'created_by_user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'summary' => fake()->sentence(),
            'client_issue' => $this->tipTapDocument(fake()->paragraph()),
            'proposed_solution' => $this->tipTapDocument(fake()->paragraph()),
            'resolution' => $this->tipTapDocument(fake()->paragraph()),
            'workload_reduction_details' => $this->tipTapDocument(fake()->paragraph()),
            'workload_hours_saved' => fake()->randomFloat(2, 1, 40),
            'workload_percentage_reduction' => fake()->randomFloat(2, 5, 80),
            'workload_period' => fake()->randomElement(WorkloadPeriod::cases()),
        ];
    }

    public function forTask(ProjectTask $task): static
    {
        return $this->state(fn (): array => [
            'project_id' => $task->project_id,
            'project_task_id' => $task->id,
        ]);
    }

    private function tipTapDocument(string $text): string
    {
        return (string) json_encode([
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => $text],
                    ],
                ],
            ],
        ]);
    }
}
