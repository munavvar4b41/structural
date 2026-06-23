<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\CaseStudy;
use App\Models\Project;
use App\Models\User;

class CaseStudyPolicy
{
    public function viewAny(User $actor): bool
    {
        if ($actor->isClient()) {
            return false;
        }

        return $actor->can('viewAny', Project::class);
    }

    public function view(User $actor, CaseStudy $caseStudy): bool
    {
        if ($actor->isClient()) {
            return false;
        }

        return $actor->can('view', $caseStudy->project);
    }

    public function create(User $actor, Project $project): bool
    {
        if ($actor->isClient()) {
            return false;
        }

        return $actor->can('view', $project);
    }

    public function update(User $actor, CaseStudy $caseStudy): bool
    {
        if (! $this->view($actor, $caseStudy)) {
            return false;
        }

        if ($caseStudy->created_by_user_id === $actor->id) {
            return true;
        }

        return in_array($actor->role, [UserRole::SuperAdmin, UserRole::Admin, UserRole::TeamHead], true)
            && $actor->can('view', $caseStudy->project);
    }

    public function delete(User $actor, CaseStudy $caseStudy): bool
    {
        return $this->update($actor, $caseStudy);
    }

    public function downloadAttachment(User $actor, CaseStudy $caseStudy): bool
    {
        return $this->view($actor, $caseStudy);
    }
}
