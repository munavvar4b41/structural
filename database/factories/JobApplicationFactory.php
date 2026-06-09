<?php

namespace Database\Factories;

use App\Enums\JobApplicationStatus;
use App\Models\JobApplication;
use App\Models\JobPosting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobApplication>
 */
class JobApplicationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_posting_id' => JobPosting::factory()->open(),
            'status' => JobApplicationStatus::Received,
            'candidate_name' => fake()->name(),
            'candidate_email' => fake()->safeEmail(),
            'candidate_phone' => fake()->phoneNumber(),
            'linkedin_url' => fake()->optional()->url(),
            'portfolio_url' => fake()->optional()->url(),
            'cover_letter' => fake()->optional()->paragraph(),
            'skills' => implode(', ', fake()->words(5)),
            'years_of_experience' => fake()->numberBetween(0, 20),
            'salary_expectation' => fake()->numerify('#####').' USD',
            'preferred_location' => fake()->city(),
            'resume_path' => 'careers/resumes/'.fake()->uuid().'.pdf',
            'resume_original_name' => 'resume.pdf',
            'resume_mime' => 'application/pdf',
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
            'rejection_reason' => null,
            'applied_at' => now(),
        ];
    }

    public function screening(): static
    {
        return $this->state(fn (): array => [
            'status' => JobApplicationStatus::Screening,
        ]);
    }
}
