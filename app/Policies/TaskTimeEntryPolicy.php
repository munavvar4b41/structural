<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\TaskTimeEntry;
use App\Models\User;

/**
 * Time tracking access:
 * - Any internal user with view access on a task may start/stop their own timer for it.
 * - Manual entries and edits/deletes are scoped to the entry's owner.
 * - Reports: a user can always view their own; admins, super admins, and team heads
 *   can view anyone's; project leads can view team members' time scoped to projects
 *   they lead (controller filters projects accordingly).
 */
class TaskTimeEntryPolicy
{
    public function view(User $actor, TaskTimeEntry $entry): bool
    {
        if ($entry->user_id === $actor->id) {
            return true;
        }

        return $this->canViewReportFor($actor, $entry->user);
    }

    public function start(User $actor, ProjectTask $task): bool
    {
        if ($actor->isClient()) {
            return false;
        }

        return $actor->can('view', $task->project);
    }

    public function stop(User $actor): bool
    {
        return ! $actor->isClient();
    }

    public function create(User $actor, ProjectTask $task): bool
    {
        return $this->start($actor, $task);
    }

    public function update(User $actor, TaskTimeEntry $entry): bool
    {
        return $entry->user_id === $actor->id && ! $actor->isClient();
    }

    public function delete(User $actor, TaskTimeEntry $entry): bool
    {
        return $entry->user_id === $actor->id && ! $actor->isClient();
    }

    /**
     * Whether the actor may view the target user's time report (possibly scoped to
     * specific projects in the controller).
     */
    public function viewReportFor(User $actor, User $target): bool
    {
        if ($actor->id === $target->id) {
            return ! $actor->isClient();
        }

        return $this->canViewReportFor($actor, $target);
    }

    private function canViewReportFor(User $actor, ?User $target): bool
    {
        if ($target === null || $actor->isClient()) {
            return false;
        }

        if (in_array($actor->role, [UserRole::SuperAdmin, UserRole::Admin, UserRole::TeamHead], true)) {
            return true;
        }

        return Project::query()
            ->where('lead_user_id', $actor->id)
            ->whereHas('tasks.timeEntries', static function ($query) use ($target): void {
                $query->where('user_id', $target->id);
            })
            ->exists();
    }
}
