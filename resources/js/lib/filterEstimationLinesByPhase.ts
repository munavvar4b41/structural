import type {
    EstimationLineEditable,
    EstimationLineReadonly,
} from '@/composables/useEstimationLinesIndex';

export function filterEditableEstimationLinesByPhase(
    lines: EstimationLineEditable[],
    phase: number,
): EstimationLineEditable[] {
    const matchingKeys = new Set(
        lines.filter((line) => line.phase === phase).map((line) => line.client_key),
    );

    if (matchingKeys.size === 0) {
        return [];
    }

    const byKey = new Map(lines.map((line) => [line.client_key, line]));
    const visibleKeys = new Set(matchingKeys);

    for (const key of matchingKeys) {
        let cursor = byKey.get(key);

        while (cursor?.parent_client_key !== null && cursor?.parent_client_key !== undefined) {
            visibleKeys.add(cursor.parent_client_key);
            cursor = byKey.get(cursor.parent_client_key);
        }
    }

    return lines.filter((line) => visibleKeys.has(line.client_key));
}

export function filterReadonlyEstimationLinesByPhase(
    lines: EstimationLineReadonly[],
    phase: number,
): EstimationLineReadonly[] {
    const matchingIds = new Set(
        lines.filter((line) => line.phase === phase).map((line) => line.id),
    );

    if (matchingIds.size === 0) {
        return [];
    }

    const byId = new Map(lines.map((line) => [line.id, line]));
    const visibleIds = new Set(matchingIds);

    for (const id of matchingIds) {
        let cursor = byId.get(id);

        while (cursor?.parent_estimation_item_id !== null && cursor?.parent_estimation_item_id !== undefined) {
            visibleIds.add(cursor.parent_estimation_item_id);
            cursor = byId.get(cursor.parent_estimation_item_id);
        }
    }

    return lines.filter((line) => visibleIds.has(line.id));
}
