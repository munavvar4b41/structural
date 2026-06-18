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

class TaskTimeEntryTest extends TestCase
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

    public function test_staff_can_create_manual_entry(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();
        Carbon::setTestNow(Carbon::parse('2026-05-07 12:00:00'));

        $start = Carbon::parse('2026-05-07 09:00:00');
        $end = Carbon::parse('2026-05-07 10:30:00');

        $this->actingAs($staff)
            ->post(route('admin.projects.tasks.time-entries.store', [$project, $task]), [
                'started_at' => $start->toDateTimeString(),
                'ended_at' => $end->toDateTimeString(),
                'notes' => 'Did the thing',
            ])
            ->assertRedirect();

        $entry = TaskTimeEntry::query()->firstOrFail();
        $this->assertSame($staff->id, $entry->user_id);
        $this->assertSame(TimeEntrySource::Manual, $entry->source);
        $this->assertSame(90 * 60, $entry->duration_seconds);
        $this->assertSame('Did the thing', $entry->notes);
    }

    public function test_manual_entry_rejects_end_before_start(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();
        Carbon::setTestNow(Carbon::parse('2026-05-07 12:00:00'));

        $this->actingAs($staff)
            ->post(route('admin.projects.tasks.time-entries.store', [$project, $task]), [
                'started_at' => '2026-05-07 11:00:00',
                'ended_at' => '2026-05-07 10:00:00',
            ])
            ->assertSessionHasErrors('ended_at');

        $this->assertSame(0, TaskTimeEntry::query()->count());
    }

    public function test_manual_entry_rejects_overlap_with_existing_entry(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();
        Carbon::setTestNow(Carbon::parse('2026-05-07 12:00:00'));

        TaskTimeEntry::factory()
            ->forTask($task)
            ->forUser($staff)
            ->between(
                Carbon::parse('2026-05-07 09:00:00'),
                Carbon::parse('2026-05-07 10:00:00'),
            )
            ->manual()
            ->create();

        $this->actingAs($staff)
            ->post(route('admin.projects.tasks.time-entries.store', [$project, $task]), [
                'started_at' => '2026-05-07 09:30:00',
                'ended_at' => '2026-05-07 10:30:00',
            ])
            ->assertSessionHasErrors('started_at');

        $this->assertSame(1, TaskTimeEntry::query()->count());
    }

    public function test_user_can_update_own_entry(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();
        Carbon::setTestNow(Carbon::parse('2026-05-07 12:00:00'));

        $entry = TaskTimeEntry::factory()
            ->forTask($task)
            ->forUser($staff)
            ->between(
                Carbon::parse('2026-05-07 09:00:00'),
                Carbon::parse('2026-05-07 10:00:00'),
            )
            ->manual()
            ->create();

        $this->actingAs($staff)
            ->patch(route('admin.projects.tasks.time-entries.update', [$project, $task, $entry]), [
                'started_at' => '2026-05-07 09:00:00',
                'ended_at' => '2026-05-07 09:45:00',
                'notes' => 'Trimmed',
            ])
            ->assertRedirect();

        $fresh = $entry->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame(45 * 60, $fresh->duration_seconds);
        $this->assertSame('Trimmed', $fresh->notes);
    }

    public function test_user_cannot_update_other_users_entry(): void
    {
        ['team' => $team, 'staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();
        $otherStaff = User::factory()->withPrimaryTeam($team)->create();
        Carbon::setTestNow(Carbon::parse('2026-05-07 12:00:00'));

        $entry = TaskTimeEntry::factory()
            ->forTask($task)
            ->forUser($staff)
            ->between(
                Carbon::parse('2026-05-07 09:00:00'),
                Carbon::parse('2026-05-07 10:00:00'),
            )
            ->manual()
            ->create();

        $this->actingAs($otherStaff)
            ->patch(route('admin.projects.tasks.time-entries.update', [$project, $task, $entry]), [
                'started_at' => '2026-05-07 09:00:00',
                'ended_at' => '2026-05-07 09:30:00',
            ])
            ->assertForbidden();
    }

    public function test_user_can_delete_own_entry(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();

        $entry = TaskTimeEntry::factory()
            ->forTask($task)
            ->forUser($staff)
            ->manual()
            ->create();

        $this->actingAs($staff)
            ->delete(route('admin.projects.tasks.time-entries.destroy', [$project, $task, $entry]))
            ->assertRedirect();

        $this->assertNull($entry->fresh());
    }

    public function test_user_cannot_delete_other_users_entry(): void
    {
        ['team' => $team, 'staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();
        $otherStaff = User::factory()->withPrimaryTeam($team)->create();

        $entry = TaskTimeEntry::factory()
            ->forTask($task)
            ->forUser($staff)
            ->manual()
            ->create();

        $this->actingAs($otherStaff)
            ->delete(route('admin.projects.tasks.time-entries.destroy', [$project, $task, $entry]))
            ->assertForbidden();

        $this->assertNotNull($entry->fresh());
    }

    public function test_staff_can_create_entry_with_duration_minutes(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();
        Carbon::setTestNow(Carbon::parse('2026-05-07 12:00:00'));

        $this->actingAs($staff)
            ->post(route('admin.projects.tasks.time-entries.store', [$project, $task]), [
                'duration_minutes' => 40,
            ])
            ->assertRedirect();

        $entry = TaskTimeEntry::query()->firstOrFail();
        $this->assertSame(40 * 60, $entry->duration_seconds);
        $this->assertSame('2026-05-07 11:20:00', $entry->started_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-05-07 12:00:00', $entry->ended_at?->format('Y-m-d H:i:s'));
        $this->assertNull($entry->notes);

        Carbon::setTestNow(null);
    }

    public function test_duration_entry_ignores_start_and_end_when_duration_provided(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();
        Carbon::setTestNow(Carbon::parse('2026-05-07 12:00:00'));

        $this->actingAs($staff)
            ->post(route('admin.projects.tasks.time-entries.store', [$project, $task]), [
                'duration_minutes' => 40,
                'started_at' => '2026-05-07 09:00:00',
                'ended_at' => '2026-05-07 10:00:00',
            ])
            ->assertRedirect();

        $entry = TaskTimeEntry::query()->firstOrFail();
        $this->assertSame(40 * 60, $entry->duration_seconds);
        $this->assertSame('2026-05-07 11:20:00', $entry->started_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-05-07 12:00:00', $entry->ended_at?->format('Y-m-d H:i:s'));

        Carbon::setTestNow(null);
    }

    public function test_duration_entry_accepts_long_duration_without_working_hours_limit(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();
        Carbon::setTestNow(Carbon::parse('2026-05-07 12:00:00'));

        $this->actingAs($staff)
            ->post(route('admin.projects.tasks.time-entries.store', [$project, $task]), [
                'duration_minutes' => 500,
            ])
            ->assertRedirect();

        $entry = TaskTimeEntry::query()->firstOrFail();
        $this->assertSame(500 * 60, $entry->duration_seconds);

        Carbon::setTestNow(null);
    }

    public function test_user_can_update_entry_with_duration_minutes(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();
        Carbon::setTestNow(Carbon::parse('2026-05-07 12:00:00'));

        $entry = TaskTimeEntry::factory()
            ->forTask($task)
            ->forUser($staff)
            ->between(
                Carbon::parse('2026-05-07 09:00:00'),
                Carbon::parse('2026-05-07 10:00:00'),
            )
            ->manual()
            ->create();

        $this->actingAs($staff)
            ->patch(route('admin.projects.tasks.time-entries.update', [$project, $task, $entry]), [
                'duration_minutes' => 30,
            ])
            ->assertRedirect();

        $fresh = $entry->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame(30 * 60, $fresh->duration_seconds);
        $this->assertSame('2026-05-07 11:30:00', $fresh->started_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-05-07 12:00:00', $fresh->ended_at?->format('Y-m-d H:i:s'));
        $this->assertNull($fresh->notes);

        Carbon::setTestNow(null);
    }

    public function test_manual_entry_does_not_change_task_status(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();
        Carbon::setTestNow(Carbon::parse('2026-05-07 12:00:00'));

        $this->actingAs($staff)
            ->post(route('admin.projects.tasks.time-entries.store', [$project, $task]), [
                'started_at' => '2026-05-07 09:00:00',
                'ended_at' => '2026-05-07 10:00:00',
            ])
            ->assertRedirect();

        $this->assertSame(ProjectTaskStatus::ToDo, $task->fresh()->status);

        $entry = TaskTimeEntry::query()->firstOrFail();
        $this->assertNull($entry->previous_task_status);
    }
}
