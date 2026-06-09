<?php

namespace App\Support;

use App\Models\ProjectProposal;
use App\Models\ProjectRequirement;
use App\Models\ProjectTask;
use App\Models\User;
use App\Notifications\ProjectProposalDiscussionNotification;
use App\Notifications\ProjectProposalReviewedNotification;
use App\Notifications\ProjectProposalSubmittedNotification;
use App\Notifications\RequirementAssignedNotification;
use App\Notifications\RequirementClarificationDiscussionNotification;
use App\Notifications\RequirementReviewUnderstandingSubmittedNotification;
use App\Notifications\RequirementUpdatedNotification;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TaskUpdatedNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class AssignmentNotificationDispatcher
{
    public function sendTaskAssigned(ProjectTask $task, User $actor): void
    {
        $task->loadMissing(['project:id,name,code', 'assignee:id,name,email']);

        $this->notifyUsers(
            collect([$task->assignee]),
            new TaskAssignedNotification($task),
            $actor,
        );
    }

    public function sendTaskUpdated(ProjectTask $task, User $actor): void
    {
        $task->loadMissing(['project:id,name,code', 'assignee:id,name,email']);

        $this->notifyUsers(
            collect([$task->assignee]),
            new TaskUpdatedNotification($task),
            $actor,
        );
    }

    /**
     * @param  list<int>|null  $recipientIds
     */
    public function sendRequirementAssigned(ProjectRequirement $requirement, User $actor, ?array $recipientIds = null): void
    {
        $requirement->loadMissing([
            'project:id,name,code',
            'responsibleUser:id,name,email',
            'reviewer:id,name,email',
        ]);

        $users = collect([$requirement->responsibleUser, $requirement->reviewer]);

        if (is_array($recipientIds)) {
            $allowedIds = collect($recipientIds)->filter(static fn (mixed $id): bool => is_int($id))->values();
            $users = $users->filter(
                static fn (?User $user): bool => $user instanceof User && $allowedIds->contains($user->id),
            );
        }

        $this->notifyUsers(
            $users,
            new RequirementAssignedNotification($requirement),
            $actor,
        );
    }

    public function sendRequirementUpdated(ProjectRequirement $requirement, User $actor): void
    {
        $requirement->loadMissing([
            'project:id,name,code',
            'creator:id,name,email',
            'responsibleUser:id,name,email',
            'reviewer:id,name,email',
        ]);

        $this->notifyUsers(
            collect([$requirement->creator, $requirement->responsibleUser, $requirement->reviewer]),
            new RequirementUpdatedNotification($requirement),
            $actor,
        );
    }

    public function sendRequirementClarificationDiscussion(ProjectRequirement $requirement, User $actor): void
    {
        $requirement->loadMissing([
            'project:id,name,code',
            'creator:id,name,email',
            'responsibleUser:id,name,email',
            'reviewer:id,name,email',
        ]);

        $this->notifyUsers(
            collect([$requirement->creator, $requirement->responsibleUser, $requirement->reviewer]),
            new RequirementClarificationDiscussionNotification($requirement),
            $actor,
        );
    }

    public function sendRequirementReviewUnderstandingSubmitted(ProjectRequirement $requirement, User $actor): void
    {
        $requirement->loadMissing([
            'project:id,name,code',
            'creator:id,name,email',
            'responsibleUser:id,name,email',
            'reviewer:id,name,email',
        ]);

        $this->notifyUsers(
            collect([$requirement->creator, $requirement->responsibleUser, $requirement->reviewer]),
            new RequirementReviewUnderstandingSubmittedNotification($requirement),
            $actor,
        );
    }

    public function sendProjectProposalSubmitted(ProjectProposal $proposal, User $actor): void
    {
        $proposal->loadMissing([
            'project:id,name,code,client_user_id,lead_user_id',
            'project.clientUser:id,name,email',
            'project.leadUser:id,name,email',
        ]);

        $this->notifyUsers(
            collect([$proposal->project->clientUser, $proposal->project->leadUser]),
            new ProjectProposalSubmittedNotification($proposal),
            $actor,
        );
    }

    public function sendProjectProposalReviewed(ProjectProposal $proposal, User $actor): void
    {
        $proposal->loadMissing([
            'project:id,name,code',
            'creator:id,name,email',
        ]);

        $this->notifyUsers(
            collect([$proposal->creator]),
            new ProjectProposalReviewedNotification($proposal),
            $actor,
        );
    }

    public function sendProjectProposalDiscussion(ProjectProposal $proposal, User $actor): void
    {
        $proposal->loadMissing([
            'project:id,name,code,client_user_id,lead_user_id',
            'project.clientUser:id,name,email',
            'project.leadUser:id,name,email',
            'creator:id,name,email',
        ]);

        $this->notifyUsers(
            collect([$proposal->creator, $proposal->project->clientUser, $proposal->project->leadUser]),
            new ProjectProposalDiscussionNotification($proposal),
            $actor,
        );
    }

    /**
     * @param  Collection<int, User|null>  $users
     */
    private function notifyUsers(Collection $users, Notification $notification, User $actor): void
    {
        $users
            ->filter(fn (?User $user): bool => $user instanceof User)
            ->reject(fn (User $user): bool => $user->id === $actor->id)
            ->unique(fn (User $user): int => $user->id)
            ->each(fn (User $user) => $user->notify($notification));
    }
}
