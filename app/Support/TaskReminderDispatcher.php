<?php

namespace App\Support;

use App\Models\ProjectTask;
use App\Models\User;
use App\Notifications\TaskReminderNotification;
use Illuminate\Support\Collection;

class TaskReminderDispatcher
{
    /**
     * @return array{recipients: int, channels: list<string>}
     */
    public function dispatch(ProjectTask $task): array
    {
        $channels = $this->channelsForTask($task);
        $recipients = $this->recipientsForTask($task);

        if ($recipients->isEmpty()) {
            return ['recipients' => 0, 'channels' => $channels];
        }

        $notification = new TaskReminderNotification($task, $channels);
        $recipients->each(fn (User $user) => $user->notify($notification));

        return ['recipients' => $recipients->count(), 'channels' => $channels];
    }

    /**
     * @return list<string>
     */
    private function channelsForTask(ProjectTask $task): array
    {
        return ['database'];
    }

    /**
     * @return Collection<int, User>
     */
    private function recipientsForTask(ProjectTask $task): Collection
    {
        $task->loadMissing(['assignee:id,name,email', 'project:id,lead_user_id', 'project.leadUser:id,name,email']);

        /** @var Collection<int, User|null> $users */
        $users = collect([
            $task->assignee,
            $task->project?->leadUser,
        ]);

        return $users
            ->filter(fn (?User $user): bool => $user instanceof User)
            ->unique(fn (User $user): int => $user->id)
            ->values();
    }
}
