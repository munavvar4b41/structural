<?php

namespace Tests\Feature\Admin;

use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\ProjectTask;
use App\Models\Team;
use App\Models\User;
use App\Notifications\RequirementAssignedNotification;
use App\Notifications\RequirementReviewUnderstandingSubmittedNotification;
use App\Notifications\RequirementUpdatedNotification;
use Carbon\Carbon;
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
                ->where('requirement.id', $requirement->id)
                ->has('requirement_chat_messages.data')
                ->has('can_post_requirement_chat')
                ->where('can_create_tasks', true));
    }

    public function test_client_on_requirement_show_cannot_create_tasks(): void
    {
        $team = Team::factory()->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);
        $project->teams()->sync([$team->id]);
        $requirement = ProjectRequirement::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $client->id,
        ]);

        $this->actingAs($client)
            ->get(route('admin.projects.requirements.show', [$project, $requirement]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can_create_tasks', false));
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
                'max_generated_phase' => 1,
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
                'max_generated_phase' => 1,
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
                'max_generated_phase' => 1,
            ])
            ->assertRedirect(route('admin.projects.requirements.index', $project));

        $this->assertSame($staff->id, ProjectRequirement::query()->value('responsible_user_id'));
    }

    public function test_create_sends_assignment_notification_to_responsible_user(): void
    {
        $team = Team::factory()->create();
        $teamHead = User::factory()->teamHead()->withPrimaryTeam($team)->create();
        $staff = User::factory()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);
        $project->teams()->sync([$team->id]);

        $this->actingAs($client)
            ->post(route('admin.projects.requirements.store', $project), [
                'title' => 'Notify responsible',
                'description' => $this->tipTapJson('Body'),
                'responsible_user_id' => $staff->id,
                'max_generated_phase' => 1,
            ])
            ->assertRedirect(route('admin.projects.requirements.index', $project));

        $this->assertDatabaseHas('notifications', [
            'type' => RequirementAssignedNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $staff->id,
        ]);

        $this->assertDatabaseMissing('notifications', [
            'type' => RequirementAssignedNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $teamHead->id,
        ]);
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
                'max_generated_phase' => 1,
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
                'max_generated_phase' => 1,
            ])
            ->assertRedirect(route('admin.projects.requirements.index', $project));

        $this->assertSame($lead->id, ProjectRequirement::query()->value('responsible_user_id'));
    }

    public function test_team_head_can_assign_reviewer_when_project_lead_is_staff(): void
    {
        $team = Team::factory()->create();
        $staffLead = User::factory()->withPrimaryTeam($team)->create();
        $staffReviewer = User::factory()->withPrimaryTeam($team)->create();
        $teamHead = User::factory()->teamHead()->withPrimaryTeam($team)->create();
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

        $this->actingAs($teamHead)
            ->put(route('admin.projects.requirements.update', [$project, $requirement]), [
                'title' => 'Needs reviewer',
                'description' => $requirement->description,
                'reviewer_user_id' => $staffReviewer->id,
                'responsible_user_id' => $staffLead->id,
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
            ])
            ->assertSessionHasErrors('reviewer_user_id');
    }

    public function test_assigned_reviewer_can_submit_understanding_and_sets_reviewed_at(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-15 10:00:00', 'UTC'));

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

        $understanding = $this->tipTapJson('We will ship CSV export first.');

        $this->actingAs($staff)
            ->patch(route('admin.projects.requirements.review', [$project, $requirement]), [
                'review_understanding' => $understanding,
            ])
            ->assertRedirect(route('admin.projects.requirements.show', [$project, $requirement]));

        $fresh = $requirement->fresh();
        $this->assertNotNull($fresh->reviewed_at);
        $this->assertTrue($fresh->reviewed_at->equalTo(Carbon::parse('2026-01-15 10:00:00', 'UTC')));
        $this->assertSame($understanding, $fresh->review_understanding);

        Carbon::setTestNow(null);
    }

    public function test_mark_reviewed_sends_review_understanding_submission_notification_to_other_stakeholders(): void
    {
        $team = Team::factory()->create();
        $reviewer = User::factory()->withPrimaryTeam($team)->create();
        $responsible = User::factory()->teamHead()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);
        $project->teams()->sync([$team->id]);

        $requirement = ProjectRequirement::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $client->id,
            'responsible_user_id' => $responsible->id,
            'reviewer_user_id' => $reviewer->id,
            'reviewed_at' => null,
        ]);

        $this->actingAs($reviewer)
            ->patch(route('admin.projects.requirements.review', [$project, $requirement]), [
                'review_understanding' => $this->tipTapJson('Submitted understanding for review.'),
            ])
            ->assertRedirect(route('admin.projects.requirements.show', [$project, $requirement]));

        $this->assertDatabaseHas('notifications', [
            'type' => RequirementReviewUnderstandingSubmittedNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $client->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'type' => RequirementReviewUnderstandingSubmittedNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $responsible->id,
        ]);

        $this->assertDatabaseMissing('notifications', [
            'type' => RequirementReviewUnderstandingSubmittedNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $reviewer->id,
        ]);
    }

    public function test_staff_cannot_access_requirement_edit(): void
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
        ]);

        $this->actingAs($staff)
            ->get(route('admin.projects.requirements.edit', [$project, $requirement]))
            ->assertForbidden();
    }

    public function test_staff_cannot_update_requirement_via_full_form(): void
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
        ]);

        $this->actingAs($staff)
            ->put(route('admin.projects.requirements.update', [$project, $requirement]), [
                'title' => 'Changed title',
                'description' => $requirement->description,
                'reviewer_user_id' => $staff->id,
                'responsible_user_id' => $requirement->responsible_user_id,
            ])
            ->assertForbidden();
    }

    public function test_staff_not_assigned_as_reviewer_cannot_patch_review(): void
    {
        $team = Team::factory()->create();
        $staffReviewer = User::factory()->withPrimaryTeam($team)->create();
        $staffOther = User::factory()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);
        $project->teams()->sync([$team->id]);
        $requirement = ProjectRequirement::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $client->id,
            'reviewer_user_id' => $staffReviewer->id,
        ]);

        $this->actingAs($staffOther)
            ->patch(route('admin.projects.requirements.review', [$project, $requirement]), [
                'review_understanding' => $this->tipTapJson('Not my review'),
            ])
            ->assertForbidden();
    }

    public function test_team_head_can_patch_review_when_no_reviewer_assigned(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-01 14:00:00', 'UTC'));

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
            ->patch(route('admin.projects.requirements.review', [$project, $requirement]), [
                'review_understanding' => $this->tipTapJson('Team interpretation as lead.'),
            ])
            ->assertRedirect(route('admin.projects.requirements.show', [$project, $requirement]));

        $fresh = $requirement->fresh();
        $this->assertNotNull($fresh->reviewed_at);
        $this->assertTrue($fresh->reviewed_at->equalTo(Carbon::parse('2026-02-01 14:00:00', 'UTC')));

        Carbon::setTestNow(null);
    }

    public function test_team_head_cannot_patch_review_when_staff_is_assigned_reviewer(): void
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
            ->patch(route('admin.projects.requirements.review', [$project, $requirement]), [
                'review_understanding' => $this->tipTapJson('Trying as head'),
            ])
            ->assertForbidden();
    }

    public function test_team_head_can_update_requirement_without_touching_review_via_update_route(): void
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
            ])
            ->assertRedirect(route('admin.projects.requirements.index', $project));

        $this->assertNull($requirement->fresh()->reviewed_at);
    }

    public function test_update_without_assignment_change_sends_requirement_updated_notification(): void
    {
        $team = Team::factory()->create();
        $teamHead = User::factory()->teamHead()->withPrimaryTeam($team)->create();
        $staffReviewer = User::factory()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);
        $project->teams()->sync([$team->id]);

        $requirement = ProjectRequirement::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $client->id,
            'responsible_user_id' => $teamHead->id,
            'reviewer_user_id' => $staffReviewer->id,
            'title' => 'Before',
        ]);

        $this->actingAs($teamHead)
            ->put(route('admin.projects.requirements.update', [$project, $requirement]), [
                'title' => 'After',
                'description' => $requirement->description,
                'reviewer_user_id' => $staffReviewer->id,
                'responsible_user_id' => $teamHead->id,
            ])
            ->assertRedirect(route('admin.projects.requirements.index', $project));

        $this->assertDatabaseHas('notifications', [
            'type' => RequirementUpdatedNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $staffReviewer->id,
        ]);

        $this->assertDatabaseMissing('notifications', [
            'type' => RequirementUpdatedNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $teamHead->id,
        ]);
    }

    public function test_update_with_reviewer_change_sends_assignment_notification_to_changed_reviewer_only(): void
    {
        $team = Team::factory()->create();
        $teamHead = User::factory()->teamHead()->withPrimaryTeam($team)->create();
        $oldReviewer = User::factory()->withPrimaryTeam($team)->create();
        $newReviewer = User::factory()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);
        $project->teams()->sync([$team->id]);

        $requirement = ProjectRequirement::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $client->id,
            'responsible_user_id' => $teamHead->id,
            'reviewer_user_id' => $oldReviewer->id,
            'title' => 'Needs reviewer change',
        ]);

        $this->actingAs($teamHead)
            ->put(route('admin.projects.requirements.update', [$project, $requirement]), [
                'title' => $requirement->title,
                'description' => $requirement->description,
                'reviewer_user_id' => $newReviewer->id,
                'responsible_user_id' => $teamHead->id,
            ])
            ->assertRedirect(route('admin.projects.requirements.index', $project));

        $this->assertDatabaseHas('notifications', [
            'type' => RequirementAssignedNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $newReviewer->id,
        ]);

        $this->assertDatabaseMissing('notifications', [
            'type' => RequirementAssignedNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $oldReviewer->id,
        ]);

        $this->assertDatabaseMissing('notifications', [
            'type' => RequirementAssignedNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $teamHead->id,
        ]);
    }

    public function test_admin_cannot_patch_review_when_staff_is_assigned_reviewer(): void
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
            ->patch(route('admin.projects.requirements.review', [$project, $requirement]), [
                'review_understanding' => $this->tipTapJson('Admin tries'),
            ])
            ->assertForbidden();
    }

    public function test_client_cannot_patch_review_even_without_reviewer(): void
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
            ->patch(route('admin.projects.requirements.review', [$project, $requirement]), [
                'review_understanding' => $this->tipTapJson('Client tries'),
            ])
            ->assertForbidden();
    }

    public function test_review_route_rejects_empty_tiptap_understanding(): void
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
        ]);

        $emptyDoc = (string) json_encode([
            'type' => 'doc',
            'content' => [],
        ]);

        $this->actingAs($staff)
            ->patch(route('admin.projects.requirements.review', [$project, $requirement]), [
                'review_understanding' => $emptyDoc,
            ])
            ->assertSessionHasErrors('review_understanding');
    }

    public function test_creator_can_confirm_understanding_after_review(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-10 08:00:00', 'UTC'));

        $team = Team::factory()->create();
        $staff = User::factory()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);
        $project->teams()->sync([$team->id]);

        $requirement = ProjectRequirement::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $client->id,
            'reviewer_user_id' => $staff->id,
        ]);

        $this->actingAs($staff)
            ->patch(route('admin.projects.requirements.review', [$project, $requirement]), [
                'review_understanding' => $this->tipTapJson('Reviewer notes'),
            ])
            ->assertRedirect(route('admin.projects.requirements.show', [$project, $requirement]));

        $this->actingAs($client)
            ->patch(route('admin.projects.requirements.confirm-understanding', [$project, $requirement]))
            ->assertRedirect(route('admin.projects.requirements.show', [$project, $requirement]));

        $fresh = $requirement->fresh();
        $this->assertNotNull($fresh->understanding_confirmed_at);
        $this->assertTrue($fresh->understanding_confirmed_at->equalTo(Carbon::parse('2026-03-10 08:00:00', 'UTC')));
        $this->assertSame($client->id, $fresh->understanding_confirmed_by_user_id);

        Carbon::setTestNow(null);
    }

    public function test_responsible_cannot_confirm_understanding(): void
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
            'responsible_user_id' => $teamHead->id,
            'reviewer_user_id' => $staff->id,
        ]);

        $this->actingAs($staff)
            ->patch(route('admin.projects.requirements.review', [$project, $requirement]), [
                'review_understanding' => $this->tipTapJson('Scope is clear'),
            ])
            ->assertRedirect(route('admin.projects.requirements.show', [$project, $requirement]));

        $this->actingAs($teamHead)
            ->patch(route('admin.projects.requirements.confirm-understanding', [$project, $requirement]))
            ->assertForbidden();

        $this->assertNull($requirement->fresh()->understanding_confirmed_at);
    }

    public function test_staff_who_is_not_owner_cannot_confirm_understanding(): void
    {
        $team = Team::factory()->create();
        $staffReviewer = User::factory()->withPrimaryTeam($team)->create();
        $staffOther = User::factory()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);
        $project->teams()->sync([$team->id]);

        $requirement = ProjectRequirement::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $client->id,
            'reviewer_user_id' => $staffReviewer->id,
        ]);

        $this->actingAs($staffReviewer)
            ->patch(route('admin.projects.requirements.review', [$project, $requirement]), [
                'review_understanding' => $this->tipTapJson('Done'),
            ])
            ->assertRedirect(route('admin.projects.requirements.show', [$project, $requirement]));

        $this->actingAs($staffOther)
            ->patch(route('admin.projects.requirements.confirm-understanding', [$project, $requirement]))
            ->assertForbidden();
    }

    public function test_cannot_confirm_before_review_understanding_exists(): void
    {
        $team = Team::factory()->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);
        $project->teams()->sync([$team->id]);

        $requirement = ProjectRequirement::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $client->id,
            'review_understanding' => null,
            'reviewed_at' => null,
        ]);

        $this->actingAs($client)
            ->patch(route('admin.projects.requirements.confirm-understanding', [$project, $requirement]))
            ->assertForbidden();
    }

    public function test_resubmit_review_clears_prior_confirmation(): void
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
        ]);

        $this->actingAs($staff)
            ->patch(route('admin.projects.requirements.review', [$project, $requirement]), [
                'review_understanding' => $this->tipTapJson('First pass'),
            ])
            ->assertRedirect(route('admin.projects.requirements.show', [$project, $requirement]));

        $this->actingAs($client)
            ->patch(route('admin.projects.requirements.confirm-understanding', [$project, $requirement]))
            ->assertRedirect(route('admin.projects.requirements.show', [$project, $requirement]));

        $this->assertNotNull($requirement->fresh()->understanding_confirmed_at);

        $this->actingAs($staff)
            ->patch(route('admin.projects.requirements.review', [$project, $requirement]), [
                'review_understanding' => $this->tipTapJson('Updated interpretation'),
            ])
            ->assertRedirect(route('admin.projects.requirements.show', [$project, $requirement]));

        $fresh = $requirement->fresh();
        $this->assertNull($fresh->understanding_confirmed_at);
        $this->assertNull($fresh->understanding_confirmed_by_user_id);
    }

    public function test_cannot_confirm_understanding_twice(): void
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
        ]);

        $this->actingAs($staff)
            ->patch(route('admin.projects.requirements.review', [$project, $requirement]), [
                'review_understanding' => $this->tipTapJson('Once'),
            ])
            ->assertRedirect(route('admin.projects.requirements.show', [$project, $requirement]));

        $this->actingAs($client)
            ->patch(route('admin.projects.requirements.confirm-understanding', [$project, $requirement]))
            ->assertRedirect(route('admin.projects.requirements.show', [$project, $requirement]));

        $this->actingAs($client)
            ->patch(route('admin.projects.requirements.confirm-understanding', [$project, $requirement]))
            ->assertForbidden();
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

    public function test_requirements_index_filters_by_status_search_and_responsible(): void
    {
        $team = Team::factory()->create();
        $staff = User::factory()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);
        $project->teams()->sync([$team->id]);

        $pending = ProjectRequirement::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $client->id,
            'title' => 'Zebra pending item',
            'responsible_user_id' => $staff->id,
            'reviewed_at' => null,
        ]);

        ProjectRequirement::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $client->id,
            'title' => 'Other noise',
            'responsible_user_id' => $staff->id,
            'reviewed_at' => now(),
            'understanding_confirmed_at' => null,
        ]);

        $this->actingAs($staff)
            ->get(route('admin.projects.requirements.index', [
                'project' => $project,
                'review_status' => 'pending_review',
                'search' => 'Zebra',
                'responsible_user_id' => $staff->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('requirements.data', 1)
                ->where('requirements.data.0.id', $pending->id));
    }

    public function test_client_can_store_requirement_with_custom_max_phases(): void
    {
        $team = Team::factory()->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);
        $project->teams()->sync([$team->id]);

        $this->actingAs($client)
            ->post(route('admin.projects.requirements.store', $project), [
                'title' => 'Multi-phase scope',
                'description' => $this->tipTapJson('Phased delivery'),
                'max_generated_phase' => 3,
            ])
            ->assertRedirect(route('admin.projects.requirements.index', $project));

        $this->assertSame(3, ProjectRequirement::query()->value('max_generated_phase'));
    }

    public function test_requirement_show_includes_phase_settings(): void
    {
        $team = Team::factory()->create();
        $staff = User::factory()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);
        $project->teams()->sync([$team->id]);
        $requirement = ProjectRequirement::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $client->id,
            'max_generated_phase' => 2,
        ]);

        $this->actingAs($staff)
            ->get(route('admin.projects.requirements.show', [$project, $requirement]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('phase_settings.max_generated_phase', 2)
                ->where('phase_settings.requires_phase_selection', true)
                ->has('phase_settings.phase_options', 2));
    }

    public function test_client_can_update_phase_settings(): void
    {
        $team = Team::factory()->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);
        $project->teams()->sync([$team->id]);
        $requirement = ProjectRequirement::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $client->id,
            'max_generated_phase' => 1,
        ]);

        $this->actingAs($client)
            ->from(route('admin.projects.requirements.show', [$project, $requirement]))
            ->patch(route('admin.projects.requirements.phase-settings', [$project, $requirement]), [
                'max_generated_phase' => 4,
            ])
            ->assertRedirect(route('admin.projects.requirements.show', [$project, $requirement]));

        $this->assertSame(4, $requirement->fresh()->max_generated_phase);
    }

    public function test_cannot_shrink_max_phases_below_highest_used(): void
    {
        $team = Team::factory()->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);
        $project->teams()->sync([$team->id]);
        $requirement = ProjectRequirement::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $client->id,
            'max_generated_phase' => 3,
        ]);

        ProjectTask::factory()
            ->forProject($project)
            ->create([
                'project_requirement_id' => $requirement->id,
                'created_by_user_id' => $client->id,
                'phase' => 2,
            ]);

        $this->actingAs($client)
            ->from(route('admin.projects.requirements.show', [$project, $requirement]))
            ->patch(route('admin.projects.requirements.phase-settings', [$project, $requirement]), [
                'max_generated_phase' => 1,
            ])
            ->assertSessionHasErrors('max_generated_phase');
    }
}
