<?php

namespace App\Policies;

use App\Enums\RequirementEstimationStatus;
use App\Enums\UserRole;
use App\Models\ProjectRequirement;
use App\Models\ProjectRequirementEstimation;
use App\Models\User;

class ProjectRequirementEstimationPolicy
{
    public function view(User $actor, ProjectRequirementEstimation $estimation): bool
    {
        return $actor->can('view', $estimation->requirement);
    }

    public function create(User $actor, ProjectRequirement $requirement): bool
    {
        if ($requirement->understanding_confirmed_at === null) {
            return false;
        }

        if (! $actor->can('view', $requirement->project)) {
            return false;
        }

        if ($actor->isClient()) {
            return false;
        }

        if ($requirement->estimations()->exists()) {
            return false;
        }

        return $this->isProjectDeliveryUser($actor, $requirement);
    }

    public function syncLines(User $actor, ProjectRequirementEstimation $estimation): bool
    {
        if (! $estimation->status->isEditable()) {
            return false;
        }

        if (! $this->isProjectDeliveryUser($actor, $estimation->requirement)) {
            return false;
        }

        return $estimation->created_by_user_id === $actor->id
            || $estimation->requirement->responsible_user_id === $actor->id;
    }

    public function submit(User $actor, ProjectRequirementEstimation $estimation): bool
    {
        if (! in_array($estimation->status, [RequirementEstimationStatus::Draft, RequirementEstimationStatus::ChangesRequested], true)) {
            return false;
        }

        if ($estimation->created_by_user_id !== $actor->id) {
            return false;
        }

        return $estimation->items()->exists();
    }

    public function approve(User $actor, ProjectRequirementEstimation $estimation): bool
    {
        return $this->canReviewPending($actor, $estimation);
    }

    public function reject(User $actor, ProjectRequirementEstimation $estimation): bool
    {
        return $this->canReviewPending($actor, $estimation);
    }

    public function requestChanges(User $actor, ProjectRequirementEstimation $estimation): bool
    {
        return $this->canReviewPending($actor, $estimation);
    }

    public function requestRevision(User $actor, ProjectRequirementEstimation $estimation): bool
    {
        if (! in_array($estimation->status, [RequirementEstimationStatus::Approved, RequirementEstimationStatus::Transferred], true)) {
            return false;
        }

        if ($estimation->requirement->activeEstimation() !== null) {
            return false;
        }

        if ($estimation->created_by_user_id === $actor->id) {
            return true;
        }

        return $estimation->requirement->responsible_user_id === $actor->id;
    }

    public function createNextVersion(User $actor, ProjectRequirementEstimation $estimation): bool
    {
        if ($estimation->status !== RequirementEstimationStatus::Rejected) {
            return false;
        }

        if ($estimation->requirement->activeEstimation() !== null) {
            return false;
        }

        return $this->isProjectDeliveryUser($actor, $estimation->requirement);
    }

    public function transfer(User $actor, ProjectRequirementEstimation $estimation): bool
    {
        if ($estimation->status !== RequirementEstimationStatus::Approved) {
            return false;
        }

        if ($estimation->transferred_at !== null) {
            return false;
        }

        $project = $estimation->requirement->project;

        if ($project->lead_user_id === $actor->id) {
            return true;
        }

        if ($actor->role === UserRole::TeamHead) {
            return $actor->can('view', $project);
        }

        return in_array($actor->role, [UserRole::SuperAdmin, UserRole::Admin], true);
    }

    private function canReviewPending(User $actor, ProjectRequirementEstimation $estimation): bool
    {
        if ($estimation->status !== RequirementEstimationStatus::PendingApproval) {
            return false;
        }

        return $estimation->submitted_to_user_id === $actor->id;
    }

    private function isProjectDeliveryUser(User $actor, ProjectRequirement $requirement): bool
    {
        $project = $requirement->project;

        if (in_array($actor->role, [UserRole::SuperAdmin, UserRole::Admin], true)) {
            return true;
        }

        if ($project->lead_user_id === $actor->id) {
            return true;
        }

        if (in_array($actor->role, [UserRole::TeamHead, UserRole::Staff], true)) {
            return $project->teams()
                ->whereIn('teams.id', $actor->teams()->select('teams.id'))
                ->exists();
        }

        return false;
    }
}
