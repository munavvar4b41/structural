<?php

namespace Tests\Feature\Admin;

use App\Enums\ProjectTaskStatus;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskChecklistItem;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectTaskChecklistItemTest extends TestCase
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
            ]);

        return compact('team', 'head', 'staff', 'client', 'project', 'task');
    }

    private function taskShowUrl(Project $project, ProjectTask $task): string
    {
        return route('admin.projects.tasks.show', [$project, $task]);
    }

    public function test_task_show_includes_checklist(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();

        $item = ProjectTaskChecklistItem::factory()->forTask($task)->create([
            'title' => 'Ship fix',
            'is_completed' => false,
        ]);

        $this->actingAs($staff)
            ->get(route('admin.projects.tasks.show', [$project, $task]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/projects/tasks/Show')
                ->has('checklist', fn (Assert $checklist) => $checklist
                    ->where('can_manage', true)
                    ->has('items', 1, fn (Assert $row) => $row
                        ->where('id', $item->id)
                        ->where('title', 'Ship fix')
                        ->where('is_completed', false)
                    )
                )
            );
    }

    public function test_index_redirects_to_task_show_without_inertia(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();

        $this->actingAs($staff)
            ->get(route('admin.projects.tasks.checklist-items.index', [$project, $task]))
            ->assertRedirect($this->taskShowUrl($project, $task));
    }

    public function test_staff_can_create_checklist_item(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();

        $this->actingAs($staff)
            ->from($this->taskShowUrl($project, $task))
            ->post(route('admin.projects.tasks.checklist-items.store', [$project, $task]), [
                'title' => 'Write tests',
            ])
            ->assertRedirect();

        $item = ProjectTaskChecklistItem::query()->firstOrFail();
        $this->assertSame('Write tests', $item->title);
        $this->assertFalse($item->is_completed);
        $this->assertSame($task->id, $item->project_task_id);
    }

    public function test_staff_can_update_title_and_completion(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();

        $item = ProjectTaskChecklistItem::factory()->forTask($task)->create([
            'title' => 'Old title',
            'is_completed' => false,
        ]);

        $this->actingAs($staff)
            ->from($this->taskShowUrl($project, $task))
            ->patch(route('admin.projects.tasks.checklist-items.update', [$project, $task, $item]), [
                'title' => 'New title',
                'is_completed' => true,
            ])
            ->assertRedirect();

        $fresh = $item->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame('New title', $fresh->title);
        $this->assertTrue($fresh->is_completed);
    }

    public function test_staff_can_toggle_completion_only(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();

        $item = ProjectTaskChecklistItem::factory()->forTask($task)->create([
            'is_completed' => false,
        ]);

        $this->actingAs($staff)
            ->from($this->taskShowUrl($project, $task))
            ->patch(route('admin.projects.tasks.checklist-items.update', [$project, $task, $item]), [
                'is_completed' => true,
            ])
            ->assertRedirect();

        $this->assertTrue($item->fresh()->is_completed);
    }

    public function test_staff_can_delete_checklist_item(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();

        $item = ProjectTaskChecklistItem::factory()->forTask($task)->create();

        $this->actingAs($staff)
            ->from($this->taskShowUrl($project, $task))
            ->delete(route('admin.projects.tasks.checklist-items.destroy', [$project, $task, $item]))
            ->assertRedirect();

        $this->assertNull($item->fresh());
    }

    public function test_client_cannot_create_or_delete_checklist_items(): void
    {
        ['client' => $client, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();

        $item = ProjectTaskChecklistItem::factory()->forTask($task)->create();

        $this->actingAs($client)
            ->from($this->taskShowUrl($project, $task))
            ->post(route('admin.projects.tasks.checklist-items.store', [$project, $task]), [
                'title' => 'Blocked',
            ])
            ->assertForbidden();

        $this->actingAs($client)
            ->from($this->taskShowUrl($project, $task))
            ->delete(route('admin.projects.tasks.checklist-items.destroy', [$project, $task, $item]))
            ->assertForbidden();

        $this->assertSame(1, ProjectTaskChecklistItem::query()->count());
    }

    public function test_client_can_index_but_cannot_update_checklist(): void
    {
        ['client' => $client, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();

        $item = ProjectTaskChecklistItem::factory()->forTask($task)->create();

        $this->actingAs($client)
            ->get(route('admin.projects.tasks.checklist-items.index', [$project, $task]))
            ->assertRedirect($this->taskShowUrl($project, $task));

        $this->actingAs($client)
            ->from($this->taskShowUrl($project, $task))
            ->patch(route('admin.projects.tasks.checklist-items.update', [$project, $task, $item]), [
                'is_completed' => true,
            ])
            ->assertForbidden();
    }

    public function test_item_from_other_task_returns_not_found(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();

        $otherTask = ProjectTask::factory()->forProject($project)->create();
        $item = ProjectTaskChecklistItem::factory()->forTask($otherTask)->create();

        $this->actingAs($staff)
            ->from($this->taskShowUrl($project, $task))
            ->patch(route('admin.projects.tasks.checklist-items.update', [$project, $task, $item]), [
                'is_completed' => true,
            ])
            ->assertNotFound();
    }

    public function test_update_requires_at_least_one_field(): void
    {
        ['staff' => $staff, 'project' => $project, 'task' => $task] = $this->setupProjectWithTask();

        $item = ProjectTaskChecklistItem::factory()->forTask($task)->create();

        $this->actingAs($staff)
            ->from($this->taskShowUrl($project, $task))
            ->patch(route('admin.projects.tasks.checklist-items.update', [$project, $task, $item]), [])
            ->assertSessionHasErrors('title');
    }
}
