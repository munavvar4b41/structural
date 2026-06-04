<?php

namespace App\Notifications;

use App\Models\ProjectRequirementEstimation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RequirementEstimationReviewedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly ProjectRequirementEstimation $estimation,
        private readonly string $outcome,
    ) {}

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

        $title = match ($this->outcome) {
            'approved' => __('Estimation approved: :requirement', ['requirement' => $requirement->title]),
            'rejected' => __('Estimation rejected: :requirement', ['requirement' => $requirement->title]),
            'changes_requested' => __('Estimation changes requested: :requirement', ['requirement' => $requirement->title]),
            default => __('Estimation updated: :requirement', ['requirement' => $requirement->title]),
        };

        return [
            'type' => 'requirement_estimation_reviewed',
            'outcome' => $this->outcome,
            'project_requirement_estimation_id' => $this->estimation->id,
            'project_requirement_id' => $requirement->id,
            'project_id' => $project->id,
            'title' => $title,
            'project_name' => $project->name,
            'project_code' => $project->code,
            'task_show_url' => route('admin.projects.requirements.estimation.show', [$project, $requirement]),
        ];
    }
}
