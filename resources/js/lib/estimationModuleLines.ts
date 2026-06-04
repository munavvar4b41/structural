import type { EstimationLineOrderable } from '@/lib/estimationLinesOrder';
import { depthFirstEstimationLines } from '@/lib/estimationLinesOrder';

/**
 * All lines belonging to one root module (root + descendants by client_key).
 */
export function moduleLinesForRoot<T extends EstimationLineOrderable>(
    lines: readonly T[],
    rootClientKey: string,
): T[] {
    const root = lines.find((line) => line.client_key === rootClientKey);

    if (root === undefined) {
        return [];
    }

    const descendantKeys = new Set<string>();
    let changed = true;

    while (changed) {
        changed = false;

        for (const line of lines) {
            const parentKey = line.parent_client_key;

            if (
                parentKey !== null
                && parentKey !== ''
                && (parentKey === rootClientKey || descendantKeys.has(parentKey))
                && !descendantKeys.has(line.client_key)
            ) {
                descendantKeys.add(line.client_key);
                changed = true;
            }
        }
    }

    const moduleKeys = new Set<string>([rootClientKey, ...descendantKeys]);

    return depthFirstEstimationLines(
        lines.filter((line) => moduleKeys.has(line.client_key)),
    );
}
