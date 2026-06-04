export type EstimationLineTreeNode = {
    lineKey: string;
    parentKey: string | null;
};

/**
 * Keep depth-first order; hide lines under collapsed ancestors.
 */
export function filterVisibleEstimationLines<T extends EstimationLineTreeNode>(
    lines: readonly T[],
    collapsedKeys: ReadonlySet<string>,
): T[] {
    if (collapsedKeys.size === 0) {
        return [...lines];
    }

    const parentKeyByLineKey = new Map<string, string | null>();

    for (const line of lines) {
        parentKeyByLineKey.set(line.lineKey, line.parentKey);
    }

    const isVisible = (lineKey: string): boolean => {
        let parentKey = parentKeyByLineKey.get(lineKey) ?? null;

        while (parentKey !== null && parentKey !== '') {
            if (collapsedKeys.has(parentKey)) {
                return false;
            }

            parentKey = parentKeyByLineKey.get(parentKey) ?? null;
        }

        return true;
    };

    return lines.filter((line) => isVisible(line.lineKey));
}

/**
 * Direct child count per parent key (for collapse labels).
 */
export function buildDirectChildCountByParentKey(
    lines: readonly EstimationLineTreeNode[],
): Map<string, number> {
    const counts = new Map<string, number>();

    for (const line of lines) {
        const parentKey = line.parentKey;

        if (parentKey === null || parentKey === '') {
            continue;
        }

        counts.set(parentKey, (counts.get(parentKey) ?? 0) + 1);
    }

    return counts;
}
