<?php

namespace App\Notifications;

use App\Models\ProjectRequirementEstimation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RequirementEstimationSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly ProjectRequirementEstimation $estimation) {}

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
        $requirement = $this->estimation->requirement;
        $project = $requirement->project;

        return [
            'type' => 'requirement_estimation_submitted',
            'project_requirement_estimation_id' => $this->estimation->id,
            'project_requirement_id' => $requirement->id,
            'project_id' => $project->id,
            'title' => __('Estimation submitted: :requirement', ['requirement' => $requirement->title]),
            'project_name' => $project->name,
            'project_code' => $project->code,
            'task_show_url' => route('admin.projects.requirements.estimation.show', [$project, $requirement]),
        ];
    }
}
