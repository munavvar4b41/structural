<?php

namespace Tests\Feature\Api\Desktop;

use App\Enums\ProjectTaskStatus;
use App\Enums\TimerPauseReason;
use App\Enums\TimerResumedBy;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\TaskTimeEntry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DesktopApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{team: Team, staff: User, project: Project, task: ProjectTask}
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

        return compact('team', 'staff', 'project', 'task');
    }

    public function test_login_returns_token(): void
    {
        ['staff' => $staff] = $this->setupProjectWithTask();

        $this->postJson(route('api.desktop.login'), [
            'email' => $staff->email,
            'password' => 'password',
            'device_name' => 'test-device',
        ])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);
    }

    public function test_tray_requires_authentication(): void
    {
        $this->getJson(route('api.desktop.tray'))->assertUnauthorized();
    }

    public function test_tray_returns_active_and_pending_tasks(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();

        $pending = ProjectTask::factory()
            ->forProject($project)
            ->create([
                'assignee_user_id' => $staff->id,
                'status' => ProjectTaskStatus::ToDo,
                'title' => 'Pending todo item',
            ]);

        Sanctum::actingAs($staff);

        $this->postJson(route('api.desktop.timer.start'), [
            'project_id' => $project->id,
            'task_id' => $task->id,
        ])->assertOk();

        $response = $this->getJson(route('api.desktop.tray'))->assertOk();

        $response->assertJsonPath('active.task_id', $task->id);
        $response->assertJsonPath('active.is_paused', false);
        $pendingIds = collect($response->json('pending_tasks'))->pluck('id')->all();
        $this->assertContains($pending->id, $pendingIds);
        $this->assertNotContains($task->id, $pendingIds);

        $pendingItem = collect($response->json('pending_tasks'))
            ->firstWhere('id', $pending->id);
        $this->assertIsArray($pendingItem);
        $this->assertSame(ProjectTaskStatus::ToDo->value, $pendingItem['status']);
        $this->assertSame(ProjectTaskStatus::ToDo->label(), $pendingItem['status_label']);
    }

    public function test_tray_pending_tasks_sort_in_progress_before_to_do_and_cap_at_fifteen(): void
    {
        ['staff' => $staff, 'project' => $project] = $this->setupProjectWithTask();

        Sanctum::actingAs($staff);

        $inProgress = ProjectTask::factory()
            ->forProject($project)
            ->create([
                'assignee_user_id' => $staff->id,
                'status' => ProjectTaskStatus::InProgress,
                'title' => 'In progress task',
                'updated_at' => now()->subHour(),
            ]);

        $toDo = ProjectTask::factory()
            ->forProject($project)
            ->create([
                'assignee_user_id' => $staff->id,
                'status' => ProjectTaskStatus::ToDo,
                'title' => 'To do task',
                'updated_at' => now(),
            ]);

        for ($i = 0; $i < 16; $i++) {
            ProjectTask::factory()
                ->forProject($project)
                ->create([
                    'assignee_user_id' => $staff->id,
                    'status' => ProjectTaskStatus::ToDo,
                    'title' => "Extra todo {$i}",
                ]);
        }

        $response = $this->getJson(route('api.desktop.tray'))->assertOk();

        $pending = $response->json('pending_tasks');
        $this->assertCount(15, $pending);
        $this->assertSame($inProgress->id, $pending[0]['id']);
        $this->assertSame(ProjectTaskStatus::InProgress->value, $pending[0]['status']);
        $this->assertContains($toDo->id, collect($pending)->pluck('id')->all());
    }

    public function test_timer_start_via_api_switches_running_task(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();

        $second = ProjectTask::factory()
            ->forProject($project)
            ->create([
                'assignee_user_id' => $staff->id,
                'status' => ProjectTaskStatus::ToDo,
            ]);

        Sanctum::actingAs($staff);

        Carbon::setTestNow(Carbon::parse('2026-05-07 14:00:00'));
        $this->postJson(route('api.desktop.timer.start'), [
            'project_id' => $project->id,
            'task_id' => $task->id,
        ])->assertOk()->assertJsonPath('active.task_id', $task->id);

        Carbon::setTestNow(Carbon::parse('2026-05-07 14:30:00'));
        $this->postJson(route('api.desktop.timer.start'), [
            'project_id' => $project->id,
            'task_id' => $second->id,
        ])->assertOk()->assertJsonPath('active.task_id', $second->id);

        $first = TaskTimeEntry::query()->where('project_task_id', $task->id)->firstOrFail();
        $this->assertNull($first->ended_at);
        $this->assertNotNull($first->paused_at);
        $this->assertSame(30 * 60, $first->elapsedSeconds());

        Carbon::setTestNow();
    }

    public function test_pause_and_resume_via_api(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();

        Sanctum::actingAs($staff);

        Carbon::setTestNow(Carbon::parse('2026-05-07 15:00:00'));
        $this->postJson(route('api.desktop.timer.start'), [
            'project_id' => $project->id,
            'task_id' => $task->id,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-05-07 15:10:00'));
        $this->postJson(route('api.desktop.timer.pause'))
            ->assertOk()
            ->assertJsonPath('active.is_paused', true);

        Carbon::setTestNow(Carbon::parse('2026-05-07 15:20:00'));
        $this->postJson(route('api.desktop.timer.resume'))
            ->assertOk()
            ->assertJsonPath('active.is_paused', false);

        Carbon::setTestNow();
    }

    public function test_pause_with_inactivity_reason_persists_metadata(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();

        Sanctum::actingAs($staff);

        Carbon::setTestNow(Carbon::parse('2026-05-07 16:00:00'));
        $this->postJson(route('api.desktop.timer.start'), [
            'project_id' => $project->id,
            'task_id' => $task->id,
        ]);

        $clientAt = '2026-05-07T16:05:00Z';
        $this->postJson(route('api.desktop.timer.pause'), [
            'reason' => TimerPauseReason::Inactivity->value,
            'client_event_at' => $clientAt,
        ])
            ->assertOk()
            ->assertJsonPath('active.is_paused', true);

        $entry = TaskTimeEntry::query()->where('project_task_id', $task->id)->firstOrFail();
        $this->assertSame(TimerPauseReason::Inactivity, $entry->pause_reason);
        $this->assertNotNull($entry->last_client_event_at);

        Carbon::setTestNow();
    }

    public function test_resume_with_inactivity_only_works_after_inactivity_pause(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();

        Sanctum::actingAs($staff);

        Carbon::setTestNow(Carbon::parse('2026-05-07 17:00:00'));
        $this->postJson(route('api.desktop.timer.start'), [
            'project_id' => $project->id,
            'task_id' => $task->id,
        ]);

        $this->postJson(route('api.desktop.timer.pause'), [
            'reason' => TimerPauseReason::Manual->value,
        ])->assertOk();

        $this->postJson(route('api.desktop.timer.resume'), [
            'resumed_by' => TimerResumedBy::Inactivity->value,
        ])
            ->assertOk()
            ->assertJsonPath('active.is_paused', true);

        $this->postJson(route('api.desktop.timer.resume'))->assertOk();

        $this->postJson(route('api.desktop.timer.pause'), [
            'reason' => TimerPauseReason::Inactivity->value,
        ])
            ->assertOk()
            ->assertJsonPath('active.is_paused', true);

        Carbon::setTestNow(Carbon::parse('2026-05-07 17:10:00'));
        $this->postJson(route('api.desktop.timer.resume'), [
            'resumed_by' => TimerResumedBy::Inactivity->value,
        ])
            ->assertOk()
            ->assertJsonPath('active.is_paused', false);

        $entry = TaskTimeEntry::query()->where('project_task_id', $task->id)->firstOrFail();
        $this->assertNull($entry->paused_at);
        $this->assertSame(TimerResumedBy::Inactivity, $entry->resumed_by);

        Carbon::setTestNow();
    }

    public function test_my_work_returns_columns(): void
    {
        ['staff' => $staff] = $this->setupProjectWithTask();

        Sanctum::actingAs($staff);

        $toDoIndex = array_search(ProjectTaskStatus::ToDo, ProjectTaskStatus::boardOrder(), true);
        $this->assertNotFalse($toDoIndex);

        $this->getJson(route('api.desktop.my-work'))
            ->assertOk()
            ->assertJsonStructure([
                'columns',
                'status_options',
                'project_options',
                'filters' => ['project_id'],
            ])
            ->assertJsonPath("columns.{$toDoIndex}.meta.per_page", 20)
            ->assertJsonPath("columns.{$toDoIndex}.tasks.0.timer_state", 'idle')
            ->assertJsonStructure([
                'columns' => [
                    [
                        'status',
                        'label',
                        'tasks',
                        'meta' => [
                            'total',
                            'current_page',
                            'last_page',
                            'per_page',
                        ],
                    ],
                ],
            ]);
    }

    public function test_my_work_hides_tasks_scheduled_for_future_display(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-27 12:00:00'));

        ['staff' => $staff, 'project' => $project] = $this->setupProjectWithTask();

        ProjectTask::factory()
            ->forProject($project)
            ->create([
                'assignee_user_id' => $staff->id,
                'status' => ProjectTaskStatus::ToDo,
                'title' => 'Future task',
                'display_after_at' => now()->addHour(),
            ]);

        ProjectTask::factory()
            ->forProject($project)
            ->create([
                'assignee_user_id' => $staff->id,
                'status' => ProjectTaskStatus::ToDo,
                'title' => 'Ready task',
                'display_after_at' => now()->subMinute(),
            ]);

        Sanctum::actingAs($staff);

        $response = $this->getJson(route('api.desktop.my-work'))->assertOk();
        $toDoIndex = array_search(ProjectTaskStatus::ToDo, ProjectTaskStatus::boardOrder(), true);
        $this->assertNotFalse($toDoIndex);

        $titles = collect($response->json("columns.{$toDoIndex}.tasks"))->pluck('title')->all();
        $this->assertContains('Ready task', $titles);
        $this->assertNotContains('Future task', $titles);

        Carbon::setTestNow();
    }

    public function test_logout_revokes_token(): void
    {
        ['staff' => $staff] = $this->setupProjectWithTask();

        $token = $staff->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson(route('api.desktop.logout'))
            ->assertOk();

        $this->assertSame(0, $staff->tokens()->count());
        $this->assertNull(PersonalAccessToken::findToken($token));
    }
}
