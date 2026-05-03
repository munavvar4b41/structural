<?php

namespace Tests\Feature\Admin;

use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_projects_index(): void
    {
        $this->get(route('admin.projects.index'))
            ->assertRedirect(route('login'));
    }

    public function test_project_visibility_is_limited_to_assigned_teams(): void
    {
        $visibleTeam = Team::factory()->create();
        $hiddenTeam = Team::factory()->create();
        $staff = User::factory()->withPrimaryTeam($visibleTeam)->create();

        $visibleProject = Project::factory()->create(['name' => 'Visible Project']);
        $visibleProject->teams()->sync([$visibleTeam->id]);

        $hiddenProject = Project::factory()->create(['name' => 'Hidden Project']);
        $hiddenProject->teams()->sync([$hiddenTeam->id]);

        $this->assertTrue($staff->can('viewAny', Project::class));
        $this->assertTrue($staff->can('view', $visibleProject));
        $this->assertFalse($staff->can('view', $hiddenProject));
    }

    public function test_admin_can_list_and_view_all_projects_regardless_of_team(): void
    {
        $teamA = Team::factory()->create();
        $teamB = Team::factory()->create();
        $admin = User::factory()->admin()->create(['primary_team_id' => null]);

        $projectA = Project::factory()->create(['name' => 'Admin Sees A']);
        $projectA->teams()->sync([$teamA->id]);
        $projectB = Project::factory()->create(['name' => 'Admin Sees B']);
        $projectB->teams()->sync([$teamB->id]);

        $this->assertTrue($admin->can('view', $projectA));
        $this->assertTrue($admin->can('view', $projectB));

        $this->actingAs($admin)
            ->get(route('admin.projects.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/projects/Index')
                ->has('projects.data', 2));
    }

    public function test_super_admin_can_list_and_view_all_projects_regardless_of_team(): void
    {
        $teamA = Team::factory()->create();
        $teamB = Team::factory()->create();
        $super = User::factory()->superAdmin()->create(['primary_team_id' => null]);

        $projectA = Project::factory()->create(['name' => 'Super Sees A']);
        $projectA->teams()->sync([$teamA->id]);
        $projectB = Project::factory()->create(['name' => 'Super Sees B']);
        $projectB->teams()->sync([$teamB->id]);

        $this->assertTrue($super->can('view', $projectA));
        $this->assertTrue($super->can('view', $projectB));

        $this->actingAs($super)
            ->get(route('admin.projects.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/projects/Index')
                ->has('projects.data', 2));
    }

    public function test_project_lead_must_be_team_head_or_staff_on_assigned_teams(): void
    {
        $team = Team::factory()->create();
        $admin = User::factory()->admin()->create();
        $client = User::factory()->client()->create();
        $teamHead = User::factory()->teamHead()->withPrimaryTeam($team)->create();

        $this->actingAs($teamHead)
            ->post(route('admin.projects.store'), [
                'name' => 'Invalid lead role',
                'client_user_id' => $client->id,
                'team_ids' => [$team->id],
                'lead_user_id' => $admin->id,
            ])
            ->assertSessionHasErrors('lead_user_id');
    }

    public function test_team_head_can_create_update_and_delete_project(): void
    {
        $teamA = Team::factory()->create();
        $teamB = Team::factory()->create();
        $clientUser = User::factory()->client()->create();
        $teamHead = User::factory()->teamHead()->withPrimaryTeam($teamA)->create();
        $teamHead->teams()->syncWithoutDetaching([$teamB->id]);

        $this->actingAs($teamHead)
            ->post(route('admin.projects.store'), [
                'name' => 'Platform Revamp',
                'code' => 'PRJ-100',
                'description' => 'Delivery milestone one',
                'client_user_id' => $clientUser->id,
                'team_ids' => [$teamA->id, $teamB->id],
            ])
            ->assertRedirect(route('admin.projects.index'));

        $project = Project::query()->where('name', 'Platform Revamp')->firstOrFail();
        $this->assertSame($clientUser->id, $project->client_user_id);
        $this->assertSame(2, $project->teams()->count());
        $this->assertSame($teamHead->id, $project->lead_user_id);

        $this->actingAs($teamHead)
            ->put(route('admin.projects.update', $project), [
                'name' => 'Platform Revamp Updated',
                'code' => 'PRJ-101',
                'description' => 'Updated milestone',
                'client_user_id' => $clientUser->id,
                'team_ids' => [$teamA->id],
            ])
            ->assertRedirect(route('admin.projects.index'));

        $project = $project->fresh();
        $this->assertSame('Platform Revamp Updated', $project?->name);
        $this->assertSame(1, $project?->teams()->count());
        $this->assertSame($teamHead->id, $project?->lead_user_id);

        $this->actingAs($teamHead)
            ->delete(route('admin.projects.destroy', $project))
            ->assertRedirect(route('admin.projects.index'));

        $this->assertDatabaseMissing('projects', ['id' => $project?->id]);
    }

    public function test_staff_and_client_cannot_manage_projects(): void
    {
        $team = Team::factory()->create();
        $staff = User::factory()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $projectClient = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $projectClient->id]);
        $project->teams()->sync([$team->id]);

        $this->actingAs($staff)
            ->post(route('admin.projects.store'), [
                'name' => 'Blocked Project',
                'client_user_id' => $client->id,
                'team_ids' => [$team->id],
            ])
            ->assertForbidden();

        $this->actingAs($staff)
            ->put(route('admin.projects.update', $project), [
                'name' => 'Blocked Update',
                'client_user_id' => $projectClient->id,
                'team_ids' => [$team->id],
            ])
            ->assertForbidden();

        $this->actingAs($client)
            ->delete(route('admin.projects.destroy', $project))
            ->assertForbidden();

        $this->actingAs($client)
            ->post(route('admin.projects.store'), [
                'name' => 'Client Blocked',
                'client_user_id' => $client->id,
                'team_ids' => [$team->id],
            ])
            ->assertForbidden();
    }

    public function test_project_requires_at_least_one_valid_team_assignment(): void
    {
        $team = Team::factory()->create();
        $clientUser = User::factory()->client()->create();
        $teamHead = User::factory()->teamHead()->withPrimaryTeam($team)->create();

        $this->actingAs($teamHead)
            ->post(route('admin.projects.store'), [
                'name' => 'Invalid Project',
                'client_user_id' => $clientUser->id,
                'team_ids' => [],
            ])
            ->assertSessionHasErrors('team_ids');

        $this->actingAs($teamHead)
            ->post(route('admin.projects.store'), [
                'name' => 'Invalid Team Id',
                'client_user_id' => $clientUser->id,
                'team_ids' => [999999],
            ])
            ->assertSessionHasErrors('team_ids.0');
    }

    public function test_project_requires_client_user_with_client_role(): void
    {
        $team = Team::factory()->create();
        $staff = User::factory()->withPrimaryTeam($team)->create();
        $teamHead = User::factory()->teamHead()->withPrimaryTeam($team)->create();

        $this->actingAs($teamHead)
            ->post(route('admin.projects.store'), [
                'name' => 'Bad Client Ref',
                'client_user_id' => $staff->id,
                'team_ids' => [$team->id],
            ])
            ->assertSessionHasErrors('client_user_id');
    }

    public function test_client_user_sees_only_assigned_projects_on_index(): void
    {
        $team = Team::factory()->create();
        $clientA = User::factory()->client()->create();
        $clientB = User::factory()->client()->create();

        $projectForA = Project::factory()->create([
            'name' => 'Alpha Build',
            'client_user_id' => $clientA->id,
        ]);
        $projectForA->teams()->sync([$team->id]);

        $projectForB = Project::factory()->create([
            'name' => 'Beta Build',
            'client_user_id' => $clientB->id,
        ]);
        $projectForB->teams()->sync([$team->id]);

        $this->assertTrue($clientA->can('viewAny', Project::class));
        $this->assertTrue($clientA->can('view', $projectForA));
        $this->assertFalse($clientA->can('view', $projectForB));

        $this->actingAs($clientA)
            ->get(route('admin.projects.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/projects/Index')
                ->has('projects.data', 1)
                ->where('projects.data.0.name', 'Alpha Build'));
    }

    public function test_deleting_project_detaches_related_teams(): void
    {
        $team = Team::factory()->create();
        $teamHead = User::factory()->teamHead()->withPrimaryTeam($team)->create();
        $project = Project::factory()->create();
        $project->teams()->sync([$team->id]);

        $this->assertDatabaseHas('project_team', [
            'project_id' => $project->id,
            'team_id' => $team->id,
        ]);

        $this->actingAs($teamHead)
            ->delete(route('admin.projects.destroy', $project))
            ->assertRedirect(route('admin.projects.index'));

        $this->assertDatabaseMissing('project_team', [
            'project_id' => $project->id,
            'team_id' => $team->id,
        ]);
    }
}
