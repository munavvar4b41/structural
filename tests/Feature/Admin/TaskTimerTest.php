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
}
