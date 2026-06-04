<?php

namespace App\Support;

use App\Enums\RequirementEstimationStatus;
use App\Enums\UserRole;
use App\Models\ProjectRequirement;
use App\Models\ProjectTask;
use App\Models\User;

final class RequirementEstimationTaskSource
{
    /**
     * @return 'transferred'|'ad_hoc'|null
     */
    public static function forTask(ProjectTask $task, ProjectRequirement $requirement): ?string
    {
        if ($task->project_requirement_id !== $requirement->id) {
            return null;
        }

        if ($task->project_requirement_estimation_item_id !== null) {
            return 'transferred';
        }

        $hasTransferredEstimation = $requirement->estimations()
            ->where('status', RequirementEstimationStatus::Transferred)
            ->exists();

        if ($hasTransferredEstimation) {
            return 'ad_hoc';
        }

        return null;
    }

    public static function canFilterBySource(User $actor): bool
    {
        return in_array($actor->role, [UserRole::SuperAdmin, UserRole::Admin], true);
    }
}
