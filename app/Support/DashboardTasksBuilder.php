<?php

namespace App\Support;

use App\Enums\ProjectTaskStatus;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\TaskPriority;
use App\Models\User;

class DashboardTasksBuilder
{
    public const LIMIT = 20;

    public function __construct(private readonly DashboardTaskPreferences $preferences) {}

    /**
     * @return array{
     *     enabled: bool,
     *     tasks: list<array{
     *         id: int,
     *         title: string,
     *         status: string,
     *         status_label: string,
     *         priority: array{id: int, name: string, color: string, shade: string}|null,
     *         project: array{id: int, name: string, code: string|null},
     *         task_show_url: string
     *     }>,
     *     total: int,
     *     has_more: bool
     * }
     */
    public function build(User $user): array
    {
        $preferences = $this->preferences->forUser($user);

        if (! $preferences['enabled'] || ! $user->can('viewAny', Project::class)) {
            return [
                'enabled' => $preferences['enabled'],
                'tasks' => [],
                'total' => 0,
                'has_more' => false,
            ];
        }

        $query = ProjectTask::query()
            ->where('assignee_user_id', $user->id)
            ->whereNull('parent_project_task_id')
            ->where(static function ($query): void {
                $query->whereNull('display_after_at')
                    ->orWhere('display_after_at', '<=', now());
            })
            ->whereIn('project_id', Project::query()->visibleToUser($user)->select('projects.id'));

        if ($preferences['statuses'] !== []) {
            $query->whereIn('status', $preferences['statuses']);
        }

        if ($preferences['project_ids'] !== []) {
            $query->whereIn('project_id', $preferences['project_ids']);
        }

        TaskPriority::applyFilter($query, TaskPriority::parseFilter($preferences['priorities']));

        $total = (clone $query)->count();

        $tasks = (clone $query)
            ->with([
                'project:id,name,code',
                'priority:id,name,color,shade',
            ])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(static function (ProjectTask $task): array {
                $project = $task->project;

                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'status' => $task->status->value,
                    'status_label' => $task->status->label(),
                    'priority' => $task->priority?->toBadgeArray(),
                    'project' => [
                        'id' => $project->id,
                        'name' => $project->name,
                        'code' => $project->code,
                    ],
                    'task_show_url' => route('admin.projects.tasks.show', [$project, $task]),
                ];
            })
            ->all();

        return [
            'enabled' => true,
            'tasks' => $tasks,
            'total' => $total,
            'has_more' => $total > self::LIMIT,
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function statusOptions(): array
    {
        return collect(ProjectTaskStatus::cases())
            ->map(static fn (ProjectTaskStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ])
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function projectOptions(User $user): array
    {
        return Project::query()
            ->visibleToUser($user)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(static fn (Project $project): array => [
                'value' => (string) $project->id,
                'label' => $project->code !== null && $project->code !== ''
                    ? "{$project->name} ({$project->code})"
                    : $project->name,
            ])
            ->all();
    }
}
