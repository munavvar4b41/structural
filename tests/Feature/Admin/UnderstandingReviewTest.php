<?php

namespace Tests\Feature\Admin;

use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UnderstandingReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_reviewer_sees_pending_review_requirement_in_queue(): void
    {
        $team = Team::factory()->create();
        $staff = User::factory()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);
        $project->teams()->sync([$team->id]);

        $requirement = ProjectRequirement::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $client->id,
            'reviewer_user_id' => $staff->id,
            'reviewed_at' => null,
        ]);

        $this->actingAs($staff)
            ->get(route('admin.understanding-reviews.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/understanding-reviews/Index')
                ->has('requirements', 1)
                ->where('requirements.0.id', $requirement->id)
                ->where('requirements.0.review_stage', 'pending_review'));
    }

    public function test_creator_sees_awaiting_confirmation_requirement_in_queue(): void
    {
        $team = Team::factory()->create();
        $staff = User::factory()->withPrimaryTeam($team)->create();
        $creator = User::factory()->teamHead()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);
        $project->teams()->sync([$team->id]);

        $requirement = ProjectRequirement::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $creator->id,
            'reviewer_user_id' => $staff->id,
            'reviewed_at' => now(),
            'review_understanding' => $this->tipTapJson('Reviewer understanding notes'),
            'understanding_confirmed_at' => null,
        ]);

        $this->actingAs($creator)
            ->get(route('admin.understanding-reviews.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/understanding-reviews/Index')
                ->has('requirements', 1)
                ->where('requirements.0.id', $requirement->id)
                ->where('requirements.0.review_stage', 'awaiting_confirmation'));
    }

    public function test_unrelated_staff_does_not_see_requirements_in_queue(): void
    {
        $team = Team::factory()->create();
        $reviewer = User::factory()->withPrimaryTeam($team)->create();
        $otherStaff = User::factory()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);
        $project->teams()->sync([$team->id]);

        ProjectRequirement::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $client->id,
            'reviewer_user_id' => $reviewer->id,
            'reviewed_at' => null,
        ]);

        ProjectRequirement::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $client->id,
            'reviewer_user_id' => $reviewer->id,
            'reviewed_at' => now(),
            'review_understanding' => $this->tipTapJson('Notes'),
            'understanding_confirmed_at' => null,
        ]);

        $this->actingAs($otherStaff)
            ->get(route('admin.understanding-reviews.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/understanding-reviews/Index')
                ->has('requirements', 0));
    }

    public function test_client_cannot_access_understanding_reviews_queue(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client)
            ->get(route('admin.understanding-reviews.index'))
            ->assertForbidden();
    }

    private function tipTapJson(string $plainText): string
    {
        return (string) json_encode([
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => $plainText],
                    ],
                ],
            ],
        ]);
    }
}
