<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectProposalStatus;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectProposal;
use App\Models\User;
use App\Support\TipTapDocument;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProposalController extends Controller
{
    public function index(Request $request): Response
    {
        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $visibleProjects = Project::query()
            ->visibleToUser($actor)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'estimation_required']);

        $visibleProjectIds = $visibleProjects->pluck('id')->all();

        $projectQuery = $request->query('project_id');
        $projectId = null;
        if ($projectQuery !== null && $projectQuery !== '') {
            $candidateProjectId = (int) $projectQuery;
            if (in_array($candidateProjectId, $visibleProjectIds, true)) {
                $projectId = $candidateProjectId;
            }
        }

        $search = trim((string) $request->query('search', ''));

        $statusQuery = (string) $request->query('status', '');
        $allowedStatuses = ['', ...array_map(static fn (ProjectProposalStatus $s): string => $s->value, ProjectProposalStatus::cases())];
        $statusFilter = in_array($statusQuery, $allowedStatuses, true) ? $statusQuery : '';

        $proposals = ProjectProposal::query()
            ->whereIn('project_id', $visibleProjectIds)
            ->when($projectId !== null, static fn ($query) => $query->where('project_id', $projectId))
            ->when($statusFilter !== '', static fn ($query) => $query->where('status', $statusFilter))
            ->when($search !== '', static function ($query) use ($search): void {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $query->where('title', 'like', $term);
            })
            ->with(['creator', 'linkedRequirement:id,title', 'project:id,name,code'])
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (ProjectProposal $proposal): array => $this->proposalRow($proposal, $actor));

        $selectedProject = $projectId === null
            ? null
            : $visibleProjects->firstWhere('id', $projectId);

        return Inertia::render('admin/proposals/Index', [
            'projects' => $visibleProjects->map(static fn (Project $project): array => [
                'value' => $project->id,
                'label' => $project->code !== null && $project->code !== ''
                    ? "{$project->name} ({$project->code})"
                    : $project->name,
            ])->all(),
            'selected_project' => $selectedProject === null ? null : [
                'id' => $selectedProject->id,
                'name' => $selectedProject->name,
                'code' => $selectedProject->code,
                'estimation_required' => $selectedProject->estimation_required,
            ],
            'proposals' => $proposals,
            'filters' => [
                'project_id' => $projectId !== null ? (string) $projectId : '',
                'search' => $search,
                'status' => $statusFilter,
            ],
            'status_options' => $this->statusOptions(),
            'can_create_proposals_for_selected_project' => $selectedProject !== null
                && $actor->can('create', [ProjectProposal::class, $selectedProject]),
        ]);
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
            'project' => [
                'id' => $proposal->project->id,
                'name' => $proposal->project->name,
                'code' => $proposal->project->code,
            ],
            'can_update' => $actor->can('update', $proposal),
            'can_delete' => $actor->can('delete', $proposal),
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
}
