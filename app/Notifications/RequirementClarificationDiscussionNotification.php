<?php

namespace App\Notifications;

use App\Models\ProjectRequirement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RequirementClarificationDiscussionNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(private readonly ProjectRequirement $requirement) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        $project = $this->requirement->project;

        return [
            'type' => 'requirement_clarification_discussion',
            'project_requirement_id' => $this->requirement->id,
            'project_id' => $project->id,
            'title' => __('New clarification message: :requirement', ['requirement' => $this->requirement->title]),
            'project_name' => $project->name,
            'project_code' => $project->code,
            'task_show_url' => route('admin.projects.requirements.show', [$project, $this->requirement]),
        ];
    }
}
