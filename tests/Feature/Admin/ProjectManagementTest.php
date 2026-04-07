<?php

namespace Tests\Feature\Admin;

use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_team_head_can_create_update_and_delete_project(): void
    {
        $teamA = Team::factory()->create();
        $teamB = Team::factory()->create();
        $teamHead = User::factory()->teamHead()->withPrimaryTeam($teamA)->create();
        $teamHead->teams()->syncWithoutDetaching([$teamB->id]);

        $this->actingAs($teamHead)
            ->post(route('admin.projects.store'), [
                'name' => 'Platform Revamp',
                'code' => 'PRJ-100',
                'description' => 'Delivery milestone one',
                'team_ids' => [$teamA->id, $teamB->id],
            ])
            ->assertRedirect(route('admin.projects.index'));

        $project = Project::query()->where('name', 'Platform Revamp')->firstOrFail();
        $this->assertSame(2, $project->teams()->count());

        $this->actingAs($teamHead)
            ->put(route('admin.projects.update', $project), [
                'name' => 'Platform Revamp Updated',
                'code' => 'PRJ-101',
                'description' => 'Updated milestone',
                'team_ids' => [$teamA->id],
            ])
            ->assertRedirect(route('admin.projects.index'));

        $project = $project->fresh();
        $this->assertSame('Platform Revamp Updated', $project?->name);
        $this->assertSame(1, $project?->teams()->count());

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
        $project = Project::factory()->create();
        $project->teams()->sync([$team->id]);

        $this->actingAs($staff)
            ->post(route('admin.projects.store'), [
                'name' => 'Blocked Project',
                'team_ids' => [$team->id],
            ])
            ->assertForbidden();

        $this->actingAs($staff)
            ->put(route('admin.projects.update', $project), [
                'name' => 'Blocked Update',
                'team_ids' => [$team->id],
            ])
            ->assertForbidden();

        $this->actingAs($client)
            ->delete(route('admin.projects.destroy', $project))
            ->assertForbidden();
    }

    public function test_project_requires_at_least_one_valid_team_assignment(): void
    {
        $team = Team::factory()->create();
        $teamHead = User::factory()->teamHead()->withPrimaryTeam($team)->create();

        $this->actingAs($teamHead)
            ->post(route('admin.projects.store'), [
                'name' => 'Invalid Project',
                'team_ids' => [],
            ])
            ->assertSessionHasErrors('team_ids');

        $this->actingAs($teamHead)
            ->post(route('admin.projects.store'), [
                'name' => 'Invalid Team Id',
                'team_ids' => [999999],
            ])
            ->assertSessionHasErrors('team_ids.0');
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
