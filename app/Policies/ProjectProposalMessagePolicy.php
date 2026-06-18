<?php

namespace App\Policies;

use App\Models\ProjectProposal;
use App\Models\ProjectProposalMessage;
use App\Models\User;

class ProjectProposalMessagePolicy
{
    public function create(User $user, ProjectProposal $proposal): bool
    {
        return $user->can('view', $proposal);
    }

    public function view(User $user, ProjectProposalMessage $message): bool
    {
        return $user->can('view', $message->proposal);
    }
}
