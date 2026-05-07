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
use App\Support\ProjectTaskDisplayOrder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectTaskController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $filter = (string) $request->query('task_filter', 'all');
        if (! in_array($filter, ['all', 'linked', 'unlinked'], true)) {
            $filter = 'all';
        }

        $query = $project->tasks()
            ->with(['assignee:id,name,email', 'requirement:id,title'])
            ->withCount('children');

        if ($filter === 'linked') {
            $query->whereNotNull('project_requirement_id');
        } elseif ($filter === 'unlinked') {
            $query->whereNull('project_requirement_id');
        }

        $tasksCollection = $query->get();

        $tasks = collect(ProjectTaskDisplayOrder::depthFirstWithDepth($tasksCollection))
            ->map(fn (array $row): array => $this->taskRow($row['task'], $actor, $row['depth']))
            ->all();

        return Inertia::render('admin/projects/tasks/Index', [
            'project' => $this->projectSummary($project),
            'tasks' => $tasks,
            'task_filter' => $filter,
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
            'time_tracking' => $this->timeTrackingProps($task, $actor),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function timeTrackingProps(ProjectTask $task, User $actor): array
    {
        $startOfToday = CarbonImmutable::today();
        $endOfToday = $startOfToday->endOfDay();

        $entries = TaskTimeEntry::query()
            ->where('project_task_id', $task->id)
            ->with('user:id,name,email')
            ->orderByDesc('started_at')
            ->limit(50)
            ->get();

        $myEntries = $entries->where('user_id', $actor->id);

        $myTodayTotal = (int) $myEntries
            ->filter(fn (TaskTimeEntry $e): bool => $e->ended_at !== null
                && $e->started_at->between($startOfToday, $endOfToday))
            ->sum('duration_seconds');

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
                ])
                ->values()
                ->all(),
            'can_update' => $actor->can('update', $task),
            'can_delete' => $actor->can('delete', $task),
        ];
    }

    private function ensureTaskBelongsToProject(Project $project, ProjectTask $task): void
    {
        abort_if($task->project_id !== $project->id, 404);
    }
}
