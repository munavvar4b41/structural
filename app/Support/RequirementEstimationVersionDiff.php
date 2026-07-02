<?php

namespace App\Support;

use App\Models\ProjectRequirementEstimation;
use App\Models\ProjectRequirementEstimationItem;
use Illuminate\Database\Eloquent\Collection;

final class RequirementEstimationVersionDiff
{
    /**
     * @return array{
     *     added: list<array<string, mixed>>,
     *     removed: list<array<string, mixed>>,
     *     modified: list<array<string, mixed>>,
     *     summary: array{added_count: int, removed_count: int, modified_count: int, minutes_from: int, minutes_to: int, minutes_delta: int}
     * }
     */
    public function compare(ProjectRequirementEstimation $from, ProjectRequirementEstimation $to): array
    {
        abort_if($from->project_requirement_id !== $to->project_requirement_id, 404);

        $fromItems = $from->items()->get();
        $toItems = $to->items()->get();

        $fromById = $fromItems->keyBy('id');
        $fromKeyed = $this->keyedLines($fromItems);
        $toKeyed = $this->keyedLines($toItems);

        $matchedFromIds = [];
        $matchedToIds = [];
        $modified = [];

        foreach ($toItems as $toItem) {
            $fromItem = null;

            if ($toItem->source_estimation_item_id !== null) {
                $candidate = $fromById->get($toItem->source_estimation_item_id);
                if ($candidate instanceof ProjectRequirementEstimationItem) {
                    $fromItem = $candidate;
                }
            }

            if ($fromItem === null) {
                $structuralKey = $this->structuralKeyForItem($toItem, $toItems);
                $fromItem = $fromKeyed[$structuralKey] ?? null;
            }

            if ($fromItem === null) {
                continue;
            }

            $matchedFromIds[$fromItem->id] = true;
            $matchedToIds[$toItem->id] = true;

            $changes = $this->fieldChanges($fromItem, $toItem);
            if ($changes !== []) {
                $modified[] = [
                    'from' => $this->lineBrief($fromItem),
                    'to' => $this->lineBrief($toItem),
                    'changes' => $changes,
                ];
            }
        }

        $added = [];
        foreach ($toItems as $toItem) {
            if (isset($matchedToIds[$toItem->id])) {
                continue;
            }

            $added[] = $this->lineBrief($toItem);
        }

        $removed = [];
        foreach ($fromItems as $fromItem) {
            if (isset($matchedFromIds[$fromItem->id])) {
                continue;
            }

            $removed[] = $this->lineBrief($fromItem);
        }

        $minutesFrom = RequirementEstimationTotals::totalMinutesFromItems($fromItems);
        $minutesTo = RequirementEstimationTotals::totalMinutesFromItems($toItems);

        return [
            'added' => $added,
            'removed' => $removed,
            'modified' => $modified,
            'summary' => [
                'added_count' => count($added),
                'removed_count' => count($removed),
                'modified_count' => count($modified),
                'minutes_from' => $minutesFrom,
                'minutes_to' => $minutesTo,
                'minutes_delta' => $minutesTo - $minutesFrom,
            ],
        ];
    }

    /**
     * @param  Collection<int, ProjectRequirementEstimationItem>  $items
     * @return array<string, ProjectRequirementEstimationItem>
     */
    private function keyedLines(Collection $items): array
    {
        $keyed = [];

        foreach (RequirementEstimationDisplayOrder::depthFirstWithDepth($items) as ['item' => $item]) {
            $keyed[$this->structuralKeyForItem($item, $items)] = $item;
        }

        return $keyed;
    }

    /**
     * @param  Collection<int, ProjectRequirementEstimationItem>  $items
     */
    private function structuralKeyForItem(ProjectRequirementEstimationItem $item, Collection $items): string
    {
        $segments = [];
        $current = $item;

        while (true) {
            array_unshift($segments, $current->sort_order.':'.$current->title);

            if ($current->parent_estimation_item_id === null) {
                break;
            }

            $parent = $items->firstWhere('id', $current->parent_estimation_item_id);
            if ($parent === null) {
                break;
            }

            $current = $parent;
        }

        return implode('/', $segments);
    }

    /**
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function fieldChanges(ProjectRequirementEstimationItem $from, ProjectRequirementEstimationItem $to): array
    {
        $changes = [];

        if ($from->title !== $to->title) {
            $changes['title'] = ['from' => $from->title, 'to' => $to->title];
        }

        if ($from->description !== $to->description) {
            $changes['description'] = ['from' => $from->description, 'to' => $to->description];
        }

        if ($from->estimated_minutes !== $to->estimated_minutes) {
            $changes['estimated_minutes'] = ['from' => $from->estimated_minutes, 'to' => $to->estimated_minutes];
        }

        if ($from->phase !== $to->phase) {
            $changes['phase'] = ['from' => $from->phase, 'to' => $to->phase];
        }

        return $changes;
    }

    /**
     * @return array<string, mixed>
     */
    private function lineBrief(ProjectRequirementEstimationItem $item): array
    {
        return [
            'id' => $item->id,
            'title' => $item->title,
            'description' => $item->description,
            'estimated_minutes' => $item->estimated_minutes,
            'phase' => $item->phase,
            'sort_order' => $item->sort_order,
        ];
    }
}
