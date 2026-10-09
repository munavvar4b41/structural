<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\ProjectNote;
use App\Models\User;

class ProjectNotePolicy
{
    public function viewAny(User $actor): bool
    {
        if ($actor->isClient()) {
            return false;
        }

        return $actor->can('viewAny', Project::class);
    }

    public function view(User $actor, ProjectNote $note): bool
    {
        if ($actor->isClient()) {
            return false;
        }

        return $actor->can('view', $note->project);
    }

    public function create(User $actor, Project $project): bool
    {
        if ($actor->isClient()) {
            return false;
        }

        return $actor->can('view', $project);
    }

    public function update(User $actor, ProjectNote $note): bool
    {
        if (! $this->view($actor, $note)) {
            return false;
        }

        if ($note->created_by_user_id === $actor->id) {
            return true;
        }

        return in_array($actor->role, [UserRole::SuperAdmin, UserRole::Admin, UserRole::TeamHead], true)
            && $actor->can('view', $note->project);
    }

    public function delete(User $actor, ProjectNote $note): bool
    {
        return $this->update($actor, $note);
    }
}
