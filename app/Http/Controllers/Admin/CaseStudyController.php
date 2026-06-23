<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WorkloadPeriod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCaseStudyRequest;
use App\Http\Requests\Admin\UpdateCaseStudyRequest;
use App\Models\CaseStudy;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use App\Support\CaseStudyAttachmentStorage;
use App\Support\TipTapDocument;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

class CaseStudyController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly CaseStudyAttachmentStorage $attachmentStorage,
    ) {}

    public function globalIndex(Request $request): Response
    {
        $this->authorize('viewAny', CaseStudy::class);

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

        $caseStudies = CaseStudy::query()
            ->whereIn('project_id', $visibleProjectIds)
            ->when($projectId !== null, static fn ($query) => $query->where('project_id', $projectId))
            ->when($search !== '', static function ($query) use ($search): void {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $query->where(static function ($query) use ($term): void {
                    $query->where('title', 'like', $term)
                        ->orWhere('summary', 'like', $term);
                });
            })
            ->with(['creator', 'task:id,title', 'project:id,name,code'])
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (CaseStudy $caseStudy): array => $this->caseStudyRow($caseStudy, $actor));

        $selectedProject = $projectId === null
            ? null
            : $visibleProjects->firstWhere('id', $projectId);

        return Inertia::render('admin/case-studies/Index', [
            'projects' => $visibleProjects->map(static fn (Project $project): array => [
                'value' => $project->id,
                'label' => $project->code !== null && $project->code !== ''
                    ? "{$project->name} ({$project->code})"
                    : $project->name,
            ])->all(),
            'selected_project' => $selectedProject === null ? null : $this->projectSummary($selectedProject),
            'case_studies' => $caseStudies,
            'filters' => [
                'project_id' => $projectId !== null ? (string) $projectId : '',
                'search' => $search,
            ],
            'can_create_for_selected_project' => $selectedProject !== null
                && $actor->can('create', [CaseStudy::class, $selectedProject]),
        ]);
    }

    public function index(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $search = trim((string) $request->query('search', ''));

        $caseStudies = $project->caseStudies()
            ->with(['creator', 'task:id,title'])
            ->when($search !== '', static function ($query) use ($search): void {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $query->where(static function ($query) use ($term): void {
                    $query->where('title', 'like', $term)
                        ->orWhere('summary', 'like', $term);
                });
            })
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (CaseStudy $caseStudy): array => $this->caseStudyRow($caseStudy, $actor));

        return Inertia::render('admin/projects/case-studies/Index', [
            'project' => $this->projectSummary($project),
            'case_studies' => $caseStudies,
            'can_create' => $actor->can('create', [CaseStudy::class, $project]),
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function create(Request $request, Project $project): Response
    {
        $this->authorize('create', [CaseStudy::class, $project]);

        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $preselectedTaskId = $request->query('project_task_id');
        $taskId = null;
        if ($preselectedTaskId !== null && $preselectedTaskId !== '') {
            $candidateTaskId = (int) $preselectedTaskId;
            $exists = $project->tasks()->whereKey($candidateTaskId)->exists();
            if ($exists) {
                $taskId = $candidateTaskId;
            }
        }

        return Inertia::render('admin/projects/case-studies/Create', [
            'project' => $this->projectSummary($project),
            'task_options' => $this->taskOptions($project),
            'workload_period_options' => $this->workloadPeriodOptions(),
            'preselected_task_id' => $taskId,
        ]);
    }

    public function store(StoreCaseStudyRequest $request, Project $project): RedirectResponse
    {
        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $validated = $request->validated();

        $caseStudy = CaseStudy::query()->create([
            'project_id' => $project->id,
            'project_task_id' => $validated['project_task_id'] ?? null,
            'created_by_user_id' => $actor->id,
            'title' => $validated['title'],
            'summary' => $validated['summary'] ?? null,
            'client_issue' => $validated['client_issue'] ?? null,
            'business_impact' => $validated['business_impact'] ?? null,
            'solution_discovery' => $validated['solution_discovery'] ?? null,
            'proposed_solution' => $validated['proposed_solution'] ?? null,
            'implementation' => $validated['implementation'] ?? null,
            'resolution' => $validated['resolution'] ?? null,
            'workload_reduction_details' => $validated['workload_reduction_details'] ?? null,
            'workload_hours_saved' => $validated['workload_hours_saved'] ?? null,
            'workload_percentage_reduction' => $validated['workload_percentage_reduction'] ?? null,
            'workload_period' => $validated['workload_period'] ?? null,
        ]);

        $this->storeUploadedAttachments($caseStudy, $request->file('attachments', []));

        return to_route('admin.projects.case-studies.show', [$project, $caseStudy])
            ->with('toast', __('Case study created.'));
    }

    public function show(Request $request, Project $project, CaseStudy $caseStudy): Response
    {
        $this->ensureCaseStudyBelongsToProject($project, $caseStudy);
        $this->authorize('view', $caseStudy);

        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $caseStudy->loadMissing(['creator', 'task', 'attachments']);

        return Inertia::render('admin/projects/case-studies/Show', [
            'project' => $this->projectSummary($project),
            'case_study' => $this->caseStudyDetailPayload($caseStudy),
            'can_update' => $actor->can('update', $caseStudy),
            'can_delete' => $actor->can('delete', $caseStudy),
        ]);
    }

    public function edit(Request $request, Project $project, CaseStudy $caseStudy): Response
    {
        $this->ensureCaseStudyBelongsToProject($project, $caseStudy);
        $this->authorize('update', $caseStudy);

        $caseStudy->loadMissing('attachments');

        return Inertia::render('admin/projects/case-studies/Edit', [
            'project' => $this->projectSummary($project),
            'case_study' => $this->caseStudyEditPayload($caseStudy),
            'task_options' => $this->taskOptions($project),
            'workload_period_options' => $this->workloadPeriodOptions(),
        ]);
    }

    public function update(UpdateCaseStudyRequest $request, Project $project, CaseStudy $caseStudy): RedirectResponse
    {
        $this->ensureCaseStudyBelongsToProject($project, $caseStudy);

        $validated = $request->validated();

        $caseStudy->update([
            'project_task_id' => $validated['project_task_id'] ?? null,
            'title' => $validated['title'],
            'summary' => $validated['summary'] ?? null,
            'client_issue' => $validated['client_issue'] ?? null,
            'business_impact' => $validated['business_impact'] ?? null,
            'solution_discovery' => $validated['solution_discovery'] ?? null,
            'proposed_solution' => $validated['proposed_solution'] ?? null,
            'implementation' => $validated['implementation'] ?? null,
            'resolution' => $validated['resolution'] ?? null,
            'workload_reduction_details' => $validated['workload_reduction_details'] ?? null,
            'workload_hours_saved' => $validated['workload_hours_saved'] ?? null,
            'workload_percentage_reduction' => $validated['workload_percentage_reduction'] ?? null,
            'workload_period' => $validated['workload_period'] ?? null,
        ]);

        $removeIds = $validated['remove_attachment_ids'] ?? [];
        if (is_array($removeIds) && $removeIds !== []) {
            $this->attachmentStorage->deleteMany($caseStudy, array_map(intval(...), $removeIds));
        }

        $this->storeUploadedAttachments($caseStudy, $request->file('attachments', []));

        return to_route('admin.projects.case-studies.show', [$project, $caseStudy])
            ->with('toast', __('Case study updated.'));
    }

    public function destroy(Project $project, CaseStudy $caseStudy): RedirectResponse
    {
        $this->ensureCaseStudyBelongsToProject($project, $caseStudy);
        $this->authorize('delete', $caseStudy);

        $this->attachmentStorage->deleteAllForCaseStudy($caseStudy);
        $caseStudy->delete();

        return to_route('admin.case-studies.index', ['project_id' => $project->id])
            ->with('toast', __('Case study deleted.'));
    }

    private function ensureCaseStudyBelongsToProject(Project $project, CaseStudy $caseStudy): void
    {
        abort_if($caseStudy->project_id !== $project->id, 404);
    }

    /**
     * @param  array<int, UploadedFile|null>|UploadedFile|null  $files
     */
    private function storeUploadedAttachments(CaseStudy $caseStudy, array|UploadedFile|null $files): void
    {
        if ($files === null) {
            return;
        }

        $uploads = is_array($files) ? array_values(array_filter($files)) : [$files];
        $this->attachmentStorage->storeMany($caseStudy, $uploads);
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
     * @return list<array{value: int, label: string}>
     */
    private function taskOptions(Project $project): array
    {
        return $project->tasks()
            ->orderBy('title')
            ->get(['id', 'title'])
            ->map(static fn (ProjectTask $task): array => [
                'value' => $task->id,
                'label' => $task->title,
            ])
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function workloadPeriodOptions(): array
    {
        return array_map(
            static fn (WorkloadPeriod $period): array => [
                'value' => $period->value,
                'label' => $period->label(),
            ],
            WorkloadPeriod::cases(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function caseStudyRow(CaseStudy $caseStudy, User $actor): array
    {
        return [
            'id' => $caseStudy->id,
            'title' => $caseStudy->title,
            'summary_preview' => $caseStudy->summary !== null && $caseStudy->summary !== ''
                ? $caseStudy->summary
                : TipTapDocument::previewFromStored($caseStudy->client_issue),
            'created_at' => $caseStudy->created_at?->toIso8601String(),
            'creator' => $this->userBrief($caseStudy->creator),
            'task' => $caseStudy->task ? [
                'id' => $caseStudy->task->id,
                'title' => $caseStudy->task->title,
            ] : null,
            'project' => [
                'id' => $caseStudy->project->id,
                'name' => $caseStudy->project->name,
                'code' => $caseStudy->project->code,
            ],
            'can_update' => $actor->can('update', $caseStudy),
            'can_delete' => $actor->can('delete', $caseStudy),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function caseStudyDetailPayload(CaseStudy $caseStudy): array
    {
        return [
            'id' => $caseStudy->id,
            'title' => $caseStudy->title,
            'summary' => $caseStudy->summary,
            'client_issue' => $caseStudy->client_issue,
            'business_impact' => $caseStudy->business_impact,
            'solution_discovery' => $caseStudy->solution_discovery,
            'proposed_solution' => $caseStudy->proposed_solution,
            'implementation' => $caseStudy->implementation,
            'resolution' => $caseStudy->resolution,
            'workload_reduction_details' => $caseStudy->workload_reduction_details,
            'workload_hours_saved' => $caseStudy->workload_hours_saved,
            'workload_percentage_reduction' => $caseStudy->workload_percentage_reduction,
            'workload_period' => $caseStudy->workload_period?->value,
            'workload_period_label' => $caseStudy->workload_period?->label(),
            'created_at' => $caseStudy->created_at?->toIso8601String(),
            'creator' => $this->userBrief($caseStudy->creator),
            'task' => $caseStudy->task ? [
                'id' => $caseStudy->task->id,
                'title' => $caseStudy->task->title,
            ] : null,
            'attachments' => $caseStudy->attachments->map(static fn ($attachment): array => [
                'id' => $attachment->id,
                'original_name' => $attachment->original_name,
                'mime' => $attachment->mime,
                'type' => $attachment->type->value,
                'sort_order' => $attachment->sort_order,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function caseStudyEditPayload(CaseStudy $caseStudy): array
    {
        return [
            'id' => $caseStudy->id,
            'project_task_id' => $caseStudy->project_task_id,
            'title' => $caseStudy->title,
            'summary' => $caseStudy->summary,
            'client_issue' => $caseStudy->client_issue,
            'business_impact' => $caseStudy->business_impact,
            'solution_discovery' => $caseStudy->solution_discovery,
            'proposed_solution' => $caseStudy->proposed_solution,
            'implementation' => $caseStudy->implementation,
            'resolution' => $caseStudy->resolution,
            'workload_reduction_details' => $caseStudy->workload_reduction_details,
            'workload_hours_saved' => $caseStudy->workload_hours_saved,
            'workload_percentage_reduction' => $caseStudy->workload_percentage_reduction,
            'workload_period' => $caseStudy->workload_period?->value,
            'attachments' => $caseStudy->attachments->map(static fn ($attachment): array => [
                'id' => $attachment->id,
                'original_name' => $attachment->original_name,
                'mime' => $attachment->mime,
                'type' => $attachment->type->value,
            ])->all(),
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
