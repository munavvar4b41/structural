<?php

namespace App\Policies;

use App\Models\TaskTag;
use App\Models\User;

class TaskTagPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->canManageCompanySettings();
    }

    public function view(User $actor, TaskTag $taskTag): bool
    {
        return $actor->canManageCompanySettings();
    }

    public function create(User $actor): bool
    {
        return $actor->canManageCompanySettings();
    }

    public function update(User $actor, TaskTag $taskTag): bool
    {
        return $actor->canManageCompanySettings();
    }

    public function delete(User $actor, TaskTag $taskTag): bool
    {
        return $actor->canManageCompanySettings();
    }
}
