<?php

namespace App\Policies;

use App\Enums\ProjectProposalStatus;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\ProjectProposal;
use App\Models\User;
use App\Support\TipTapDocument;

class ProjectProposalPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('viewAny', Project::class);
    }

    public function view(User $actor, ProjectProposal $proposal): bool
    {
        return $actor->can('view', $proposal->project);
    }

    public function create(User $actor, Project $project): bool
    {
        return $actor->can('view', $project);
    }

    public function update(User $actor, ProjectProposal $proposal): bool
    {
        if (! $proposal->status->isEditable()) {
            return false;
        }

        return $proposal->created_by_user_id === $actor->id;
    }

    public function submit(User $actor, ProjectProposal $proposal): bool
    {
        if (! $proposal->status->canSubmit()) {
            return false;
        }

        if ($proposal->created_by_user_id !== $actor->id) {
            return false;
        }

        return $proposal->title !== ''
            && TipTapDocument::isSubstantiveDocumentJson($proposal->description);
    }

    public function confirm(User $actor, ProjectProposal $proposal): bool
    {
        return $this->canReviewPending($actor, $proposal);
    }

    public function reject(User $actor, ProjectProposal $proposal): bool
    {
        return $this->canReviewPending($actor, $proposal);
    }

    public function reopen(User $actor, ProjectProposal $proposal): bool
    {
        if (! $proposal->status->canReopen()) {
            return false;
        }

        return $this->canReviewProposals($actor, $proposal);
    }

    public function delete(User $actor, ProjectProposal $proposal): bool
    {
        if ($proposal->status !== ProjectProposalStatus::Draft) {
            return false;
        }

        if ($proposal->created_by_user_id === $actor->id) {
            return true;
        }

        return in_array($actor->role, [UserRole::SuperAdmin, UserRole::Admin, UserRole::TeamHead], true)
            && $actor->can('view', $proposal->project);
    }

    private function canReviewPending(User $actor, ProjectProposal $proposal): bool
    {
        if (! $proposal->status->canReview()) {
            return false;
        }

        return $this->canReviewProposals($actor, $proposal);
    }

    private function canReviewProposals(User $actor, ProjectProposal $proposal): bool
    {
        if (! $actor->role->canReviewProjectProposals()) {
            return false;
        }

        if ($actor->isClient()) {
            $proposal->loadMissing('project');

            return $proposal->project->client_user_id === $actor->id;
        }

        return $actor->can('view', $proposal->project);
    }
}
