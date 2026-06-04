<?php

namespace App\Support;

use App\Models\ProjectRequirementEstimationItem;
use Illuminate\Database\Eloquent\Collection;

final class RequirementEstimationDisplayOrder
{
    /**
     * @param  Collection<int, ProjectRequirementEstimationItem>  $items
     * @return list<array{item: ProjectRequirementEstimationItem, depth: int}>
     */
    public static function depthFirstWithDepth(Collection $items): array
    {
        if ($items->isEmpty()) {
            return [];
        }

        $roots = $items->whereNull('parent_estimation_item_id')->sortBy('sort_order')->values();

        $result = [];
        $seen = [];

        $walk = function (ProjectRequirementEstimationItem $node, int $depth) use (&$walk, &$result, &$seen, $items): void {
            $result[] = ['item' => $node, 'depth' => $depth];
            $seen[$node->id] = true;

            foreach ($items->where('parent_estimation_item_id', $node->id)->sortBy('sort_order') as $child) {
                $walk($child, $depth + 1);
            }
        };

        foreach ($roots as $root) {
            $walk($root, 0);
        }

        foreach ($items->sortBy('sort_order') as $item) {
            if (! isset($seen[$item->id])) {
                $result[] = ['item' => $item, 'depth' => 0];
                $seen[$item->id] = true;
            }
        }

        return $result;
    }
}
