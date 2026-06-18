<?php

namespace Tests\Feature\Api\Desktop;

use App\Enums\ProjectTaskStatus;
use App\Enums\TimeEntrySource;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskReview;
use App\Models\TaskTimeEntry;
use App\Models\Team;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DesktopTaskApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{team: Team, head: User, staff: User, client: User, project: Project, task: ProjectTask}
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
                'title' => 'Desktop task',
            ]);

        return compact('team', 'head', 'staff', 'client', 'project', 'task');
    }

    public function test_task_endpoints_require_authentication(): void
    {
        ['project' => $project, 'task' => $task] = $this->setupProjectWithTask();

        $this->getJson(route('api.desktop.projects.tasks.show', [$project, $task]))->assertUnauthorized();
        $this->getJson(route('api.desktop.projects.tasks.form-options', $project))->assertUnauthorized();
        $this->getJson(route('api.desktop.notifications.index'))->assertUnauthorized();
    }

    public function test_task_show_returns_full_payload(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();

        Sanctum::actingAs($staff);

        $this->getJson(route('api.desktop.projects.tasks.show', [$project, $task]))
            ->assertOk()
            ->assertJsonStructure([
                'project' => ['id', 'name', 'code', 'estimation_required'],
                'task' => [
                    'id', 'title', 'status', 'status_label', 'can_update', 'can_delete',
                    'can_submit_task_completion', 'subtasks',
                ],
                'checklist' => ['can_manage', 'items'],
                'time_tracking' => ['can_track', 'totals', 'entries'],
                'can_manage_project',
            ])
            ->assertJsonPath('task.title', 'Desktop task');
    }

    public function test_form_options_returns_metadata(): void
    {
        ['head' => $head, 'project' => $project] = $this->setupProjectWithTask();

        Sanctum::actingAs($head);

        $this->getJson(route('api.desktop.projects.tasks.form-options', $project))
            ->assertOk()
            ->assertJsonStructure([
                'project',
                'status_options',
                'assignable_users',
                'requirements',
                'parent_tasks',
                'can_create_tasks',
                'can_manage_project',
            ])
            ->assertJsonPath('can_create_tasks', true);
    }

    public function test_head_can_create_task(): void
    {
        ['head' => $head, 'staff' => $staff, 'project' => $project] = $this->setupProjectWithTask();

        Sanctum::actingAs($head);

        $this->postJson(route('api.desktop.projects.tasks.store', $project), [
            'title' => 'New desktop task',
            'status' => ProjectTaskStatus::ToDo->value,
            'assignee_user_id' => $staff->id,
        ])
            ->assertOk()
            ->assertJsonPath('task.title', 'New desktop task');

        $this->assertDatabaseHas('project_tasks', [
            'project_id' => $project->id,
            'title' => 'New desktop task',
            'assignee_user_id' => $staff->id,
        ]);

        $staff->refresh();
        $this->assertCount(1, $staff->notifications);
        $this->assertSame(TaskAssignedNotification::class, $staff->notifications->first()->type);
    }

    public function test_staff_can_update_assigned_task_status(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();

        Sanctum::actingAs($staff);

        $this->patchJson(route('api.desktop.projects.tasks.update', [$project, $task]), [
            'status' => ProjectTaskStatus::InProgress->value,
        ])
            ->assertOk()
            ->assertJsonPath('task.status', ProjectTaskStatus::InProgress->value);
    }

    public function test_head_can_delete_task(): void
    {
        ['head' => $head, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();

        Sanctum::actingAs($head);

        $this->deleteJson(route('api.desktop.projects.tasks.destroy', [$project, $task]))
            ->assertOk()
            ->assertJsonPath('deleted', true);

        $this->assertDatabaseMissing('project_tasks', ['id' => $task->id]);
    }

    public function test_assignee_can_submit_completion(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();
        $task->update(['status' => ProjectTaskStatus::InProgress]);

        Sanctum::actingAs($staff);

        $this->postJson(route('api.desktop.projects.tasks.submit-completion', [$project, $task]))
            ->assertOk()
            ->assertJsonPath('task.status', ProjectTaskStatus::Review->value);
    }

    public function test_team_head_can_confirm_completion(): void
    {
        ['head' => $head, 'staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();
        $task->update([
            'status' => ProjectTaskStatus::Review,
            'completion_submitted_at' => now(),
            'completion_submitted_by_user_id' => $staff->id,
        ]);

        Sanctum::actingAs($head);

        $this->postJson(route('api.desktop.projects.tasks.confirm-completion', [$project, $task]), [
            'review_notes' => 'Looks good.',
            'task_rating' => 4,
            'assignee_rating' => 5,
            'creator_rating' => 3,
        ])
            ->assertOk()
            ->assertJsonPath('task.status', ProjectTaskStatus::Done->value);

        $this->assertSame(1, ProjectTaskReview::query()->count());
    }

    public function test_staff_can_manage_checklist(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();

        Sanctum::actingAs($staff);

        $create = $this->postJson(route('api.desktop.projects.tasks.checklist-items.store', [$project, $task]), [
            'title' => 'Check item',
        ])->assertOk();

        $itemId = collect($create->json('checklist.items'))->first()['id'];

        $this->patchJson(route('api.desktop.projects.tasks.checklist-items.update', [$project, $task, $itemId]), [
            'is_completed' => true,
        ])
            ->assertOk()
            ->assertJsonPath('checklist.items.0.is_completed', true);

        $this->deleteJson(route('api.desktop.projects.tasks.checklist-items.destroy', [$project, $task, $itemId]))
            ->assertOk()
            ->assertJsonPath('checklist.items', []);
    }

    public function test_staff_can_create_manual_time_entry(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();
        Carbon::setTestNow(Carbon::parse('2026-05-07 12:00:00'));

        Sanctum::actingAs($staff);

        $this->postJson(route('api.desktop.projects.tasks.time-entries.store', [$project, $task]), [
            'started_at' => '2026-05-07 09:00:00',
            'ended_at' => '2026-05-07 10:30:00',
            'notes' => 'Manual work',
        ])
            ->assertOk()
            ->assertJsonPath('time_tracking.entries.0.notes', 'Manual work');

        $entry = TaskTimeEntry::query()->firstOrFail();
        $this->assertSame(TimeEntrySource::Manual, $entry->source);
        $this->assertSame(90 * 60, $entry->duration_seconds);
    }

    public function test_staff_can_update_and_delete_time_entry(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();
        Carbon::setTestNow(Carbon::parse('2026-05-07 12:00:00'));

        $entry = TaskTimeEntry::factory()->forTask($task)->forUser($staff)->create([
            'source' => TimeEntrySource::Manual,
            'started_at' => Carbon::parse('2026-05-07 09:00:00'),
            'ended_at' => Carbon::parse('2026-05-07 10:00:00'),
            'duration_seconds' => 3600,
        ]);

        Sanctum::actingAs($staff);

        $this->patchJson(route('api.desktop.projects.tasks.time-entries.update', [$project, $task, $entry]), [
            'duration_minutes' => 45,
        ])
            ->assertOk();

        $this->deleteJson(route('api.desktop.projects.tasks.time-entries.destroy', [$project, $task, $entry]))
            ->assertOk();

        $this->assertDatabaseMissing('task_time_entries', ['id' => $entry->id]);
    }

    public function test_notifications_feed_returns_items_with_navigation_ids(): void
    {
        ['head' => $head, 'staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();

        $staff->notify(new TaskAssignedNotification($task->load('project')));

        Sanctum::actingAs($staff);

        $this->getJson(route('api.desktop.notifications.index'))
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonStructure([
                'unread_items' => [['id', 'title', 'project_id', 'project_task_id']],
            ])
            ->assertJsonPath('unread_items.0.project_id', $project->id)
            ->assertJsonPath('unread_items.0.project_task_id', $task->id);
    }

    public function test_notifications_can_be_marked_read(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();

        $staff->notify(new TaskAssignedNotification($task->load('project')));
        $notificationId = (string) $staff->unreadNotifications->first()->id;

        Sanctum::actingAs($staff);

        $this->patchJson(route('api.desktop.notifications.mark-as-read', $notificationId))
            ->assertOk()
            ->assertJsonPath('unread_count', 0)
            ->assertJsonPath('read_count', 1);

        $this->patchJson(route('api.desktop.notifications.mark-all-read'))
            ->assertOk();
    }

    public function test_create_task_validation_errors_return_422(): void
    {
        ['head' => $head, 'project' => $project] = $this->setupProjectWithTask();

        Sanctum::actingAs($head);

        $this->postJson(route('api.desktop.projects.tasks.store', $project), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'status']);
    }
}
