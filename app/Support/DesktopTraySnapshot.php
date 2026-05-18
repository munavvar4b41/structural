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

    private const PROJECT_LIMIT = 12;

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
        $entry = TaskTimeEntry::activeSessionForUser($actor->id);
        if ($entry === null) {
            return null;
        }

        $entry->loadMissing(['task:id,title', 'project:id,name,code']);

        $title = $entry->task?->title ?? '';
        $projectName = $entry->project?->name ?? '';
        $projectCode = $entry->project?->code;
        $projectLabel = $projectCode !== null && $projectCode !== ''
            ? $projectCode
            : $projectName;
        $description = $this->taskDescription($projectLabel, $title);
        $taskTodaySeconds = TaskTimeEntry::todayElapsedSecondsForUserOnTask(
            $actor->id,
            $entry->project_task_id,
        );

        return [
            'id' => $entry->id,
            'task_id' => $entry->project_task_id,
            'project_id' => $entry->project_id,
            'task_title' => $title,
            'task_title_short' => Str::limit($title, self::TITLE_LIMIT),
            'task_title_tray' => Str::limit($title, self::TRAY_ICON_TITLE_LIMIT),
            'project_name' => $projectName,
            'project_name_short' => Str::limit($projectLabel, self::PROJECT_LIMIT),
            'description' => Str::limit($description, self::TITLE_LIMIT),
            'description_tray' => Str::limit($description, self::TRAY_ICON_TITLE_LIMIT),
            'is_paused' => $entry->isPaused(),
            'elapsed_seconds' => $entry->elapsedSeconds(),
            'task_today_seconds' => $taskTodaySeconds,
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
            ->with('project:id,name,code')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get()
            ->map(function (ProjectTask $task): array {
                $projectLabel = $task->project?->code ?: ($task->project?->name ?? '');
                $description = $this->taskDescription($projectLabel, $task->title);

                return [
                    'id' => $task->id,
                    'project_id' => $task->project_id,
                    'title' => $task->title,
                    'title_short' => Str::limit($task->title, self::TITLE_LIMIT),
                    'project_name' => $task->project?->name ?? '',
                    'project_name_short' => Str::limit($projectLabel, self::PROJECT_LIMIT),
                    'description' => Str::limit($description, self::TITLE_LIMIT),
                    'description_tray' => Str::limit($description, self::TRAY_ICON_TITLE_LIMIT),
                ];
            })
            ->all();
    }

    private function taskDescription(string $projectLabel, string $taskTitle): string
    {
        if ($projectLabel === '') {
            return $taskTitle;
        }

        return "{$projectLabel} · {$taskTitle}";
    }
}
