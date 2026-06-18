<?php

namespace Tests\Feature\Admin;

use App\Enums\ProjectProposalStatus;
use App\Models\Project;
use App\Models\ProjectProposal;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProposalIndexTest extends TestCase
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

    public function test_team_head_can_view_global_proposals_index(): void
    {
        extract($this->projectWithTeamHead());

        ProjectProposal::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $head->id,
            'title' => 'Visible global proposal',
        ]);

        $this->actingAs($head)
            ->get(route('admin.proposals.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/proposals/Index')
                ->has('proposals.data', 1)
                ->where('proposals.data.0.title', 'Visible global proposal'));
    }

    public function test_global_proposals_index_only_shows_proposals_for_visible_projects(): void
    {
        extract($this->projectWithTeamHead());

        $otherTeam = Team::factory()->create();
        $otherHead = User::factory()->teamHead()->withPrimaryTeam($otherTeam)->create();
        $otherClient = User::factory()->client()->create();
        $hiddenProject = Project::factory()->create(['client_user_id' => $otherClient->id]);
        $hiddenProject->teams()->sync([$otherTeam->id]);

        ProjectProposal::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $head->id,
            'title' => 'Visible proposal',
        ]);

        ProjectProposal::factory()->create([
            'project_id' => $hiddenProject->id,
            'created_by_user_id' => $otherHead->id,
            'title' => 'Hidden proposal',
        ]);

        $this->actingAs($head)
            ->get(route('admin.proposals.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/proposals/Index')
                ->where('proposals.data', fn ($rows): bool => collect($rows)
                    ->pluck('title')
                    ->contains('Visible proposal'))
                ->where('proposals.data', fn ($rows): bool => ! collect($rows)
                    ->pluck('title')
                    ->contains('Hidden proposal')));
    }

    public function test_project_filter_narrows_global_proposals_index(): void
    {
        extract($this->projectWithTeamHead());

        $secondProject = Project::factory()->create(['client_user_id' => $client->id]);
        $secondProject->teams()->sync([$team->id]);

        ProjectProposal::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $head->id,
            'title' => 'First project proposal',
        ]);

        ProjectProposal::factory()->create([
            'project_id' => $secondProject->id,
            'created_by_user_id' => $head->id,
            'title' => 'Second project proposal',
        ]);

        $this->actingAs($head)
            ->get(route('admin.proposals.index', ['project_id' => $secondProject->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/proposals/Index')
                ->where('filters.project_id', (string) $secondProject->id)
                ->where('proposals.data', fn ($rows): bool => collect($rows)
                    ->pluck('title')
                    ->contains('Second project proposal'))
                ->where('proposals.data', fn ($rows): bool => ! collect($rows)
                    ->pluck('title')
                    ->contains('First project proposal')));
    }

    public function test_status_filter_narrows_global_proposals_index(): void
    {
        extract($this->projectWithTeamHead());

        ProjectProposal::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $head->id,
            'title' => 'Draft proposal',
            'status' => ProjectProposalStatus::Draft,
        ]);

        ProjectProposal::factory()->pending()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $head->id,
            'title' => 'Pending proposal',
        ]);

        $this->actingAs($head)
            ->get(route('admin.proposals.index', ['status' => ProjectProposalStatus::Pending->value]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/proposals/Index')
                ->where('filters.status', ProjectProposalStatus::Pending->value)
                ->where('proposals.data', fn ($rows): bool => collect($rows)
                    ->pluck('title')
                    ->contains('Pending proposal'))
                ->where('proposals.data', fn ($rows): bool => ! collect($rows)
                    ->pluck('title')
                    ->contains('Draft proposal')));
    }
}
