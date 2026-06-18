<?php

namespace App\Notifications;

use App\Models\ProjectTask;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskUpdatedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(private readonly ProjectTask $task) {}

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
        $project = $this->task->project;

        return [
            'type' => 'task_updated',
            'project_task_id' => $this->task->id,
            'project_id' => $project->id,
            'title' => __('Task updated: :task', ['task' => $this->task->title]),
            'project_name' => $project->name,
            'project_code' => $project->code,
            'task_show_url' => route('admin.projects.tasks.show', [$project, $this->task]),
        ];
    }
}
