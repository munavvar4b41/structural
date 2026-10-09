<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\ProjectPassword;
use App\Models\User;

class ProjectPasswordPolicy
{
    public function viewAny(User $actor, Project $project): bool
    {
        return $actor->can('view', $project);
    }

    public function create(User $actor, Project $project): bool
    {
        return $actor->can('view', $project);
    }

    public function update(User $actor, ProjectPassword $password): bool
    {
        return $actor->can('view', $password->project);
    }

    public function delete(User $actor, ProjectPassword $password): bool
    {
        return $actor->can('view', $password->project);
    }

    public function reveal(User $actor, ProjectPassword $password): bool
    {
        return $actor->can('view', $password->project);
    }
}
