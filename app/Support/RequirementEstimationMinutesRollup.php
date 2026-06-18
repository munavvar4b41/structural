<?php

namespace App\Support;

use App\Models\ProjectRequirementEstimationItem;
use Illuminate\Database\Eloquent\Collection;

final class RequirementEstimationMinutesRollup
{
    /**
     * @param  Collection<int, ProjectRequirementEstimationItem>  $items
     */
    public function __construct(private Collection $items) {}

    public function itemHasChildren(ProjectRequirementEstimationItem $item): bool
    {
        return $this->items->contains(
            static fn (ProjectRequirementEstimationItem $candidate): bool => $candidate->parent_estimation_item_id === $item->id,
        );
    }

    public function effectiveMinutes(ProjectRequirementEstimationItem $item): int
    {
        if (! $this->itemHasChildren($item)) {
            $minutes = $item->estimated_minutes;

            return $minutes !== null && $minutes >= 1 ? (int) $minutes : 0;
        }

        return (int) $this->items
            ->filter(
                static fn (ProjectRequirementEstimationItem $candidate): bool => $candidate->parent_estimation_item_id === $item->id,
            )
            ->sum(fn (ProjectRequirementEstimationItem $child): int => $this->effectiveMinutes($child));
    }

    /**
     * Total duration = sum of each root module's effective minutes (avoids double-counting).
     */
    public function totalMinutes(): int
    {
        return (int) $this->items
            ->filter(static fn (ProjectRequirementEstimationItem $item): bool => $item->parent_estimation_item_id === null)
            ->sum(fn (ProjectRequirementEstimationItem $root): int => $this->effectiveMinutes($root));
    }

    public function leafMissingMinutes(): bool
    {
        foreach ($this->items as $item) {
            if ($this->itemHasChildren($item)) {
                continue;
            }

            if ($item->estimated_minutes === null || $item->estimated_minutes < 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Persist rolled-up minutes on parent rows (sum of subtasks).
     */
    public function persistRollups(): void
    {
        $this->persistRollupsBatched();
    }

    /**
     * Batch-update parent rows whose rolled-up minutes changed.
     */
    public function persistRollupsBatched(): void
    {
        $now = now();
        $upsertRows = [];

        foreach ($this->items as $item) {
            if (! $this->itemHasChildren($item)) {
                continue;
            }

            $minutes = $this->effectiveMinutes($item);
            $normalized = $minutes > 0 ? $minutes : null;

            if ($item->estimated_minutes === $normalized) {
                continue;
            }

            $upsertRows[] = [
                'id' => $item->id,
                'project_requirement_estimation_id' => $item->project_requirement_estimation_id,
                'parent_estimation_item_id' => $item->parent_estimation_item_id,
                'title' => $item->title,
                'description' => $item->description,
                'estimated_minutes' => $normalized,
                'sort_order' => $item->sort_order,
                'created_at' => $item->created_at,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($upsertRows, 100) as $chunk) {
            if ($chunk === []) {
                continue;
            }

            ProjectRequirementEstimationItem::query()->upsert(
                $chunk,
                ['id'],
                ['estimated_minutes', 'updated_at'],
            );
        }
    }

    /**
     * @param  Collection<int, ProjectRequirementEstimationItem>  $items
     */
    public static function forItems(Collection $items): self
    {
        return new self($items);
    }
}
