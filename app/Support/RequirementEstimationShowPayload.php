<?php

namespace App\Support;

use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\ProjectRequirementEstimation;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class RequirementEstimationShowPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function forRequirement(
        ProjectRequirement $requirement,
        Project $project,
        User $actor,
        ?ProjectRequirementEstimation $estimation = null,
        ?int $compareFromEstimationId = null,
        ?int $compareToEstimationId = null,
    ): array {
        $estimation ??= $requirement->activeEstimation()
            ?? $requirement->latestRejectedEstimation()
            ?? $requirement->latestApprovedOrTransferredEstimation();

        $versionHistory = self::versionHistory($requirement);
        $versionCompare = self::versionCompare(
            $requirement,
            $compareFromEstimationId,
            $compareToEstimationId,
        );

        $understandingConfirmed = $requirement->understanding_confirmed_at !== null;

        $rejectedSource = $requirement->latestRejectedEstimation();

        $canManage = $understandingConfirmed && (
            $actor->can('create', [ProjectRequirementEstimation::class, $requirement])
            || ($rejectedSource !== null && $actor->can('createNextVersion', $rejectedSource))
        );

        $approverOptions = $understandingConfirmed
            ? RequirementEstimationAssignableApprovers::approverUsers($project)
                ->map(static fn (User $u): array => [
                    'value' => $u->id,
                    'label' => $u->name.' ('.$u->email.')',
                ])
                ->values()
                ->all()
            : [];

        /** @var RequirementPhaseRegistry $phaseRegistry */
        $phaseRegistry = app(RequirementPhaseRegistry::class);
        $phaseSettings = $phaseRegistry->settingsPayload($requirement);

        $sharedMeta = [
            'version_history' => $versionHistory,
            'version_compare' => $versionCompare,
        ];

        if ($estimation === null) {
            return array_merge([
                'understanding_confirmed' => $understandingConfirmed,
                'estimation' => null,
                'estimation_lines' => [],
                'analytics' => RequirementEstimationAnalytics::fromItems(new Collection),
                'total_minutes' => 0,
                'approver_options' => $approverOptions,
                'phase_options' => $phaseSettings['phase_options'],
                'show_phase_column' => $phaseSettings['requires_phase_selection'],
                'max_generated_phase' => $phaseSettings['max_generated_phase'],
                'can_manage_estimation' => $canManage,
                'can_create_estimation' => $canManage && $requirement->estimations()->doesntExist(),
                'can_create_next_version' => false,
                'can_sync_lines' => false,
                'can_submit' => false,
                'can_approve' => false,
                'can_reject' => false,
                'can_request_changes' => false,
                'can_request_revision' => false,
                'can_transfer' => false,
            ], $sharedMeta);
        }

        $estimation->load([
            'creator:id,name,email',
            'submittedTo:id,name,email',
            'reviewedBy:id,name,email',
            'transferredBy:id,name,email',
        ]);
        $items = $estimation->items()->get([
            'id',
            'project_requirement_estimation_id',
            'parent_estimation_item_id',
            'title',
            'description',
            'estimated_minutes',
            'sort_order',
            'phase',
            'transferred_project_task_id',
        ]);

        $lines = [];
        foreach (RequirementEstimationDisplayOrder::depthFirstWithDepth($items) as ['item' => $item, 'depth' => $depth]) {
            $lines[] = [
                'id' => $item->id,
                'parent_estimation_item_id' => $item->parent_estimation_item_id,
                'title' => $item->title,
                'description' => $item->description,
                'estimated_minutes' => $item->estimated_minutes,
                'sort_order' => $item->sort_order,
                'phase' => $item->phase,
                'tree_depth' => $depth,
                'transferred_project_task_id' => $item->transferred_project_task_id,
            ];
        }

        return array_merge([
            'understanding_confirmed' => $understandingConfirmed,
            'estimation' => [
                'id' => $estimation->id,
                'version' => $estimation->version,
                'status' => $estimation->status->value,
                'status_label' => $estimation->status->label(),
                'submitted_at' => $estimation->submitted_at?->toIso8601String(),
                'submission_notes' => $estimation->submission_notes,
                'reviewed_at' => $estimation->reviewed_at?->toIso8601String(),
                'review_notes' => $estimation->review_notes,
                'transferred_at' => $estimation->transferred_at?->toIso8601String(),
                'creator' => self::userBrief($estimation->creator),
                'submitted_to' => self::userBrief($estimation->submittedTo),
                'reviewed_by' => self::userBrief($estimation->reviewedBy),
                'transferred_by' => self::userBrief($estimation->transferredBy),
            ],
            'estimation_lines' => $lines,
            'analytics' => RequirementEstimationAnalytics::fromItems($items),
            'total_minutes' => RequirementEstimationTotals::totalMinutesFromItems($items),
            'approver_options' => $approverOptions,
            'phase_options' => $phaseSettings['phase_options'],
            'show_phase_column' => $phaseSettings['requires_phase_selection'],
            'max_generated_phase' => $phaseSettings['max_generated_phase'],
            'can_manage_estimation' => $canManage,
            'can_create_estimation' => $canManage
                && $requirement->activeEstimation() === null
                && $requirement->estimations()->doesntExist(),
            'can_create_next_version' => $rejectedSource !== null
                && $actor->can('createNextVersion', $rejectedSource),
            'can_sync_lines' => $actor->can('syncLines', $estimation),
            'can_submit' => $actor->can('submit', $estimation),
            'can_approve' => $actor->can('approve', $estimation),
            'can_reject' => $actor->can('reject', $estimation),
            'can_request_changes' => $actor->can('requestChanges', $estimation),
            'can_request_revision' => $actor->can('requestRevision', $estimation),
            'can_transfer' => $actor->can('transfer', $estimation),
            'next_version_source_estimation_id' => $rejectedSource?->id,
        ], $sharedMeta);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function versionHistory(ProjectRequirement $requirement): array
    {
        return $requirement->estimations()
            ->orderBy('version')
            ->get(['id', 'version', 'status', 'reviewed_at', 'created_at'])
            ->map(static fn (ProjectRequirementEstimation $row): array => [
                'id' => $row->id,
                'version' => $row->version,
                'status' => $row->status->value,
                'status_label' => $row->status->label(),
                'reviewed_at' => $row->reviewed_at?->toIso8601String(),
                'created_at' => $row->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function versionCompare(
        ProjectRequirement $requirement,
        ?int $compareFromEstimationId,
        ?int $compareToEstimationId,
    ): ?array {
        if ($compareFromEstimationId === null || $compareToEstimationId === null) {
            return null;
        }

        if ($compareFromEstimationId === $compareToEstimationId) {
            return null;
        }

        $from = $requirement->estimations()->whereKey($compareFromEstimationId)->first();
        $to = $requirement->estimations()->whereKey($compareToEstimationId)->first();

        if ($from === null || $to === null) {
            return null;
        }

        /** @var RequirementEstimationVersionDiff $diff */
        $diff = app(RequirementEstimationVersionDiff::class);

        return [
            'from' => [
                'id' => $from->id,
                'version' => $from->version,
            ],
            'to' => [
                'id' => $to->id,
                'version' => $to->version,
            ],
            'diff' => $diff->compare($from, $to),
        ];
    }

    /**
     * @return array{id: int, name: string, email: string}|null
     */
    private static function userBrief(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
}
