<?php

namespace Tests\Feature\Admin;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeamManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_teams_index(): void
    {
        $this->get(route('admin.teams.index'))
            ->assertRedirect(route('login'));
    }

    public function test_staff_cannot_view_teams_index(): void
    {
        $user = User::factory()->withPrimaryTeam()->create();

        $this->actingAs($user)
            ->get(route('admin.teams.index'))
            ->assertForbidden();
    }

    public function test_admin_can_view_teams_index(): void
    {
        $admin = User::factory()->admin()->withPrimaryTeam()->create();
        Team::factory()->create(['name' => 'Platform Team']);

        $this->actingAs($admin)
            ->get(route('admin.teams.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/teams/Index')
                ->has('teams.data', 2));
    }

    public function test_admin_can_store_team(): void
    {
        $admin = User::factory()->admin()->withPrimaryTeam()->create();

        $this->actingAs($admin)
            ->post(route('admin.teams.store'), [
                'name' => 'Backend Team',
                'code' => 'BE-001',
                'description' => 'Backend development group',
            ])
            ->assertRedirect(route('admin.teams.index'));

        $this->assertDatabaseHas('teams', [
            'name' => 'Backend Team',
            'code' => 'BE-001',
        ]);
    }

    public function test_admin_can_update_team(): void
    {
        $admin = User::factory()->admin()->withPrimaryTeam()->create();
        $team = Team::factory()->create(['name' => 'Old Name']);

        $this->actingAs($admin)
            ->put(route('admin.teams.update', $team), [
                'name' => 'New Name',
                'code' => 'NEW-001',
                'description' => 'Updated description',
            ])
            ->assertRedirect(route('admin.teams.index'));

        $this->assertSame('New Name', $team->fresh()->name);
    }

    public function test_admin_can_delete_team_without_primary_assignments(): void
    {
        $admin = User::factory()->admin()->withPrimaryTeam()->create();
        $team = Team::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.teams.destroy', $team))
            ->assertRedirect(route('admin.teams.index'));

        $this->assertDatabaseMissing('teams', ['id' => $team->id]);
    }

    public function test_admin_cannot_delete_team_if_it_is_primary_for_any_user(): void
    {
        $admin = User::factory()->admin()->withPrimaryTeam()->create();
        $team = Team::factory()->create();
        $user = User::factory()->create(['primary_team_id' => $team->id]);
        $user->teams()->attach($team->id);

        $this->actingAs($admin)
            ->delete(route('admin.teams.destroy', $team))
            ->assertSessionHasErrors('team');

        $this->assertDatabaseHas('teams', ['id' => $team->id]);
    }

    public function test_admin_can_search_teams_index_by_name(): void
    {
        $admin = User::factory()->admin()->withPrimaryTeam()->create();
        Team::factory()->create(['name' => 'Zebra Squad']);
        Team::factory()->create(['name' => 'Other Guild']);

        $this->actingAs($admin)
            ->get(route('admin.teams.index', ['search' => 'Zebra']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/teams/Index')
                ->has('teams.data', 1)
                ->where('teams.data.0.name', 'Zebra Squad')
                ->where('filters.search', 'Zebra'));
    }
}
