<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectTaskStatus;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\ProjectTask;
use App\Models\User;
use App\Support\ProjectRequirementAssignableUsers;
use App\Support\ProjectTaskAssigneeCapabilities;
use App\Support\ProjectTaskDisplayOrder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
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

        $allowedStatusValues = array_map(
            static fn (ProjectTaskStatus $status): string => $status->value,
            ProjectTaskStatus::cases(),
        );

        $statusRaw = $request->query('status');
        $statuses = [];
        if (is_array($statusRaw)) {
            $statuses = array_values(array_intersect(
                array_map(static fn (mixed $value): string => (string) $value, $statusRaw),
                $allowedStatusValues,
            ));
        }

        $assigneeQuery = $request->query('assignee_id');
        $assigneeId = null;
        if ($assigneeQuery !== null && $assigneeQuery !== '') {
            $candidateAssigneeId = (int) $assigneeQuery;
            $assigneeId = $candidateAssigneeId > 0 ? $candidateAssigneeId : null;
        }

        $assignableOptions = $this->assignableUserOptions($projectId);
        $assignableIds = collect($assignableOptions)->pluck('value')->all();
        if ($assigneeId !== null && ! in_array($assigneeId, $assignableIds, true)) {
            $assigneeId = null;
        }

        $taskFilter = (string) $request->query('task_filter', 'all');
        if (! in_array($taskFilter, ['all', 'linked', 'unlinked'], true)) {
            $taskFilter = 'all';
        }

        $taskQuery = ProjectTask::query()
            ->whereIn('project_id', $visibleProjectIds);

        if ($projectId !== null) {
            $taskQuery->where('project_id', $projectId);
        }

        if ($taskFilter === 'linked') {
            $taskQuery->whereNotNull('project_requirement_id');
        } elseif ($taskFilter === 'unlinked') {
            $taskQuery->whereNull('project_requirement_id');
        }

        $taskQuery
            ->when($search !== '', static function ($query) use ($search): void {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $query->where(static function ($query) use ($term): void {
                    $query->where('title', 'like', $term)
                        ->orWhere('description', 'like', $term);
                });
            })
            ->when($statuses !== [], static fn ($query) => $query->whereIn('status', $statuses))
            ->when($assigneeId !== null, static fn ($query) => $query->where('assignee_user_id', $assigneeId));

        $tasksCollection = $taskQuery
            ->with([
                'assignee:id,name,email',
                'requirement:id,title',
                'project:id,name,code,estimation_required',
            ])
            ->withCount('children')
            ->get();

        $tasks = $this->tasksWithDepthByProject($tasksCollection, $actor);

        $selectedProject = $projectId === null
            ? null
            : $visibleProjects->firstWhere('id', $projectId);

        return Inertia::render('admin/tasks/Index', [
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
            'tasks' => $tasks,
            'task_filter' => $taskFilter,
            'filters' => [
                'project_id' => $projectId !== null ? (string) $projectId : '',
                'search' => $search,
                'assignee_id' => $assigneeId !== null ? (string) $assigneeId : '',
                'status' => $statuses,
            ],
            'status_options' => collect(ProjectTaskStatus::cases())
                ->map(static fn (ProjectTaskStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ])
                ->all(),
            'assignable_users' => $assignableOptions,
            'requirements' => $this->requirementsOptions($projectId),
            'parent_tasks' => $this->parentTaskOptions($projectId),
            'can_create_tasks_for_selected_project' => $selectedProject !== null
                && $actor->can('create', [ProjectTask::class, $selectedProject]),
        ]);
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function assignableUserOptions(?int $projectId): array
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
     * @return list<array{value: int, label: string}>
     */
    private function requirementsOptions(?int $projectId): array
    {
        if ($projectId === null) {
            return [];
        }

        return ProjectRequirement::query()
            ->where('project_id', $projectId)
            ->orderBy('title')
            ->get(['id', 'title'])
            ->map(static fn (ProjectRequirement $requirement): array => [
                'value' => $requirement->id,
                'label' => $requirement->title,
            ])
            ->all();
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function parentTaskOptions(?int $projectId): array
    {
        if ($projectId === null) {
            return [];
        }

        $tasks = ProjectTask::query()
            ->where('project_id', $projectId)
            ->get();

        return collect(ProjectTaskDisplayOrder::depthFirstWithDepth($tasks))
            ->map(static function (array $row): array {
                /** @var ProjectTask $task */
                $task = $row['task'];
                $depth = $row['depth'];

                return [
                    'value' => $task->id,
                    'label' => $depth > 0 ? str_repeat('— ', $depth).$task->title : $task->title,
                ];
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tasksWithDepthByProject(EloquentCollection $tasksCollection, User $actor): array
    {
        $tasksByProject = $tasksCollection->groupBy('project_id');
        $orderedRows = [];

        foreach ($tasksByProject as $projectTasks) {
            $ordered = ProjectTaskDisplayOrder::depthFirstWithDepth(new EloquentCollection($projectTasks->all()));

            foreach ($ordered as $row) {
                /** @var ProjectTask $task */
                $task = $row['task'];
                $orderedRows[] = $this->taskRow($task, $actor, $row['depth']);
            }
        }

        return $orderedRows;
    }

    /**
     * @return array<string, mixed>
     */
    private function taskRow(ProjectTask $task, User $actor, int $treeDepth): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status->value,
            'status_label' => $task->status->label(),
            'assignee_user_id' => $task->assignee_user_id,
            'assignee' => $task->assignee === null ? null : [
                'id' => $task->assignee->id,
                'name' => $task->assignee->name,
                'email' => $task->assignee->email,
            ],
            'project_requirement_id' => $task->project_requirement_id,
            'requirement_title' => $task->requirement?->title,
            'parent_project_task_id' => $task->parent_project_task_id,
            'estimated_minutes' => $task->estimated_minutes,
            'display_after_at' => $task->display_after_at?->toIso8601String(),
            'notify_at' => $task->notify_at?->toIso8601String(),
            'children_count' => $task->children_count,
            'tree_depth' => $treeDepth,
            'project' => [
                'id' => $task->project->id,
                'name' => $task->project->name,
                'code' => $task->project->code,
                'estimation_required' => $task->project->estimation_required,
            ],
            'can_update' => $actor->can('update', $task),
            'can_delete' => $actor->can('delete', $task),
            'is_assignee_only_limited' => ProjectTaskAssigneeCapabilities::isAssigneeOnlyLimited($actor, $task),
        ];
    }
}
