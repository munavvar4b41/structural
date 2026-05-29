<?php

namespace App\Support;

use App\Enums\ProjectTaskStatus;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\TaskTimeEntry;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class MyWorkBoardBuilder
{
    private const PER_COLUMN = 20;

    /**
     * @return array{
     *     columns: list<array{status: string, label: string, tasks: list<array<string, mixed>>, meta: array{total: int, current_page: int, last_page: int, per_page: int}}>,
     *     status_options: list<array{value: string, label: string}>,
     *     project_options: list<array{value: int, label: string}>,
     *     filters: array{project_id: int|null}
     * }
     */
    public function build(User $actor, ?Request $request = null): array
    {
        $projectId = $request !== null && $request->filled('project_id')
            ? (int) $request->input('project_id')
            : null;

        $visibleProjectsQuery = Project::query()->visibleToUser($actor);

        $baseQuery = ProjectTask::query()
            ->where('assignee_user_id', $actor->id)
            ->where(static function ($query): void {
                $query->whereNull('display_after_at')
                    ->orWhere('display_after_at', '<=', now());
            })
            ->whereIn('project_id', (clone $visibleProjectsQuery)->select('projects.id'));

        if ($projectId !== null) {
            $baseQuery->where('project_id', $projectId);
        }

        $activeEntry = TaskTimeEntry::activeSessionForUser($actor->id);

        /** @var array<string, int> $countsByStatus */
        $countsByStatus = (clone $baseQuery)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();

        /** @var list<array{status: ProjectTaskStatus, tasks: Collection<int, ProjectTask>, meta: array{total: int, current_page: int, last_page: int, per_page: int}}> $columnData */
        $columnData = [];

        foreach (ProjectTaskStatus::boardOrder() as $status) {
            $pageName = 'page_'.$status->value;
            $page = max(1, (int) ($request?->input($pageName) ?? 1));

            $total = $countsByStatus[$status->value] ?? 0;
            $lastPage = max(1, (int) ceil($total / self::PER_COLUMN));
            $limit = min($total, self::PER_COLUMN * $page);

            $tasks = (clone $baseQuery)
                ->where('status', $status)
                ->with(['project:id,name,code,lead_user_id', 'requirement:id,title'])
                ->orderByDesc('updated_at')
                ->limit($limit)
                ->get();

            $columnData[] = [
                'status' => $status,
                'tasks' => $tasks,
                'meta' => [
                    'total' => $total,
                    'current_page' => min($page, $lastPage),
                    'last_page' => $lastPage,
                    'per_page' => self::PER_COLUMN,
                ],
            ];
        }

        $todaySecondsByTask = TaskTimeEntry::todayElapsedSecondsForUserOnTasks(
            $actor->id,
            collect($columnData)
                ->flatMap(static fn (array $column): Collection => $column['tasks'])
                ->pluck('id')
                ->all(),
        );

        $columns = [];
        foreach ($columnData as $column) {
            $status = $column['status'];

            $columns[] = [
                'status' => $status->value,
                'label' => $status->label(),
                'tasks' => $column['tasks']
                    ->map(fn (ProjectTask $task): array => $this->taskCard(
                        $task,
                        $actor,
                        $activeEntry,
                        $todaySecondsByTask,
                    ))
                    ->all(),
                'meta' => $column['meta'],
            ];
        }

        return [
            'columns' => $columns,
            'status_options' => collect(ProjectTaskStatus::cases())
                ->map(static fn (ProjectTaskStatus $s): array => [
                    'value' => $s->value,
                    'label' => $s->label(),
                ])
                ->all(),
            'project_options' => (clone $visibleProjectsQuery)
                ->orderBy('name')
                ->get(['id', 'name', 'code'])
                ->map(static fn (Project $p): array => [
                    'value' => $p->id,
                    'label' => $p->code !== null && $p->code !== ''
                        ? "{$p->name} ({$p->code})"
                        : $p->name,
                ])
                ->all(),
            'filters' => [
                'project_id' => $projectId,
            ],
        ];
    }

    /**
     * @param  array<int, int>  $todaySecondsByTask
     * @return array<string, mixed>
     */
    private function taskCard(
        ProjectTask $task,
        User $actor,
        ?TaskTimeEntry $activeEntry,
        array $todaySecondsByTask,
    ): array {
        $project = $task->project;
        $timerState = 'idle';

        if ($activeEntry !== null && $activeEntry->project_task_id === $task->id) {
            $timerState = $activeEntry->isPaused() ? 'paused' : 'running';
        }

        return [
            'id' => $task->id,
            'project_id' => $project->id,
            'title' => $task->title,
            'status' => $task->status->value,
            'estimated_minutes' => $task->estimated_minutes,
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'code' => $project->code,
            ],
            'requirement' => $task->requirement === null ? null : [
                'id' => $task->requirement->id,
                'title' => $task->requirement->title,
            ],
            'project_tasks_url' => route('admin.projects.tasks.index', $project),
            'task_show_url' => route('admin.projects.tasks.show', [$project, $task]),
            'is_assignee_only_limited' => ProjectTaskAssigneeCapabilities::isAssigneeOnlyLimited($actor, $task),
            'can_submit_task_completion' => $this->canSubmitTaskCompletion($actor, $task),
            'timer_today_seconds' => $todaySecondsByTask[$task->id] ?? 0,
            'timer_state' => $timerState,
        ];
    }

    private function canSubmitTaskCompletion(User $actor, ProjectTask $task): bool
    {
        if ($actor->isClient()) {
            return false;
        }

        if ($task->assignee_user_id !== $actor->id) {
            return false;
        }

        return ! in_array($task->status, [
            ProjectTaskStatus::Review,
            ProjectTaskStatus::Done,
            ProjectTaskStatus::Cancelled,
        ], true);
    }
}
