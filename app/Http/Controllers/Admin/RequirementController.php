<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\User;
use App\Support\ProjectRequirementAssignableUsers;
use App\Support\TipTapDocument;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RequirementController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $this->authorize('viewAny', ProjectRequirement::class);

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

        $reviewStatus = (string) $request->query('review_status', '');
        $allowedReviewStatuses = ['', 'pending_review', 'awaiting_understanding', 'confirmed'];
        if (! in_array($reviewStatus, $allowedReviewStatuses, true)) {
            $reviewStatus = '';
        }

        $responsibleQuery = $request->query('responsible_user_id');
        $responsibleUserId = null;
        if ($responsibleQuery !== null && $responsibleQuery !== '') {
            $rid = (int) $responsibleQuery;
            $responsibleUserId = $rid > 0 ? $rid : null;
        }

        $responsibleOptions = $this->responsibleOptions($projectId);
        $assignableIds = collect($responsibleOptions)->pluck('value')->all();

        if ($responsibleUserId !== null && ! in_array($responsibleUserId, $assignableIds, true)) {
            $responsibleUserId = null;
        }

        $requirements = ProjectRequirement::query()
            ->whereIn('project_id', $visibleProjectIds)
            ->when($projectId !== null, static fn ($query) => $query->where('project_id', $projectId))
            ->when($search !== '', static function ($query) use ($search): void {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $query->where(static function ($query) use ($term): void {
                    $query->where('title', 'like', $term)
                        ->orWhere('description', 'like', $term);
                });
            })
            ->when($reviewStatus === 'pending_review', static fn ($query) => $query->whereNull('reviewed_at'))
            ->when($reviewStatus === 'awaiting_understanding', static function ($query): void {
                $query->whereNotNull('reviewed_at')
                    ->whereNull('understanding_confirmed_at');
            })
            ->when($reviewStatus === 'confirmed', static fn ($query) => $query->whereNotNull('understanding_confirmed_at'))
            ->when($responsibleUserId !== null, static fn ($query) => $query->where('responsible_user_id', $responsibleUserId))
            ->with(['creator', 'responsibleUser', 'reviewer', 'project:id,name,code'])
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (ProjectRequirement $requirement): array => $this->requirementRow($requirement, $actor));

        $selectedProject = $projectId === null
            ? null
            : $visibleProjects->firstWhere('id', $projectId);

        return Inertia::render('admin/requirements/Index', [
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
            'requirements' => $requirements,
            'filters' => [
                'project_id' => $projectId !== null ? (string) $projectId : '',
                'search' => $search,
                'review_status' => $reviewStatus,
                'responsible_user_id' => $responsibleUserId !== null ? (string) $responsibleUserId : '',
            ],
            'filter_options' => [
                'review_status' => [
                    ['value' => 'pending_review', 'label' => 'Pending review'],
                    ['value' => 'awaiting_understanding', 'label' => 'Awaiting understanding'],
                    ['value' => 'confirmed', 'label' => 'Understanding confirmed'],
                ],
                'responsibles' => $responsibleOptions,
            ],
            'can_create_requirements_for_selected_project' => $selectedProject !== null
                && $actor->can('create', [ProjectRequirement::class, $selectedProject]),
        ]);
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function responsibleOptions(?int $projectId): array
    {
        if ($projectId === null) {
            return [];
        }

        $project = Project::query()->find($projectId);
        if (! $project instanceof Project) {
            return [];
        }

        $ids = ProjectRequirementAssignableUsers::responsibleUserIds($project);
        if ($ids === []) {
            return [];
        }

        return User::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(static fn (User $user): array => [
                'value' => $user->id,
                'label' => $user->name.' ('.$user->email.')',
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function requirementRow(ProjectRequirement $requirement, User $actor): array
    {
        return [
            'id' => $requirement->id,
            'title' => $requirement->title,
            'description_preview' => TipTapDocument::previewFromStored($requirement->description),
            'reviewed_at' => $requirement->reviewed_at?->toIso8601String(),
            'understanding_confirmed_at' => $requirement->understanding_confirmed_at?->toIso8601String(),
            'created_at' => $requirement->created_at?->toIso8601String(),
            'creator' => $this->userBrief($requirement->creator),
            'responsible_user' => $this->userBrief($requirement->responsibleUser),
            'reviewer' => $this->userBrief($requirement->reviewer),
            'project' => [
                'id' => $requirement->project->id,
                'name' => $requirement->project->name,
                'code' => $requirement->project->code,
            ],
            'can_update' => $actor->can('update', $requirement),
            'can_delete' => $actor->can('delete', $requirement),
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
