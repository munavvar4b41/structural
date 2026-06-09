<?php

namespace App\Policies;

use App\Models\JobApplication;
use App\Models\User;

class JobApplicationPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->canManageCareers();
    }

    public function view(User $actor, JobApplication $jobApplication): bool
    {
        return $actor->canManageCareers();
    }

    public function advance(User $actor, JobApplication $jobApplication): bool
    {
        return $actor->canManageCareers() && $jobApplication->status->canAdvance();
    }

    public function reject(User $actor, JobApplication $jobApplication): bool
    {
        return $actor->canManageCareers() && $jobApplication->status->canAdvance();
    }

    public function downloadResume(User $actor, JobApplication $jobApplication): bool
    {
        return $actor->canManageCareers();
    }
}
