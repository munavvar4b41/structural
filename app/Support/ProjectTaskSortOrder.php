<?php

namespace App\Support;

use App\Models\ProjectTask;

final class ProjectTaskSortOrder
{
    public static function nextForCreate(
        int $projectId,
        ?int $parentProjectTaskId,
        ?int $phase,
    ): int {
        $max = (int) ProjectTask::query()
            ->where('project_id', $projectId)
            ->where('parent_project_task_id', $parentProjectTaskId)
            ->when(
                $phase === null,
                static fn ($query) => $query->whereNull('phase'),
                static fn ($query) => $query->where('phase', $phase),
            )
            ->max('sort_order');

        return $max + 1;
    }
}
