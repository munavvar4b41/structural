<?php

namespace App\Notifications;

use App\Models\ProjectProposal;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProjectProposalDiscussionNotification extends Notification
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
            'type' => 'project_proposal_discussion',
            'project_proposal_id' => $this->proposal->id,
            'project_id' => $project->id,
            'title' => __('New proposal message: :title', ['title' => $this->proposal->title]),
            'project_name' => $project->name,
            'project_code' => $project->code,
            'task_show_url' => route('admin.projects.proposals.show', [$project, $this->proposal]),
        ];
    }
}
