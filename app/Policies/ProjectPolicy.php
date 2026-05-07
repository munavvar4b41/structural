<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $actor): bool
    {
        if ($actor->isClient()) {
            return true;
        }

        if ($actor->role->canViewAllProjects()) {
            return true;
        }

        return $actor->teams()->exists();
    }

    public function view(User $actor, Project $project): bool
    {
        if ($actor->isClient()) {
            return $project->client_user_id === $actor->id;
        }

        if ($actor->role->canViewAllProjects()) {
            return true;
        }

        return $project->teams()
            ->whereIn('teams.id', $actor->teams()->select('teams.id'))
            ->exists();
    }

    public function create(User $actor): bool
    {
        return $actor->canManageProjects();
    }

    public function update(User $actor, Project $project): bool
    {
        return $actor->canManageProjects() && $this->view($actor, $project);
    }

    public function delete(User $actor, Project $project): bool
    {
        return $actor->canManageProjects() && $this->view($actor, $project);
    }
}
