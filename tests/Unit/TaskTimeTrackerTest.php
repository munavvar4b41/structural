<?php

namespace Tests\Unit;

use App\Enums\ProjectTaskStatus;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\TaskTimeEntry;
use App\Models\Team;
use App\Models\User;
use App\Support\TaskTimeTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TaskTimeTrackerTest extends TestCase
{
    use RefreshDatabase;

    public function test_switch_pauses_first_entry_and_starts_second(): void
    {
        $team = Team::factory()->create();
        $staff = User::factory()->withPrimaryTeam($team)->create();
        $project = Project::factory()->create(['client_user_id' => User::factory()->client()->create()->id]);
        $project->teams()->sync([$team->id]);

        $taskA = ProjectTask::factory()->forProject($project)->create([
            'assignee_user_id' => $staff->id,
            'status' => ProjectTaskStatus::ToDo,
        ]);
        $taskB = ProjectTask::factory()->forProject($project)->create([
            'assignee_user_id' => $staff->id,
            'status' => ProjectTaskStatus::ToDo,
        ]);

        $tracker = app(TaskTimeTracker::class);

        Carbon::setTestNow(Carbon::parse('2026-05-07 10:00:00'));
        $tracker->start($staff, $taskA);

        Carbon::setTestNow(Carbon::parse('2026-05-07 10:30:00'));
        $tracker->start($staff, $taskB);

        $first = TaskTimeEntry::query()->where('project_task_id', $taskA->id)->firstOrFail();
        $second = TaskTimeEntry::query()->where('project_task_id', $taskB->id)->firstOrFail();

        $this->assertNull($first->ended_at);
        $this->assertNotNull($first->paused_at);
        $this->assertSame(30 * 60, $first->elapsedSeconds());
        $this->assertNull($second->paused_at);

        Carbon::setTestNow();
    }

    public function test_switch_back_resumes_same_day_entry(): void
    {
        $team = Team::factory()->create();
        $staff = User::factory()->withPrimaryTeam($team)->create();
        $project = Project::factory()->create(['client_user_id' => User::factory()->client()->create()->id]);
        $project->teams()->sync([$team->id]);

        $taskA = ProjectTask::factory()->forProject($project)->create([
            'assignee_user_id' => $staff->id,
            'status' => ProjectTaskStatus::ToDo,
        ]);
        $taskB = ProjectTask::factory()->forProject($project)->create([
            'assignee_user_id' => $staff->id,
            'status' => ProjectTaskStatus::ToDo,
        ]);

        $tracker = app(TaskTimeTracker::class);

        Carbon::setTestNow(Carbon::parse('2026-05-07 10:00:00'));
        $entryA = $tracker->start($staff, $taskA);

        Carbon::setTestNow(Carbon::parse('2026-05-07 10:20:00'));
        $tracker->start($staff, $taskB);

        Carbon::setTestNow(Carbon::parse('2026-05-07 10:50:00'));
        $resumed = $tracker->start($staff, $taskA);

        $this->assertSame($entryA->id, $resumed->id);
        $this->assertNull($resumed->paused_at);
        $this->assertSame(20 * 60, $resumed->elapsedSeconds());

        Carbon::setTestNow();
    }
}
