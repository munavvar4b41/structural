<?php

namespace Database\Factories;

use App\Models\ProjectProposal;
use App\Models\ProjectProposalMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectProposalMessage>
 */
class ProjectProposalMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_proposal_id' => ProjectProposal::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentences(2, true),
        ];
    }
}
