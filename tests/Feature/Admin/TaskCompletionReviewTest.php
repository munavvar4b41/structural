<?php

namespace Tests\Feature\Admin;

use App\Enums\ProjectTaskStatus;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskReview;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TaskCompletionReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignee_can_submit_task_for_completion(): void
    {
        $team = Team::factory()->create();
        $head = User::factory()->teamHead()->withPrimaryTeam($team)->create();
        $staff = User::factory()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);
        $project->teams()->sync([$team->id]);

        $task = ProjectTask::factory()
            ->forProject($project)
            ->create([
                'created_by_user_id' => $head->id,
                'assignee_user_id' => $staff->id,
                'status' => ProjectTaskStatus::InProgress,
            ]);

        $this->actingAs($staff)
            ->post(route('admin.projects.tasks.submit-completion', [$project, $task]))
            ->assertRedirect();

        $task->refresh();
        $this->assertSame(ProjectTaskStatus::Review, $task->status);
        $this->assertNotNull($task->completion_submitted_at);
        $this->assertSame($staff->id, $task->completion_submitted_by_user_id);
    }

    public function test_team_head_can_confirm_completion_and_create_review(): void
    {
        $team = Team::factory()->create();
        $head = User::factory()->teamHead()->withPrimaryTeam($team)->create();
        $staff = User::factory()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);
        $project->teams()->sync([$team->id]);

        $task = ProjectTask::factory()
            ->forProject($project)
            ->create([
                'created_by_user_id' => $head->id,
                'assignee_user_id' => $staff->id,
                'status' => ProjectTaskStatus::Review,
                'completion_submitted_at' => now(),
                'completion_submitted_by_user_id' => $staff->id,
            ]);

        $this->actingAs($head)
            ->post(route('admin.projects.tasks.confirm-completion', [$project, $task]), [
                'review_notes' => 'Great work.',
                'task_rating' => 4,
                'assignee_rating' => 5,
                'creator_rating' => 3,
            ])
            ->assertRedirect();

        $task->refresh();
        $this->assertSame(ProjectTaskStatus::Done, $task->status);

        $this->assertDatabaseHas('project_task_reviews', [
            'project_task_id' => $task->id,
            'reviewer_user_id' => $head->id,
            'task_rating' => 4,
            'assignee_rating' => 5,
            'creator_rating' => 3,
        ]);
    }

    public function test_staff_assignee_cannot_confirm_completion(): void
    {
        $team = Team::factory()->create();
        $head = User::factory()->teamHead()->withPrimaryTeam($team)->create();
        $staff = User::factory()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);
        $project->teams()->sync([$team->id]);

        $task = ProjectTask::factory()
            ->forProject($project)
            ->create([
                'created_by_user_id' => $head->id,
                'assignee_user_id' => $staff->id,
                'status' => ProjectTaskStatus::Review,
                'completion_submitted_at' => now(),
                'completion_submitted_by_user_id' => $staff->id,
            ]);

        $otherStaff = User::factory()->withPrimaryTeam($team)->create();

        $this->actingAs($otherStaff)
            ->post(route('admin.projects.tasks.confirm-completion', [$project, $task]), [
                'task_rating' => 4,
                'assignee_rating' => 5,
                'creator_rating' => 3,
            ])
            ->assertForbidden();

        $this->assertSame(0, ProjectTaskReview::query()->count());
    }

    public function test_submitter_cannot_confirm_own_submission(): void
    {
        $team = Team::factory()->create();
        $head = User::factory()->teamHead()->withPrimaryTeam($team)->create();
        $staff = User::factory()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create([
            'client_user_id' => $client->id,
            'lead_user_id' => $head->id,
        ]);
        $project->teams()->sync([$team->id]);

        $task = ProjectTask::factory()
            ->forProject($project)
            ->create([
                'created_by_user_id' => $head->id,
                'assignee_user_id' => $head->id,
                'status' => ProjectTaskStatus::Review,
                'completion_submitted_at' => now(),
                'completion_submitted_by_user_id' => $head->id,
            ]);

        $this->actingAs($head)
            ->post(route('admin.projects.tasks.confirm-completion', [$project, $task]), [
                'task_rating' => 4,
                'assignee_rating' => 5,
                'creator_rating' => 3,
            ])
            ->assertForbidden();
    }

    public function test_project_lead_can_confirm_on_led_project(): void
    {
        $team = Team::factory()->create();
        $lead = User::factory()->withPrimaryTeam($team)->create();
        $staff = User::factory()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create([
            'client_user_id' => $client->id,
            'lead_user_id' => $lead->id,
        ]);
        $project->teams()->sync([$team->id]);

        $task = ProjectTask::factory()
            ->forProject($project)
            ->create([
                'created_by_user_id' => $lead->id,
                'assignee_user_id' => $staff->id,
                'status' => ProjectTaskStatus::Review,
                'completion_submitted_at' => now(),
                'completion_submitted_by_user_id' => $staff->id,
            ]);

        $this->actingAs($lead)
            ->post(route('admin.projects.tasks.confirm-completion', [$project, $task]), [
                'task_rating' => 5,
                'assignee_rating' => 4,
                'creator_rating' => 4,
            ])
            ->assertRedirect();

        $this->assertSame(ProjectTaskStatus::Done, $task->fresh()->status);
    }

    public function test_task_reviews_queue_requires_reviewer(): void
    {
        $team = Team::factory()->create();
        $head = User::factory()->teamHead()->withPrimaryTeam($team)->create();
        $staff = User::factory()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);
        $project->teams()->sync([$team->id]);

        $this->actingAs($staff)
            ->get(route('admin.task-reviews.index'))
            ->assertForbidden();

        $this->actingAs($head)
            ->get(route('admin.task-reviews.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/task-reviews/Index')
                ->has('tasks'));
    }

    public function test_task_ratings_report_forbidden_for_plain_staff(): void
    {
        $team = Team::factory()->create();
        $staff = User::factory()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);
        $project->teams()->sync([$team->id]);

        $this->actingAs($staff)
            ->get(route('admin.task-ratings-report.index'))
            ->assertForbidden();
    }

    public function test_task_ratings_report_ok_for_team_head(): void
    {
        $team = Team::factory()->create();
        $head = User::factory()->teamHead()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);
        $project->teams()->sync([$team->id]);

        $this->actingAs($head)
            ->get(route('admin.task-ratings-report.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/task-ratings-report/Index')
                ->has('rows')
                ->has('recent_reviews'));
    }
}
