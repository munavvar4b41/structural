<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RequirementEstimationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewRequirementEstimationRequest;
use App\Http\Requests\Admin\SubmitRequirementEstimationRequest;
use App\Http\Requests\Admin\SyncRequirementEstimationLinesRequest;
use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\ProjectRequirementEstimation;
use App\Models\User;
use App\Notifications\RequirementEstimationReviewedNotification;
use App\Notifications\RequirementEstimationSubmittedNotification;
use App\Notifications\RequirementEstimationTransferredNotification;
use App\Support\RequirementEstimationShowPayload;
use App\Support\SyncRequirementEstimationLines;
use App\Support\TransferEstimationToTasks;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectRequirementEstimationController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly SyncRequirementEstimationLines $syncLines,
        private readonly TransferEstimationToTasks $transferEstimationToTasks,
    ) {}

    public function show(Request $request, Project $project, ProjectRequirement $requirement): Response
    {
        $this->ensureRequirementBelongsToProject($project, $requirement);
        $this->authorize('view', $requirement);

        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        abort_if($requirement->understanding_confirmed_at === null, 403);

        $requirement->loadMissing('project');

        return Inertia::render('admin/projects/requirements/Estimation', array_merge(
            [
                'project' => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'code' => $project->code,
                ],
                'requirement' => [
                    'id' => $requirement->id,
                    'title' => $requirement->title,
                ],
            ],
            RequirementEstimationShowPayload::forRequirement($requirement, $project, $actor),
        ));
    }

    public function store(Request $request, Project $project, ProjectRequirement $requirement): RedirectResponse
    {
        $this->ensureRequirementBelongsToProject($project, $requirement);
        $this->authorize('create', [ProjectRequirementEstimation::class, $requirement]);

        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $estimation = ProjectRequirementEstimation::query()->create([
            'project_requirement_id' => $requirement->id,
            'version' => $this->nextVersionNumber($requirement),
            'status' => RequirementEstimationStatus::Draft,
            'created_by_user_id' => $actor->id,
        ]);

        $estimation->items()->create([
            'title' => __('Parent task'),
            'sort_order' => 0,
            'phase' => 1,
        ]);

        return $this->estimationRedirect($project, $requirement)
            ->with('toast', __('Estimation draft created.'));
    }

    public function syncLines(
        SyncRequirementEstimationLinesRequest $request,
        Project $project,
        ProjectRequirement $requirement,
        ProjectRequirementEstimation $estimation,
    ): RedirectResponse {
        $this->ensureEstimationContext($project, $requirement, $estimation);

        $partialModule = $request->boolean('partial_module');

        $this->syncLines->sync($estimation, $request->validated('lines'), $partialModule);

        return back()
            ->with(
                'toast',
                $partialModule
                    ? __('Module saved.')
                    : __('Estimation lines saved.'),
            );
    }

    public function submit(
        SubmitRequirementEstimationRequest $request,
        Project $project,
        ProjectRequirement $requirement,
        ProjectRequirementEstimation $estimation,
    ): RedirectResponse {
        $this->ensureEstimationContext($project, $requirement, $estimation);

        $data = $request->validated();
        $estimation->forceFill([
            'status' => RequirementEstimationStatus::PendingApproval,
            'submitted_at' => now(),
            'submitted_to_user_id' => $data['submitted_to_user_id'],
            'submission_notes' => $data['submission_notes'] ?? null,
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
            'review_notes' => null,
        ])->save();

        $estimation->load('requirement.project', 'submittedTo');
        $approver = $estimation->submittedTo;
        if ($approver instanceof User) {
            $approver->notify(new RequirementEstimationSubmittedNotification($estimation));
        }

        return $this->estimationRedirect($project, $requirement)
            ->with('toast', __('Estimation submitted for approval.'));
    }

    public function approve(
        ReviewRequirementEstimationRequest $request,
        Project $project,
        ProjectRequirement $requirement,
        ProjectRequirementEstimation $estimation,
    ): RedirectResponse {
        return $this->completeReview($request, $project, $requirement, $estimation, RequirementEstimationStatus::Approved, 'approved');
    }

    public function reject(
        ReviewRequirementEstimationRequest $request,
        Project $project,
        ProjectRequirement $requirement,
        ProjectRequirementEstimation $estimation,
    ): RedirectResponse {
        return $this->completeReview($request, $project, $requirement, $estimation, RequirementEstimationStatus::Rejected, 'rejected');
    }

    public function requestChanges(
        ReviewRequirementEstimationRequest $request,
        Project $project,
        ProjectRequirement $requirement,
        ProjectRequirementEstimation $estimation,
    ): RedirectResponse {
        return $this->completeReview($request, $project, $requirement, $estimation, RequirementEstimationStatus::ChangesRequested, 'changes_requested');
    }

    public function requestRevision(
        Request $request,
        Project $project,
        ProjectRequirement $requirement,
        ProjectRequirementEstimation $estimation,
    ): RedirectResponse {
        $this->ensureEstimationContext($project, $requirement, $estimation);
        $this->authorize('requestRevision', $estimation);

        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $newEstimation = ProjectRequirementEstimation::query()->create([
            'project_requirement_id' => $requirement->id,
            'version' => $this->nextVersionNumber($requirement),
            'status' => RequirementEstimationStatus::Draft,
            'created_by_user_id' => $actor->id,
        ]);

        $oldItems = $estimation->items()->orderBy('sort_order')->get();
        $idMap = [];

        foreach ($oldItems as $item) {
            $newItem = $newEstimation->items()->create([
                'parent_estimation_item_id' => null,
                'title' => $item->title,
                'description' => $item->description,
                'estimated_minutes' => $item->estimated_minutes,
                'sort_order' => $item->sort_order,
            ]);
            $idMap[$item->id] = $newItem->id;
        }

        foreach ($oldItems as $item) {
            if ($item->parent_estimation_item_id === null) {
                continue;
            }

            $newParentId = $idMap[$item->parent_estimation_item_id] ?? null;
            if ($newParentId === null) {
                continue;
            }

            $newItemId = $idMap[$item->id] ?? null;
            if ($newItemId === null) {
                continue;
            }

            $newEstimation->items()->whereKey($newItemId)->update([
                'parent_estimation_item_id' => $newParentId,
            ]);
        }

        $estimation->forceFill([
            'status' => RequirementEstimationStatus::Superseded,
            'superseded_by_estimation_id' => $newEstimation->id,
        ])->save();

        return $this->estimationRedirect($project, $requirement)
            ->with('toast', __('New estimation version created. Update lines and submit when ready.'));
    }

    public function transfer(
        Request $request,
        Project $project,
        ProjectRequirement $requirement,
        ProjectRequirementEstimation $estimation,
    ): RedirectResponse {
        $this->ensureEstimationContext($project, $requirement, $estimation);
        $this->authorize('transfer', $estimation);

        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $this->transferEstimationToTasks->transfer($estimation, $project, $requirement, $actor);

        $estimation->load('creator', 'requirement.project');
        $creator = $estimation->creator;
        if ($creator instanceof User) {
            $creator->notify(new RequirementEstimationTransferredNotification($estimation->fresh()));
        }

        return $this->estimationRedirect($project, $requirement)
            ->with('toast', __('Estimation transferred to project tasks.'));
    }

    private function completeReview(
        ReviewRequirementEstimationRequest $request,
        Project $project,
        ProjectRequirement $requirement,
        ProjectRequirementEstimation $estimation,
        RequirementEstimationStatus $status,
        string $outcome,
    ): RedirectResponse {
        $this->ensureEstimationContext($project, $requirement, $estimation);

        $reviewer = $request->user();
        abort_if(! $reviewer instanceof User, 403);

        $data = $request->validated();

        $estimation->forceFill([
            'status' => $status,
            'reviewed_by_user_id' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => $data['review_notes'] ?? null,
        ])->save();

        $estimation->load('creator', 'requirement.project');
        $creator = $estimation->creator;
        if ($creator instanceof User) {
            $creator->notify(new RequirementEstimationReviewedNotification($estimation, $outcome));
        }

        $message = match ($status) {
            RequirementEstimationStatus::Approved => __('Estimation approved.'),
            RequirementEstimationStatus::Rejected => __('Estimation rejected.'),
            RequirementEstimationStatus::ChangesRequested => __('Changes requested on estimation.'),
            default => __('Estimation updated.'),
        };

        return $this->estimationRedirect($project, $requirement)->with('toast', $message);
    }

    private function estimationRedirect(Project $project, ProjectRequirement $requirement): RedirectResponse
    {
        return to_route('admin.projects.requirements.estimation.show', [$project, $requirement]);
    }

    private function ensureRequirementBelongsToProject(Project $project, ProjectRequirement $requirement): void
    {
        abort_if($requirement->project_id !== $project->id, 404);
    }

    private function ensureEstimationContext(
        Project $project,
        ProjectRequirement $requirement,
        ProjectRequirementEstimation $estimation,
    ): void {
        $this->ensureRequirementBelongsToProject($project, $requirement);
        abort_if($estimation->project_requirement_id !== $requirement->id, 404);
        $this->authorize('view', $estimation);
    }

    private function nextVersionNumber(ProjectRequirement $requirement): int
    {
        $max = (int) $requirement->estimations()->max('version');

        return $max + 1;
    }
}
