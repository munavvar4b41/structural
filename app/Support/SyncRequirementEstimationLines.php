<?php

namespace App\Support;

use App\Models\ProjectRequirementEstimation;
use App\Models\ProjectRequirementEstimationItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SyncRequirementEstimationLines
{
    /**
     * @param  list<array{
     *     id?: int|null,
     *     client_key?: string|null,
     *     parent_id?: int|null,
     *     parent_client_key?: string|null,
     *     title: string,
     *     description?: string|null,
     *     estimated_minutes?: int|null,
     *     sort_order?: int
     * }>  $lines
     */
    public function sync(ProjectRequirementEstimation $estimation, array $lines): void
    {
        DB::transaction(function () use ($estimation, $lines): void {
            $existingIds = $estimation->items()->pluck('id')->all();
            $payloadIds = collect($lines)
                ->pluck('id')
                ->filter(static fn ($id): bool => $id !== null && $id !== '')
                ->map(static fn ($id): int => (int) $id)
                ->all();

            $idsToDelete = array_diff($existingIds, $payloadIds);
            if ($idsToDelete !== []) {
                ProjectRequirementEstimationItem::query()
                    ->where('project_requirement_estimation_id', $estimation->id)
                    ->whereIn('id', $idsToDelete)
                    ->delete();
            }

            $clientKeyToId = [];

            foreach ($lines as $index => $line) {
                $sortOrder = isset($line['sort_order']) ? (int) $line['sort_order'] : $index;

                $parentId = $this->resolveParentId($line, $clientKeyToId);

                $attributes = [
                    'project_requirement_estimation_id' => $estimation->id,
                    'parent_estimation_item_id' => $parentId,
                    'title' => $line['title'],
                    'description' => $line['description'] ?? null,
                    'estimated_minutes' => isset($line['estimated_minutes']) && $line['estimated_minutes'] !== ''
                        ? (int) $line['estimated_minutes']
                        : null,
                    'sort_order' => $sortOrder,
                ];

                $id = isset($line['id']) && $line['id'] !== null && $line['id'] !== ''
                    ? (int) $line['id']
                    : null;

                if ($id !== null) {
                    $item = ProjectRequirementEstimationItem::query()
                        ->where('project_requirement_estimation_id', $estimation->id)
                        ->whereKey($id)
                        ->firstOrFail();
                    $item->fill($attributes)->save();
                    $savedId = $item->id;
                } else {
                    $item = ProjectRequirementEstimationItem::query()->create($attributes);
                    $savedId = $item->id;
                }

                if (! empty($line['client_key'])) {
                    $clientKeyToId[(string) $line['client_key']] = $savedId;
                }
            }

            $this->assertNoCycles($estimation);

            $items = $estimation->items()->get();
            RequirementEstimationMinutesRollup::forItems($items)->persistRollups();
        });
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  array<string, int>  $clientKeyToId
     */
    private function resolveParentId(array $line, array $clientKeyToId): ?int
    {
        if (! empty($line['parent_client_key'])) {
            $key = (string) $line['parent_client_key'];

            return $clientKeyToId[$key] ?? null;
        }

        if (isset($line['parent_id']) && $line['parent_id'] !== null && $line['parent_id'] !== '') {
            return (int) $line['parent_id'];
        }

        return null;
    }

    private function assertNoCycles(ProjectRequirementEstimation $estimation): void
    {
        $items = $estimation->items()->get(['id', 'parent_estimation_item_id']);

        foreach ($items as $start) {
            $visited = [];
            $cursor = $start;

            while ($cursor !== null) {
                $cursorId = (int) $cursor->id;
                if (isset($visited[$cursorId])) {
                    throw ValidationException::withMessages([
                        'lines' => [__('Estimation lines contain a circular parent hierarchy.')],
                    ]);
                }

                $visited[$cursorId] = true;
                $parentId = $cursor->parent_estimation_item_id;
                $cursor = $parentId !== null
                    ? $items->firstWhere('id', $parentId)
                    : null;
            }
        }
    }
}
