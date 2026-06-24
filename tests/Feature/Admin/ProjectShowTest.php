<?php

namespace Tests\Feature\Admin;

use App\Models\CaseStudy;
use App\Models\Project;
use App\Models\ProjectMetadata;
use App\Models\ProjectRequirement;
use App\Models\ProjectTag;
use App\Models\ProjectTask;
use App\Models\TaskTimeEntry;
use App\Models\Team;
use App\Models\User;
use App\Settings\CompanySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_head_can_view_project_show_for_assigned_project(): void
    {
        $settings = app(CompanySettings::class);
        $settings->work_day_start_time = '09:00';
        $settings->work_day_end_time = '17:00';
        $settings->save();

        $team = Team::factory()->create();
        $teamHead = User::factory()->teamHead()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id, 'name' => 'Alpha Project']);
        $project->teams()->sync([$team->id]);

        ProjectTag::factory()->create(['project_id' => $project->id, 'name' => 'manage-user']);
        ProjectMetadata::factory()->create([
            'project_id' => $project->id,
            'key' => 'framework',
            'value' => 'laravel',
        ]);
        ProjectRequirement::factory()->create(['project_id' => $project->id]);
        $task = ProjectTask::factory()->create(['project_id' => $project->id]);
        CaseStudy::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $teamHead->id,
            'title' => 'Reporting automation',
        ]);
        TaskTimeEntry::factory()->create([
            'project_id' => $project->id,
            'project_task_id' => $task->id,
            'user_id' => $teamHead->id,
        ]);

        $this->actingAs($teamHead)
            ->get(route('admin.projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/projects/Show')
                ->where('project.name', 'Alpha Project')
                ->has('tags', 1)
                ->has('metadata', 1)
                ->has('requirements')
                ->has('case_studies', 1)
                ->has('tasks')
                ->has('time_entries')
                ->where('can_create_requirements', true)
                ->where('can_create_tasks', true)
                ->where('can_view_case_studies', true)
                ->where('can_create_case_studies', true)
                ->where('can_manage_tags_metadata', true)
                ->where('working_hours.start', '09:00')
                ->where('working_hours.end', '17:00'));
    }

    public function test_staff_can_view_assigned_project_show_without_manage_permissions(): void
    {
        $team = Team::factory()->create();
        $staff = User::factory()->withPrimaryTeam($team)->create();
        $project = Project::factory()->create();
        $project->teams()->sync([$team->id]);

        $this->actingAs($staff)
            ->get(route('admin.projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can_create_requirements', false)
                ->where('can_create_tasks', true)
                ->where('can_manage_tags_metadata', false));
    }

    public function test_admin_can_manage_tags_metadata_flags_on_project_show(): void
    {
        $admin = User::factory()->admin()->create(['primary_team_id' => null]);
        $project = Project::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can_manage_project', true)
                ->where('can_manage_tags_metadata', true));
    }

    public function test_staff_cannot_view_unassigned_project_show(): void
    {
        $team = Team::factory()->create();
        $otherTeam = Team::factory()->create();
        $staff = User::factory()->withPrimaryTeam($team)->create();
        $project = Project::factory()->create();
        $project->teams()->sync([$otherTeam->id]);

        $this->actingAs($staff)
            ->get(route('admin.projects.show', $project))
            ->assertForbidden();
    }

    public function test_client_can_view_own_project_show(): void
    {
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);

        $this->actingAs($client)
            ->get(route('admin.projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/projects/Show')
                ->where('can_manage_tags_metadata', false)
                ->where('can_view_case_studies', false)
                ->has('case_studies', 0));
    }
}
