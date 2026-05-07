<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeamSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_without_primary_team_can_view_team_selection_screen(): void
    {
        $user = User::factory()->create(['primary_team_id' => null]);
        Team::factory()->count(2)->create();

        $this->actingAs($user)
            ->get(route('teams.select.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/SelectTeam')
                ->has('teams', 2));
    }

    public function test_user_can_select_primary_team_and_continue_to_dashboard(): void
    {
        $user = User::factory()->create(['primary_team_id' => null]);
        $team = Team::factory()->create();

        $this->actingAs($user)
            ->post(route('teams.select.store'), [
                'primary_team_id' => $team->id,
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'primary_team_id' => $team->id,
        ]);
        $this->assertDatabaseHas('team_user', [
            'user_id' => $user->id,
            'team_id' => $team->id,
        ]);
    }

    public function test_user_with_primary_team_can_access_dashboard(): void
    {
        $team = Team::factory()->create();
        $user = User::factory()->create(['primary_team_id' => $team->id]);
        $user->teams()->attach($team->id);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_admin_can_access_dashboard_without_primary_team(): void
    {
        $user = User::factory()->admin()->create(['primary_team_id' => null]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_super_admin_can_access_dashboard_without_primary_team(): void
    {
        $user = User::factory()->superAdmin()->create(['primary_team_id' => null]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_client_can_access_dashboard_without_primary_team(): void
    {
        $user = User::factory()->client()->create(['primary_team_id' => null]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_exempt_roles_are_redirected_from_team_selection_screen(): void
    {
        foreach ([UserRole::SuperAdmin, UserRole::Admin, UserRole::Client] as $role) {
            $user = User::factory()->create([
                'role' => $role,
                'primary_team_id' => null,
            ]);

            $this->actingAs($user)
                ->get(route('teams.select.create'))
                ->assertRedirect(route('dashboard'));
        }
    }
}
