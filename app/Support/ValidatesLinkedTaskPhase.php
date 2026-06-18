<?php

namespace App\Support;

use App\Models\Project;
use App\Models\ProjectRequirement;
use Illuminate\Validation\ValidationException;

trait ValidatesLinkedTaskPhase
{
    private function resolveValidatedTaskPhase(Project $project, mixed $requirementId, mixed $phase): ?int
    {
        $normalizedRequirementId = $requirementId === null || $requirementId === ''
            ? null
            : (int) $requirementId;

        if ($normalizedRequirementId === null) {
            if ($phase !== null && $phase !== '') {
                throw ValidationException::withMessages([
                    'phase' => [__('Phase can only be set when the task is linked to a requirement.')],
                ]);
            }

            return null;
        }

        $requirement = ProjectRequirement::query()
            ->where('project_id', $project->id)
            ->find($normalizedRequirementId);

        if ($requirement === null) {
            return null;
        }

        /** @var RequirementPhaseRegistry $registry */
        $registry = app(RequirementPhaseRegistry::class);

        return $registry->resolvePhase($requirement, $phase);
    }
}
