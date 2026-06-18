<?php

namespace Database\Factories;

use App\Enums\ProjectProposalStatus;
use App\Models\Project;
use App\Models\ProjectProposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectProposal>
 */
class ProjectProposalFactory extends Factory
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
            'project_requirement_id' => null,
            'transferred_project_requirement_id' => null,
            'created_by_user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => $this->tipTapDocument(fake()->paragraph()),
            'status' => ProjectProposalStatus::Draft,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => ProjectProposalStatus::Pending,
            'submitted_at' => now(),
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (): array => [
            'status' => ProjectProposalStatus::Confirmed,
            'submitted_at' => now()->subHour(),
            'reviewed_at' => now(),
            'reviewed_by_user_id' => User::factory(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => ProjectProposalStatus::Rejected,
            'submitted_at' => now()->subHour(),
            'reviewed_at' => now(),
            'reviewed_by_user_id' => User::factory(),
            'rejection_reason' => fake()->sentence(),
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
