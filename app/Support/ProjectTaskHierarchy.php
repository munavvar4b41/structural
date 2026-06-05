<?php

namespace App\Support;

use App\Enums\ProjectTaskStatus;
use App\Models\ProjectTask;
use Illuminate\Support\Collection;

class ProjectTaskHierarchy
{
    /**
     * @return list<int>
     */
    public function directChildIds(ProjectTask $parent): array
    {
        return ProjectTask::query()
            ->where('parent_project_task_id', $parent->id)
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @return list<int>
     */
    public function descendantIds(ProjectTask $parent): array
    {
        $projectId = $parent->project_id;

        /** @var array<int, int|null> $parentOf */
        $parentOf = ProjectTask::query()
            ->where('project_id', $projectId)
            ->pluck('parent_project_task_id', 'id')
            ->map(static fn (mixed $pid): ?int => $pid === null ? null : (int) $pid)
            ->all();

        $descendants = [];
        $queue = $this->directChildIds($parent);

        while ($queue !== []) {
            $childId = array_shift($queue);
            $descendants[] = $childId;

            foreach ($parentOf as $taskId => $parentId) {
                if ($parentId === $childId) {
                    $queue[] = $taskId;
                }
            }
        }

        return $descendants;
    }

    /**
     * @param  array<int, int|null>  $parentOf
     */
    public function rootAncestorId(int $taskId, ?array $parentOf = null): int
    {
        if ($parentOf === null) {
            $parentOf = $this->parentLinksForProject(
                (int) ProjectTask::query()->whereKey($taskId)->value('project_id'),
            );
        }

        $current = $taskId;
        $visited = [];

        while (isset($parentOf[$current]) && $parentOf[$current] !== null) {
            if (isset($visited[$current])) {
                break;
            }

            $visited[$current] = true;
            $current = $parentOf[$current];
        }

        return $current;
    }

    /**
     * @return array<int, int|null>
     */
    public function parentLinksForProject(int $projectId): array
    {
        return ProjectTask::query()
            ->where('project_id', $projectId)
            ->pluck('parent_project_task_id', 'id')
            ->map(static fn (mixed $pid): ?int => $pid === null ? null : (int) $pid)
            ->all();
    }

    /**
     * @return list<int>
     */
    public function familyIds(ProjectTask $task): array
    {
        return array_values(array_unique([
            $task->id,
            ...$this->descendantIds($task),
        ]));
    }

    public function hasDirectChildren(ProjectTask $task): bool
    {
        return ProjectTask::query()
            ->where('parent_project_task_id', $task->id)
            ->exists();
    }

    /**
     * @return Collection<int, ProjectTask>
     */
    public function directChildren(ProjectTask $parent): Collection
    {
        return ProjectTask::query()
            ->where('parent_project_task_id', $parent->id)
            ->orderBy('id')
            ->get();
    }

    public function cascadeStatus(ProjectTask $parent, ProjectTaskStatus $status): void
    {
        $descendantIds = $this->descendantIds($parent);

        if ($descendantIds === []) {
            return;
        }

        ProjectTask::query()
            ->whereIn('id', $descendantIds)
            ->where('status', '!=', $status)
            ->update(['status' => $status]);
    }

    /**
     * @param  list<ProjectTask>  $children
     * @return list<int>
     */
    public function distributeSecondsByEstimates(int $totalSeconds, array $children): array
    {
        if ($children === []) {
            return [];
        }

        $allHaveEstimates = collect($children)->every(
            static fn (ProjectTask $child): bool => $child->estimated_minutes !== null && $child->estimated_minutes > 0,
        );

        $weights = [];
        foreach ($children as $index => $child) {
            if ($allHaveEstimates) {
                $weights[$index] = (int) $child->estimated_minutes;
            } else {
                $weights[$index] = 1;
            }
        }

        $weightSum = array_sum($weights);

        if ($weightSum <= 0) {
            $count = count($children);

            return array_fill(0, $count, intdiv($totalSeconds, $count));
        }

        $allocations = [];
        $fractions = [];
        $allocated = 0;

        foreach ($weights as $index => $weight) {
            $exact = ($totalSeconds * $weight) / $weightSum;
            $whole = (int) floor($exact);
            $allocations[$index] = $whole;
            $fractions[$index] = $exact - $whole;
            $allocated += $whole;
        }

        $remainder = $totalSeconds - $allocated;

        if ($remainder > 0) {
            $indices = array_keys($fractions);
            usort($indices, static fn (int $a, int $b): int => $fractions[$b] <=> $fractions[$a]);

            for ($i = 0; $i < $remainder; $i++) {
                $allocations[$indices[$i % count($indices)]]++;
            }
        }

        return array_values($allocations);
    }
}
