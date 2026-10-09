<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectPassword;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectPassword>
 */
class ProjectPasswordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'created_by_user_id' => User::factory(),
            'label' => fake()->words(3, true),
            'username' => fake()->optional()->userName(),
            'url' => fake()->optional()->url(),
            'secret_nonce' => random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            'secret_ciphertext' => random_bytes(48),
            'notes_nonce' => null,
            'notes_ciphertext' => null,
        ];
    }
}
