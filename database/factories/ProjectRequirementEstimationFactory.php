<?php

namespace Database\Factories;

use App\Enums\RequirementEstimationStatus;
use App\Models\ProjectRequirement;
use App\Models\ProjectRequirementEstimation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectRequirementEstimation>
 */
class ProjectRequirementEstimationFactory extends Factory
{
    protected $model = ProjectRequirementEstimation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_requirement_id' => ProjectRequirement::factory(),
            'version' => 1,
            'status' => RequirementEstimationStatus::Draft,
            'created_by_user_id' => User::factory(),
            'submitted_at' => null,
            'submitted_to_user_id' => null,
            'submission_notes' => null,
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
            'review_notes' => null,
            'transferred_at' => null,
            'transferred_by_user_id' => null,
            'superseded_by_estimation_id' => null,
        ];
    }

    public function pendingApproval(): static
    {
        return $this->state(fn (): array => [
            'status' => RequirementEstimationStatus::PendingApproval,
            'submitted_at' => now(),
            'submitted_to_user_id' => User::factory(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => RequirementEstimationStatus::Approved,
            'submitted_at' => now(),
            'reviewed_at' => now(),
            'reviewed_by_user_id' => User::factory(),
        ]);
    }

    public function transferred(): static
    {
        return $this->state(fn (): array => [
            'status' => RequirementEstimationStatus::Transferred,
            'transferred_at' => now(),
            'transferred_by_user_id' => User::factory(),
        ]);
    }
}
