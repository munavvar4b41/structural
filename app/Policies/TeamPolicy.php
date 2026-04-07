<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->canManageUsers();
    }

    public function view(User $actor, Team $team): bool
    {
        return $actor->canManageUsers();
    }

    public function create(User $actor): bool
    {
        return $actor->canManageUsers();
    }

    public function update(User $actor, Team $team): bool
    {
        return $actor->canManageUsers();
    }

    public function delete(User $actor, Team $team): bool
    {
        return $actor->canManageUsers();
    }
}
