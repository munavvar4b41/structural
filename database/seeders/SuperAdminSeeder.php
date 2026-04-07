<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Create or refresh the primary super admin account for this deployment.
     *
     * Override with env: SUPER_ADMIN_NAME, SUPER_ADMIN_EMAIL, SUPER_ADMIN_PASSWORD.
     */
    public function run(): void
    {
        User::create(
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'role' => UserRole::SuperAdmin,
                'email_verified_at' => now(),
                'email' => 'superadmin@example.com',
            ]
        );
    }
}
