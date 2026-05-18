<?php

namespace Tests\Feature\Api\Desktop;

use App\Enums\ProjectTaskStatus;
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

        $this->actingAs($staff)->post(route('admin.projects.tasks.timer.start', [$project, $task]));

        $response = $this->getJson(route('api.desktop.tray'))->assertOk();

        $response->assertJsonPath('active.task_id', $task->id);
        $response->assertJsonPath('active.is_paused', false);
        $pendingIds = collect($response->json('pending_tasks'))->pluck('id')->all();
        $this->assertContains($pending->id, $pendingIds);
        $this->assertNotContains($task->id, $pendingIds);
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
        $this->assertNotNull($first->ended_at);

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

    public function test_my_work_returns_columns(): void
    {
        ['staff' => $staff] = $this->setupProjectWithTask();

        Sanctum::actingAs($staff);

        $this->getJson(route('api.desktop.my-work'))
            ->assertOk()
            ->assertJsonStructure(['columns', 'status_options']);
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
