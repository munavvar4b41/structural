<?php

namespace App\Http\Controllers\Admin;

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
                        ->orWhereRaw('overview LIKE ?', [$term]);
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
        $this->authorize('viewAny', CaseStudy::class);
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
                        ->orWhereRaw('overview LIKE ?', [$term]);
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
            'overview' => $validated['overview'] ?? null,
            'client_issue' => $validated['client_issue'] ?? null,
            'our_solution' => $validated['our_solution'] ?? null,
            'implementation' => $validated['implementation'] ?? null,
            'other_details' => $validated['other_details'] ?? null,
            'result_and_impact' => $validated['result_and_impact'] ?? null,
            'conclusion' => $validated['conclusion'] ?? null,
        ]);

        $this->attachmentStorage->storeManyDocuments($caseStudy, $this->uploadedDocuments($request));

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
        ]);
    }

    public function update(UpdateCaseStudyRequest $request, Project $project, CaseStudy $caseStudy): RedirectResponse
    {
        $this->ensureCaseStudyBelongsToProject($project, $caseStudy);

        $validated = $request->validated();

        $caseStudy->update([
            'project_task_id' => $validated['project_task_id'] ?? null,
            'title' => $validated['title'],
            'overview' => $validated['overview'] ?? null,
            'client_issue' => $validated['client_issue'] ?? null,
            'our_solution' => $validated['our_solution'] ?? null,
            'implementation' => $validated['implementation'] ?? null,
            'other_details' => $validated['other_details'] ?? null,
            'result_and_impact' => $validated['result_and_impact'] ?? null,
            'conclusion' => $validated['conclusion'] ?? null,
        ]);

        $removeIds = $validated['remove_attachment_ids'] ?? [];
        if (is_array($removeIds) && $removeIds !== []) {
            $this->attachmentStorage->deleteMany($caseStudy, array_map(intval(...), $removeIds));
        }

        $this->attachmentStorage->storeManyDocuments($caseStudy, $this->uploadedDocuments($request));

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
     * @return list<array{title: string, file: UploadedFile}>
     */
    private function uploadedDocuments(Request $request): array
    {
        $inputDocuments = $request->input('documents', []);
        if (! is_array($inputDocuments)) {
            return [];
        }

        $documents = [];
        foreach ($inputDocuments as $index => $document) {
            if (! is_array($document)) {
                continue;
            }

            $file = $request->file("documents.{$index}.file");
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $documents[] = [
                'title' => trim((string) ($document['title'] ?? '')),
                'file' => $file,
            ];
        }

        return $documents;
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
     * @return array<string, mixed>
     */
    private function caseStudyRow(CaseStudy $caseStudy, User $actor): array
    {
        $overviewPreview = TipTapDocument::previewFromStored($caseStudy->overview);

        return [
            'id' => $caseStudy->id,
            'title' => $caseStudy->title,
            'summary_preview' => $overviewPreview !== null && $overviewPreview !== ''
                ? $overviewPreview
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
            'overview' => $caseStudy->overview,
            'client_issue' => $caseStudy->client_issue,
            'our_solution' => $caseStudy->our_solution,
            'implementation' => $caseStudy->implementation,
            'other_details' => $caseStudy->other_details,
            'result_and_impact' => $caseStudy->result_and_impact,
            'conclusion' => $caseStudy->conclusion,
            'created_at' => $caseStudy->created_at?->toIso8601String(),
            'creator' => $this->userBrief($caseStudy->creator),
            'task' => $caseStudy->task ? [
                'id' => $caseStudy->task->id,
                'title' => $caseStudy->task->title,
            ] : null,
            'attachments' => $caseStudy->attachments->map(static fn ($attachment): array => [
                'id' => $attachment->id,
                'title' => $attachment->title,
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
            'overview' => $caseStudy->overview,
            'client_issue' => $caseStudy->client_issue,
            'our_solution' => $caseStudy->our_solution,
            'implementation' => $caseStudy->implementation,
            'other_details' => $caseStudy->other_details,
            'result_and_impact' => $caseStudy->result_and_impact,
            'conclusion' => $caseStudy->conclusion,
            'attachments' => $caseStudy->attachments->map(static fn ($attachment): array => [
                'id' => $attachment->id,
                'title' => $attachment->title,
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
