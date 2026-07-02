<?php

namespace Database\Factories;

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
            'overview' => $this->tipTapDocument(fake()->paragraph()),
            'client_issue' => $this->tipTapDocument(fake()->paragraph()),
            'our_solution' => $this->tipTapDocument(fake()->paragraph()),
            'implementation' => $this->tipTapDocument(fake()->paragraph()),
            'other_details' => $this->tipTapDocument(fake()->paragraph()),
            'result_and_impact' => $this->tipTapDocument(fake()->paragraph()),
            'conclusion' => $this->tipTapDocument(fake()->paragraph()),
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
