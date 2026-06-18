export type EstimationLineOrderable = {
    client_key: string;
    parent_client_key: string | null;
    parent_id?: number | null;
    sort_order: number;
    tree_depth: number;
};

function descendantKeysOf<T extends EstimationLineOrderable>(
    lines: T[],
    parentKey: string,
): Set<string> {
    const keys = new Set<string>();
    let changed = true;

    while (changed) {
        changed = false;

        for (const line of lines) {
            const parentClientKey = line.parent_client_key;

            if (
                parentClientKey === parentKey
                || (parentClientKey !== null && keys.has(parentClientKey))
            ) {
                if (! keys.has(line.client_key)) {
                    keys.add(line.client_key);
                    changed = true;
                }
            }
        }
    }

    return keys;
}

export function insertIndexAfterSubtree<T extends EstimationLineOrderable>(
    lines: T[],
    parentKey: string,
): number {
    const parentIndex = lines.findIndex((line) => line.client_key === parentKey);

    if (parentIndex === -1) {
        return lines.length;
    }

    const descendants = descendantKeysOf(lines, parentKey);
    let lastIndex = parentIndex;

    lines.forEach((line, index) => {
        if (descendants.has(line.client_key)) {
            lastIndex = Math.max(lastIndex, index);
        }
    });

    return lastIndex + 1;
}

/**
 * Depth-first order for estimation lines (matches server-side RequirementEstimationDisplayOrder).
 */
export function depthFirstEstimationLines<T extends EstimationLineOrderable>(
    lines: T[],
): T[] {
    if (lines.length === 0) {
        return [];
    }

    const childrenByParentKey = new Map<string, T[]>();

    for (const line of lines) {
        const parentKey = line.parent_client_key;

        if (parentKey === null || parentKey === '') {
            continue;
        }

        const siblings = childrenByParentKey.get(parentKey) ?? [];
        siblings.push(line);
        childrenByParentKey.set(parentKey, siblings);
    }

    const roots = lines
        .filter((line) => line.parent_client_key === null || line.parent_client_key === '')
        .sort((a, b) => a.sort_order - b.sort_order);

    const result: T[] = [];
    const seen = new Set<string>();

    const walk = (line: T, depth: number): void => {
        line.tree_depth = depth;
        result.push(line);
        seen.add(line.client_key);

        const children = (childrenByParentKey.get(line.client_key) ?? []).sort(
            (a, b) => a.sort_order - b.sort_order,
        );

        for (const child of children) {
            walk(child, depth + 1);
        }
    };

    for (const root of roots) {
        walk(root, 0);
    }

    for (const line of [...lines].sort((a, b) => a.sort_order - b.sort_order)) {
        if (! seen.has(line.client_key)) {
            line.parent_client_key = null;
            walk(line, 0);
        }
    }

    return result;
}

/**
 * Depth-first order without mutating the source array (clones each line first).
 */
export function depthFirstEstimationLinesCopy<T extends EstimationLineOrderable>(
    lines: readonly T[],
): Array<T & { tree_depth: number }> {
    const copies = lines.map((line) => ({ ...line }));

    return depthFirstEstimationLines(copies);
}
