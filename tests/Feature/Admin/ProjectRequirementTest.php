<?php

namespace Tests\Feature\Admin;

use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectRequirementTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_view_requirements_index_on_assigned_project(): void
    {
        $team = Team::factory()->create();
        $staff = User::factory()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);
        $project->teams()->sync([$team->id]);

        $this->actingAs($staff)
            ->get(route('admin.projects.requirements.index', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/projects/requirements/Index')
                ->has('requirements.data'));
    }

    public function test_staff_cannot_create_requirement(): void
    {
        $team = Team::factory()->create();
        $staff = User::factory()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);
        $project->teams()->sync([$team->id]);

        $this->actingAs($staff)
            ->post(route('admin.projects.requirements.store', $project), [
                'title' => 'Need API docs',
                'description' => 'Please document endpoints',
            ])
            ->assertForbidden();
    }

    public function test_client_can_create_requirement_on_own_project(): void
    {
        $team = Team::factory()->create();
        $teamHead = User::factory()->teamHead()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);
        $project->teams()->sync([$team->id]);

        $this->actingAs($client)
            ->post(route('admin.projects.requirements.store', $project), [
                'title' => 'Need darker theme',
                'description' => 'Contrast improvements',
            ])
            ->assertRedirect(route('admin.projects.requirements.index', $project));

        $requirement = ProjectRequirement::query()->firstOrFail();
        $this->assertSame($project->id, $requirement->project_id);
        $this->assertSame($client->id, $requirement->created_by_user_id);
        $this->assertSame($teamHead->id, $requirement->responsible_user_id);
    }

    public function test_client_cannot_create_requirement_on_another_clients_project(): void
    {
        $team = Team::factory()->create();
        $clientA = User::factory()->client()->create();
        $clientB = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $clientA->id]);
        $project->teams()->sync([$team->id]);

        $this->actingAs($clientB)
            ->post(route('admin.projects.requirements.store', $project), [
                'title' => 'Unauthorized',
            ])
            ->assertForbidden();
    }

    public function test_responsible_defaults_to_project_lead_when_set(): void
    {
        $team = Team::factory()->create();
        $lead = User::factory()->teamHead()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create([
            'client_user_id' => $client->id,
            'lead_user_id' => $lead->id,
        ]);
        $project->teams()->sync([$team->id]);

        $this->actingAs($client)
            ->post(route('admin.projects.requirements.store', $project), [
                'title' => 'Lead-owned',
            ])
            ->assertRedirect(route('admin.projects.requirements.index', $project));

        $this->assertSame($lead->id, ProjectRequirement::query()->value('responsible_user_id'));
    }

    public function test_team_head_can_assign_reviewer_staff(): void
    {
        $team = Team::factory()->create();
        $staff = User::factory()->withPrimaryTeam($team)->create();
        $teamHead = User::factory()->teamHead()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);
        $project->teams()->sync([$team->id]);

        $requirement = ProjectRequirement::query()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $client->id,
            'responsible_user_id' => $teamHead->id,
            'title' => 'Review me',
        ]);

        $this->actingAs($teamHead)
            ->put(route('admin.projects.requirements.update', [$project, $requirement]), [
                'title' => 'Review me',
                'description' => null,
                'reviewer_user_id' => $staff->id,
                'responsible_user_id' => $teamHead->id,
                'reviewed_at' => null,
            ])
            ->assertRedirect(route('admin.projects.requirements.index', $project));

        $this->assertSame($staff->id, $requirement->fresh()->reviewer_user_id);
    }

    public function test_client_cannot_change_reviewer(): void
    {
        $team = Team::factory()->create();
        $staff = User::factory()->withPrimaryTeam($team)->create();
        $teamHead = User::factory()->teamHead()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);
        $project->teams()->sync([$team->id]);

        $requirement = ProjectRequirement::query()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $client->id,
            'responsible_user_id' => $teamHead->id,
            'title' => 'Client item',
        ]);

        $this->actingAs($client)
            ->put(route('admin.projects.requirements.update', [$project, $requirement]), [
                'title' => 'Client item',
                'description' => null,
                'reviewer_user_id' => $staff->id,
                'responsible_user_id' => $teamHead->id,
                'reviewed_at' => null,
            ])
            ->assertSessionHasErrors('reviewer_user_id');
    }

    public function test_staff_on_project_cannot_view_requirements_for_other_project(): void
    {
        $teamA = Team::factory()->create();
        $teamB = Team::factory()->create();
        $staff = User::factory()->withPrimaryTeam($teamA)->create();
        $client = User::factory()->client()->create();
        $visible = Project::factory()->create(['client_user_id' => $client->id]);
        $visible->teams()->sync([$teamA->id]);
        $hidden = Project::factory()->create(['client_user_id' => $client->id]);
        $hidden->teams()->sync([$teamB->id]);

        $this->actingAs($staff)
            ->get(route('admin.projects.requirements.index', $hidden))
            ->assertForbidden();
    }
}
