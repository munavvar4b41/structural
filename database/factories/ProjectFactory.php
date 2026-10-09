<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use App\Support\ProjectPasswordCipher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->catchPhrase(),
            'code' => strtoupper(fake()->unique()->bothify('PRJ-###')),
            'description' => fake()->optional()->sentence(),
            'client_user_id' => User::factory()->client(),
            'lead_user_id' => null,
        ];
    }

    public function withPassphrase(string $passphrase): static
    {
        return $this->afterCreating(function (Project $project) use ($passphrase): void {
            app(ProjectPasswordCipher::class)->seal($project, $passphrase);
        });
    }
}
