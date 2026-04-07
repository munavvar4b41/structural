<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->canManageUsers();
    }

    public function view(User $actor, User $user): bool
    {
        return $this->canManageSubject($actor, $user);
    }

    public function create(User $actor): bool
    {
        return $actor->canManageUsers();
    }

    public function update(User $actor, User $user): bool
    {
        return $this->canManageSubject($actor, $user);
    }

    public function delete(User $actor, User $user): bool
    {
        if ($actor->is($user)) {
            return false;
        }

        if (! $this->canManageSubject($actor, $user)) {
            return false;
        }

        if ($user->role === UserRole::SuperAdmin) {
            $count = User::query()->where('role', UserRole::SuperAdmin)->count();

            if ($count <= 1) {
                return false;
            }
        }

        return true;
    }

    private function canManageSubject(User $actor, User $user): bool
    {
        if (! $actor->canManageUsers()) {
            return false;
        }

        if ($user->role === UserRole::SuperAdmin && $actor->role !== UserRole::SuperAdmin) {
            return false;
        }

        return true;
    }
}
