<?php

namespace Tests\Feature\Admin;

use App\Enums\ProjectTaskStatus;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MyWorkTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_my_work(): void
    {
        $this->get(route('admin.my-work.index'))
            ->assertRedirect(route('login'));
    }

    public function test_staff_sees_assigned_tasks_on_my_work_board(): void
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
                'status' => ProjectTaskStatus::InProgress,
                'title' => 'My assigned item',
            ]);

        $inProgressColumnIndex = array_search(
            ProjectTaskStatus::InProgress,
            ProjectTaskStatus::boardOrder(),
            true,
        );
        $this->assertNotFalse($inProgressColumnIndex);

        $this->actingAs($staff)
            ->get(route('admin.my-work.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/my-work/Index')
                ->has('columns')
                ->has('columns.'.$inProgressColumnIndex.'.tasks', 1)
                ->where('columns.'.$inProgressColumnIndex.'.tasks.0.title', 'My assigned item')
                ->where(
                    'columns.'.$inProgressColumnIndex.'.tasks.0.task_show_url',
                    route('admin.projects.tasks.show', [$project, $task]),
                ));
    }

    public function test_project_filter_limits_board_to_one_project(): void
    {
        $team = Team::factory()->create();
        $head = User::factory()->teamHead()->withPrimaryTeam($team)->create();
        $staff = User::factory()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $projectA = Project::factory()->create(['client_user_id' => $client->id, 'name' => 'Alpha']);
        $projectB = Project::factory()->create(['client_user_id' => $client->id, 'name' => 'Beta']);
        $projectA->teams()->sync([$team->id]);
        $projectB->teams()->sync([$team->id]);

        ProjectTask::factory()->forProject($projectA)->create([
            'created_by_user_id' => $head->id,
            'assignee_user_id' => $staff->id,
            'status' => ProjectTaskStatus::ToDo,
            'title' => 'Alpha task',
        ]);
        ProjectTask::factory()->forProject($projectB)->create([
            'created_by_user_id' => $head->id,
            'assignee_user_id' => $staff->id,
            'status' => ProjectTaskStatus::ToDo,
            'title' => 'Beta task',
        ]);

        $toDoIndex = array_search(ProjectTaskStatus::ToDo, ProjectTaskStatus::boardOrder(), true);
        $this->assertNotFalse($toDoIndex);

        $this->actingAs($staff)
            ->get(route('admin.my-work.index', ['project_id' => $projectA->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/my-work/Index')
                ->where('filters.project_id', $projectA->id)
                ->has('project_options')
                ->has('columns.'.$toDoIndex.'.tasks', 1)
                ->where('columns.'.$toDoIndex.'.tasks.0.title', 'Alpha task'));
    }

    public function test_assignee_only_staff_cannot_patch_status_to_done(): void
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

        $this->actingAs($staff)
            ->from(route('admin.my-work.index'))
            ->patch(route('admin.projects.tasks.update', [$project, $task]), [
                'status' => ProjectTaskStatus::Done->value,
            ])
            ->assertRedirect(route('admin.my-work.index'))
            ->assertSessionHasErrors('status');

        $this->assertSame(ProjectTaskStatus::ToDo, $task->fresh()->status);
    }
}
