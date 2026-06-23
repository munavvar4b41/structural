<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectTaskStatus;
use App\Enums\RequirementEstimationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProjectTaskRequest;
use App\Http\Requests\Admin\UpdateProjectTaskRequest;
use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\ProjectTask;
use App\Models\User;
use App\Support\AssignmentNotificationDispatcher;
use App\Support\ProjectRequirementAssignableUsers;
use App\Support\ProjectTaskAssigneeCapabilities;
use App\Support\ProjectTaskDisplayOrder;
use App\Support\ProjectTaskHierarchy;
use App\Support\ProjectTaskShowPayloadBuilder;
use App\Support\RequirementEstimationTaskSource;
use App\Support\RequirementPhaseRegistry;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ProjectTaskController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ProjectTaskShowPayloadBuilder $showPayloadBuilder,
        private readonly AssignmentNotificationDispatcher $assignmentNotificationDispatcher,
        private readonly RequirementPhaseRegistry $requirementPhaseRegistry,
        private readonly ProjectTaskHierarchy $taskHierarchy,
    ) {}

    public function index(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $filter = (string) $request->query('task_filter', 'all');
        if (! in_array($filter, ['all', 'linked', 'unlinked'], true)) {
            $filter = 'all';
        }

        $estimationSource = (string) $request->query('estimation_source', '');
        $canFilterEstimationSource = RequirementEstimationTaskSource::canFilterBySource($actor);
        if (! $canFilterEstimationSource || ! in_array($estimationSource, ['transferred', 'ad_hoc'], true)) {
            $estimationSource = '';
        }

        $search = trim((string) $request->query('search', ''));

        $assigneeQuery = $request->query('assignee_id');
        $assigneeId = null;
        if ($assigneeQuery !== null && $assigneeQuery !== '') {
            $aid = (int) $assigneeQuery;
            $assigneeId = $aid > 0 ? $aid : null;
        }

        $assignableIds = collect($this->assignableUserOptions($project))
            ->pluck('value')
            ->all();

        if ($assigneeId !== null && ! in_array($assigneeId, $assignableIds, true)) {
            $assigneeId = null;
        }

        $allowedStatusValues = array_map(
            static fn (ProjectTaskStatus $s): string => $s->value,
            ProjectTaskStatus::cases(),
        );

        $statusRaw = $request->query('status');
        $statuses = [];
        if (is_array($statusRaw)) {
            $statuses = array_values(array_intersect(
                array_map(static fn (mixed $v): string => (string) $v, $statusRaw),
                $allowedStatusValues,
            ));
        }

        $phaseFilterPayload = $this->requirementPhaseRegistry->taskFilterPayloadForProject($project);
        $allowedPhaseValues = array_map(
            static fn (array $option): int => (int) $option['value'],
            $phaseFilterPayload['options'],
        );
        $phaseQuery = $request->query('phase');
        $phase = null;
        if ($phaseQuery !== null && $phaseQuery !== '') {
            $parsedPhase = (int) $phaseQuery;
            if (in_array($parsedPhase, $allowedPhaseValues, true)) {
                $phase = $parsedPhase;
            }
        }

        $parentLinks = ProjectTask::query()
            ->where('project_id', $project->id)
            ->get(['id', 'parent_project_task_id']);

        /** @var array<int, int|null> */
        $parentOf = [];
        foreach ($parentLinks as $row) {
            $parentOf[$row->id] = $row->parent_project_task_id;
        }

        $matchQuery = $project->tasks()->getQuery();

        if ($filter === 'linked') {
            $matchQuery->whereNotNull('project_requirement_id');
        } elseif ($filter === 'unlinked') {
            $matchQuery->whereNull('project_requirement_id');
        }

        $matchQuery
            ->when($search !== '', static function ($query) use ($search): void {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $query->where(static function ($query) use ($term): void {
                    $query->where('title', 'like', $term)
                        ->orWhere('description', 'like', $term);
                });
            })
            ->when($statuses !== [], static fn ($query) => $query->whereIn('status', $statuses))
            ->when($assigneeId !== null, static fn ($query) => $query->where('assignee_user_id', $assigneeId))
            ->when($estimationSource === 'transferred', static fn ($query) => $query->whereNotNull('project_requirement_estimation_item_id'))
            ->when($estimationSource === 'ad_hoc', static function ($query): void {
                $query->whereNotNull('project_requirement_id')
                    ->whereNull('project_requirement_estimation_item_id')
                    ->whereHas('requirement.estimations', static function ($estimationQuery): void {
                        $estimationQuery->where('status', RequirementEstimationStatus::Transferred);
                    });
            })
            ->when($phase !== null, static fn ($query) => $query->where('phase', $phase));

        $matchingIds = $matchQuery->pluck('id')->all();

        if ($matchingIds === []) {
            $tasksCollection = new EloquentCollection([]);
        } else {
            $expandedIds = $this->expandTaskIdsWithAncestors($parentOf, $matchingIds);

            $tasksCollection = $project->tasks()
                ->whereIn('id', $expandedIds)
                ->with(['assignee:id,name,email', 'requirement:id,title'])
                ->withCount('children')
                ->get();
        }

        $transferredRequirementIds = $tasksCollection
            ->pluck('project_requirement_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $requirementsWithTransfer = $transferredRequirementIds === []
            ? []
            : ProjectRequirement::query()
                ->whereIn('id', $transferredRequirementIds)
                ->whereHas('estimations', static function ($query): void {
                    $query->where('status', RequirementEstimationStatus::Transferred);
                })
                ->pluck('id')
                ->all();

        $tasks = collect(ProjectTaskDisplayOrder::depthFirstWithDepth($tasksCollection))
            ->map(fn (array $row): array => $this->taskRow(
                $row['task'],
                $actor,
                $row['depth'],
                $requirementsWithTransfer,
            ))
            ->all();

        return Inertia::render('admin/projects/tasks/Index', [
            'project' => $this->projectSummary($project),
            'tasks' => $tasks,
            'task_filter' => $filter,
            'filters' => [
                'search' => $search,
                'assignee_id' => $assigneeId !== null ? (string) $assigneeId : '',
                'status' => $statuses,
                'estimation_source' => $estimationSource,
                'phase' => $phase !== null ? (string) $phase : '',
            ],
            'show_phase_filter' => $phaseFilterPayload['show_filter'],
            'phase_filter_options' => array_map(
                static fn (array $option): array => [
                    'value' => (string) $option['value'],
                    'label' => $option['label'],
                ],
                $phaseFilterPayload['options'],
            ),
            'can_filter_estimation_source' => $canFilterEstimationSource,
            'estimation_source_options' => $canFilterEstimationSource
                ? [
                    ['value' => 'transferred', 'label' => 'From estimation'],
                    ['value' => 'ad_hoc', 'label' => 'New task (post-transfer)'],
                ]
                : [],
            ...$this->taskFormOptions($project),
            'can_create_tasks' => $actor->can('create', [ProjectTask::class, $project]),
            'can_manage_project' => $actor->can('update', $project),
        ]);
    }

    /**
     * @param  array<int, int|null>  $parentOf
     * @param  list<int>  $seedIds
     * @return list<int>
     */
    private function expandTaskIdsWithAncestors(array $parentOf, array $seedIds): array
    {
        $keep = [];

        foreach ($seedIds as $id) {
            $current = $id;

            while ($current !== null) {
                $keep[$current] = true;
                $current = $parentOf[$current] ?? null;
            }
        }

        return array_keys($keep);
    }

    public function create(Request $request, Project $project): Response
    {
        $this->authorize('create', [ProjectTask::class, $project]);

        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $requirementId = $this->validatedRequirementIdFromQuery($request, $project);
        $parentTaskId = $this->validatedParentTaskIdFromQuery($request, $project);

        return Inertia::render('admin/projects/tasks/Create', [
            'project' => $this->projectSummary($project),
            ...$this->taskFormOptions($project),
            'parent_tasks' => $this->parentTaskOptions($project),
            'defaults' => [
                'project_requirement_id' => $requirementId !== null ? (string) $requirementId : '',
                'parent_project_task_id' => $parentTaskId !== null ? (string) $parentTaskId : '',
            ],
            'cancel_href' => $this->resolveCancelHref($request, $project),
        ]);
    }

    public function edit(Request $request, Project $project, ProjectTask $task): Response
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $this->authorize('update', $task);

        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        return Inertia::render('admin/projects/tasks/Edit', [
            'project' => $this->projectSummary($project),
            'task' => $this->taskEditPayload($task),
            ...$this->taskFormOptions($project),
            'parent_tasks' => $this->parentTaskOptionsForEdit($project, $task),
            'is_assignee_only_limited' => ProjectTaskAssigneeCapabilities::isAssigneeOnlyLimited($actor, $task),
            'cancel_href' => $this->resolveCancelHref(
                $request,
                $project,
                route('admin.projects.tasks.show', [$project, $task]),
            ),
        ]);
    }

    public function show(Request $request, Project $project, ProjectTask $task): Response
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $this->authorize('view', $task);

        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        return Inertia::render('admin/projects/tasks/Show', $this->showPayloadBuilder->build($project, $task, $actor));
    }

    public function store(StoreProjectTaskRequest $request, Project $project): RedirectResponse
    {
        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $data = $request->validated();
        $data['project_id'] = $project->id;
        $data['created_by_user_id'] = $actor->id;

        $task = ProjectTask::query()->create($data);

        if ($task->assignee_user_id !== null) {
            $this->assignmentNotificationDispatcher->sendTaskAssigned($task, $actor);
        }

        if ($this->cameFromGlobalTasksIndex($request)) {
            return redirect()
                ->back()
                ->with('toast', __('Task created.'));
        }

        return to_route('admin.projects.tasks.index', $project)->with('toast', __('Task created.'));
    }

    public function update(UpdateProjectTaskRequest $request, Project $project, ProjectTask $task): RedirectResponse
    {
        $this->ensureTaskBelongsToProject($project, $task);

        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $originalAssigneeId = $task->assignee_user_id;
        $originalStatus = $task->status;
        $payload = $request->validated();

        if (array_key_exists('notify_at', $payload)) {
            $existingNotifyAt = $task->notify_at;
            $incomingNotifyAt = $payload['notify_at'] === null ? null : Carbon::parse($payload['notify_at']);

            if (($existingNotifyAt?->toIso8601String()) !== ($incomingNotifyAt?->toIso8601String())) {
                $payload['notified_at'] = null;
            }
        }

        $task->update($payload);

        if ($task->wasChanged('status')
            && $task->status !== $originalStatus
            && $this->taskHierarchy->hasDirectChildren($task)) {
            $this->taskHierarchy->cascadeStatus($task, $task->status);
        }

        $assigneeChanged = $task->wasChanged('assignee_user_id') && $originalAssigneeId !== $task->assignee_user_id;

        if ($assigneeChanged && $task->assignee_user_id !== null) {
            $this->assignmentNotificationDispatcher->sendTaskAssigned($task, $actor);
        } elseif ($task->wasChanged()) {
            $this->assignmentNotificationDispatcher->sendTaskUpdated($task, $actor);
        }

        $return = $request->input('return');
        if (is_string($return) && $return !== '' && $this->isSafeAdminReturnUrl($return)) {
            return redirect($return)->with('toast', __('Task updated.'));
        }

        return back()->with('toast', __('Task updated.'));
    }

    public function destroy(Request $request, Project $project, ProjectTask $task): RedirectResponse
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $this->authorize('delete', $task);

        $task->delete();

        if ($request->boolean('from_my_work')) {
            return redirect()
                ->route('admin.my-work.index')
                ->with('toast', __('Task deleted.'));
        }

        return to_route('admin.projects.tasks.index', $project)->with('toast', __('Task deleted.'));
    }

    /**
     * @return array<string, mixed>
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
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return collect(ProjectTaskStatus::cases())
            ->map(static fn (ProjectTaskStatus $s): array => [
                'value' => $s->value,
                'label' => $s->label(),
            ])
            ->all();
    }

    /**
     * @return array{
     *     status_options: list<array{value: string, label: string}>,
     *     assignable_users: list<array{value: int, label: string}>,
     *     requirements: list<array{value: int, label: string, max_generated_phase: int}>
     * }
     */
    private function taskFormOptions(Project $project): array
    {
        return [
            'status_options' => $this->statusOptions(),
            'assignable_users' => $this->assignableUserOptions($project),
            'requirements' => $project->requirements()->orderBy('title')->get(['id', 'title', 'max_generated_phase'])->map(static fn (ProjectRequirement $r): array => [
                'value' => $r->id,
                'label' => $r->title,
                'max_generated_phase' => max(1, (int) ($r->max_generated_phase ?? RequirementPhaseRegistry::INITIAL_MAX_PHASE)),
            ])->all(),
        ];
    }

    /**
     * @return list<array{id: int, title: string, tree_depth: int}>
     */
    private function parentTaskOptions(Project $project): array
    {
        $tasks = $project->tasks()->orderBy('title')->get(['id', 'title', 'parent_project_task_id']);

        return collect(ProjectTaskDisplayOrder::depthFirstWithDepth($tasks))
            ->map(static fn (array $row): array => [
                'id' => $row['task']->id,
                'title' => $row['task']->title,
                'tree_depth' => $row['depth'],
            ])
            ->all();
    }

    private function parentTaskOptionsForEdit(Project $project, ProjectTask $editing): array
    {
        $tasks = $project->tasks()->orderBy('title')->get(['id', 'title', 'parent_project_task_id']);

        /** @var array<int, list<int>> $childrenByParent */
        $childrenByParent = [];
        foreach ($tasks as $task) {
            if ($task->parent_project_task_id !== null) {
                $childrenByParent[$task->parent_project_task_id][] = $task->id;
            }
        }

        $blockedIds = $this->collectDescendantTaskIds((int) $editing->id, $childrenByParent);
        $blockedIds[(int) $editing->id] = true;

        $eligible = $tasks->filter(static fn (ProjectTask $task): bool => ! isset($blockedIds[$task->id]));

        return collect(ProjectTaskDisplayOrder::depthFirstWithDepth($eligible))
            ->map(static fn (array $row): array => [
                'id' => $row['task']->id,
                'title' => $row['task']->title,
                'tree_depth' => $row['depth'],
            ])
            ->all();
    }

    /**
     * @param  array<int, list<int>>  $childrenByParent
     * @return array<int, true>
     */
    private function collectDescendantTaskIds(int $taskId, array $childrenByParent): array
    {
        $descendantIds = [];
        $queue = $childrenByParent[$taskId] ?? [];

        while ($queue !== []) {
            $currentId = array_shift($queue);

            if ($currentId === null || isset($descendantIds[$currentId])) {
                continue;
            }

            $descendantIds[$currentId] = true;
            $queue = array_merge($queue, $childrenByParent[$currentId] ?? []);
        }

        return $descendantIds;
    }

    private function validatedRequirementIdFromQuery(Request $request, Project $project): ?int
    {
        $raw = $request->query('requirement');
        if ($raw === null || $raw === '') {
            return null;
        }

        $id = (int) $raw;
        if ($id <= 0) {
            return null;
        }

        $exists = ProjectRequirement::query()
            ->where('project_id', $project->id)
            ->whereKey($id)
            ->exists();

        return $exists ? $id : null;
    }

    private function validatedParentTaskIdFromQuery(Request $request, Project $project): ?int
    {
        $raw = $request->query('parent');
        if ($raw === null || $raw === '') {
            return null;
        }

        $id = (int) $raw;
        if ($id <= 0) {
            return null;
        }

        $exists = ProjectTask::query()
            ->where('project_id', $project->id)
            ->whereKey($id)
            ->exists();

        return $exists ? $id : null;
    }

    private function resolveCancelHref(Request $request, Project $project, ?string $default = null): string
    {
        $return = $request->query('return');
        if (is_string($return) && $return !== '' && $this->isSafeAdminReturnUrl($return)) {
            return $return;
        }

        return $default ?? route('admin.projects.tasks.index', $project);
    }

    private function isSafeAdminReturnUrl(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && str_starts_with($path, '/admin/');
    }

    private function cameFromGlobalTasksIndex(Request $request): bool
    {
        $previous = $request->headers->get('referer', url()->previous());
        $path = parse_url($previous, PHP_URL_PATH);

        return is_string($path) && preg_match('#/admin/tasks/?$#', $path) === 1;
    }

    /**
     * @return array<string, mixed>
     */
    private function taskEditPayload(ProjectTask $task): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status->value,
            'assignee_user_id' => $task->assignee_user_id,
            'project_requirement_id' => $task->project_requirement_id,
            'parent_project_task_id' => $task->parent_project_task_id,
            'estimated_minutes' => $task->estimated_minutes,
            'phase' => $task->phase,
            'display_after_at' => $task->display_after_at?->toIso8601String(),
            'notify_at' => $task->notify_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function assignableUserOptions(Project $project): array
    {
        $ids = ProjectRequirementAssignableUsers::responsibleUserIds($project);
        if ($ids === []) {
            return [];
        }

        return User::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(static fn (User $u): array => [
                'value' => $u->id,
                'label' => $u->name.' ('.$u->email.')',
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @param  list<int>  $requirementsWithTransferredEstimation
     */
    private function taskRow(ProjectTask $task, User $actor, int $treeDepth = 0, array $requirementsWithTransferredEstimation = []): array
    {
        $estimationSource = null;
        if ($task->project_requirement_id !== null) {
            if ($task->project_requirement_estimation_item_id !== null) {
                $estimationSource = 'transferred';
            } elseif (in_array($task->project_requirement_id, $requirementsWithTransferredEstimation, true)) {
                $estimationSource = 'ad_hoc';
            }
        }

        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status->value,
            'status_label' => $task->status->label(),
            'assignee_user_id' => $task->assignee_user_id,
            'assignee' => $this->userBrief($task->assignee),
            'project_requirement_id' => $task->project_requirement_id,
            'requirement_title' => $task->requirement?->title,
            'parent_project_task_id' => $task->parent_project_task_id,
            'estimated_minutes' => $task->estimated_minutes,
            'phase' => $task->phase,
            'phase_label' => $task->phase !== null ? $this->requirementPhaseRegistry->phaseLabel((int) $task->phase) : null,
            'display_after_at' => $task->display_after_at?->toIso8601String(),
            'notify_at' => $task->notify_at?->toIso8601String(),
            'children_count' => $task->children_count,
            'tree_depth' => $treeDepth,
            'can_update' => $actor->can('update', $task),
            'can_delete' => $actor->can('delete', $task),
            'is_assignee_only_limited' => ProjectTaskAssigneeCapabilities::isAssigneeOnlyLimited($actor, $task),
            'can_submit_task_completion' => $this->canSubmitTaskCompletion($actor, $task),
            'can_confirm_task_completion' => $actor->can('confirmCompletion', $task),
            'estimation_source' => $estimationSource,
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

    private function canSubmitTaskCompletion(User $actor, ProjectTask $task): bool
    {
        if (! $actor->can('submitCompletion', $task)) {
            return false;
        }

        return ! in_array($task->status, [
            ProjectTaskStatus::Review,
            ProjectTaskStatus::Done,
            ProjectTaskStatus::Cancelled,
        ], true);
    }

    private function ensureTaskBelongsToProject(Project $project, ProjectTask $task): void
    {
        abort_if($task->project_id !== $project->id, 404);
    }
}
