<?php

namespace Tests\Feature\Admin;

use App\Enums\ProjectProposalStatus;
use App\Models\Project;
use App\Models\ProjectProposal;
use App\Models\ProjectRequirement;
use App\Models\Team;
use App\Models\User;
use App\Notifications\ProjectProposalDiscussionNotification;
use App\Notifications\ProjectProposalReviewedNotification;
use App\Notifications\ProjectProposalSubmittedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectProposalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{team: Team, project: Project, staff: User, client: User, teamHead: User}
     */
    private function projectFixture(): array
    {
        $team = Team::factory()->create();
        $staff = User::factory()->withPrimaryTeam($team)->create();
        $teamHead = User::factory()->teamHead()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);
        $project->teams()->sync([$team->id]);

        return compact('team', 'project', 'staff', 'client', 'teamHead');
    }

    private function tipTapDocument(string $text): string
    {
        return (string) json_encode([
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => $text],
                    ],
                ],
            ],
        ]);
    }

    public function test_staff_on_project_can_create_proposal(): void
    {
        $fixture = $this->projectFixture();

        $description = $this->tipTapDocument('Add export to CSV.');

        $this->actingAs($fixture['staff'])
            ->post(route('admin.projects.proposals.store', $fixture['project']), [
                'title' => 'New feature proposal',
                'description' => $description,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('project_proposals', [
            'project_id' => $fixture['project']->id,
            'created_by_user_id' => $fixture['staff']->id,
            'title' => 'New feature proposal',
            'description' => $description,
            'status' => ProjectProposalStatus::Draft->value,
        ]);
    }

    public function test_staff_without_project_access_cannot_create_proposal(): void
    {
        $teamA = Team::factory()->create();
        $teamB = Team::factory()->create();
        $staff = User::factory()->withPrimaryTeam($teamA)->create();
        $project = Project::factory()->create();
        $project->teams()->sync([$teamB->id]);

        $this->actingAs($staff)
            ->post(route('admin.projects.proposals.store', $project), [
                'title' => 'Blocked',
                'description' => 'Should not save.',
            ])
            ->assertForbidden();
    }

    public function test_create_with_linked_requirement_seeds_title_and_description_on_form(): void
    {
        $fixture = $this->projectFixture();
        $requirement = ProjectRequirement::factory()->create([
            'project_id' => $fixture['project']->id,
            'title' => 'Billing module',
            'description' => '{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"Invoice exports"}]}]}',
        ]);

        $this->actingAs($fixture['staff'])
            ->get(route('admin.projects.proposals.create', $fixture['project']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/projects/proposals/Create')
                ->has('requirement_options', 1)
                ->where('requirement_options.0.value', $requirement->id)
                ->where('requirement_options.0.title', 'Billing module')
                ->where('requirement_options.0.description', $requirement->description));
    }

    public function test_proposal_workflow_submit_confirm_and_reopen(): void
    {
        $fixture = $this->projectFixture();
        $proposal = ProjectProposal::factory()->create([
            'project_id' => $fixture['project']->id,
            'created_by_user_id' => $fixture['staff']->id,
            'title' => 'Workflow proposal',
            'description' => $this->tipTapDocument('Details here.'),
        ]);

        $this->actingAs($fixture['staff'])
            ->patch(route('admin.projects.proposals.submit', [$fixture['project'], $proposal]))
            ->assertRedirect();

        $proposal->refresh();
        $this->assertSame(ProjectProposalStatus::Pending, $proposal->status);

        $this->actingAs($fixture['client'])
            ->patch(route('admin.projects.proposals.confirm', [$fixture['project'], $proposal]), [
                'review_notes' => 'Looks good.',
            ])
            ->assertRedirect();

        $proposal->refresh();
        $this->assertSame(ProjectProposalStatus::Confirmed, $proposal->status);
        $this->assertNotNull($proposal->transferred_project_requirement_id);

        $this->actingAs($fixture['teamHead'])
            ->patch(route('admin.projects.proposals.reopen', [$fixture['project'], $proposal]))
            ->assertRedirect();

        $proposal->refresh();
        $this->assertSame(ProjectProposalStatus::Draft, $proposal->status);
        $this->assertNull($proposal->reviewed_at);
    }

    public function test_staff_cannot_confirm_or_reject_proposal(): void
    {
        $fixture = $this->projectFixture();
        $proposal = ProjectProposal::factory()->pending()->create([
            'project_id' => $fixture['project']->id,
            'created_by_user_id' => $fixture['staff']->id,
        ]);

        $this->actingAs($fixture['staff'])
            ->patch(route('admin.projects.proposals.confirm', [$fixture['project'], $proposal]))
            ->assertForbidden();

        $this->actingAs($fixture['staff'])
            ->patch(route('admin.projects.proposals.reject', [$fixture['project'], $proposal]), [
                'rejection_reason' => 'No',
            ])
            ->assertForbidden();
    }

    public function test_client_can_confirm_on_own_project(): void
    {
        $fixture = $this->projectFixture();
        $proposal = ProjectProposal::factory()->pending()->create([
            'project_id' => $fixture['project']->id,
            'created_by_user_id' => $fixture['staff']->id,
        ]);

        $this->actingAs($fixture['client'])
            ->patch(route('admin.projects.proposals.confirm', [$fixture['project'], $proposal]))
            ->assertRedirect();

        $proposal->refresh();
        $this->assertSame(ProjectProposalStatus::Confirmed, $proposal->status);
    }

    public function test_independent_confirm_creates_requirement_without_estimation(): void
    {
        $fixture = $this->projectFixture();
        $proposal = ProjectProposal::factory()->pending()->create([
            'project_id' => $fixture['project']->id,
            'created_by_user_id' => $fixture['staff']->id,
            'title' => 'Standalone scope',
            'description' => $this->tipTapDocument('Plain description text.'),
            'project_requirement_id' => null,
        ]);

        $this->actingAs($fixture['client'])
            ->patch(route('admin.projects.proposals.confirm', [$fixture['project'], $proposal]))
            ->assertRedirect();

        $proposal->refresh();
        $this->assertNotNull($proposal->transferred_project_requirement_id);

        $requirement = ProjectRequirement::query()->findOrFail($proposal->transferred_project_requirement_id);
        $this->assertSame('Standalone scope', $requirement->title);
        $this->assertDatabaseMissing('project_requirement_estimations', [
            'project_requirement_id' => $requirement->id,
        ]);
    }

    public function test_linked_confirm_does_not_create_new_requirement(): void
    {
        $fixture = $this->projectFixture();
        $requirement = ProjectRequirement::factory()->create([
            'project_id' => $fixture['project']->id,
        ]);
        $proposal = ProjectProposal::factory()->pending()->create([
            'project_id' => $fixture['project']->id,
            'created_by_user_id' => $fixture['staff']->id,
            'project_requirement_id' => $requirement->id,
        ]);

        $requirementCountBefore = ProjectRequirement::query()->count();

        $this->actingAs($fixture['client'])
            ->patch(route('admin.projects.proposals.confirm', [$fixture['project'], $proposal]))
            ->assertRedirect();

        $this->assertSame($requirementCountBefore, ProjectRequirement::query()->count());

        $proposal->refresh();
        $this->assertNull($proposal->transferred_project_requirement_id);
    }

    public function test_linked_proposals_visible_on_requirement_show(): void
    {
        $fixture = $this->projectFixture();
        $requirement = ProjectRequirement::factory()->create([
            'project_id' => $fixture['project']->id,
        ]);
        $proposal = ProjectProposal::factory()->confirmed()->create([
            'project_id' => $fixture['project']->id,
            'created_by_user_id' => $fixture['staff']->id,
            'project_requirement_id' => $requirement->id,
            'title' => 'Linked proposal',
        ]);

        $this->actingAs($fixture['staff'])
            ->get(route('admin.projects.requirements.show', [$fixture['project'], $requirement]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/projects/requirements/Show')
                ->has('linked_proposals', 1)
                ->where('linked_proposals.0.id', $proposal->id)
                ->where('linked_proposals.0.title', 'Linked proposal'));
    }

    public function test_discussion_message_can_be_posted_and_notifies_stakeholders(): void
    {
        $fixture = $this->projectFixture();
        $proposal = ProjectProposal::factory()->create([
            'project_id' => $fixture['project']->id,
            'created_by_user_id' => $fixture['staff']->id,
        ]);

        $this->actingAs($fixture['client'])
            ->post(route('admin.projects.proposals.messages.store', [$fixture['project'], $proposal]), [
                'body' => 'Can we clarify the scope?',
            ])
            ->assertRedirect(route('admin.projects.proposals.show', [$fixture['project'], $proposal]));

        $this->assertDatabaseHas('project_proposal_messages', [
            'project_proposal_id' => $proposal->id,
            'user_id' => $fixture['client']->id,
            'body' => 'Can we clarify the scope?',
        ]);

        $this->assertDatabaseHas('notifications', [
            'type' => ProjectProposalDiscussionNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $fixture['staff']->id,
        ]);
    }

    public function test_submit_sends_notification_to_client(): void
    {
        $fixture = $this->projectFixture();
        $proposal = ProjectProposal::factory()->create([
            'project_id' => $fixture['project']->id,
            'created_by_user_id' => $fixture['staff']->id,
        ]);

        $this->actingAs($fixture['staff'])
            ->patch(route('admin.projects.proposals.submit', [$fixture['project'], $proposal]));

        $this->assertDatabaseHas('notifications', [
            'type' => ProjectProposalSubmittedNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $fixture['client']->id,
        ]);
    }

    public function test_confirm_sends_reviewed_notification_to_creator(): void
    {
        $fixture = $this->projectFixture();
        $proposal = ProjectProposal::factory()->pending()->create([
            'project_id' => $fixture['project']->id,
            'created_by_user_id' => $fixture['staff']->id,
        ]);

        $this->actingAs($fixture['client'])
            ->patch(route('admin.projects.proposals.confirm', [$fixture['project'], $proposal]));

        $this->assertDatabaseHas('notifications', [
            'type' => ProjectProposalReviewedNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $fixture['staff']->id,
        ]);
    }

    public function test_update_blocked_when_proposal_not_editable(): void
    {
        $fixture = $this->projectFixture();
        $proposal = ProjectProposal::factory()->pending()->create([
            'project_id' => $fixture['project']->id,
            'created_by_user_id' => $fixture['staff']->id,
        ]);

        $this->actingAs($fixture['staff'])
            ->put(route('admin.projects.proposals.update', [$fixture['project'], $proposal]), [
                'title' => 'Changed',
                'description' => $this->tipTapDocument('Changed body'),
            ])
            ->assertForbidden();
    }

    public function test_creator_can_access_edit_page_for_draft_proposal(): void
    {
        $fixture = $this->projectFixture();
        $proposal = ProjectProposal::factory()->create([
            'project_id' => $fixture['project']->id,
            'created_by_user_id' => $fixture['staff']->id,
        ]);

        $this->actingAs($fixture['staff'])
            ->get(route('admin.projects.proposals.edit', [$fixture['project'], $proposal]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/projects/proposals/Edit')
                ->where('proposal.id', $proposal->id));
    }

    public function test_proposals_index_renders_list_rows(): void
    {
        $fixture = $this->projectFixture();
        $proposal = ProjectProposal::factory()->create([
            'project_id' => $fixture['project']->id,
            'created_by_user_id' => $fixture['staff']->id,
            'title' => 'Visible proposal',
        ]);

        $this->actingAs($fixture['staff'])
            ->get(route('admin.projects.proposals.index', $fixture['project']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/projects/proposals/Index')
                ->has('proposals.data', 1)
                ->where('proposals.data.0.id', $proposal->id)
                ->where('proposals.data.0.title', 'Visible proposal'));
    }
}
