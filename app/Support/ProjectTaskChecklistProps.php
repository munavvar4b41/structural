<?php

namespace App\Support;

use App\Models\ProjectTask;
use App\Models\ProjectTaskChecklistItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class ProjectTaskChecklistProps
{
    /**
     * @return array{can_manage: bool, items: list<array{id: int, title: string, is_completed: bool}>}
     */
    public static function forTask(ProjectTask $task, User $actor): array
    {
        $items = $task->relationLoaded('checklistItems')
            ? $task->checklistItems
            : $task->checklistItems()->orderBy('created_at')->get();

        return [
            'can_manage' => $actor->can('update', $task),
            'items' => self::mapItems($items),
        ];
    }

    /**
     * @param  Collection<int, ProjectTaskChecklistItem>  $items
     * @return list<array{id: int, title: string, is_completed: bool}>
     */
    private static function mapItems(Collection $items): array
    {
        return $items
            ->map(fn (ProjectTaskChecklistItem $item): array => [
                'id' => $item->id,
                'title' => $item->title,
                'is_completed' => $item->is_completed,
            ])
            ->values()
            ->all();
    }
}
