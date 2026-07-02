<?php

namespace Tests\Feature\Admin;

use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RequirementIndexTest extends TestCase
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

    public function test_team_head_can_view_global_requirements_index(): void
    {
        extract($this->projectWithTeamHead());

        ProjectRequirement::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $head->id,
            'title' => 'Visible global requirement',
        ]);

        $this->actingAs($head)
            ->get(route('admin.requirements.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/requirements/Index')
                ->has('requirements.data', 1)
                ->where('requirements.data.0.title', 'Visible global requirement'));
    }

    public function test_global_requirements_index_only_shows_requirements_for_visible_projects(): void
    {
        extract($this->projectWithTeamHead());

        $otherTeam = Team::factory()->create();
        $otherHead = User::factory()->teamHead()->withPrimaryTeam($otherTeam)->create();
        $otherClient = User::factory()->client()->create();
        $hiddenProject = Project::factory()->create(['client_user_id' => $otherClient->id]);
        $hiddenProject->teams()->sync([$otherTeam->id]);

        ProjectRequirement::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $head->id,
            'title' => 'Visible requirement',
        ]);

        ProjectRequirement::factory()->create([
            'project_id' => $hiddenProject->id,
            'created_by_user_id' => $otherHead->id,
            'title' => 'Hidden requirement',
        ]);

        $this->actingAs($head)
            ->get(route('admin.requirements.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/requirements/Index')
                ->where('requirements.data', fn ($rows): bool => collect($rows)
                    ->pluck('title')
                    ->contains('Visible requirement'))
                ->where('requirements.data', fn ($rows): bool => ! collect($rows)
                    ->pluck('title')
                    ->contains('Hidden requirement')));
    }

    public function test_project_filter_narrows_global_requirements_index(): void
    {
        extract($this->projectWithTeamHead());

        $secondProject = Project::factory()->create(['client_user_id' => $client->id]);
        $secondProject->teams()->sync([$team->id]);

        ProjectRequirement::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $head->id,
            'title' => 'First project requirement',
        ]);

        ProjectRequirement::factory()->create([
            'project_id' => $secondProject->id,
            'created_by_user_id' => $head->id,
            'title' => 'Second project requirement',
        ]);

        $this->actingAs($head)
            ->get(route('admin.requirements.index', ['project_id' => $secondProject->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/requirements/Index')
                ->where('filters.project_id', (string) $secondProject->id)
                ->where('requirements.data', fn ($rows): bool => collect($rows)
                    ->pluck('title')
                    ->contains('Second project requirement'))
                ->where('requirements.data', fn ($rows): bool => ! collect($rows)
                    ->pluck('title')
                    ->contains('First project requirement')));
    }

    public function test_review_status_filter_narrows_global_requirements_index(): void
    {
        extract($this->projectWithTeamHead());

        ProjectRequirement::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $head->id,
            'title' => 'Pending review requirement',
            'reviewed_at' => null,
        ]);

        ProjectRequirement::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $head->id,
            'title' => 'Reviewed requirement',
            'reviewed_at' => now(),
            'review_understanding' => 'Understood.',
        ]);

        $this->actingAs($head)
            ->get(route('admin.requirements.index', ['review_status' => 'pending_review']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/requirements/Index')
                ->where('filters.review_status', 'pending_review')
                ->where('requirements.data', fn ($rows): bool => collect($rows)
                    ->pluck('title')
                    ->contains('Pending review requirement'))
                ->where('requirements.data', fn ($rows): bool => ! collect($rows)
                    ->pluck('title')
                    ->contains('Reviewed requirement')));
    }
}
