<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\ProjectTask;
use App\Models\User;

final class ProjectTaskAssigneeCapabilities
{
    /**
     * Staff who may only change status/estimate (not title, assignee, etc.).
     */
    public static function isAssigneeOnlyLimited(User $user, ProjectTask $task): bool
    {
        if ($user->role !== UserRole::Staff) {
            return false;
        }

        if ($task->project->lead_user_id === $user->id) {
            return false;
        }

        if ($task->created_by_user_id === $user->id) {
            return false;
        }

        return $task->assignee_user_id === $user->id;
    }
}
