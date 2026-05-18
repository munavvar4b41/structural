<?php

namespace Database\Factories;

use App\Models\ProjectTask;
use App\Models\ProjectTaskReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectTaskReview>
 */
class ProjectTaskReviewFactory extends Factory
{
    /**
     * @var class-string<ProjectTaskReview>
     */
    protected $model = ProjectTaskReview::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_task_id' => ProjectTask::factory(),
            'reviewer_user_id' => User::first()->id,
            'review_notes' => fake()->optional(0.7)->sentence(),
            'task_rating' => fake()->numberBetween(1, 5),
            'assignee_rating' => fake()->optional(0.9)->numberBetween(1, 5),
            'creator_rating' => fake()->optional(0.9)->numberBetween(1, 5),
        ];
    }
}
