<?php

namespace Tests\Feature\Admin;

use App\Enums\RequirementEstimationStatus;
use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\ProjectRequirementEstimation;
use App\Models\ProjectTask;
use App\Models\Team;
use App\Models\User;
use App\Notifications\RequirementEstimationSubmittedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectRequirementEstimationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{
     *     team: Team,
     *     staff: User,
     *     approver: User,
     *     project: Project,
     *     requirement: ProjectRequirement
     * }
     */
    private function confirmedRequirementSetup(): array
    {
        $team = Team::factory()->create();
        $staff = User::factory()->withPrimaryTeam($team)->create();
        $approver = User::factory()->teamHead()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);
        $project->teams()->sync([$team->id]);

        $requirement = ProjectRequirement::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $client->id,
            'understanding_confirmed_at' => now(),
            'understanding_confirmed_by_user_id' => $client->id,
            'reviewed_at' => now(),
        ]);

        return compact('team', 'staff', 'approver', 'project', 'requirement');
    }

    public function test_cannot_create_estimation_before_understanding_confirmed(): void
    {
        $team = Team::factory()->create();
        $staff = User::factory()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);
        $project->teams()->sync([$team->id]);
        $requirement = ProjectRequirement::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $client->id,
            'understanding_confirmed_at' => null,
        ]);

        $this->actingAs($staff)
            ->post(route('admin.projects.requirements.estimation.store', [$project, $requirement]))
            ->assertForbidden();
    }

    public function test_staff_syncs_multi_level_lines_and_sees_total_on_show(): void
    {
        ['staff' => $staff, 'project' => $project, 'requirement' => $requirement] = $this->confirmedRequirementSetup();

        $this->actingAs($staff)
            ->post(route('admin.projects.requirements.estimation.store', [$project, $requirement]))
            ->assertRedirect();

        $estimation = ProjectRequirementEstimation::query()->firstOrFail();
        $root = $estimation->items()->firstOrFail();

        $this->actingAs($staff)
            ->from(route('admin.projects.requirements.estimation.show', [$project, $requirement]))
            ->put(route('admin.projects.requirements.estimation.lines', [$project, $requirement, $estimation]), [
                'lines' => [
                    [
                        'id' => $root->id,
                        'title' => 'Parent work',
                        'description' => 'Root scope',
                        'estimated_minutes' => 120,
                        'sort_order' => 0,
                    ],
                    [
                        'client_key' => 'child-1',
                        'parent_id' => $root->id,
                        'title' => 'Child work',
                        'estimated_minutes' => 60,
                        'sort_order' => 1,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($staff)
            ->get(route('admin.projects.requirements.estimation.show', [$project, $requirement]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/projects/requirements/Estimation')
                ->where('total_minutes', 60)
                ->where('analytics.total_minutes', 60)
                ->has('estimation_lines', 2));

        $root->refresh();
        $this->assertSame(60, $root->estimated_minutes);
    }

    public function test_submit_notifies_approver(): void
    {
        Notification::fake();

        ['staff' => $staff, 'approver' => $approver, 'project' => $project, 'requirement' => $requirement] = $this->confirmedRequirementSetup();

        $this->actingAs($staff)
            ->post(route('admin.projects.requirements.estimation.store', [$project, $requirement]));

        $estimation = ProjectRequirementEstimation::query()->firstOrFail();
        $line = $estimation->items()->firstOrFail();
        $line->forceFill(['estimated_minutes' => 90, 'title' => 'Task A'])->save();

        $this->actingAs($staff)
            ->patch(route('admin.projects.requirements.estimation.submit', [$project, $requirement, $estimation]), [
                'submitted_to_user_id' => $approver->id,
                'submission_notes' => 'Please review',
            ])
            ->assertRedirect();

        $this->assertSame(RequirementEstimationStatus::PendingApproval, $estimation->fresh()->status);
        Notification::assertSentTo($approver, RequirementEstimationSubmittedNotification::class);
    }

    public function test_approver_requests_changes_then_staff_resubmits(): void
    {
        ['staff' => $staff, 'approver' => $approver, 'project' => $project, 'requirement' => $requirement] = $this->confirmedRequirementSetup();

        $estimation = ProjectRequirementEstimation::factory()->create([
            'project_requirement_id' => $requirement->id,
            'created_by_user_id' => $staff->id,
            'status' => RequirementEstimationStatus::PendingApproval,
            'submitted_at' => now(),
            'submitted_to_user_id' => $approver->id,
        ]);
        $estimation->items()->create(['title' => 'Line', 'estimated_minutes' => 30, 'sort_order' => 0]);

        $this->actingAs($approver)
            ->patch(route('admin.projects.requirements.estimation.request-changes', [$project, $requirement, $estimation]), [
                'review_notes' => 'Add breakdown',
            ])
            ->assertRedirect();

        $this->assertSame(RequirementEstimationStatus::ChangesRequested, $estimation->fresh()->status);

        $this->actingAs($staff)
            ->patch(route('admin.projects.requirements.estimation.submit', [$project, $requirement, $estimation]), [
                'submitted_to_user_id' => $approver->id,
            ])
            ->assertRedirect();

        $this->assertSame(RequirementEstimationStatus::PendingApproval, $estimation->fresh()->status);
    }

    public function test_approved_estimation_request_revision_creates_version_two(): void
    {
        ['staff' => $staff, 'project' => $project, 'requirement' => $requirement] = $this->confirmedRequirementSetup();

        $estimation = ProjectRequirementEstimation::factory()->approved()->create([
            'project_requirement_id' => $requirement->id,
            'created_by_user_id' => $staff->id,
        ]);
        $estimation->items()->create(['title' => 'Original', 'estimated_minutes' => 45, 'sort_order' => 0]);

        $this->actingAs($staff)
            ->post(route('admin.projects.requirements.estimation.request-revision', [$project, $requirement, $estimation]))
            ->assertRedirect();

        $old = $estimation->fresh();
        $this->assertSame(RequirementEstimationStatus::Superseded, $old->status);
        $this->assertNotNull($old->superseded_by_estimation_id);

        $new = ProjectRequirementEstimation::query()->findOrFail($old->superseded_by_estimation_id);
        $this->assertSame(2, $new->version);
        $this->assertSame(RequirementEstimationStatus::Draft, $new->status);
        $this->assertSame('Original', $new->items()->first()?->title);
    }

    public function test_team_lead_transfer_creates_tasks_with_estimation_link(): void
    {
        ['staff' => $staff, 'approver' => $approver, 'project' => $project, 'requirement' => $requirement] = $this->confirmedRequirementSetup();
        $project->forceFill(['lead_user_id' => $approver->id])->save();

        $estimation = ProjectRequirementEstimation::factory()->create([
            'project_requirement_id' => $requirement->id,
            'created_by_user_id' => $staff->id,
            'status' => RequirementEstimationStatus::Approved,
            'reviewed_at' => now(),
            'reviewed_by_user_id' => $approver->id,
        ]);

        $parent = $estimation->items()->create([
            'title' => 'Parent',
            'estimated_minutes' => 100,
            'sort_order' => 0,
        ]);
        $child = $estimation->items()->create([
            'parent_estimation_item_id' => $parent->id,
            'title' => 'Child',
            'estimated_minutes' => 50,
            'sort_order' => 1,
        ]);

        $this->actingAs($approver)
            ->post(route('admin.projects.requirements.estimation.transfer', [$project, $requirement, $estimation]))
            ->assertRedirect();

        $parentTask = ProjectTask::query()->where('project_requirement_estimation_item_id', $parent->id)->first();
        $childTask = ProjectTask::query()->where('project_requirement_estimation_item_id', $child->id)->first();

        $this->assertNotNull($parentTask);
        $this->assertNotNull($childTask);
        $this->assertNull($parentTask->parent_project_task_id);
        $this->assertSame($parentTask->id, $childTask->parent_project_task_id);
        $this->assertSame(RequirementEstimationStatus::Transferred, $estimation->fresh()->status);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.projects.requirements.show', [$project, $requirement]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('requirement_tasks.0.estimation_source', 'transferred')
                ->where('requirement_tasks.1.estimation_source', 'transferred'));
    }

    public function test_ad_hoc_task_after_transfer_is_marked_on_show(): void
    {
        ['staff' => $staff, 'approver' => $approver, 'project' => $project, 'requirement' => $requirement] = $this->confirmedRequirementSetup();
        $project->forceFill(['lead_user_id' => $approver->id])->save();

        $estimation = ProjectRequirementEstimation::factory()->transferred()->create([
            'project_requirement_id' => $requirement->id,
            'created_by_user_id' => $staff->id,
            'transferred_by_user_id' => $approver->id,
        ]);

        ProjectTask::factory()->create([
            'project_id' => $project->id,
            'project_requirement_id' => $requirement->id,
            'created_by_user_id' => $staff->id,
            'title' => 'Ad hoc follow-up',
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.projects.requirements.show', [$project, $requirement]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('requirement_tasks.0.estimation_source', 'ad_hoc'));
    }

    public function test_wrong_approver_cannot_approve(): void
    {
        ['team' => $team, 'staff' => $staff, 'approver' => $approver, 'project' => $project, 'requirement' => $requirement] = $this->confirmedRequirementSetup();
        $otherHead = User::factory()->teamHead()->withPrimaryTeam($team)->create();

        $estimation = ProjectRequirementEstimation::factory()->create([
            'project_requirement_id' => $requirement->id,
            'created_by_user_id' => $staff->id,
            'status' => RequirementEstimationStatus::PendingApproval,
            'submitted_at' => now(),
            'submitted_to_user_id' => $approver->id,
        ]);

        $this->actingAs($otherHead)
            ->patch(route('admin.projects.requirements.estimation.approve', [$project, $requirement, $estimation]), [
                'review_notes' => 'Nope',
            ])
            ->assertForbidden();
    }

    public function test_requirement_show_links_to_estimation_without_full_payload(): void
    {
        ['staff' => $staff, 'project' => $project, 'requirement' => $requirement] = $this->confirmedRequirementSetup();

        $this->actingAs($staff)
            ->get(route('admin.projects.requirements.show', [$project, $requirement]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/projects/requirements/Show')
                ->where('can_open_estimation', true)
                ->missing('estimation_lines')
                ->missing('analytics'));
    }

    public function test_syncs_two_hundred_lines_in_one_request_with_batched_queries(): void
    {
        ['staff' => $staff, 'project' => $project, 'requirement' => $requirement] = $this->confirmedRequirementSetup();

        $estimation = ProjectRequirementEstimation::factory()->create([
            'project_requirement_id' => $requirement->id,
            'created_by_user_id' => $staff->id,
            'status' => RequirementEstimationStatus::Draft,
        ]);

        for ($index = 0; $index < 200; $index++) {
            $estimation->items()->create([
                'title' => 'Line '.$index,
                'estimated_minutes' => 30,
                'sort_order' => $index,
            ]);
        }

        $lines = $estimation->items()
            ->orderBy('sort_order')
            ->get()
            ->map(static fn ($item): array => [
                'id' => $item->id,
                'title' => 'Updated '.$item->id,
                'estimated_minutes' => 45,
                'sort_order' => $item->sort_order,
            ])
            ->all();

        $queryCount = 0;
        DB::listen(static function () use (&$queryCount): void {
            $queryCount++;
        });

        $this->actingAs($staff)
            ->from(route('admin.projects.requirements.estimation.show', [$project, $requirement]))
            ->put(route('admin.projects.requirements.estimation.lines', [$project, $requirement, $estimation]), [
                'lines' => $lines,
            ])
            ->assertRedirect();

        $this->assertSame(200, $estimation->items()->count());
        $this->assertSame('Updated '.$estimation->items()->orderBy('id')->value('id'), $estimation->items()->orderBy('id')->value('title'));
        $this->assertLessThan(40, $queryCount);
    }

    public function test_partial_module_sync_updates_one_module_without_deleting_others(): void
    {
        ['staff' => $staff, 'project' => $project, 'requirement' => $requirement] = $this->confirmedRequirementSetup();

        $this->actingAs($staff)
            ->post(route('admin.projects.requirements.estimation.store', [$project, $requirement]))
            ->assertRedirect();

        $estimation = ProjectRequirementEstimation::query()->firstOrFail();
        $moduleA = $estimation->items()->firstOrFail();

        $this->actingAs($staff)
            ->put(route('admin.projects.requirements.estimation.lines', [$project, $requirement, $estimation]), [
                'lines' => [
                    [
                        'id' => $moduleA->id,
                        'title' => 'Module A',
                        'estimated_minutes' => 60,
                        'sort_order' => 0,
                    ],
                    [
                        'client_key' => 'module-b',
                        'title' => 'Module B',
                        'estimated_minutes' => 90,
                        'sort_order' => 1,
                    ],
                ],
            ])
            ->assertRedirect();

        $moduleB = $estimation->items()->where('title', 'Module B')->firstOrFail();

        $this->actingAs($staff)
            ->put(route('admin.projects.requirements.estimation.lines', [$project, $requirement, $estimation]), [
                'partial_module' => true,
                'lines' => [
                    [
                        'id' => $moduleB->id,
                        'title' => 'Module B updated',
                        'estimated_minutes' => 120,
                        'sort_order' => 0,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(2, $estimation->items()->count());
        $this->assertSame('Module A', $moduleA->fresh()->title);
        $this->assertSame(60, $moduleA->fresh()->estimated_minutes);
        $this->assertSame('Module B updated', $moduleB->fresh()->title);
        $this->assertSame(120, $moduleB->fresh()->estimated_minutes);
    }

    public function test_estimation_show_returns_many_lines(): void
    {
        ['staff' => $staff, 'project' => $project, 'requirement' => $requirement] = $this->confirmedRequirementSetup();

        $estimation = ProjectRequirementEstimation::factory()->create([
            'project_requirement_id' => $requirement->id,
            'created_by_user_id' => $staff->id,
            'status' => RequirementEstimationStatus::Draft,
        ]);

        for ($index = 0; $index < 120; $index++) {
            $estimation->items()->create([
                'title' => 'Line '.$index,
                'estimated_minutes' => 30,
                'sort_order' => $index,
            ]);
        }

        $this->actingAs($staff)
            ->get(route('admin.projects.requirements.estimation.show', [$project, $requirement]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/projects/requirements/Estimation')
                ->has('estimation_lines', 120)
                ->where('analytics.total_lines', 120));
    }

    public function test_estimation_reviews_index_lists_pending_for_approver(): void
    {
        ['staff' => $staff, 'approver' => $approver, 'project' => $project, 'requirement' => $requirement] = $this->confirmedRequirementSetup();

        ProjectRequirementEstimation::factory()->create([
            'project_requirement_id' => $requirement->id,
            'created_by_user_id' => $staff->id,
            'status' => RequirementEstimationStatus::PendingApproval,
            'submitted_at' => now(),
            'submitted_to_user_id' => $approver->id,
        ]);

        $this->actingAs($approver)
            ->get(route('admin.estimation-reviews.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/estimation-reviews/Index')
                ->has('estimations', 1));
    }

    public function test_sync_without_phase_defaults_to_one_when_max_is_one(): void
    {
        ['staff' => $staff, 'project' => $project, 'requirement' => $requirement] = $this->confirmedRequirementSetup();
        $requirement->forceFill(['max_generated_phase' => 1])->save();

        $this->actingAs($staff)
            ->post(route('admin.projects.requirements.estimation.store', [$project, $requirement]));

        $estimation = ProjectRequirementEstimation::query()->firstOrFail();
        $root = $estimation->items()->firstOrFail();

        $this->actingAs($staff)
            ->put(route('admin.projects.requirements.estimation.lines', [$project, $requirement, $estimation]), [
                'lines' => [
                    [
                        'id' => $root->id,
                        'title' => 'Single phase work',
                        'estimated_minutes' => 45,
                        'sort_order' => 0,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(1, $root->fresh()->phase);
    }

    public function test_sync_with_phase_when_max_greater_than_one(): void
    {
        ['staff' => $staff, 'project' => $project, 'requirement' => $requirement] = $this->confirmedRequirementSetup();
        $requirement->forceFill(['max_generated_phase' => 3])->save();

        $this->actingAs($staff)
            ->post(route('admin.projects.requirements.estimation.store', [$project, $requirement]));

        $estimation = ProjectRequirementEstimation::query()->firstOrFail();
        $root = $estimation->items()->firstOrFail();

        $this->actingAs($staff)
            ->put(route('admin.projects.requirements.estimation.lines', [$project, $requirement, $estimation]), [
                'lines' => [
                    [
                        'id' => $root->id,
                        'title' => 'Phase two work',
                        'estimated_minutes' => 45,
                        'phase' => 2,
                        'sort_order' => 0,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(2, $root->fresh()->phase);
    }

    public function test_sync_rejects_phase_above_requirement_max(): void
    {
        ['staff' => $staff, 'project' => $project, 'requirement' => $requirement] = $this->confirmedRequirementSetup();
        $requirement->forceFill(['max_generated_phase' => 2])->save();

        $this->actingAs($staff)
            ->post(route('admin.projects.requirements.estimation.store', [$project, $requirement]));

        $estimation = ProjectRequirementEstimation::query()->firstOrFail();
        $root = $estimation->items()->firstOrFail();

        $this->actingAs($staff)
            ->from(route('admin.projects.requirements.estimation.show', [$project, $requirement]))
            ->put(route('admin.projects.requirements.estimation.lines', [$project, $requirement, $estimation]), [
                'lines' => [
                    [
                        'id' => $root->id,
                        'title' => 'Too high',
                        'estimated_minutes' => 45,
                        'phase' => 3,
                        'sort_order' => 0,
                    ],
                ],
            ])
            ->assertSessionHasErrors('lines');
    }

    public function test_transfer_copies_phase_to_tasks(): void
    {
        ['staff' => $staff, 'approver' => $approver, 'project' => $project, 'requirement' => $requirement] = $this->confirmedRequirementSetup();
        $requirement->forceFill(['max_generated_phase' => 3])->save();
        $project->forceFill(['lead_user_id' => $approver->id])->save();

        $estimation = ProjectRequirementEstimation::factory()->create([
            'project_requirement_id' => $requirement->id,
            'created_by_user_id' => $staff->id,
            'status' => RequirementEstimationStatus::Approved,
            'reviewed_at' => now(),
            'reviewed_by_user_id' => $approver->id,
        ]);

        $item = $estimation->items()->create([
            'title' => 'Phase scoped',
            'estimated_minutes' => 60,
            'sort_order' => 0,
            'phase' => 2,
        ]);

        $this->actingAs($approver)
            ->post(route('admin.projects.requirements.estimation.transfer', [$project, $requirement, $estimation]))
            ->assertRedirect();

        $task = ProjectTask::query()->where('project_requirement_estimation_item_id', $item->id)->firstOrFail();
        $this->assertSame(2, $task->phase);
    }
}
