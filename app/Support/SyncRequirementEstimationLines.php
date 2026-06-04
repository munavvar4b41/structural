<?php

namespace App\Support;

use App\Models\ProjectRequirementEstimation;
use App\Models\ProjectRequirementEstimationItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SyncRequirementEstimationLines
{
    private const int UPSERT_CHUNK_SIZE = 100;

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
    public function sync(ProjectRequirementEstimation $estimation, array $lines, bool $partialModule = false): void
    {
        DB::transaction(function () use ($estimation, $lines, $partialModule): void {
            $lines = RequirementEstimationLineSyncOrder::sortForSync($lines);
            $estimationId = $estimation->id;
            $now = now();

            /** @var Collection<int, ProjectRequirementEstimationItem> $existingById */
            $existingById = $estimation->items()->get()->keyBy('id');

            $this->assertPayloadIdsBelongToEstimation($lines, $existingById);

            if ($partialModule) {
                $this->assertPartialModulePayload($lines);
            }

            $payloadIds = collect($lines)
                ->pluck('id')
                ->filter(static fn ($id): bool => $id !== null && $id !== '')
                ->map(static fn ($id): int => (int) $id)
                ->all();

            $idsToDelete = $partialModule
                ? $this->moduleScopedIdsToDelete($lines, $existingById, $payloadIds)
                : array_diff($existingById->keys()->all(), $payloadIds);

            if ($idsToDelete !== []) {
                ProjectRequirementEstimationItem::query()
                    ->where('project_requirement_estimation_id', $estimationId)
                    ->whereIn('id', $idsToDelete)
                    ->delete();

                $existingById = $existingById->except($idsToDelete);
            }

            $clientKeyToId = [];
            $upsertRows = [];

            foreach ($lines as $index => $line) {
                $sortOrder = isset($line['sort_order']) ? (int) $line['sort_order'] : $index;
                $parentId = $this->resolveParentId($line, $clientKeyToId);
                $estimatedMinutes = isset($line['estimated_minutes']) && $line['estimated_minutes'] !== ''
                    ? (int) $line['estimated_minutes']
                    : null;

                $id = isset($line['id']) && $line['id'] !== null && $line['id'] !== ''
                    ? (int) $line['id']
                    : null;

                if ($id !== null) {
                    $existing = $existingById->get($id);

                    if ($existing === null) {
                        throw ValidationException::withMessages([
                            'lines' => [__('One or more estimation lines are invalid for this estimation.')],
                        ]);
                    }

                    $upsertRows[] = [
                        'id' => $id,
                        'project_requirement_estimation_id' => $estimationId,
                        'parent_estimation_item_id' => $parentId,
                        'title' => $line['title'],
                        'description' => $line['description'] ?? null,
                        'estimated_minutes' => $estimatedMinutes,
                        'sort_order' => $sortOrder,
                        'created_at' => $existing->created_at,
                        'updated_at' => $now,
                    ];

                    if (! empty($line['client_key'])) {
                        $clientKeyToId[(string) $line['client_key']] = $id;
                    }

                    continue;
                }

                $item = ProjectRequirementEstimationItem::query()->create([
                    'project_requirement_estimation_id' => $estimationId,
                    'parent_estimation_item_id' => $parentId,
                    'title' => $line['title'],
                    'description' => $line['description'] ?? null,
                    'estimated_minutes' => $estimatedMinutes,
                    'sort_order' => $sortOrder,
                ]);

                if (! empty($line['client_key'])) {
                    $clientKeyToId[(string) $line['client_key']] = $item->id;
                }
            }

            foreach (array_chunk($upsertRows, self::UPSERT_CHUNK_SIZE) as $chunk) {
                ProjectRequirementEstimationItem::query()->upsert(
                    $chunk,
                    ['id'],
                    [
                        'parent_estimation_item_id',
                        'title',
                        'description',
                        'estimated_minutes',
                        'sort_order',
                        'updated_at',
                    ],
                );
            }

            $items = $estimation->items()->get();
            RequirementEstimationMinutesRollup::forItems($items)->persistRollupsBatched();

            $this->assertNoCycles($items);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function assertPartialModulePayload(array $lines): void
    {
        $rootCount = 0;

        foreach ($lines as $line) {
            $hasParentClientKey = ! empty($line['parent_client_key']);
            $hasParentId = isset($line['parent_id']) && $line['parent_id'] !== null && $line['parent_id'] !== '';

            if (! $hasParentClientKey && ! $hasParentId) {
                $rootCount++;
            }
        }

        if ($rootCount !== 1) {
            throw ValidationException::withMessages([
                'lines' => [__('Module save must include exactly one parent line.')],
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @param  Collection<int, ProjectRequirementEstimationItem>  $existingById
     * @param  list<int>  $payloadIds
     * @return list<int>
     */
    private function moduleScopedIdsToDelete(array $lines, Collection $existingById, array $payloadIds): array
    {
        $rootId = $this->resolveModuleRootId($lines, $existingById);

        if ($rootId === null) {
            return [];
        }

        $moduleScopeIds = $this->descendantIdsIncludingRoot($existingById, $rootId);

        return array_values(array_diff($moduleScopeIds, $payloadIds));
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @param  Collection<int, ProjectRequirementEstimationItem>  $existingById
     */
    private function resolveModuleRootId(array $lines, Collection $existingById): ?int
    {
        foreach ($lines as $line) {
            $hasParentClientKey = ! empty($line['parent_client_key']);
            $hasParentId = isset($line['parent_id']) && $line['parent_id'] !== null && $line['parent_id'] !== '';

            if ($hasParentClientKey || $hasParentId) {
                continue;
            }

            if (isset($line['id']) && $line['id'] !== null && $line['id'] !== '') {
                return (int) $line['id'];
            }

            return null;
        }

        return null;
    }

    /**
     * @param  Collection<int, ProjectRequirementEstimationItem>  $items
     * @return list<int>
     */
    private function descendantIdsIncludingRoot(Collection $items, int $rootId): array
    {
        $ids = [$rootId];
        $changed = true;

        while ($changed) {
            $changed = false;

            foreach ($items as $item) {
                $parentId = $item->parent_estimation_item_id;

                if (
                    $parentId !== null
                    && in_array($parentId, $ids, true)
                    && ! in_array($item->id, $ids, true)
                ) {
                    $ids[] = (int) $item->id;
                    $changed = true;
                }
            }
        }

        return $ids;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @param  Collection<int, ProjectRequirementEstimationItem>  $existingById
     */
    private function assertPayloadIdsBelongToEstimation(array $lines, Collection $existingById): void
    {
        foreach ($lines as $line) {
            if (! isset($line['id']) || $line['id'] === null || $line['id'] === '') {
                continue;
            }

            $id = (int) $line['id'];

            if (! $existingById->has($id)) {
                throw ValidationException::withMessages([
                    'lines' => [__('One or more estimation lines are invalid for this estimation.')],
                ]);
            }
        }
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

    /**
     * @param  Collection<int, ProjectRequirementEstimationItem>  $items
     */
    private function assertNoCycles(Collection $items): void
    {
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
