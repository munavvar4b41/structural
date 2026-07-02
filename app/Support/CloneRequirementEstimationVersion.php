<?php

namespace App\Support;

use App\Enums\RequirementEstimationStatus;
use App\Models\ProjectRequirement;
use App\Models\ProjectRequirementEstimation;
use App\Models\User;

final class CloneRequirementEstimationVersion
{
    /**
     * @return array{new: ProjectRequirementEstimation, source_status: RequirementEstimationStatus}
     */
    public function clone(
        ProjectRequirementEstimation $source,
        ProjectRequirement $requirement,
        User $actor,
        RequirementEstimationStatus $sourceStatusAfterClone,
    ): array {
        $version = (int) $requirement->estimations()->max('version') + 1;

        $newEstimation = ProjectRequirementEstimation::query()->create([
            'project_requirement_id' => $requirement->id,
            'version' => $version,
            'status' => RequirementEstimationStatus::Draft,
            'created_by_user_id' => $actor->id,
        ]);

        $oldItems = $source->items()->orderBy('sort_order')->get();
        $idMap = [];

        foreach ($oldItems as $item) {
            $newItem = $newEstimation->items()->create([
                'parent_estimation_item_id' => null,
                'source_estimation_item_id' => $item->id,
                'title' => $item->title,
                'description' => $item->description,
                'estimated_minutes' => $item->estimated_minutes,
                'sort_order' => $item->sort_order,
                'phase' => $item->phase,
            ]);
            $idMap[$item->id] = $newItem->id;
        }

        foreach ($oldItems as $item) {
            if ($item->parent_estimation_item_id === null) {
                continue;
            }

            $newParentId = $idMap[$item->parent_estimation_item_id] ?? null;
            if ($newParentId === null) {
                continue;
            }

            $newItemId = $idMap[$item->id] ?? null;
            if ($newItemId === null) {
                continue;
            }

            $newEstimation->items()->whereKey($newItemId)->update([
                'parent_estimation_item_id' => $newParentId,
            ]);
        }

        $source->forceFill([
            'status' => $sourceStatusAfterClone,
            'superseded_by_estimation_id' => $newEstimation->id,
        ])->save();

        return [
            'new' => $newEstimation,
            'source_status' => $sourceStatusAfterClone,
        ];
    }
}
