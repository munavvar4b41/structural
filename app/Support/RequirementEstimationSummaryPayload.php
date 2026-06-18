<?php

namespace App\Support;

use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\ProjectRequirementEstimation;
use App\Models\User;

final class RequirementEstimationSummaryPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function forRequirementShow(
        ProjectRequirement $requirement,
        Project $project,
        User $actor,
    ): array {
        $understandingConfirmed = $requirement->understanding_confirmed_at !== null;
        $estimation = $requirement->activeEstimation()
            ?? $requirement->latestApprovedOrTransferredEstimation();

        $canOpen = $understandingConfirmed
            && $actor->can('view', $requirement);

        $canManage = $understandingConfirmed
            && $actor->can('create', [ProjectRequirementEstimation::class, $requirement]);

        return [
            'understanding_confirmed' => $understandingConfirmed,
            'can_open_estimation' => $canOpen,
            'can_create_estimation' => $canManage && $requirement->activeEstimation() === null,
            'estimation_summary' => $estimation === null ? null : [
                'id' => $estimation->id,
                'version' => $estimation->version,
                'status' => $estimation->status->value,
                'status_label' => $estimation->status->label(),
                'total_minutes' => RequirementEstimationTotals::totalMinutes($estimation),
            ],
        ];
    }
}
