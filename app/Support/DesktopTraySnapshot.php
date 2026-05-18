<?php

namespace App\Support;

use App\Enums\ProjectTaskStatus;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\TaskTimeEntry;
use App\Models\User;
use Illuminate\Support\Str;

class DesktopTraySnapshot
{
    private const TITLE_LIMIT = 24;

    private const TRAY_ICON_TITLE_LIMIT = 15;

    /**
     * @return array{active: array<string, mixed>|null, pending_tasks: list<array<string, mixed>>}
     */
    public function build(User $actor): array
    {
        return [
            'active' => $this->activeEntry($actor),
            'pending_tasks' => $this->pendingTasks($actor),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function activeEntry(User $actor): ?array
    {
        $entry = TaskTimeEntry::query()
            ->where('user_id', $actor->id)
            ->open()
            ->with(['task:id,title', 'project:id'])
            ->latest('started_at')
            ->first();

        if ($entry === null) {
            return null;
        }

        $title = $entry->task?->title ?? '';

        return [
            'id' => $entry->id,
            'task_id' => $entry->project_task_id,
            'project_id' => $entry->project_id,
            'task_title' => $title,
            'task_title_short' => Str::limit($title, self::TITLE_LIMIT),
            'task_title_tray' => Str::limit($title, self::TRAY_ICON_TITLE_LIMIT),
            'is_paused' => $entry->isPaused(),
            'elapsed_seconds' => $entry->elapsedSeconds(),
            'started_at' => $entry->started_at->toIso8601String(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pendingTasks(User $actor): array
    {
        return ProjectTask::query()
            ->where('assignee_user_id', $actor->id)
            ->where('status', ProjectTaskStatus::ToDo)
            ->whereIn('project_id', Project::query()->visibleToUser($actor)->select('projects.id'))
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get(['id', 'project_id', 'title'])
            ->map(static fn (ProjectTask $task): array => [
                'id' => $task->id,
                'project_id' => $task->project_id,
                'title' => $task->title,
                'title_short' => Str::limit($task->title, self::TITLE_LIMIT),
            ])
            ->all();
    }
}
