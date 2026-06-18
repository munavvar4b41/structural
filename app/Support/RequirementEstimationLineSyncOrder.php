<?php

namespace App\Support;

final class RequirementEstimationLineSyncOrder
{
    /**
     * Parents before children so client_key → id resolution works during sync.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    public static function sortForSync(array $lines): array
    {
        if (count($lines) <= 1) {
            return $lines;
        }

        $indexed = array_values($lines);
        $indexByClientKey = [];
        $indexById = [];

        foreach ($indexed as $index => $line) {
            if (! empty($line['client_key'])) {
                $indexByClientKey[(string) $line['client_key']] = $index;
            }

            if (isset($line['id']) && $line['id'] !== null && $line['id'] !== '') {
                $indexById[(int) $line['id']] = $index;
            }
        }

        $parentIndex = [];

        foreach ($indexed as $index => $line) {
            if (! empty($line['parent_client_key'])) {
                $parentIndex[$index] = $indexByClientKey[(string) $line['parent_client_key']] ?? null;
            } elseif (isset($line['parent_id']) && $line['parent_id'] !== null && $line['parent_id'] !== '') {
                $parentIndex[$index] = $indexById[(int) $line['parent_id']] ?? null;
            } else {
                $parentIndex[$index] = null;
            }
        }

        $sorted = [];
        $visited = [];

        $visit = function (int $index) use (&$visit, &$sorted, &$visited, $indexed, $parentIndex): void {
            if (isset($visited[$index])) {
                return;
            }

            $visited[$index] = true;

            $parent = $parentIndex[$index] ?? null;

            if ($parent !== null) {
                $visit($parent);
            }

            $sorted[] = $indexed[$index];
        };

        foreach (array_keys($indexed) as $index) {
            $visit($index);
        }

        return $sorted;
    }
}
