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

    private function tipTapJson(string $plainText): string
    {
        return (string) json_encode([
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => $plainText],
                    ],
                ],
            ],
        ]);
    }

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

    public function test_staff_can_view_requirement_show_on_assigned_project(): void
    {
        $team = Team::factory()->create();
        $staff = User::factory()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);
        $project->teams()->sync([$team->id]);
        $requirement = ProjectRequirement::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $client->id,
        ]);

        $this->actingAs($staff)
            ->get(route('admin.projects.requirements.show', [$project, $requirement]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/projects/requirements/Show')
                ->where('requirement.id', $requirement->id));
    }

    public function test_show_returns_404_when_requirement_belongs_to_other_project(): void
    {
        $team = Team::factory()->create();
        $staff = User::factory()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $projectA = Project::factory()->create(['client_user_id' => $client->id]);
        $projectA->teams()->sync([$team->id]);
        $projectB = Project::factory()->create(['client_user_id' => $client->id]);
        $projectB->teams()->sync([$team->id]);
        $requirement = ProjectRequirement::factory()->create([
            'project_id' => $projectB->id,
            'created_by_user_id' => $client->id,
        ]);

        $this->actingAs($staff)
            ->get(route('admin.projects.requirements.show', [$projectA, $requirement]))
            ->assertNotFound();
    }

    public function test_staff_on_project_cannot_view_show_for_other_project(): void
    {
        $teamA = Team::factory()->create();
        $teamB = Team::factory()->create();
        $staff = User::factory()->withPrimaryTeam($teamA)->create();
        $client = User::factory()->client()->create();
        $visible = Project::factory()->create(['client_user_id' => $client->id]);
        $visible->teams()->sync([$teamA->id]);
        $hidden = Project::factory()->create(['client_user_id' => $client->id]);
        $hidden->teams()->sync([$teamB->id]);
        $requirement = ProjectRequirement::factory()->create([
            'project_id' => $hidden->id,
            'created_by_user_id' => $client->id,
        ]);

        $this->actingAs($staff)
            ->get(route('admin.projects.requirements.show', [$hidden, $requirement]))
            ->assertForbidden();
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
                'description' => $this->tipTapJson('Please document endpoints'),
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
                'description' => $this->tipTapJson('Contrast improvements'),
            ])
            ->assertRedirect(route('admin.projects.requirements.index', $project));

        $requirement = ProjectRequirement::query()->firstOrFail();
        $this->assertSame($project->id, $requirement->project_id);
        $this->assertSame($client->id, $requirement->created_by_user_id);
        $this->assertSame($teamHead->id, $requirement->responsible_user_id);
    }

    public function test_client_can_set_responsible_user_on_create(): void
    {
        $team = Team::factory()->create();
        $teamHead = User::factory()->teamHead()->withPrimaryTeam($team)->create();
        $staff = User::factory()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);
        $project->teams()->sync([$team->id]);

        $this->actingAs($client)
            ->post(route('admin.projects.requirements.store', $project), [
                'title' => 'Pick staff',
                'description' => $this->tipTapJson('Body'),
                'responsible_user_id' => $staff->id,
            ])
            ->assertRedirect(route('admin.projects.requirements.index', $project));

        $this->assertSame($staff->id, ProjectRequirement::query()->value('responsible_user_id'));
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
                'description' => $this->tipTapJson('x'),
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
                'description' => $this->tipTapJson(''),
            ])
            ->assertRedirect(route('admin.projects.requirements.index', $project));

        $this->assertSame($lead->id, ProjectRequirement::query()->value('responsible_user_id'));
    }

    public function test_staff_project_lead_can_assign_reviewer(): void
    {
        $team = Team::factory()->create();
        $staffLead = User::factory()->withPrimaryTeam($team)->create();
        $staffReviewer = User::factory()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create([
            'client_user_id' => $client->id,
            'lead_user_id' => $staffLead->id,
        ]);
        $project->teams()->sync([$team->id]);

        $requirement = ProjectRequirement::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $client->id,
            'responsible_user_id' => $staffLead->id,
            'title' => 'Needs reviewer',
        ]);

        $this->actingAs($staffLead)
            ->put(route('admin.projects.requirements.update', [$project, $requirement]), [
                'title' => 'Needs reviewer',
                'description' => $requirement->description,
                'reviewer_user_id' => $staffReviewer->id,
                'responsible_user_id' => $staffLead->id,
                'reviewed_at' => null,
            ])
            ->assertRedirect(route('admin.projects.requirements.index', $project));

        $this->assertSame($staffReviewer->id, $requirement->fresh()->reviewer_user_id);
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
            'description' => $this->tipTapJson('Body'),
        ]);

        $this->actingAs($teamHead)
            ->put(route('admin.projects.requirements.update', [$project, $requirement]), [
                'title' => 'Review me',
                'description' => $requirement->description,
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
            'description' => $this->tipTapJson('Hi'),
        ]);

        $this->actingAs($client)
            ->put(route('admin.projects.requirements.update', [$project, $requirement]), [
                'title' => 'Client item',
                'description' => $requirement->description,
                'reviewer_user_id' => $staff->id,
                'responsible_user_id' => $teamHead->id,
                'reviewed_at' => null,
            ])
            ->assertSessionHasErrors('reviewer_user_id');
    }

    public function test_assigned_reviewer_can_set_reviewed_at(): void
    {
        $team = Team::factory()->create();
        $staff = User::factory()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);
        $project->teams()->sync([$team->id]);

        $requirement = ProjectRequirement::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $client->id,
            'reviewer_user_id' => $staff->id,
            'reviewed_at' => null,
        ]);

        $this->actingAs($staff)
            ->put(route('admin.projects.requirements.update', [$project, $requirement]), [
                'title' => $requirement->title,
                'description' => $requirement->description,
                'reviewer_user_id' => $staff->id,
                'responsible_user_id' => $requirement->responsible_user_id,
                'reviewed_at' => '2026-01-15T10:00',
            ])
            ->assertRedirect(route('admin.projects.requirements.index', $project));

        $this->assertNotNull($requirement->fresh()->reviewed_at);
    }

    public function test_team_head_cannot_set_reviewed_at_when_other_user_is_reviewer(): void
    {
        $team = Team::factory()->create();
        $staff = User::factory()->withPrimaryTeam($team)->create();
        $teamHead = User::factory()->teamHead()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);
        $project->teams()->sync([$team->id]);

        $requirement = ProjectRequirement::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $client->id,
            'reviewer_user_id' => $staff->id,
            'responsible_user_id' => $teamHead->id,
            'reviewed_at' => null,
        ]);

        $this->actingAs($teamHead)
            ->put(route('admin.projects.requirements.update', [$project, $requirement]), [
                'title' => $requirement->title,
                'description' => $requirement->description,
                'reviewer_user_id' => $staff->id,
                'responsible_user_id' => $teamHead->id,
                'reviewed_at' => '2026-01-15T10:00',
            ])
            ->assertSessionHasErrors('reviewed_at');
    }

    public function test_team_head_can_set_reviewed_at_when_no_reviewer(): void
    {
        $team = Team::factory()->create();
        $teamHead = User::factory()->teamHead()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);
        $project->teams()->sync([$team->id]);

        $requirement = ProjectRequirement::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $client->id,
            'reviewer_user_id' => null,
            'reviewed_at' => null,
        ]);

        $this->actingAs($teamHead)
            ->put(route('admin.projects.requirements.update', [$project, $requirement]), [
                'title' => $requirement->title,
                'description' => $requirement->description,
                'reviewer_user_id' => null,
                'responsible_user_id' => $requirement->responsible_user_id,
                'reviewed_at' => '2026-01-15T10:00',
            ])
            ->assertRedirect(route('admin.projects.requirements.index', $project));

        $this->assertNotNull($requirement->fresh()->reviewed_at);
    }

    public function test_admin_cannot_set_reviewed_at_when_other_user_is_reviewer(): void
    {
        $team = Team::factory()->create();
        $staff = User::factory()->withPrimaryTeam($team)->create();
        $admin = User::factory()->admin()->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);
        $project->teams()->sync([$team->id]);

        $requirement = ProjectRequirement::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $client->id,
            'reviewer_user_id' => $staff->id,
            'reviewed_at' => null,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.projects.requirements.update', [$project, $requirement]), [
                'title' => $requirement->title,
                'description' => $requirement->description,
                'reviewer_user_id' => $staff->id,
                'responsible_user_id' => $requirement->responsible_user_id,
                'reviewed_at' => '2026-01-15T10:00',
            ])
            ->assertSessionHasErrors('reviewed_at');
    }

    public function test_client_cannot_set_reviewed_at_even_without_reviewer(): void
    {
        $team = Team::factory()->create();
        $teamHead = User::factory()->teamHead()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);
        $project->teams()->sync([$team->id]);

        $requirement = ProjectRequirement::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $client->id,
            'responsible_user_id' => $teamHead->id,
            'reviewer_user_id' => null,
            'reviewed_at' => null,
        ]);

        $this->actingAs($client)
            ->put(route('admin.projects.requirements.update', [$project, $requirement]), [
                'title' => $requirement->title,
                'description' => $requirement->description,
                'reviewer_user_id' => null,
                'responsible_user_id' => $teamHead->id,
                'reviewed_at' => '2026-01-15T10:00',
            ])
            ->assertSessionHasErrors('reviewed_at');
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
