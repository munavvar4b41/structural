<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;

/**
 * Task access is scoped to project visibility. Staff who are only assignees (not the creator)
 * may update progress fields only (status, estimate). Staff who created the task, staff who are
 * the project lead, and other internal roles get full task management on that project.
 *
 * Task creation is limited to delivery roles: staff and team heads on the project's teams, the
 * designated project lead, and company admins. Clients and other viewers cannot create tasks.
 */
class ProjectTaskPolicy
{
    public function view(User $actor, ProjectTask $task): bool
    {
        return $actor->can('view', $task->project);
    }

    public function create(User $actor, Project $project): bool
    {
        if (! $actor->can('view', $project)) {
            return false;
        }

        if ($actor->isClient()) {
            return false;
        }

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

    public function update(User $actor, ProjectTask $task): bool
    {
        if (! $actor->can('view', $task->project)) {
            return false;
        }

        if ($actor->isClient()) {
            return false;
        }

        if ($actor->role === UserRole::Staff) {
            if ($task->project->lead_user_id === $actor->id) {
                return true;
            }

            if ($task->created_by_user_id === $actor->id) {
                return true;
            }

            return $task->assignee_user_id === $actor->id;
        }

        return in_array($actor->role, [UserRole::SuperAdmin, UserRole::Admin, UserRole::TeamHead], true)
            || $task->project->lead_user_id === $actor->id;
    }

    public function delete(User $actor, ProjectTask $task): bool
    {
        if (! $actor->can('view', $task->project)) {
            return false;
        }

        if ($actor->isClient()) {
            return false;
        }

        if ($actor->role === UserRole::Staff) {
            return $task->created_by_user_id === $actor->id;
        }

        return in_array($actor->role, [UserRole::SuperAdmin, UserRole::Admin, UserRole::TeamHead], true)
            || $task->project->lead_user_id === $actor->id;
    }

    /**
     * Assignee submits work for completion (moves task to Review).
     */
    public function submitCompletion(User $actor, ProjectTask $task): bool
    {
        if ($actor->isClient()) {
            return false;
        }

        if (! $actor->can('view', $task)) {
            return false;
        }

        if ($task->assignee_user_id !== $actor->id) {
            return false;
        }

        return true;
    }

    /**
     * Reviewer confirms completion, records ratings, sets task to Done.
     */
    public function confirmCompletion(User $actor, ProjectTask $task): bool
    {
        if ($actor->isClient()) {
            return false;
        }

        if (! $actor->can('view', $task)) {
            return false;
        }

        if (! $this->isTaskCompletionReviewer($actor, $task->project)) {
            return false;
        }

        if ($task->completion_submitted_by_user_id !== null
            && $task->completion_submitted_by_user_id === $actor->id) {
            return false;
        }

        return true;
    }

    private function isTaskCompletionReviewer(User $actor, Project $project): bool
    {
        if (in_array($actor->role, [UserRole::SuperAdmin, UserRole::Admin, UserRole::TeamHead], true)) {
            return true;
        }

        return $project->lead_user_id === $actor->id;
    }
}
