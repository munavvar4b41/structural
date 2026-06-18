<?php

namespace App\Policies;

use App\Models\JobPosting;
use App\Models\User;

class JobPostingPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->canManageCareers();
    }

    public function view(User $actor, JobPosting $jobPosting): bool
    {
        return $actor->canManageCareers();
    }

    public function create(User $actor): bool
    {
        return $actor->canManageCareers();
    }

    public function update(User $actor, JobPosting $jobPosting): bool
    {
        return $actor->canManageCareers();
    }

    public function delete(User $actor, JobPosting $jobPosting): bool
    {
        return $actor->canManageCareers();
    }
}
