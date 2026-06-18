<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

class NotificationFeedBuilder
{
    /**
     * @return array{
     *     unread_count: int,
     *     read_count: int,
     *     unread_items: list<array<string, mixed>>,
     *     read_items: list<array<string, mixed>>
     * }
     */
    public function buildForUser(User $user, int $limit = 15): array
    {
        return [
            'unread_count' => $user->unreadNotifications()->count(),
            'read_count' => $user->readNotifications()->count(),
            'unread_items' => $this->mapNotifications(
                $user->unreadNotifications()->latest()->limit($limit)->get(),
            ),
            'read_items' => $this->mapNotifications(
                $user->readNotifications()->latest()->limit($limit)->get(),
            ),
        ];
    }

    /**
     * @param  Collection<int, DatabaseNotification>  $notifications
     * @return list<array<string, mixed>>
     */
    public function mapNotifications(Collection $notifications): array
    {
        return $notifications
            ->map(fn (DatabaseNotification $notification): array => $this->mapNotification($notification))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function mapNotification(DatabaseNotification $notification): array
    {
        /** @var array<string, mixed> $data */
        $data = $notification->data;

        return [
            'id' => (string) $notification->id,
            'type' => (string) $notification->type,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
            'title' => isset($data['title']) && is_string($data['title']) ? $data['title'] : null,
            'project_name' => isset($data['project_name']) && is_string($data['project_name']) ? $data['project_name'] : null,
            'task_show_url' => isset($data['task_show_url']) && is_string($data['task_show_url']) ? $data['task_show_url'] : null,
            'project_id' => isset($data['project_id']) && is_numeric($data['project_id']) ? (int) $data['project_id'] : null,
            'project_task_id' => isset($data['project_task_id']) && is_numeric($data['project_task_id']) ? (int) $data['project_task_id'] : null,
            'notification_type' => isset($data['type']) && is_string($data['type']) ? $data['type'] : null,
        ];
    }
}
