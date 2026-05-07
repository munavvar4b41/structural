<?php

namespace Tests\Feature\Admin;

use App\Enums\ProjectTaskStatus;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\TaskTimeEntry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TimeReportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{team: Team, head: User, staff: User, project: Project, task: ProjectTask}
     */
    private function setupProjectWithTask(?Team $team = null): array
    {
        $team = $team ?? Team::factory()->create();
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
                'status' => ProjectTaskStatus::ToDo,
            ]);

        return compact('team', 'head', 'staff', 'project', 'task');
    }

    public function test_self_report_aggregates_per_day_project_and_task(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();

        $secondTask = ProjectTask::factory()
            ->forProject($project)
            ->create([
                'created_by_user_id' => $staff->id,
                'assignee_user_id' => $staff->id,
                'status' => ProjectTaskStatus::ToDo,
            ]);

        TaskTimeEntry::factory()
            ->forTask($task)
            ->forUser($staff)
            ->between(
                Carbon::parse('2026-05-06 09:00:00'),
                Carbon::parse('2026-05-06 10:00:00'),
            )
            ->create();

        TaskTimeEntry::factory()
            ->forTask($task)
            ->forUser($staff)
            ->between(
                Carbon::parse('2026-05-07 09:00:00'),
                Carbon::parse('2026-05-07 10:30:00'),
            )
            ->create();

        TaskTimeEntry::factory()
            ->forTask($secondTask)
            ->forUser($staff)
            ->between(
                Carbon::parse('2026-05-07 11:00:00'),
                Carbon::parse('2026-05-07 11:30:00'),
            )
            ->create();

        $this->actingAs($staff)
            ->get(route('admin.time-report.index', ['from' => '2026-05-06', 'to' => '2026-05-07']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/time-report/Index')
                ->where('totals.entries', 3)
                ->where('totals.seconds', 60 * 60 + 90 * 60 + 30 * 60)
                ->has('per_day', 2)
                ->where('per_day.0.date', '2026-05-07')
                ->where('per_day.0.total_seconds', 90 * 60 + 30 * 60)
                ->where('per_day.1.date', '2026-05-06')
                ->where('per_day.1.total_seconds', 60 * 60)
                ->has('per_project', 1)
                ->where('per_project.0.task_count', 2)
                ->where('per_project.0.total_seconds', 60 * 60 + 90 * 60 + 30 * 60)
                ->has('per_task', 2));
    }

    public function test_date_filter_excludes_entries_outside_range(): void
    {
        ['staff' => $staff, 'task' => $task] = $this->setupProjectWithTask();

        TaskTimeEntry::factory()
            ->forTask($task)
            ->forUser($staff)
            ->between(
                Carbon::parse('2026-05-05 09:00:00'),
                Carbon::parse('2026-05-05 10:00:00'),
            )
            ->create();

        TaskTimeEntry::factory()
            ->forTask($task)
            ->forUser($staff)
            ->between(
                Carbon::parse('2026-05-07 09:00:00'),
                Carbon::parse('2026-05-07 09:30:00'),
            )
            ->create();

        $this->actingAs($staff)
            ->get(route('admin.time-report.index', ['from' => '2026-05-07', 'to' => '2026-05-07']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('totals.entries', 1)
                ->where('totals.seconds', 30 * 60));
    }

    public function test_admin_can_view_other_users_report(): void
    {
        ['staff' => $staff, 'task' => $task] = $this->setupProjectWithTask();
        $admin = User::factory()->admin()->withPrimaryTeam()->create();

        TaskTimeEntry::factory()
            ->forTask($task)
            ->forUser($staff)
            ->between(
                Carbon::parse('2026-05-07 09:00:00'),
                Carbon::parse('2026-05-07 10:00:00'),
            )
            ->create();

        $this->actingAs($admin)
            ->get(route('admin.time-report.index', [
                'from' => '2026-05-07',
                'to' => '2026-05-07',
                'user_id' => $staff->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('target_user.id', $staff->id)
                ->where('totals.entries', 1)
                ->where('totals.seconds', 60 * 60));
    }

    public function test_staff_cannot_view_other_staff_report(): void
    {
        ['team' => $team, 'staff' => $staff] = $this->setupProjectWithTask();
        $otherStaff = User::factory()->withPrimaryTeam($team)->create();

        $this->actingAs($staff)
            ->get(route('admin.time-report.index', [
                'user_id' => $otherStaff->id,
            ]))
            ->assertForbidden();
    }

    public function test_project_lead_can_view_team_member_report_for_lead_projects(): void
    {
        ['team' => $team, 'staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();
        $lead = User::factory()->withPrimaryTeam($team)->create();
        $project->forceFill(['lead_user_id' => $lead->id])->save();

        TaskTimeEntry::factory()
            ->forTask($task)
            ->forUser($staff)
            ->between(
                Carbon::parse('2026-05-07 09:00:00'),
                Carbon::parse('2026-05-07 09:45:00'),
            )
            ->create();

        $this->actingAs($lead)
            ->get(route('admin.time-report.index', [
                'from' => '2026-05-07',
                'to' => '2026-05-07',
                'user_id' => $staff->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('totals.seconds', 45 * 60));
    }
}
