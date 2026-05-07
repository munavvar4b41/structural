<?php

namespace Tests\Feature\Admin;

use App\Enums\ProjectTaskStatus;
use App\Enums\TimeEntrySource;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\TaskTimeEntry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TaskTimerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{team: Team, head: User, staff: User, project: Project, task: ProjectTask}
     */
    private function setupProjectWithTask(): array
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
                'status' => ProjectTaskStatus::ToDo,
            ]);

        return compact('team', 'head', 'staff', 'project', 'task');
    }

    public function test_staff_can_start_timer_on_assigned_task(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();

        $this->actingAs($staff)
            ->post(route('admin.projects.tasks.timer.start', [$project, $task]))
            ->assertRedirect();

        $entry = TaskTimeEntry::query()->firstOrFail();
        $this->assertSame($staff->id, $entry->user_id);
        $this->assertSame($task->id, $entry->project_task_id);
        $this->assertSame($project->id, $entry->project_id);
        $this->assertNull($entry->ended_at);
        $this->assertSame(TimeEntrySource::Timer, $entry->source);
    }

    public function test_starting_a_second_timer_auto_stops_the_first(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();

        $secondTask = ProjectTask::factory()
            ->forProject($project)
            ->create([
                'created_by_user_id' => $staff->id,
                'assignee_user_id' => $staff->id,
                'status' => ProjectTaskStatus::ToDo,
            ]);

        Carbon::setTestNow(Carbon::parse('2026-05-07 10:00:00'));
        $this->actingAs($staff)->post(route('admin.projects.tasks.timer.start', [$project, $task]));

        Carbon::setTestNow(Carbon::parse('2026-05-07 10:30:00'));
        $this->actingAs($staff)->post(route('admin.projects.tasks.timer.start', [$project, $secondTask]));

        $first = TaskTimeEntry::query()->where('project_task_id', $task->id)->firstOrFail();
        $second = TaskTimeEntry::query()->where('project_task_id', $secondTask->id)->firstOrFail();

        $this->assertNotNull($first->ended_at);
        $this->assertSame(30 * 60, $first->duration_seconds);
        $this->assertNull($second->ended_at);

        $this->assertSame(
            1,
            TaskTimeEntry::query()->where('user_id', $staff->id)->whereNull('ended_at')->count(),
        );

        Carbon::setTestNow();
    }

    public function test_explicit_stop_ends_running_entry(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();

        Carbon::setTestNow(Carbon::parse('2026-05-07 11:00:00'));
        $this->actingAs($staff)->post(route('admin.projects.tasks.timer.start', [$project, $task]));

        Carbon::setTestNow(Carbon::parse('2026-05-07 11:15:30'));
        $this->actingAs($staff)
            ->post(route('admin.time-entries.stop'))
            ->assertRedirect();

        $entry = TaskTimeEntry::query()->firstOrFail();
        $this->assertNotNull($entry->ended_at);
        $this->assertSame(15 * 60 + 30, $entry->duration_seconds);

        Carbon::setTestNow();
    }

    public function test_stop_is_a_noop_when_no_running_entry(): void
    {
        ['staff' => $staff] = $this->setupProjectWithTask();

        $this->actingAs($staff)
            ->post(route('admin.time-entries.stop'))
            ->assertRedirect();

        $this->assertSame(0, TaskTimeEntry::query()->count());
    }

    public function test_user_without_project_visibility_gets_403_starting_timer(): void
    {
        ['project' => $project, 'task' => $task] = $this->setupProjectWithTask();
        $other = User::factory()->withPrimaryTeam()->create();

        $this->actingAs($other)
            ->post(route('admin.projects.tasks.timer.start', [$project, $task]))
            ->assertForbidden();

        $this->assertSame(0, TaskTimeEntry::query()->count());
    }

    public function test_client_cannot_start_timer(): void
    {
        ['team' => $team, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();
        $client = $project->clientUser;
        $this->assertNotNull($client);
        $client->teams()->syncWithoutDetaching([$team->id]);
        $client->forceFill(['primary_team_id' => $team->id])->save();

        $this->actingAs($client->fresh())
            ->post(route('admin.projects.tasks.timer.start', [$project, $task]))
            ->assertForbidden();
    }

    public function test_starting_timer_transitions_status_to_in_progress_and_snapshots_previous(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();

        $this->actingAs($staff)
            ->post(route('admin.projects.tasks.timer.start', [$project, $task]))
            ->assertRedirect();

        $this->assertSame(ProjectTaskStatus::InProgress, $task->fresh()->status);

        $entry = TaskTimeEntry::query()->firstOrFail();
        $this->assertSame(ProjectTaskStatus::ToDo, $entry->previous_task_status);
    }

    public function test_stopping_timer_restores_previous_status(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();

        $this->actingAs($staff)
            ->post(route('admin.projects.tasks.timer.start', [$project, $task]));
        $this->actingAs($staff)
            ->post(route('admin.time-entries.stop'))
            ->assertRedirect();

        $this->assertSame(ProjectTaskStatus::ToDo, $task->fresh()->status);
    }

    public function test_switch_auto_stop_restores_previous_status_on_old_task_and_transitions_new(): void
    {
        ['staff' => $staff, 'head' => $head, 'project' => $project, 'task' => $taskA] = $this->setupProjectWithTask();

        $taskB = ProjectTask::factory()
            ->forProject($project)
            ->create([
                'created_by_user_id' => $head->id,
                'assignee_user_id' => $staff->id,
                'status' => ProjectTaskStatus::Review,
            ]);

        $this->actingAs($staff)
            ->post(route('admin.projects.tasks.timer.start', [$project, $taskA]));
        $this->actingAs($staff)
            ->post(route('admin.projects.tasks.timer.start', [$project, $taskB]));

        $this->assertSame(ProjectTaskStatus::ToDo, $taskA->fresh()->status);
        $this->assertSame(ProjectTaskStatus::InProgress, $taskB->fresh()->status);

        $entryA = TaskTimeEntry::query()->where('project_task_id', $taskA->id)->firstOrFail();
        $entryB = TaskTimeEntry::query()->where('project_task_id', $taskB->id)->firstOrFail();

        $this->assertSame(ProjectTaskStatus::ToDo, $entryA->previous_task_status);
        $this->assertSame(ProjectTaskStatus::Review, $entryB->previous_task_status);
    }

    public function test_in_progress_task_records_no_snapshot_and_status_unchanged_on_stop(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();
        $task->forceFill(['status' => ProjectTaskStatus::InProgress])->save();

        $this->actingAs($staff)
            ->post(route('admin.projects.tasks.timer.start', [$project, $task]));

        $entry = TaskTimeEntry::query()->firstOrFail();
        $this->assertNull($entry->previous_task_status);
        $this->assertSame(ProjectTaskStatus::InProgress, $task->fresh()->status);

        $this->actingAs($staff)->post(route('admin.time-entries.stop'));

        $this->assertSame(ProjectTaskStatus::InProgress, $task->fresh()->status);
    }

    public function test_done_task_is_not_transitioned(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();
        $task->forceFill(['status' => ProjectTaskStatus::Done])->save();

        $this->actingAs($staff)
            ->post(route('admin.projects.tasks.timer.start', [$project, $task]));

        $entry = TaskTimeEntry::query()->firstOrFail();
        $this->assertNull($entry->previous_task_status);
        $this->assertSame(ProjectTaskStatus::Done, $task->fresh()->status);
    }

    public function test_cancelled_task_is_not_transitioned(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();
        $task->forceFill(['status' => ProjectTaskStatus::Cancelled])->save();

        $this->actingAs($staff)
            ->post(route('admin.projects.tasks.timer.start', [$project, $task]));

        $entry = TaskTimeEntry::query()->firstOrFail();
        $this->assertNull($entry->previous_task_status);
        $this->assertSame(ProjectTaskStatus::Cancelled, $task->fresh()->status);
    }

    public function test_manual_status_change_during_run_is_not_overwritten_on_stop(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();

        $this->actingAs($staff)
            ->post(route('admin.projects.tasks.timer.start', [$project, $task]));

        $task->forceFill(['status' => ProjectTaskStatus::Review])->save();

        $this->actingAs($staff)->post(route('admin.time-entries.stop'));

        $this->assertSame(ProjectTaskStatus::Review, $task->fresh()->status);
    }
}
