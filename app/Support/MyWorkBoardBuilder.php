<?php

namespace App\Support;

use App\Enums\ProjectTaskStatus;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\TaskTimeEntry;
use App\Models\User;
use Illuminate\Http\Request;

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

        $baseQuery = ProjectTask::query()
            ->where('assignee_user_id', $actor->id)
            ->whereIn('project_id', Project::query()->visibleToUser($actor)->select('projects.id'));

        if ($projectId !== null) {
            $baseQuery->where('project_id', $projectId);
        }

        $activeEntry = TaskTimeEntry::activeSessionForUser($actor->id);

        $columns = [];
        foreach (ProjectTaskStatus::boardOrder() as $status) {
            $pageName = 'page_'.$status->value;
            $page = max(1, (int) ($request?->input($pageName) ?? 1));

            $statusQuery = (clone $baseQuery)->where('status', $status);
            $total = (clone $statusQuery)->count();
            $lastPage = max(1, (int) ceil($total / self::PER_COLUMN));
            $limit = min($total, self::PER_COLUMN * $page);

            $tasks = (clone $statusQuery)
                ->with(['project:id,name,code', 'requirement:id,title'])
                ->orderByDesc('updated_at')
                ->limit($limit)
                ->get();

            $columns[] = [
                'status' => $status->value,
                'label' => $status->label(),
                'tasks' => $tasks
                    ->map(fn (ProjectTask $task): array => $this->taskCard($task, $actor, $activeEntry))
                    ->all(),
                'meta' => [
                    'total' => $total,
                    'current_page' => min($page, $lastPage),
                    'last_page' => $lastPage,
                    'per_page' => self::PER_COLUMN,
                ],
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
            'project_options' => Project::query()
                ->visibleToUser($actor)
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
     * @return array<string, mixed>
     */
    private function taskCard(ProjectTask $task, User $actor, ?TaskTimeEntry $activeEntry): array
    {
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
            'timer_today_seconds' => TaskTimeEntry::todayElapsedSecondsForUserOnTask(
                $actor->id,
                $task->id,
            ),
            'timer_state' => $timerState,
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
}
