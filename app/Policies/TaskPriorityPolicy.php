<?php

namespace App\Policies;

use App\Models\TaskPriority;
use App\Models\User;

class TaskPriorityPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->canManageCompanySettings();
    }

    public function view(User $actor, TaskPriority $taskPriority): bool
    {
        return $actor->canManageCompanySettings();
    }

    public function create(User $actor): bool
    {
        return $actor->canManageCompanySettings();
    }

    public function update(User $actor, TaskPriority $taskPriority): bool
    {
        return $actor->canManageCompanySettings();
    }

    public function delete(User $actor, TaskPriority $taskPriority): bool
    {
        return $actor->canManageCompanySettings();
    }
}
