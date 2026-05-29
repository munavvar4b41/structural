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

class TaskIndexTest extends TestCase
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

    public function test_team_head_can_view_global_tasks_index(): void
    {
        extract($this->projectWithTeamHead());

        ProjectTask::factory()
            ->forProject($project)
            ->create([
                'created_by_user_id' => $head->id,
                'status' => ProjectTaskStatus::ToDo,
                'title' => 'Visible global task',
            ]);

        $this->actingAs($head)
            ->get(route('admin.tasks.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/tasks/Index')
                ->has('tasks')
                ->where('tasks.0.title', 'Visible global task'));
    }

    public function test_global_tasks_index_only_shows_tasks_for_visible_projects(): void
    {
        extract($this->projectWithTeamHead());

        $otherTeam = Team::factory()->create();
        $otherHead = User::factory()->teamHead()->withPrimaryTeam($otherTeam)->create();
        $otherClient = User::factory()->client()->create();
        $hiddenProject = Project::factory()->create(['client_user_id' => $otherClient->id]);
        $hiddenProject->teams()->sync([$otherTeam->id]);

        ProjectTask::factory()
            ->forProject($project)
            ->create([
                'created_by_user_id' => $head->id,
                'status' => ProjectTaskStatus::ToDo,
                'title' => 'Visible task',
            ]);

        ProjectTask::factory()
            ->forProject($hiddenProject)
            ->create([
                'created_by_user_id' => $otherHead->id,
                'status' => ProjectTaskStatus::ToDo,
                'title' => 'Hidden task',
            ]);

        $this->actingAs($head)
            ->get(route('admin.tasks.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/tasks/Index')
                ->where('tasks', fn ($tasks): bool => collect($tasks)
                    ->pluck('title')
                    ->contains('Visible task'))
                ->where('tasks', fn ($tasks): bool => ! collect($tasks)
                    ->pluck('title')
                    ->contains('Hidden task')));
    }

    public function test_project_filter_narrows_global_tasks_index(): void
    {
        extract($this->projectWithTeamHead());

        $secondProject = Project::factory()->create(['client_user_id' => $client->id]);
        $secondProject->teams()->sync([$team->id]);

        ProjectTask::factory()
            ->forProject($project)
            ->create([
                'created_by_user_id' => $head->id,
                'status' => ProjectTaskStatus::ToDo,
                'title' => 'First project task',
            ]);

        ProjectTask::factory()
            ->forProject($secondProject)
            ->create([
                'created_by_user_id' => $head->id,
                'status' => ProjectTaskStatus::ToDo,
                'title' => 'Second project task',
            ]);

        $this->actingAs($head)
            ->get(route('admin.tasks.index', ['project_id' => $secondProject->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/tasks/Index')
                ->where('filters.project_id', (string) $secondProject->id)
                ->where('tasks', fn ($tasks): bool => collect($tasks)
                    ->pluck('title')
                    ->contains('Second project task'))
                ->where('tasks', fn ($tasks): bool => ! collect($tasks)
                    ->pluck('title')
                    ->contains('First project task')));
    }

    public function test_can_create_task_from_global_tasks_page_for_selected_project(): void
    {
        extract($this->projectWithTeamHead());

        $this->actingAs($head)
            ->from(route('admin.tasks.index', ['project_id' => $project->id]))
            ->post(route('admin.projects.tasks.store', $project), [
                'title' => 'Created from global page',
                'description' => null,
                'status' => ProjectTaskStatus::ToDo->value,
                'assignee_user_id' => null,
                'project_requirement_id' => null,
                'parent_project_task_id' => null,
                'estimated_minutes' => null,
            ])
            ->assertRedirect(route('admin.tasks.index', ['project_id' => $project->id]));

        $this->assertDatabaseHas('project_tasks', [
            'project_id' => $project->id,
            'title' => 'Created from global page',
            'created_by_user_id' => $head->id,
        ]);
    }
}
