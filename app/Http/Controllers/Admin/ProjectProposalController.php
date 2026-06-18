<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectProposalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewProjectProposalRequest;
use App\Http\Requests\Admin\StoreProjectProposalRequest;
use App\Http\Requests\Admin\UpdateProjectProposalRequest;
use App\Models\Project;
use App\Models\ProjectProposal;
use App\Models\ProjectProposalMessage;
use App\Models\ProjectRequirement;
use App\Models\User;
use App\Support\AssignmentNotificationDispatcher;
use App\Support\SeedProposalFromRequirement;
use App\Support\TipTapDocument;
use App\Support\TransferProposalToRequirement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectProposalController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly SeedProposalFromRequirement $seedProposalFromRequirement,
        private readonly TransferProposalToRequirement $transferProposalToRequirement,
        private readonly AssignmentNotificationDispatcher $assignmentNotificationDispatcher,
    ) {}

    public function index(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $statusQuery = (string) $request->query('status', '');
        $allowedStatuses = ['', ...array_map(static fn (ProjectProposalStatus $s): string => $s->value, ProjectProposalStatus::cases())];
        $statusFilter = in_array($statusQuery, $allowedStatuses, true) ? $statusQuery : '';

        $proposals = $project->proposals()
            ->with(['creator', 'linkedRequirement:id,title', 'reviewedBy:id,name'])
            ->when($statusFilter !== '', static fn ($query) => $query->where('status', $statusFilter))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (ProjectProposal $proposal): array => $this->proposalRow($proposal, $actor));

        return Inertia::render('admin/projects/proposals/Index', [
            'project' => $this->projectSummary($project),
            'proposals' => $proposals,
            'can_create_proposals' => $actor->can('create', [ProjectProposal::class, $project]),
            'filters' => [
                'status' => $statusFilter,
            ],
            'status_options' => $this->statusOptions(),
        ]);
    }

    public function create(Request $request, Project $project): Response
    {
        $this->authorize('create', [ProjectProposal::class, $project]);

        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        return Inertia::render('admin/projects/proposals/Create', [
            'project' => $this->projectSummary($project),
            'requirement_options' => $this->requirementOptions($project),
        ]);
    }

    public function store(StoreProjectProposalRequest $request, Project $project): RedirectResponse
    {
        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $validated = $request->validated();

        $proposal = ProjectProposal::query()->create([
            'project_id' => $project->id,
            'project_requirement_id' => $validated['project_requirement_id'] ?? null,
            'created_by_user_id' => $actor->id,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'status' => ProjectProposalStatus::Draft,
        ]);

        return to_route('admin.projects.proposals.show', [$project, $proposal])
            ->with('toast', __('Proposal created.'));
    }

    public function show(Request $request, Project $project, ProjectProposal $proposal): Response
    {
        $this->ensureProposalBelongsToProject($project, $proposal);
        $this->authorize('view', $proposal);

        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $proposal->loadMissing(['creator', 'linkedRequirement', 'transferredRequirement', 'reviewedBy', 'reopenedBy']);

        return Inertia::render('admin/projects/proposals/Show', [
            'project' => $this->projectSummary($project),
            'proposal' => $this->proposalDetailPayload($proposal),
            'proposal_chat_messages' => $this->proposalChatMessagesPayload($request, $proposal),
            'can_post_proposal_chat' => $actor->can('create', [ProjectProposalMessage::class, $proposal]),
            'can_update' => $actor->can('update', $proposal),
            'can_submit' => $actor->can('submit', $proposal),
            'can_confirm' => $actor->can('confirm', $proposal),
            'can_reject' => $actor->can('reject', $proposal),
            'can_reopen' => $actor->can('reopen', $proposal),
            'can_delete' => $actor->can('delete', $proposal),
        ]);
    }

    public function edit(Request $request, Project $project, ProjectProposal $proposal): Response
    {
        $this->ensureProposalBelongsToProject($project, $proposal);
        $this->authorize('update', $proposal);

        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        return Inertia::render('admin/projects/proposals/Edit', [
            'project' => $this->projectSummary($project),
            'proposal' => $this->proposalFormPayload($proposal),
        ]);
    }

    public function update(UpdateProjectProposalRequest $request, Project $project, ProjectProposal $proposal): RedirectResponse
    {
        $this->ensureProposalBelongsToProject($project, $proposal);

        $validated = $request->validated();

        $proposal->update([
            'title' => $validated['title'],
            'description' => $validated['description'],
        ]);

        return to_route('admin.projects.proposals.show', [$project, $proposal])
            ->with('toast', __('Proposal updated.'));
    }

    public function destroy(Request $request, Project $project, ProjectProposal $proposal): RedirectResponse
    {
        $this->ensureProposalBelongsToProject($project, $proposal);
        $this->authorize('delete', $proposal);

        $proposal->delete();

        return to_route('admin.projects.proposals.index', $project)
            ->with('toast', __('Proposal deleted.'));
    }

    public function submit(Request $request, Project $project, ProjectProposal $proposal): RedirectResponse
    {
        $this->ensureProposalBelongsToProject($project, $proposal);
        $this->authorize('submit', $proposal);

        $proposal->forceFill([
            'status' => ProjectProposalStatus::Pending,
            'submitted_at' => now(),
        ])->save();

        $this->assignmentNotificationDispatcher->sendProjectProposalSubmitted($proposal, $request->user());

        return back()->with('toast', __('Proposal submitted for review.'));
    }

    public function confirm(ReviewProjectProposalRequest $request, Project $project, ProjectProposal $proposal): RedirectResponse
    {
        $this->ensureProposalBelongsToProject($project, $proposal);

        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        if ($proposal->project_requirement_id === null && $proposal->transferred_project_requirement_id === null) {
            $this->transferProposalToRequirement->transfer($proposal, $project, $actor);
            $proposal->refresh();
        }

        $proposal->forceFill([
            'status' => ProjectProposalStatus::Confirmed,
            'reviewed_at' => now(),
            'reviewed_by_user_id' => $actor->id,
            'review_notes' => $request->validated('review_notes'),
            'rejection_reason' => null,
        ])->save();

        $this->assignmentNotificationDispatcher->sendProjectProposalReviewed($proposal, $actor);

        return back()->with('toast', __('Proposal confirmed.'));
    }

    public function reject(ReviewProjectProposalRequest $request, Project $project, ProjectProposal $proposal): RedirectResponse
    {
        $this->ensureProposalBelongsToProject($project, $proposal);

        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $proposal->forceFill([
            'status' => ProjectProposalStatus::Rejected,
            'reviewed_at' => now(),
            'reviewed_by_user_id' => $actor->id,
            'rejection_reason' => $request->validated('rejection_reason'),
            'review_notes' => null,
        ])->save();

        $this->assignmentNotificationDispatcher->sendProjectProposalReviewed($proposal, $actor);

        return back()->with('toast', __('Proposal rejected.'));
    }

    public function reopen(Request $request, Project $project, ProjectProposal $proposal): RedirectResponse
    {
        $this->ensureProposalBelongsToProject($project, $proposal);
        $this->authorize('reopen', $proposal);

        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $proposal->forceFill([
            'status' => ProjectProposalStatus::Draft,
            'submitted_at' => null,
            'reviewed_at' => null,
            'reviewed_by_user_id' => null,
            'review_notes' => null,
            'rejection_reason' => null,
            'reopened_at' => now(),
            'reopened_by_user_id' => $actor->id,
        ])->save();

        return back()->with('toast', __('Proposal reopened.'));
    }

    /**
     * @return array{id: int, name: string, code: string|null, estimation_required: bool}
     */
    private function projectSummary(Project $project): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'code' => $project->code,
            'estimation_required' => $project->estimation_required,
        ];
    }

    /**
     * @return list<array{value: int, label: string, title: string, description: string}>
     */
    private function requirementOptions(Project $project): array
    {
        return $project->requirements()
            ->orderBy('title')
            ->get()
            ->map(function (ProjectRequirement $requirement): array {
                $seeded = $this->seedProposalFromRequirement->seed($requirement);

                return [
                    'value' => $requirement->id,
                    'label' => $requirement->title,
                    'title' => $seeded['title'],
                    'description' => $seeded['description'],
                ];
            })
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(
            static fn (ProjectProposalStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            ProjectProposalStatus::cases(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function proposalRow(ProjectProposal $proposal, User $actor): array
    {
        return [
            'id' => $proposal->id,
            'title' => $proposal->title,
            'description_preview' => TipTapDocument::previewFromStored($proposal->description),
            'status' => $proposal->status->value,
            'status_label' => $proposal->status->label(),
            'created_at' => $proposal->created_at?->toIso8601String(),
            'submitted_at' => $proposal->submitted_at?->toIso8601String(),
            'creator' => $this->userBrief($proposal->creator),
            'linked_requirement' => $proposal->linkedRequirement ? [
                'id' => $proposal->linkedRequirement->id,
                'title' => $proposal->linkedRequirement->title,
            ] : null,
            'can_update' => $actor->can('update', $proposal),
            'can_delete' => $actor->can('delete', $proposal),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function proposalFormPayload(ProjectProposal $proposal): array
    {
        return [
            'id' => $proposal->id,
            'title' => $proposal->title,
            'description' => $proposal->description,
            'status' => $proposal->status->value,
            'status_label' => $proposal->status->label(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function proposalDetailPayload(ProjectProposal $proposal): array
    {
        return [
            'id' => $proposal->id,
            'title' => $proposal->title,
            'description' => $proposal->description,
            'status' => $proposal->status->value,
            'status_label' => $proposal->status->label(),
            'created_at' => $proposal->created_at?->toIso8601String(),
            'submitted_at' => $proposal->submitted_at?->toIso8601String(),
            'reviewed_at' => $proposal->reviewed_at?->toIso8601String(),
            'review_notes' => $proposal->review_notes,
            'rejection_reason' => $proposal->rejection_reason,
            'reopened_at' => $proposal->reopened_at?->toIso8601String(),
            'creator' => $this->userBrief($proposal->creator),
            'reviewed_by' => $this->userBrief($proposal->reviewedBy),
            'reopened_by' => $this->userBrief($proposal->reopenedBy),
            'linked_requirement' => $proposal->linkedRequirement ? [
                'id' => $proposal->linkedRequirement->id,
                'title' => $proposal->linkedRequirement->title,
            ] : null,
            'transferred_requirement' => $proposal->transferredRequirement ? [
                'id' => $proposal->transferredRequirement->id,
                'title' => $proposal->transferredRequirement->title,
            ] : null,
        ];
    }

    /**
     * @return array{id: int, name: string, email: string}|null
     */
    private function userBrief(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }

    private function proposalChatMessagesPayload(Request $request, ProjectProposal $proposal): LengthAwarePaginator
    {
        $perPage = 50;
        $total = $proposal->messages()->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = (int) $request->query('chat_page', (string) $lastPage);
        $page = min(max(1, $page), $lastPage);

        $paginator = $proposal->messages()
            ->with(['user:id,name,email'])
            ->orderBy('created_at')
            ->paginate($perPage, ['*'], 'chat_page', $page);

        $paginator->getCollection()->transform(function (ProjectProposalMessage $message): array {
            return [
                'id' => $message->id,
                'body' => $message->body,
                'created_at' => $message->created_at?->toIso8601String(),
                'user' => $this->userBrief($message->user),
            ];
        });

        return $paginator;
    }

    private function ensureProposalBelongsToProject(Project $project, ProjectProposal $proposal): void
    {
        abort_if($proposal->project_id !== $project->id, 404);
    }
}
