<?php

namespace App\Notifications;

use App\Models\ProjectProposal;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProjectProposalReviewedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly ProjectProposal $proposal) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $project = $this->proposal->project;

        return [
            'type' => 'project_proposal_reviewed',
            'project_proposal_id' => $this->proposal->id,
            'project_id' => $project->id,
            'title' => __('Proposal :status: :title', [
                'status' => $this->proposal->status->label(),
                'title' => $this->proposal->title,
            ]),
            'project_name' => $project->name,
            'project_code' => $project->code,
            'task_show_url' => route('admin.projects.proposals.show', [$project, $this->proposal]),
        ];
    }
}
