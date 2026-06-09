<?php

namespace Database\Factories;

use App\Enums\JobEmploymentType;
use App\Enums\JobPostingStatus;
use App\Models\JobPosting;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<JobPosting>
 */
class JobPostingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->jobTitle();

        return [
            'slug' => Str::slug($title).'-'.fake()->unique()->numerify('###'),
            'title' => $title,
            'team_id' => Team::factory(),
            'location' => fake()->city(),
            'employment_type' => fake()->randomElement(JobEmploymentType::cases()),
            'description' => json_encode([
                'type' => 'doc',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            ['type' => 'text', 'text' => fake()->paragraph()],
                        ],
                    ],
                ],
            ]),
            'requirements' => json_encode([
                'type' => 'doc',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            ['type' => 'text', 'text' => fake()->paragraph()],
                        ],
                    ],
                ],
            ]),
            'status' => JobPostingStatus::Draft,
            'published_at' => null,
            'closes_at' => null,
            'created_by_user_id' => User::factory(),
        ];
    }

    public function open(): static
    {
        return $this->state(fn (): array => [
            'status' => JobPostingStatus::Open,
            'published_at' => now()->subDay(),
            'closes_at' => now()->addMonth(),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (): array => [
            'status' => JobPostingStatus::Closed,
            'published_at' => now()->subMonth(),
            'closes_at' => now()->subDay(),
        ]);
    }
}
