<?php

namespace App\Policies;

use App\Models\ProjectRequirement;
use App\Models\ProjectRequirementMessage;
use App\Models\User;

class ProjectRequirementMessagePolicy
{
    public function create(User $user, ProjectRequirement $requirement): bool
    {
        return $user->can('view', $requirement);
    }

    public function view(User $user, ProjectRequirementMessage $message): bool
    {
        return $user->can('view', $message->requirement);
    }
}
