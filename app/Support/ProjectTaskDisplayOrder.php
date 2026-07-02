<?php

namespace App\Support;

use App\Models\ProjectTask;
use Illuminate\Database\Eloquent\Collection;

final class ProjectTaskDisplayOrder
{
    /**
     * Order tasks depth-first: roots by phase then sort_order, children by sort_order.
     *
     * @param  Collection<int, ProjectTask>  $tasks
     * @return list<array{task: ProjectTask, depth: int}>
     */
    public static function depthFirstWithDepth(Collection $tasks): array
    {
        if ($tasks->isEmpty()) {
            return [];
        }

        $roots = $tasks
            ->whereNull('parent_project_task_id')
            ->sort(self::rootComparator(...))
            ->values();

        $result = [];
        $seen = [];

        $walk = function (ProjectTask $node, int $depth) use (&$walk, &$result, &$seen, $tasks): void {
            $result[] = ['task' => $node, 'depth' => $depth];
            $seen[$node->id] = true;

            foreach ($tasks->where('parent_project_task_id', $node->id)->sort(self::childComparator(...)) as $child) {
                $walk($child, $depth + 1);
            }
        };

        foreach ($roots as $root) {
            $walk($root, 0);
        }

        foreach ($tasks->sort(self::rootComparator(...)) as $task) {
            if (! isset($seen[$task->id])) {
                $result[] = ['task' => $task, 'depth' => 0];
                $seen[$task->id] = true;
            }
        }

        return $result;
    }

    private static function rootComparator(ProjectTask $left, ProjectTask $right): int
    {
        $phaseCompare = self::phaseSortKey($left) <=> self::phaseSortKey($right);
        if ($phaseCompare !== 0) {
            return $phaseCompare;
        }

        $sortOrderCompare = $left->sort_order <=> $right->sort_order;
        if ($sortOrderCompare !== 0) {
            return $sortOrderCompare;
        }

        return $left->id <=> $right->id;
    }

    private static function childComparator(ProjectTask $left, ProjectTask $right): int
    {
        $sortOrderCompare = $left->sort_order <=> $right->sort_order;
        if ($sortOrderCompare !== 0) {
            return $sortOrderCompare;
        }

        return $left->id <=> $right->id;
    }

    private static function phaseSortKey(ProjectTask $task): int
    {
        return $task->phase ?? PHP_INT_MAX;
    }
}
