<?php

namespace App\Support;

use App\Models\ProjectTask;
use Illuminate\Database\Eloquent\Collection;

final class ProjectTaskDisplayOrder
{
    /**
     * Order tasks depth-first: each root, then its subtree (sorted by title at each level),
     * then any orphan rows (e.g. missing parent) at the end.
     *
     * @param  Collection<int, ProjectTask>  $tasks
     * @return list<array{task: ProjectTask, depth: int}>
     */
    public static function depthFirstWithDepth(Collection $tasks): array
    {
        if ($tasks->isEmpty()) {
            return [];
        }

        $roots = $tasks->whereNull('parent_project_task_id')->sortBy('title')->values();

        $result = [];
        $seen = [];

        $walk = function (ProjectTask $node, int $depth) use (&$walk, &$result, &$seen, $tasks): void {
            $result[] = ['task' => $node, 'depth' => $depth];
            $seen[$node->id] = true;

            foreach ($tasks->where('parent_project_task_id', $node->id)->sortBy('title') as $child) {
                $walk($child, $depth + 1);
            }
        };

        foreach ($roots as $root) {
            $walk($root, 0);
        }

        foreach ($tasks->sortBy('title') as $task) {
            if (! isset($seen[$task->id])) {
                $result[] = ['task' => $task, 'depth' => 0];
                $seen[$task->id] = true;
            }
        }

        return $result;
    }
}
