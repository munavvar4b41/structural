<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\User;

class ProjectRequirementPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('viewAny', Project::class);
    }

    public function view(User $actor, ProjectRequirement $requirement): bool
    {
        return $actor->can('view', $requirement->project);
    }

    public function create(User $actor, Project $project): bool
    {
        if (! $actor->can('view', $project)) {
            return false;
        }

        if (! $actor->role->canCreateProjectRequirements()) {
            return false;
        }

        if ($actor->role === UserRole::Client) {
            return $project->client_user_id === $actor->id;
        }

        return true;
    }

    public function update(User $actor, ProjectRequirement $requirement): bool
    {
        if ($actor->role === UserRole::Staff) {
            return false;
        }

        return $this->updateContent($actor, $requirement)
            || $this->updateAssignments($actor, $requirement)
            || $this->markReviewed($actor, $requirement);
    }

    public function updateContent(User $actor, ProjectRequirement $requirement): bool
    {
        if (! $actor->can('view', $requirement->project)) {
            return false;
        }

        if ($requirement->created_by_user_id === $actor->id) {
            return true;
        }

        return $actor->role === UserRole::SuperAdmin
            || $actor->role === UserRole::Admin
            || ($actor->role === UserRole::TeamHead && $actor->can('view', $requirement->project));
    }

    public function updateAssignments(User $actor, ProjectRequirement $requirement): bool
    {
        if (! $actor->can('view', $requirement->project)) {
            return false;
        }

        if ($actor->role === UserRole::SuperAdmin || $actor->role === UserRole::Admin) {
            return true;
        }

        if ($actor->role === UserRole::TeamHead) {
            return $actor->can('view', $requirement->project);
        }

        $requirement->loadMissing('project');
        $project = $requirement->project;
        if ($project->lead_user_id !== null && $project->lead_user_id === $actor->id) {
            return true;
        }

        return $requirement->responsible_user_id === $actor->id;
    }

    public function markReviewed(User $actor, ProjectRequirement $requirement): bool
    {
        if (! $actor->can('view', $requirement->project)) {
            return false;
        }

        if ($requirement->reviewer_user_id !== null) {
            return $requirement->reviewer_user_id === $actor->id
                && $actor->role === UserRole::Staff;
        }

        if ($actor->role === UserRole::SuperAdmin || $actor->role === UserRole::Admin) {
            return true;
        }

        return $actor->role === UserRole::TeamHead && $actor->can('view', $requirement->project);
    }

    public function delete(User $actor, ProjectRequirement $requirement): bool
    {
        if (! $actor->can('view', $requirement->project)) {
            return false;
        }

        return $actor->role === UserRole::SuperAdmin
            || $actor->role === UserRole::Admin
            || ($actor->role === UserRole::TeamHead && $actor->can('view', $requirement->project));
    }
}
