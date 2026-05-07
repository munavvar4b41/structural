<?php

namespace Tests\Feature\Admin;

use App\Enums\ProjectTaskStatus;
use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\ProjectTask;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectTaskTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{team: Team, head: User, client: User, project: Project}
     */
    private function projectWithTeamHead(): array
    {
        $team = Team::factory()->create();
        $head = User::factory()->teamHead()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);
        $project->teams()->sync([$team->id]);

        return ['team' => $team, 'head' => $head, 'client' => $client, 'project' => $project];
    }

    public function test_team_head_can_view_tasks_index(): void
    {
        extract($this->projectWithTeamHead());

        $this->actingAs($head)
            ->get(route('admin.projects.tasks.index', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/projects/tasks/Index')
                ->has('tasks')
                ->where('can_manage_project', true));
    }

    public function test_team_head_can_view_task_show(): void
    {
        extract($this->projectWithTeamHead());

        $task = ProjectTask::factory()
            ->forProject($project)
            ->create([
                'created_by_user_id' => $head->id,
                'status' => ProjectTaskStatus::ToDo,
                'title' => 'Visible task',
            ]);

        $this->actingAs($head)
            ->get(route('admin.projects.tasks.show', [$project, $task]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/projects/tasks/Show')
                ->where('task.title', 'Visible task')
                ->where('task.parent', null));
    }

    public function test_task_show_returns_404_when_task_belongs_to_other_project(): void
    {
        extract($this->projectWithTeamHead());
        $other = Project::factory()->create(['client_user_id' => $client->id]);
        $other->teams()->sync([$team->id]);

        $task = ProjectTask::factory()
            ->forProject($other)
            ->create([
                'created_by_user_id' => $head->id,
                'status' => ProjectTaskStatus::ToDo,
            ]);

        $this->actingAs($head)
            ->get(route('admin.projects.tasks.show', [$project, $task]))
            ->assertNotFound();
    }

    public function test_client_can_view_task_show_on_own_project(): void
    {
        extract($this->projectWithTeamHead());

        $task = ProjectTask::factory()
            ->forProject($project)
            ->create([
                'created_by_user_id' => $head->id,
                'status' => ProjectTaskStatus::ToDo,
            ]);

        $this->actingAs($client)
            ->get(route('admin.projects.tasks.show', [$project, $task]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/projects/tasks/Show'));
    }

    public function test_task_show_lists_direct_subtasks(): void
    {
        extract($this->projectWithTeamHead());

        $parent = ProjectTask::factory()
            ->forProject($project)
            ->create([
                'created_by_user_id' => $head->id,
                'status' => ProjectTaskStatus::ToDo,
                'title' => 'Parent work',
            ]);

        $child = ProjectTask::factory()
            ->forProject($project)
            ->childOf($parent)
            ->create([
                'created_by_user_id' => $head->id,
                'status' => ProjectTaskStatus::InProgress,
                'title' => 'Child step',
            ]);

        $this->actingAs($head)
            ->get(route('admin.projects.tasks.show', [$project, $parent]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/projects/tasks/Show')
                ->has('task.subtasks', 1)
                ->where('task.subtasks.0.id', $child->id)
                ->where('task.subtasks.0.title', 'Child step')
                ->where('task.subtasks.0.status_label', ProjectTaskStatus::InProgress->label()));
    }

    public function test_staff_on_project_team_can_store_task(): void
    {
        extract($this->projectWithTeamHead());
        $staff = User::factory()->withPrimaryTeam($team)->create();

        $this->actingAs($staff)
            ->from(route('admin.projects.tasks.index', $project))
            ->post(route('admin.projects.tasks.store', $project), [
                'title' => 'Staff logged task',
                'description' => null,
                'status' => ProjectTaskStatus::ToDo->value,
                'assignee_user_id' => null,
                'project_requirement_id' => null,
                'parent_project_task_id' => null,
                'estimated_minutes' => null,
            ])
            ->assertRedirect(route('admin.projects.tasks.index', $project));

        $this->assertDatabaseHas('project_tasks', [
            'project_id' => $project->id,
            'title' => 'Staff logged task',
            'created_by_user_id' => $staff->id,
        ]);
    }

    public function test_client_cannot_store_task_on_own_project(): void
    {
        extract($this->projectWithTeamHead());

        $this->actingAs($client)
            ->from(route('admin.projects.requirements.index', $project))
            ->post(route('admin.projects.tasks.store', $project), [
                'title' => 'Client task',
                'status' => ProjectTaskStatus::ToDo->value,
            ])
            ->assertForbidden();
    }

    public function test_team_head_can_store_task_without_requirement(): void
    {
        extract($this->projectWithTeamHead());

        $this->actingAs($head)
            ->from(route('admin.projects.tasks.index', $project))
            ->post(route('admin.projects.tasks.store', $project), [
                'title' => 'General task',
                'description' => null,
                'status' => ProjectTaskStatus::ToDo->value,
                'assignee_user_id' => null,
                'project_requirement_id' => null,
                'parent_project_task_id' => null,
                'estimated_minutes' => null,
            ])
            ->assertRedirect(route('admin.projects.tasks.index', $project));

        $this->assertDatabaseHas('project_tasks', [
            'project_id' => $project->id,
            'title' => 'General task',
            'project_requirement_id' => null,
        ]);
    }

    public function test_estimation_required_project_rejects_missing_estimate_on_store(): void
    {
        extract($this->projectWithTeamHead());
        $project->update(['estimation_required' => true]);

        $this->actingAs($head)
            ->from(route('admin.projects.tasks.index', $project))
            ->post(route('admin.projects.tasks.store', $project), [
                'title' => 'Needs estimate',
                'status' => ProjectTaskStatus::ToDo->value,
            ])
            ->assertSessionHasErrors('estimated_minutes');
    }

    public function test_subtask_must_match_parent_requirement_link(): void
    {
        extract($this->projectWithTeamHead());
        $reqA = ProjectRequirement::factory()->create(['project_id' => $project->id, 'created_by_user_id' => $client->id]);
        $reqB = ProjectRequirement::factory()->create(['project_id' => $project->id, 'created_by_user_id' => $client->id]);

        $parent = ProjectTask::factory()
            ->forProject($project)
            ->create([
                'created_by_user_id' => $head->id,
                'project_requirement_id' => $reqA->id,
                'parent_project_task_id' => null,
                'status' => ProjectTaskStatus::ToDo,
            ]);

        $this->actingAs($head)
            ->from(route('admin.projects.tasks.index', $project))
            ->post(route('admin.projects.tasks.store', $project), [
                'title' => 'Child',
                'status' => ProjectTaskStatus::ToDo->value,
                'parent_project_task_id' => $parent->id,
                'project_requirement_id' => $reqB->id,
                'estimated_minutes' => 30,
            ])
            ->assertSessionHasErrors('project_requirement_id');
    }

    public function test_team_head_can_store_subtask_under_existing_subtask(): void
    {
        extract($this->projectWithTeamHead());

        $parent = ProjectTask::factory()
            ->forProject($project)
            ->create([
                'created_by_user_id' => $head->id,
                'status' => ProjectTaskStatus::ToDo,
                'title' => 'Parent',
            ]);

        $child = ProjectTask::factory()
            ->forProject($project)
            ->childOf($parent)
            ->create([
                'created_by_user_id' => $head->id,
                'status' => ProjectTaskStatus::InProgress,
                'title' => 'Child',
            ]);

        $this->actingAs($head)
            ->from(route('admin.projects.tasks.index', $project))
            ->post(route('admin.projects.tasks.store', $project), [
                'title' => 'Grandchild',
                'status' => ProjectTaskStatus::ToDo->value,
                'parent_project_task_id' => $child->id,
                'estimated_minutes' => 15,
            ])
            ->assertRedirect(route('admin.projects.tasks.index', $project));

        $this->assertDatabaseHas('project_tasks', [
            'project_id' => $project->id,
            'title' => 'Grandchild',
            'parent_project_task_id' => $child->id,
        ]);
    }

    public function test_update_rejects_moving_task_under_its_descendant(): void
    {
        extract($this->projectWithTeamHead());

        $parent = ProjectTask::factory()
            ->forProject($project)
            ->create([
                'created_by_user_id' => $head->id,
                'status' => ProjectTaskStatus::ToDo,
            ]);

        $child = ProjectTask::factory()
            ->forProject($project)
            ->childOf($parent)
            ->create([
                'created_by_user_id' => $head->id,
                'status' => ProjectTaskStatus::InProgress,
            ]);

        $grandchild = ProjectTask::factory()
            ->forProject($project)
            ->childOf($child)
            ->create([
                'created_by_user_id' => $head->id,
                'status' => ProjectTaskStatus::Done,
            ]);

        $this->actingAs($head)
            ->from(route('admin.projects.tasks.index', $project))
            ->patch(route('admin.projects.tasks.update', [$project, $parent]), [
                'title' => $parent->title,
                'status' => $parent->status->value,
                'parent_project_task_id' => $grandchild->id,
            ])
            ->assertSessionHasErrors('parent_project_task_id');
    }

    public function test_scoped_update_returns_404_when_task_belongs_to_other_project(): void
    {
        extract($this->projectWithTeamHead());
        $other = Project::factory()->create(['client_user_id' => $client->id]);
        $other->teams()->sync([$team->id]);

        $task = ProjectTask::factory()
            ->forProject($other)
            ->create([
                'created_by_user_id' => $head->id,
                'status' => ProjectTaskStatus::ToDo,
            ]);

        $this->actingAs($head)
            ->patch(route('admin.projects.tasks.update', [$project, $task]), [
                'title' => $task->title,
                'status' => ProjectTaskStatus::InProgress->value,
            ])
            ->assertNotFound();
    }

    public function test_staff_assignee_who_is_not_creator_cannot_change_title(): void
    {
        extract($this->projectWithTeamHead());
        $staff = User::factory()->withPrimaryTeam($team)->create();

        $task = ProjectTask::factory()
            ->forProject($project)
            ->create([
                'created_by_user_id' => $head->id,
                'assignee_user_id' => $staff->id,
                'status' => ProjectTaskStatus::ToDo,
                'title' => 'Original',
            ]);

        $this->actingAs($staff)
            ->from(route('admin.projects.tasks.index', $project))
            ->patch(route('admin.projects.tasks.update', [$project, $task]), [
                'title' => 'Changed',
                'status' => ProjectTaskStatus::InProgress->value,
            ])
            ->assertSessionHasErrors('title');
    }

    public function test_staff_creator_can_update_all_fields_including_title(): void
    {
        extract($this->projectWithTeamHead());
        $staff = User::factory()->withPrimaryTeam($team)->create();

        $task = ProjectTask::factory()
            ->forProject($project)
            ->create([
                'created_by_user_id' => $staff->id,
                'assignee_user_id' => $head->id,
                'status' => ProjectTaskStatus::ToDo,
                'title' => 'Original',
            ]);

        $this->actingAs($staff)
            ->from(route('admin.projects.tasks.index', $project))
            ->patch(route('admin.projects.tasks.update', [$project, $task]), [
                'title' => 'Renamed by creator',
                'status' => ProjectTaskStatus::InProgress->value,
            ])
            ->assertRedirect(route('admin.projects.tasks.index', $project));

        $this->assertSame('Renamed by creator', $task->fresh()->title);
    }

    public function test_staff_creator_can_delete_own_task(): void
    {
        extract($this->projectWithTeamHead());
        $staff = User::factory()->withPrimaryTeam($team)->create();

        $task = ProjectTask::factory()
            ->forProject($project)
            ->create([
                'created_by_user_id' => $staff->id,
                'status' => ProjectTaskStatus::ToDo,
            ]);

        $this->actingAs($staff)
            ->from(route('admin.projects.tasks.index', $project))
            ->delete(route('admin.projects.tasks.destroy', [$project, $task]))
            ->assertRedirect(route('admin.projects.tasks.index', $project));

        $this->assertDatabaseMissing('project_tasks', ['id' => $task->id]);
    }

    public function test_staff_assignee_cannot_delete_task_they_did_not_create(): void
    {
        extract($this->projectWithTeamHead());
        $staff = User::factory()->withPrimaryTeam($team)->create();

        $task = ProjectTask::factory()
            ->forProject($project)
            ->create([
                'created_by_user_id' => $head->id,
                'assignee_user_id' => $staff->id,
                'status' => ProjectTaskStatus::ToDo,
            ]);

        $this->actingAs($staff)
            ->delete(route('admin.projects.tasks.destroy', [$project, $task]))
            ->assertForbidden();
    }

    public function test_staff_assignee_can_update_status_only(): void
    {
        extract($this->projectWithTeamHead());
        $staff = User::factory()->withPrimaryTeam($team)->create();

        $task = ProjectTask::factory()
            ->forProject($project)
            ->create([
                'created_by_user_id' => $head->id,
                'assignee_user_id' => $staff->id,
                'status' => ProjectTaskStatus::ToDo,
                'title' => 'Original',
            ]);

        $this->actingAs($staff)
            ->from(route('admin.projects.tasks.index', $project))
            ->patch(route('admin.projects.tasks.update', [$project, $task]), [
                'status' => ProjectTaskStatus::InProgress->value,
            ])
            ->assertRedirect(route('admin.projects.tasks.index', $project));

        $this->assertSame(ProjectTaskStatus::InProgress, $task->fresh()->status);
    }
}
