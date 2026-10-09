<?php

namespace Tests\Feature;

use App\Enums\ProjectTaskStatus;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\TaskPriority;
use App\Models\Team;
use App\Models\User;
use App\Support\DashboardTasksBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_dashboard_task_settings(): void
    {
        $this->get(route('dashboard-tasks.edit'))
            ->assertRedirect(route('login'));
    }

    public function test_dashboard_shows_assigned_tasks_and_hides_other_users_tasks(): void
    {
        ['head' => $head, 'staff' => $staff, 'project' => $project] = $this->staffProject();
        $other = User::factory()->withPrimaryTeam($staff->primaryTeam)->create();

        $task = ProjectTask::factory()->forProject($project)->create([
            'created_by_user_id' => $head->id,
            'assignee_user_id' => $staff->id,
            'status' => ProjectTaskStatus::InProgress,
            'title' => 'My assigned item',
        ]);

        ProjectTask::factory()->forProject($project)->create([
            'created_by_user_id' => $head->id,
            'assignee_user_id' => $other->id,
            'status' => ProjectTaskStatus::InProgress,
            'title' => 'Someone elses item',
        ]);

        $this->actingAs($staff)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('dashboard_tasks.enabled', true)
                ->has('dashboard_tasks.tasks', 1)
                ->where('dashboard_tasks.tasks.0.title', 'My assigned item')
                ->where('dashboard_tasks.tasks.0.status_label', 'In progress')
                ->where(
                    'dashboard_tasks.tasks.0.task_show_url',
                    route('admin.projects.tasks.show', [$project, $task]),
                ));
    }

    public function test_empty_filters_show_every_eligible_assigned_task(): void
    {
        ['head' => $head, 'staff' => $staff, 'project' => $project] = $this->staffProject();
        $otherProject = Project::factory()->create();
        $otherProject->teams()->sync([$staff->primary_team_id]);

        ProjectTask::factory()->forProject($project)->create([
            'created_by_user_id' => $head->id,
            'assignee_user_id' => $staff->id,
            'status' => ProjectTaskStatus::ToDo,
            'title' => 'First task',
        ]);
        ProjectTask::factory()->forProject($otherProject)->create([
            'created_by_user_id' => $head->id,
            'assignee_user_id' => $staff->id,
            'status' => ProjectTaskStatus::Review,
            'title' => 'Second task',
        ]);

        $this->actingAs($staff)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard_tasks.enabled', true)
                ->has('dashboard_tasks.tasks', 2));
    }

    public function test_status_priority_and_project_filters_narrow_dashboard_tasks(): void
    {
        ['head' => $head, 'staff' => $staff, 'project' => $project] = $this->staffProject();
        $otherProject = Project::factory()->create();
        $otherProject->teams()->sync([$staff->primary_team_id]);
        $priority = TaskPriority::factory()->create(['name' => 'Urgent']);

        ProjectTask::factory()->forProject($project)->create([
            'created_by_user_id' => $head->id,
            'assignee_user_id' => $staff->id,
            'status' => ProjectTaskStatus::InProgress,
            'task_priority_id' => $priority->id,
            'title' => 'Matching task',
        ]);
        ProjectTask::factory()->forProject($project)->create([
            'created_by_user_id' => $head->id,
            'assignee_user_id' => $staff->id,
            'status' => ProjectTaskStatus::ToDo,
            'task_priority_id' => $priority->id,
            'title' => 'Wrong status',
        ]);
        ProjectTask::factory()->forProject($project)->create([
            'created_by_user_id' => $head->id,
            'assignee_user_id' => $staff->id,
            'status' => ProjectTaskStatus::InProgress,
            'task_priority_id' => null,
            'title' => 'No priority',
        ]);
        ProjectTask::factory()->forProject($otherProject)->create([
            'created_by_user_id' => $head->id,
            'assignee_user_id' => $staff->id,
            'status' => ProjectTaskStatus::InProgress,
            'task_priority_id' => $priority->id,
            'title' => 'Other project',
        ]);

        $staff->dashboard_task_preferences = [
            'enabled' => true,
            'statuses' => [ProjectTaskStatus::InProgress->value],
            'priorities' => [(string) $priority->id],
            'project_ids' => [$project->id],
        ];
        $staff->save();

        $this->actingAs($staff)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('dashboard_tasks.tasks', 1)
                ->where('dashboard_tasks.tasks.0.title', 'Matching task'));
    }

    public function test_no_priority_filter_shows_tasks_without_a_priority(): void
    {
        ['head' => $head, 'staff' => $staff, 'project' => $project] = $this->staffProject();
        $priority = TaskPriority::factory()->create();

        ProjectTask::factory()->forProject($project)->create([
            'created_by_user_id' => $head->id,
            'assignee_user_id' => $staff->id,
            'status' => ProjectTaskStatus::ToDo,
            'task_priority_id' => null,
            'title' => 'Unprioritized',
        ]);
        ProjectTask::factory()->forProject($project)->create([
            'created_by_user_id' => $head->id,
            'assignee_user_id' => $staff->id,
            'status' => ProjectTaskStatus::ToDo,
            'task_priority_id' => $priority->id,
            'title' => 'Prioritized',
        ]);

        $staff->dashboard_task_preferences = [
            'enabled' => true,
            'statuses' => [],
            'priorities' => [TaskPriority::NONE_FILTER],
            'project_ids' => [],
        ];
        $staff->save();

        $this->actingAs($staff)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('dashboard_tasks.tasks', 1)
                ->where('dashboard_tasks.tasks.0.title', 'Unprioritized'));
    }

    public function test_turning_dashboard_tasks_off_hides_them_for_that_user_only(): void
    {
        ['head' => $head, 'staff' => $staff, 'project' => $project] = $this->staffProject();
        $colleague = User::factory()->withPrimaryTeam($staff->primaryTeam)->create();

        ProjectTask::factory()->forProject($project)->create([
            'created_by_user_id' => $head->id,
            'assignee_user_id' => $staff->id,
            'status' => ProjectTaskStatus::ToDo,
            'title' => 'Staff task',
        ]);
        ProjectTask::factory()->forProject($project)->create([
            'created_by_user_id' => $head->id,
            'assignee_user_id' => $colleague->id,
            'status' => ProjectTaskStatus::ToDo,
            'title' => 'Colleague task',
        ]);

        $this->actingAs($staff)
            ->patch(route('dashboard-tasks.update'), [
                'enabled' => false,
                'statuses' => [ProjectTaskStatus::ToDo->value],
                'priorities' => [],
                'project_ids' => [$project->id],
            ])
            ->assertRedirect(route('dashboard-tasks.edit'));

        $staff->refresh();
        $this->assertFalse($staff->dashboard_task_preferences['enabled']);
        $this->assertSame([ProjectTaskStatus::ToDo->value], $staff->dashboard_task_preferences['statuses']);

        $this->actingAs($staff)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard_tasks.enabled', false)
                ->has('dashboard_tasks.tasks', 0));

        $this->actingAs($colleague)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard_tasks.enabled', true)
                ->has('dashboard_tasks.tasks', 1)
                ->where('dashboard_tasks.tasks.0.title', 'Colleague task'));
    }

    public function test_a_project_the_user_cannot_see_is_not_saved(): void
    {
        ['head' => $head, 'staff' => $staff, 'project' => $project] = $this->staffProject();
        $hiddenTeam = Team::factory()->create();
        $hiddenProject = Project::factory()->create();
        $hiddenProject->teams()->sync([$hiddenTeam->id]);

        ProjectTask::factory()->forProject($project)->create([
            'created_by_user_id' => $head->id,
            'assignee_user_id' => $staff->id,
            'status' => ProjectTaskStatus::ToDo,
            'title' => 'Visible task',
        ]);
        ProjectTask::factory()->forProject($hiddenProject)->create([
            'created_by_user_id' => $head->id,
            'assignee_user_id' => $staff->id,
            'status' => ProjectTaskStatus::ToDo,
            'title' => 'Hidden project task',
        ]);

        $this->actingAs($staff)
            ->patch(route('dashboard-tasks.update'), [
                'enabled' => true,
                'statuses' => [],
                'priorities' => [],
                'project_ids' => [$project->id, $hiddenProject->id],
            ])
            ->assertRedirect(route('dashboard-tasks.edit'));

        $staff->refresh();
        $this->assertSame([$project->id], $staff->dashboard_task_preferences['project_ids']);

        $this->actingAs($staff)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('dashboard_tasks.tasks', 1)
                ->where('dashboard_tasks.tasks.0.title', 'Visible task'));
    }

    public function test_future_and_child_tasks_stay_off_the_dashboard(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-27 12:00:00'));

        ['head' => $head, 'staff' => $staff, 'project' => $project] = $this->staffProject();

        $parent = ProjectTask::factory()->forProject($project)->create([
            'created_by_user_id' => $head->id,
            'assignee_user_id' => $head->id,
            'status' => ProjectTaskStatus::ToDo,
            'title' => 'Parent for someone else',
        ]);

        ProjectTask::factory()->forProject($project)->childOf($parent)->create([
            'created_by_user_id' => $head->id,
            'assignee_user_id' => $staff->id,
            'status' => ProjectTaskStatus::ToDo,
            'title' => 'Child task',
        ]);
        ProjectTask::factory()->forProject($project)->create([
            'created_by_user_id' => $head->id,
            'assignee_user_id' => $staff->id,
            'status' => ProjectTaskStatus::ToDo,
            'title' => 'Scheduled later',
            'display_after_at' => now()->addHour(),
        ]);
        ProjectTask::factory()->forProject($project)->create([
            'created_by_user_id' => $head->id,
            'assignee_user_id' => $staff->id,
            'status' => ProjectTaskStatus::ToDo,
            'title' => 'Ready now',
            'display_after_at' => now()->subMinute(),
        ]);

        $this->actingAs($staff)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('dashboard_tasks.tasks', 1)
                ->where('dashboard_tasks.tasks.0.title', 'Ready now'));

        Carbon::setTestNow();
    }

    public function test_dashboard_lists_at_most_twenty_tasks_and_reports_the_rest(): void
    {
        ['head' => $head, 'staff' => $staff, 'project' => $project] = $this->staffProject();

        ProjectTask::factory()->forProject($project)->count(DashboardTasksBuilder::LIMIT + 1)->create([
            'created_by_user_id' => $head->id,
            'assignee_user_id' => $staff->id,
            'status' => ProjectTaskStatus::ToDo,
        ]);

        $this->actingAs($staff)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('dashboard_tasks.tasks', DashboardTasksBuilder::LIMIT)
                ->where('dashboard_tasks.total', DashboardTasksBuilder::LIMIT + 1)
                ->where('dashboard_tasks.has_more', true));
    }

    public function test_invalid_status_is_rejected(): void
    {
        ['staff' => $staff] = $this->staffProject();

        $this->actingAs($staff)
            ->from(route('dashboard-tasks.edit'))
            ->patch(route('dashboard-tasks.update'), [
                'enabled' => true,
                'statuses' => ['not-a-status'],
            ])
            ->assertRedirect(route('dashboard-tasks.edit'))
            ->assertSessionHasErrors('statuses.0');
    }

    /**
     * @return array{head: User, staff: User, project: Project}
     */
    private function staffProject(): array
    {
        $team = Team::factory()->create();
        $head = User::factory()->teamHead()->withPrimaryTeam($team)->create();
        $staff = User::factory()->withPrimaryTeam($team)->create();
        $project = Project::factory()->create();
        $project->teams()->sync([$team->id]);

        return [
            'head' => $head,
            'staff' => $staff,
            'project' => $project,
        ];
    }
}
