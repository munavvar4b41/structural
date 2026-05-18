<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectTaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProjectTaskRequest;
use App\Http\Requests\Admin\UpdateProjectTaskRequest;
use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\ProjectTask;
use App\Models\TaskTimeEntry;
use App\Models\User;
use App\Support\ProjectRequirementAssignableUsers;
use App\Support\ProjectTaskAssigneeCapabilities;
use App\Support\ProjectTaskDisplayOrder;
use App\Support\ProjectTaskShowPayloadBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectTaskController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly ProjectTaskShowPayloadBuilder $showPayloadBuilder)
    {
        //
    }

    public function index(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $filter = (string) $request->query('task_filter', 'all');
        if (! in_array($filter, ['all', 'linked', 'unlinked'], true)) {
            $filter = 'all';
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
            ->when($assigneeId !== null, static fn ($query) => $query->where('assignee_user_id', $assigneeId));

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

        $tasks = collect(ProjectTaskDisplayOrder::depthFirstWithDepth($tasksCollection))
            ->map(fn (array $row): array => $this->taskRow($row['task'], $actor, $row['depth']))
            ->all();

        return Inertia::render('admin/projects/tasks/Index', [
            'project' => $this->projectSummary($project),
            'tasks' => $tasks,
            'task_filter' => $filter,
            'filters' => [
                'search' => $search,
                'assignee_id' => $assigneeId !== null ? (string) $assigneeId : '',
                'status' => $statuses,
            ],
            'status_options' => $this->statusOptions(),
            'assignable_users' => $this->assignableUserOptions($project),
            'requirements' => $project->requirements()->orderBy('title')->get(['id', 'title'])->map(static fn (ProjectRequirement $r): array => [
                'value' => $r->id,
                'label' => $r->title,
            ])->all(),
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

    public function show(Request $request, Project $project, ProjectTask $task): Response
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $this->authorize('view', $task);

        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $task->load([
            'assignee:id,name,email',
            'requirement:id,title',
            'parent:id,title',
            'completionSubmittedBy:id,name,email',
            'checklistItems' => fn ($q) => $q->orderBy('created_at'),
        ]);
        $task->loadCount('children');

        $directChildren = ProjectTask::query()
            ->where('project_id', $project->id)
            ->where('parent_project_task_id', $task->id)
            ->orderBy('title')
            ->with(['assignee:id,name,email', 'requirement:id,title'])
            ->withCount('children')
            ->get();

        return Inertia::render('admin/projects/tasks/Show', [
            'project' => $this->projectSummary($project),
            'task' => $this->taskDetail($task, $actor, $directChildren),
            'can_manage_project' => $actor->can('update', $project),
            'checklist' => ProjectTaskChecklistProps::forTask($task, $actor),
            'time_tracking' => $this->timeTrackingProps($task, $actor),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function timeTrackingProps(ProjectTask $task, User $actor): array
    {
        $entries = TaskTimeEntry::query()
            ->where('project_task_id', $task->id)
            ->with('user:id,name,email')
            ->orderByDesc('started_at')
            ->limit(50)
            ->get();

        $myEntries = $entries->where('user_id', $actor->id);

        $myTodayTotal = TaskTimeEntry::todayElapsedSecondsForUserOnTask(
            $actor->id,
            $task->id,
        );

        $myAllTimeTotal = (int) $myEntries
            ->whereNotNull('duration_seconds')
            ->sum('duration_seconds');

        $taskAllTimeTotal = (int) $entries
            ->whereNotNull('duration_seconds')
            ->sum('duration_seconds');

        return [
            'can_track' => $actor->can('start', [TaskTimeEntry::class, $task]),
            'totals' => [
                'my_today_seconds' => $myTodayTotal,
                'my_all_time_seconds' => $myAllTimeTotal,
                'task_all_time_seconds' => $taskAllTimeTotal,
            ],
            'entries' => $entries->map(fn (TaskTimeEntry $e): array => [
                'id' => $e->id,
                'user_id' => $e->user_id,
                'user_name' => $e->user?->name,
                'started_at' => $e->started_at?->toIso8601String(),
                'ended_at' => $e->ended_at?->toIso8601String(),
                'duration_seconds' => $e->duration_seconds,
                'is_running' => $e->ended_at === null,
                'source' => $e->source->value,
                'source_label' => $e->source->label(),
                'notes' => $e->notes,
                'can_update' => $actor->can('update', $e),
                'can_delete' => $actor->can('delete', $e),
            ])->all(),
        ];
    }

    public function store(StoreProjectTaskRequest $request, Project $project): RedirectResponse
    {
        $data = $request->validated();
        $data['project_id'] = $project->id;
        $data['created_by_user_id'] = $request->user()->id;

        ProjectTask::query()->create($data);

        return back()->with('toast', __('Task created.'));
    }

    public function update(UpdateProjectTaskRequest $request, Project $project, ProjectTask $task): RedirectResponse
    {
        $this->ensureTaskBelongsToProject($project, $task);

        $task->update($request->validated());

        return back()->with('toast', __('Task updated.'));
    }

    public function destroy(Request $request, Project $project, ProjectTask $task): RedirectResponse
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $this->authorize('delete', $task);

        $task->delete();

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
    private function taskRow(ProjectTask $task, User $actor, int $treeDepth = 0): array
    {
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
            'children_count' => $task->children_count,
            'tree_depth' => $treeDepth,
            'can_update' => $actor->can('update', $task),
            'can_delete' => $actor->can('delete', $task),
            'is_assignee_only_limited' => ProjectTaskAssigneeCapabilities::isAssigneeOnlyLimited($actor, $task),
            'can_submit_task_completion' => $this->canSubmitTaskCompletion($actor, $task),
            'can_confirm_task_completion' => $actor->can('confirmCompletion', $task),
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

    /**
     * @return array<string, mixed>
     */
    private function taskDetail(ProjectTask $task, User $actor, EloquentCollection $directChildren): array
    {
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
            'parent' => $task->parent === null ? null : [
                'id' => $task->parent->id,
                'title' => $task->parent->title,
            ],
            'estimated_minutes' => $task->estimated_minutes,
            'children_count' => $task->children_count,
            'subtasks' => $directChildren
                ->map(fn (ProjectTask $child): array => [
                    'id' => $child->id,
                    'title' => $child->title,
                    'status' => $child->status->value,
                    'status_label' => $child->status->label(),
                    'assignee_user_id' => $child->assignee_user_id,
                    'assignee' => $this->userBrief($child->assignee),
                    'project_requirement_id' => $child->project_requirement_id,
                    'requirement_title' => $child->requirement?->title,
                    'estimated_minutes' => $child->estimated_minutes,
                    'children_count' => $child->children_count,
                    'tree_depth' => 1,
                    'can_update' => $actor->can('update', $child),
                    'can_delete' => $actor->can('delete', $child),
                    'is_assignee_only_limited' => ProjectTaskAssigneeCapabilities::isAssigneeOnlyLimited($actor, $child),
                    'can_submit_task_completion' => $this->canSubmitTaskCompletion($actor, $child),
                    'can_confirm_task_completion' => $actor->can('confirmCompletion', $child),
                ])
                ->values()
                ->all(),
            'can_update' => $actor->can('update', $task),
            'can_delete' => $actor->can('delete', $task),
            'completion_submitted_at' => $task->completion_submitted_at?->toIso8601String(),
            'completion_submitted_by' => $this->userBrief($task->completionSubmittedBy),
            'is_assignee_only_limited' => ProjectTaskAssigneeCapabilities::isAssigneeOnlyLimited($actor, $task),
            'can_submit_task_completion' => $this->canSubmitTaskCompletion($actor, $task),
            'can_confirm_task_completion' => $actor->can('confirmCompletion', $task),
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
