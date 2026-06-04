<?php

namespace App\Support;

use App\Models\ProjectRequirementEstimation;
use App\Models\ProjectRequirementEstimationItem;
use Illuminate\Database\Eloquent\Collection;

final class RequirementEstimationTotals
{
    /**
     * Sum estimated minutes across all line items.
     */
    public static function totalMinutes(ProjectRequirementEstimation $estimation): int
    {
        $items = $estimation->items()->get();

        return RequirementEstimationMinutesRollup::forItems($items)->totalMinutes();
    }

    /**
     * @param  Collection<int, ProjectRequirementEstimationItem>  $items
     */
    public static function totalMinutesFromItems(Collection $items): int
    {
        return RequirementEstimationMinutesRollup::forItems($items)->totalMinutes();
    }
}
