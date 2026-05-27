<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectTaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProjectTaskRequest;
use App\Http\Requests\Admin\UpdateProjectTaskRequest;
use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\ProjectTask;
use App\Models\User;
use App\Support\ProjectRequirementAssignableUsers;
use App\Support\ProjectTaskAssigneeCapabilities;
use App\Support\ProjectTaskDisplayOrder;
use App\Support\ProjectTaskShowPayloadBuilder;
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

        return Inertia::render('admin/projects/tasks/Show', $this->showPayloadBuilder->build($project, $task, $actor));
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

        $payload = $request->validated();

        if (array_key_exists('notify_at', $payload)) {
            $existingNotifyAt = $task->notify_at;
            $incomingNotifyAt = $payload['notify_at'] === null ? null : Carbon::parse($payload['notify_at']);

            if (($existingNotifyAt?->toIso8601String()) !== ($incomingNotifyAt?->toIso8601String())) {
                $payload['notified_at'] = null;
            }
        }

        $task->update($payload);

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
            'display_after_at' => $task->display_after_at?->toIso8601String(),
            'notify_at' => $task->notify_at?->toIso8601String(),
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
