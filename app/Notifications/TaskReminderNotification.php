<?php

namespace App\Notifications;

use App\Models\ProjectTask;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskReminderNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        private readonly ProjectTask $task,
        private readonly array $channels = ['database'],
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        $project = $this->task->project;

        return [
            'type' => 'task_reminder',
            'project_task_id' => $this->task->id,
            'project_id' => $project->id,
            'title' => $this->task->title,
            'project_name' => $project->name,
            'project_code' => $project->code,
            'notify_at' => $this->task->notify_at?->toIso8601String(),
            'task_show_url' => route('admin.projects.tasks.show', [$project, $this->task]),
        ];
    }
}
