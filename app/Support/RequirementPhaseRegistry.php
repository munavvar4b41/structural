<?php

namespace App\Support;

use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\ProjectRequirementEstimationItem;
use Illuminate\Validation\ValidationException;

final class RequirementPhaseRegistry
{
    public const int INITIAL_MAX_PHASE = 1;

    public function requiresPhaseSelection(ProjectRequirement $requirement): bool
    {
        return $this->maxGeneratedPhase($requirement) > 1;
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    public function optionsFor(ProjectRequirement $requirement): array
    {
        $max = $this->maxGeneratedPhase($requirement);

        if ($max <= 1) {
            return [];
        }

        return $this->buildOptions($max);
    }

    public function resolvePhase(ProjectRequirement $requirement, mixed $phase): int
    {
        if (! $this->requiresPhaseSelection($requirement)) {
            return 1;
        }

        if ($phase === null || $phase === '') {
            throw ValidationException::withMessages([
                'phase' => [__('The phase field is required.')],
            ]);
        }

        $resolved = (int) $phase;
        $this->assertPhaseInRange($requirement, $resolved);

        return $resolved;
    }

    public function highestUsedPhase(ProjectRequirement $requirement): int
    {
        $taskMax = (int) ($requirement->tasks()->max('phase') ?? 0);

        $estimationMax = (int) (ProjectRequirementEstimationItem::query()
            ->whereHas('estimation', static function ($query) use ($requirement): void {
                $query->where('project_requirement_id', $requirement->id);
            })
            ->max('phase') ?? 0);

        return max(1, $taskMax, $estimationMax);
    }

    public function minimumAllowedMax(ProjectRequirement $requirement): int
    {
        return max(1, $this->highestUsedPhase($requirement));
    }

    public function setMaxGeneratedPhase(ProjectRequirement $requirement, int $maxGeneratedPhase): void
    {
        $maxGeneratedPhase = max(1, $maxGeneratedPhase);
        $minimumAllowed = $this->minimumAllowedMax($requirement);

        if ($maxGeneratedPhase < $minimumAllowed) {
            throw ValidationException::withMessages([
                'max_generated_phase' => [
                    __('Maximum phases cannot be less than :min (highest phase in use).', [
                        'min' => $minimumAllowed,
                    ]),
                ],
            ]);
        }

        $requirement->forceFill(['max_generated_phase' => $maxGeneratedPhase])->save();
    }

    /**
     * @return array{
     *     max_generated_phase: int,
     *     min_allowed_max: int,
     *     highest_used_phase: int,
     *     phase_options: list<array{value: int, label: string}>,
     *     requires_phase_selection: bool
     * }
     */
    public function settingsPayload(ProjectRequirement $requirement): array
    {
        $highestUsed = $this->highestUsedPhase($requirement);
        $maxGeneratedPhase = $this->maxGeneratedPhase($requirement);

        return [
            'max_generated_phase' => $maxGeneratedPhase,
            'min_allowed_max' => $this->minimumAllowedMax($requirement),
            'highest_used_phase' => $highestUsed,
            'phase_options' => $this->optionsFor($requirement),
            'requires_phase_selection' => $this->requiresPhaseSelection($requirement),
        ];
    }

    public function phaseLabel(int $phase): string
    {
        return __('Phase :number', ['number' => $phase]);
    }

    /**
     * @return array{
     *     show_filter: bool,
     *     options: list<array{value: int, label: string}>
     * }
     */
    public function taskFilterPayloadForProject(Project $project): array
    {
        $requirementMax = (int) ($project->requirements()->max('max_generated_phase') ?? self::INITIAL_MAX_PHASE);
        $taskMax = (int) ($project->tasks()->max('phase') ?? 0);
        $max = max(1, $requirementMax, $taskMax);

        $hasLinkedTasks = $project->tasks()->whereNotNull('project_requirement_id')->exists();

        return [
            'show_filter' => $max > 1 || $hasLinkedTasks,
            'options' => $this->buildOptions($max),
        ];
    }

    public function assertPhaseInRange(ProjectRequirement $requirement, int $phase): void
    {
        if ($phase < 1) {
            throw ValidationException::withMessages([
                'phase' => [__('The selected phase is invalid.')],
            ]);
        }

        $max = $this->maxGeneratedPhase($requirement);

        if ($phase > $max) {
            throw ValidationException::withMessages([
                'phase' => [
                    __('The selected phase cannot exceed :max for this requirement.', [
                        'max' => $max,
                    ]),
                ],
            ]);
        }
    }

    private function maxGeneratedPhase(ProjectRequirement $requirement): int
    {
        return max(1, (int) ($requirement->max_generated_phase ?? self::INITIAL_MAX_PHASE));
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function buildOptions(int $max): array
    {
        $options = [];

        for ($phase = 1; $phase <= $max; $phase++) {
            $options[] = [
                'value' => $phase,
                'label' => $this->phaseLabel($phase),
            ];
        }

        return $options;
    }
}
