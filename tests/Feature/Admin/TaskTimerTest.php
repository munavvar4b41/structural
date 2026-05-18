<?php

namespace Tests\Feature\Admin;

use App\Enums\ProjectTaskStatus;
use App\Enums\TimeEntrySource;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\TaskTimeEntry;
use App\Models\Team;
use App\Models\User;
use App\Support\TaskTimeTracker;
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

    private function tracker(): TaskTimeTracker
    {
        return app(TaskTimeTracker::class);
    }

    public function test_staff_can_start_timer_on_assigned_task(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();

        $this->tracker()->start($staff, $task);

        $entry = TaskTimeEntry::query()->firstOrFail();
        $this->assertSame($staff->id, $entry->user_id);
        $this->assertSame($task->id, $entry->project_task_id);
        $this->assertSame($project->id, $entry->project_id);
        $this->assertNull($entry->ended_at);
        $this->assertSame(TimeEntrySource::Timer, $entry->source);
    }

    public function test_starting_a_second_timer_pauses_the_first(): void
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
        $this->tracker()->start($staff, $task);

        Carbon::setTestNow(Carbon::parse('2026-05-07 10:30:00'));
        $this->tracker()->start($staff, $secondTask);

        $first = TaskTimeEntry::query()->where('project_task_id', $task->id)->firstOrFail();
        $second = TaskTimeEntry::query()->where('project_task_id', $secondTask->id)->firstOrFail();

        $this->assertNull($first->ended_at);
        $this->assertNotNull($first->paused_at);
        $this->assertSame(30 * 60, $first->elapsedSeconds());
        $this->assertNull($second->ended_at);
        $this->assertNull($second->paused_at);

        $this->assertSame(
            1,
            TaskTimeEntry::query()->where('user_id', $staff->id)->running()->count(),
        );
        $this->assertSame(
            2,
            TaskTimeEntry::query()->where('user_id', $staff->id)->open()->count(),
        );

        Carbon::setTestNow();
    }

    public function test_switching_back_same_day_resumes_paused_entry(): void
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
        $this->tracker()->start($staff, $task);

        Carbon::setTestNow(Carbon::parse('2026-05-07 10:20:00'));
        $this->tracker()->start($staff, $secondTask);

        Carbon::setTestNow(Carbon::parse('2026-05-07 10:50:00'));
        $this->tracker()->start($staff, $task);

        $first = TaskTimeEntry::query()->where('project_task_id', $task->id)->firstOrFail();
        $second = TaskTimeEntry::query()->where('project_task_id', $secondTask->id)->firstOrFail();

        $this->assertNull($first->ended_at);
        $this->assertNull($first->paused_at);
        $this->assertNotNull($second->paused_at);
        $this->assertSame(30 * 60, $second->elapsedSeconds());
        $this->assertSame(20 * 60, $first->elapsedSeconds());

        Carbon::setTestNow();
    }

    public function test_stale_open_entry_from_yesterday_is_closed_on_start(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();

        Carbon::setTestNow(Carbon::parse('2026-05-06 16:00:00'));
        $this->tracker()->start($staff, $task);

        $stale = TaskTimeEntry::query()->firstOrFail();

        Carbon::setTestNow(Carbon::parse('2026-05-07 09:00:00'));
        $this->tracker()->start($staff, $task);

        $stale->refresh();
        $this->assertNotNull($stale->ended_at);

        $running = TaskTimeEntry::query()
            ->where('user_id', $staff->id)
            ->running()
            ->firstOrFail();
        $this->assertNotSame($stale->id, $running->id);

        Carbon::setTestNow();
    }

    public function test_today_elapsed_seconds_includes_closed_and_open_segments(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();

        Carbon::setTestNow(Carbon::parse('2026-05-07 08:00:00'));
        $this->tracker()->start($staff, $task);

        Carbon::setTestNow(Carbon::parse('2026-05-07 08:30:00'));
        $this->tracker()->stop($staff);

        Carbon::setTestNow(Carbon::parse('2026-05-07 09:00:00'));
        $this->tracker()->start($staff, $task);

        Carbon::setTestNow(Carbon::parse('2026-05-07 09:15:00'));

        $total = TaskTimeEntry::todayElapsedSecondsForUserOnTask($staff->id, $task->id);
        $this->assertSame(30 * 60 + 15 * 60, $total);

        Carbon::setTestNow();
    }

    public function test_today_elapsed_seconds_bulk_matches_per_task_helper(): void
    {
        ['staff' => $staff, 'project' => $project] = $this->setupProjectWithTask();

        $taskA = ProjectTask::factory()->forProject($project)->create([
            'assignee_user_id' => $staff->id,
            'status' => ProjectTaskStatus::ToDo,
        ]);
        $taskB = ProjectTask::factory()->forProject($project)->create([
            'assignee_user_id' => $staff->id,
            'status' => ProjectTaskStatus::ToDo,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-05-07 10:00:00'));
        $this->tracker()->start($staff, $taskA);

        Carbon::setTestNow(Carbon::parse('2026-05-07 10:20:00'));
        $this->tracker()->stop($staff);

        Carbon::setTestNow(Carbon::parse('2026-05-07 11:00:00'));
        $this->tracker()->start($staff, $taskB);

        Carbon::setTestNow(Carbon::parse('2026-05-07 11:10:00'));

        $bulk = TaskTimeEntry::todayElapsedSecondsForUserOnTasks(
            $staff->id,
            [$taskA->id, $taskB->id],
        );

        $this->assertSame(20 * 60, $bulk[$taskA->id]);
        $this->assertSame(10 * 60, $bulk[$taskB->id]);
        $this->assertSame(
            $bulk[$taskA->id],
            TaskTimeEntry::todayElapsedSecondsForUserOnTask($staff->id, $taskA->id),
        );
        $this->assertSame([], TaskTimeEntry::todayElapsedSecondsForUserOnTasks($staff->id, []));

        Carbon::setTestNow();
    }

    public function test_explicit_stop_ends_running_entry(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();

        Carbon::setTestNow(Carbon::parse('2026-05-07 11:00:00'));
        $this->tracker()->start($staff, $task);

        Carbon::setTestNow(Carbon::parse('2026-05-07 11:15:30'));
        $this->tracker()->stop($staff);

        $entry = TaskTimeEntry::query()->firstOrFail();
        $this->assertNotNull($entry->ended_at);
        $this->assertSame(15 * 60 + 30, $entry->duration_seconds);

        Carbon::setTestNow();
    }

    public function test_stop_is_a_noop_when_no_running_entry(): void
    {
        ['staff' => $staff] = $this->setupProjectWithTask();

        $this->tracker()->stop($staff);

        $this->assertSame(0, TaskTimeEntry::query()->count());
    }

    public function test_user_without_project_visibility_cannot_start_timer(): void
    {
        ['project' => $project, 'task' => $task] = $this->setupProjectWithTask();
        $other = User::factory()->withPrimaryTeam()->create();

        $this->assertFalse($other->can('start', [TaskTimeEntry::class, $task]));
    }

    public function test_client_cannot_start_timer(): void
    {
        ['team' => $team, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();
        $client = $project->clientUser;
        $this->assertNotNull($client);
        $client->teams()->syncWithoutDetaching([$team->id]);
        $client->forceFill(['primary_team_id' => $team->id])->save();

        $this->assertFalse($client->fresh()->can('start', [TaskTimeEntry::class, $task]));
    }

    public function test_starting_timer_transitions_status_to_in_progress_and_snapshots_previous(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();

        $this->tracker()->start($staff, $task);

        $this->assertSame(ProjectTaskStatus::InProgress, $task->fresh()->status);

        $entry = TaskTimeEntry::query()->firstOrFail();
        $this->assertSame(ProjectTaskStatus::ToDo, $entry->previous_task_status);
    }

    public function test_stopping_timer_restores_previous_status(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();

        $this->tracker()->start($staff, $task);
        $this->tracker()->stop($staff);

        $this->assertSame(ProjectTaskStatus::ToDo, $task->fresh()->status);
    }

    public function test_switch_pauses_old_task_and_transitions_new_while_old_stays_in_progress(): void
    {
        ['staff' => $staff, 'head' => $head, 'project' => $project, 'task' => $taskA] = $this->setupProjectWithTask();

        $taskB = ProjectTask::factory()
            ->forProject($project)
            ->create([
                'created_by_user_id' => $head->id,
                'assignee_user_id' => $staff->id,
                'status' => ProjectTaskStatus::Review,
            ]);

        $this->tracker()->start($staff, $taskA);
        $this->tracker()->start($staff, $taskB);

        $this->assertSame(ProjectTaskStatus::InProgress, $taskA->fresh()->status);
        $this->assertSame(ProjectTaskStatus::InProgress, $taskB->fresh()->status);

        $entryA = TaskTimeEntry::query()->where('project_task_id', $taskA->id)->firstOrFail();
        $entryB = TaskTimeEntry::query()->where('project_task_id', $taskB->id)->firstOrFail();

        $this->assertSame(ProjectTaskStatus::ToDo, $entryA->previous_task_status);
        $this->assertSame(ProjectTaskStatus::Review, $entryB->previous_task_status);
        $this->assertNotNull($entryA->paused_at);
    }

    public function test_in_progress_task_records_no_snapshot_and_status_unchanged_on_stop(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();
        $task->forceFill(['status' => ProjectTaskStatus::InProgress])->save();

        $this->tracker()->start($staff, $task);

        $entry = TaskTimeEntry::query()->firstOrFail();
        $this->assertNull($entry->previous_task_status);
        $this->assertSame(ProjectTaskStatus::InProgress, $task->fresh()->status);

        $this->tracker()->stop($staff);

        $this->assertSame(ProjectTaskStatus::InProgress, $task->fresh()->status);
    }

    public function test_done_task_is_not_transitioned(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();
        $task->forceFill(['status' => ProjectTaskStatus::Done])->save();

        $this->tracker()->start($staff, $task);

        $entry = TaskTimeEntry::query()->firstOrFail();
        $this->assertNull($entry->previous_task_status);
        $this->assertSame(ProjectTaskStatus::Done, $task->fresh()->status);
    }

    public function test_cancelled_task_is_not_transitioned(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();
        $task->forceFill(['status' => ProjectTaskStatus::Cancelled])->save();

        $this->tracker()->start($staff, $task);

        $entry = TaskTimeEntry::query()->firstOrFail();
        $this->assertNull($entry->previous_task_status);
        $this->assertSame(ProjectTaskStatus::Cancelled, $task->fresh()->status);
    }

    public function test_manual_status_change_during_run_is_not_overwritten_on_stop(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();

        $this->tracker()->start($staff, $task);

        $task->forceFill(['status' => ProjectTaskStatus::Review])->save();

        $this->tracker()->stop($staff);

        $this->assertSame(ProjectTaskStatus::Review, $task->fresh()->status);
    }

    public function test_pause_and_resume_accumulate_pause_duration(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();

        Carbon::setTestNow(Carbon::parse('2026-05-07 12:00:00'));
        $this->tracker()->start($staff, $task);

        Carbon::setTestNow(Carbon::parse('2026-05-07 12:10:00'));
        $this->tracker()->pause($staff);

        $entry = TaskTimeEntry::query()->firstOrFail();
        $this->assertNotNull($entry->paused_at);
        $this->assertSame(10 * 60, $entry->elapsedSeconds());

        Carbon::setTestNow(Carbon::parse('2026-05-07 12:25:00'));
        $this->tracker()->resume($staff);

        $entry->refresh();
        $this->assertNull($entry->paused_at);
        $this->assertSame(15 * 60, $entry->accumulated_pause_seconds);

        Carbon::setTestNow(Carbon::parse('2026-05-07 12:35:00'));
        $this->assertSame(10 * 60 + 10 * 60, $entry->elapsedSeconds());

        Carbon::setTestNow();
    }

    public function test_stop_after_pause_excludes_pause_from_duration(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();

        Carbon::setTestNow(Carbon::parse('2026-05-07 13:00:00'));
        $this->tracker()->start($staff, $task);

        Carbon::setTestNow(Carbon::parse('2026-05-07 13:05:00'));
        $this->tracker()->pause($staff);

        Carbon::setTestNow(Carbon::parse('2026-05-07 13:20:00'));
        $this->tracker()->stop($staff);

        $entry = TaskTimeEntry::query()->firstOrFail();
        $this->assertSame(5 * 60, $entry->duration_seconds);

        Carbon::setTestNow();
    }
}
