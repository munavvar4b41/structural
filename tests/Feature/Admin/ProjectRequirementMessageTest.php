<?php

namespace Tests\Feature\Admin;

use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\Team;
use App\Models\User;
use App\Notifications\RequirementClarificationDiscussionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectRequirementMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_on_project_can_post_clarification_message(): void
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
            ->post(route('admin.projects.requirements.messages.store', [$project, $requirement]), [
                'body' => 'Can you clarify the export format?',
            ])
            ->assertRedirect(route('admin.projects.requirements.show', [$project, $requirement]));

        $this->assertDatabaseHas('project_requirement_messages', [
            'project_requirement_id' => $requirement->id,
            'user_id' => $staff->id,
            'body' => 'Can you clarify the export format?',
        ]);
    }

    public function test_client_on_own_project_can_post_clarification_message(): void
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
        ]);

        $this->actingAs($client)
            ->post(route('admin.projects.requirements.messages.store', [$project, $requirement]), [
                'body' => 'We need CSV with headers.',
            ])
            ->assertRedirect(route('admin.projects.requirements.show', [$project, $requirement]));

        $this->assertDatabaseHas('project_requirement_messages', [
            'project_requirement_id' => $requirement->id,
            'user_id' => $client->id,
            'body' => 'We need CSV with headers.',
        ]);
    }

    public function test_posting_clarification_message_sends_notification_to_other_requirement_stakeholders(): void
    {
        $team = Team::factory()->create();
        $staffSender = User::factory()->withPrimaryTeam($team)->create();
        $staffResponsible = User::factory()->withPrimaryTeam($team)->create();
        $staffReviewer = User::factory()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);
        $project->teams()->sync([$team->id]);
        $requirement = ProjectRequirement::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $client->id,
            'responsible_user_id' => $staffResponsible->id,
            'reviewer_user_id' => $staffReviewer->id,
        ]);

        $this->actingAs($staffSender)
            ->post(route('admin.projects.requirements.messages.store', [$project, $requirement]), [
                'body' => 'Please clarify the acceptance criteria.',
            ])
            ->assertRedirect(route('admin.projects.requirements.show', [$project, $requirement]));

        $this->assertDatabaseHas('notifications', [
            'type' => RequirementClarificationDiscussionNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $client->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'type' => RequirementClarificationDiscussionNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $staffResponsible->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'type' => RequirementClarificationDiscussionNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $staffReviewer->id,
        ]);

        $this->assertDatabaseMissing('notifications', [
            'type' => RequirementClarificationDiscussionNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $staffSender->id,
        ]);
    }

    public function test_staff_without_project_access_cannot_post_message(): void
    {
        $teamA = Team::factory()->create();
        $teamB = Team::factory()->create();
        $staff = User::factory()->withPrimaryTeam($teamA)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);
        $project->teams()->sync([$teamB->id]);
        $requirement = ProjectRequirement::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $client->id,
        ]);

        $this->actingAs($staff)
            ->post(route('admin.projects.requirements.messages.store', [$project, $requirement]), [
                'body' => 'Trying to post',
            ])
            ->assertForbidden();
    }

    public function test_store_rejects_empty_body(): void
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
            ->post(route('admin.projects.requirements.messages.store', [$project, $requirement]), [
                'body' => '',
            ])
            ->assertSessionHasErrors('body');
    }

    public function test_show_includes_chat_messages_and_post_permission(): void
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
                ->has('requirement_chat_messages')
                ->has('requirement_chat_messages.data')
                ->has('requirement_chat_messages.current_page')
                ->where('can_post_requirement_chat', true));
    }
}
