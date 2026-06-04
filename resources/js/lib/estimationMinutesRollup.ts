export type EstimationMinutesLine = {
    client_key: string;
    parent_client_key: string | null;
    estimated_minutes: number | null | string;
};

export function lineHasChildren<T extends EstimationMinutesLine>(
    line: T,
    lines: T[],
): boolean {
    return lines.some(
        (candidate) =>
            candidate.parent_client_key !== null
            && candidate.parent_client_key === line.client_key,
    );
}

function parseMinutes(value: number | null | string | undefined): number {
    if (value === null || value === undefined || value === '') {
        return 0;
    }

    const parsed = typeof value === 'number' ? value : Number(value);

    return Number.isFinite(parsed) && parsed >= 1 ? parsed : 0;
}

export function effectiveMinutes<T extends EstimationMinutesLine>(
    line: T,
    lines: T[],
): number {
    if (lineHasChildren(line, lines)) {
        return lines
            .filter(
                (candidate) => candidate.parent_client_key === line.client_key,
            )
            .reduce((sum, child) => sum + effectiveMinutes(child, lines), 0);
    }

    return parseMinutes(line.estimated_minutes);
}

export function totalEffectiveMinutes<T extends EstimationMinutesLine>(
    lines: T[],
): number {
    return lines
        .filter(
            (line) =>
                line.parent_client_key === null || line.parent_client_key === '',
        )
        .reduce((sum, root) => sum + effectiveMinutes(root, lines), 0);
}

export type EstimationMinutesLineById = {
    id: number;
    parent_estimation_item_id: number | null;
    estimated_minutes: number | null;
};

export function lineHasChildrenById<T extends EstimationMinutesLineById>(
    line: T,
    lines: T[],
): boolean {
    return lines.some(
        (candidate) => candidate.parent_estimation_item_id === line.id,
    );
}

export function effectiveMinutesById<T extends EstimationMinutesLineById>(
    line: T,
    lines: T[],
): number {
    if (lineHasChildrenById(line, lines)) {
        return lines
            .filter(
                (candidate) => candidate.parent_estimation_item_id === line.id,
            )
            .reduce(
                (sum, child) => sum + effectiveMinutesById(child, lines),
                0,
            );
    }

    return parseMinutes(line.estimated_minutes);
}

export type EstimationMinutesIndex = {
    hasChildrenByKey: Set<string>;
    effectiveMinutesByKey: Map<string, number>;
    totalEffectiveMinutes: number;
};

export type EstimationMinutesIndexById = {
    hasChildrenById: Set<number>;
    effectiveMinutesById: Map<number, number>;
};

/**
 * O(n) rollup index for editable estimation lines (by client_key).
 */
export function buildEditableMinutesIndex<T extends EstimationMinutesLine>(
    lines: readonly T[],
): EstimationMinutesIndex {
    const hasChildrenByKey = new Set<string>();
    const childrenByParentKey = new Map<string, T[]>();

    for (const line of lines) {
        const parentKey = line.parent_client_key;

        if (parentKey === null || parentKey === '') {
            continue;
        }

        hasChildrenByKey.add(parentKey);

        const siblings = childrenByParentKey.get(parentKey) ?? [];
        siblings.push(line);
        childrenByParentKey.set(parentKey, siblings);
    }

    const effectiveMinutesByKey = new Map<string, number>();

    const compute = (line: T): number => {
        const cached = effectiveMinutesByKey.get(line.client_key);

        if (cached !== undefined) {
            return cached;
        }

        if (hasChildrenByKey.has(line.client_key)) {
            const sum = (childrenByParentKey.get(line.client_key) ?? []).reduce(
                (total, child) => total + compute(child),
                0,
            );
            effectiveMinutesByKey.set(line.client_key, sum);

            return sum;
        }

        const minutes = parseMinutes(line.estimated_minutes);
        effectiveMinutesByKey.set(line.client_key, minutes);

        return minutes;
    };

    for (const line of lines) {
        compute(line);
    }

    const totalEffectiveMinutes = lines
        .filter(
            (line) =>
                line.parent_client_key === null || line.parent_client_key === '',
        )
        .reduce((sum, root) => sum + (effectiveMinutesByKey.get(root.client_key) ?? 0), 0);

    return {
        hasChildrenByKey,
        effectiveMinutesByKey,
        totalEffectiveMinutes,
    };
}

/**
 * O(n) rollup index for read-only estimation lines (by id).
 */
export function buildReadonlyMinutesIndex<T extends EstimationMinutesLineById>(
    lines: readonly T[],
): EstimationMinutesIndexById {
    const hasChildrenById = new Set<number>();
    const childrenByParentId = new Map<number, T[]>();

    for (const line of lines) {
        const parentId = line.parent_estimation_item_id;

        if (parentId === null) {
            continue;
        }

        hasChildrenById.add(parentId);

        const siblings = childrenByParentId.get(parentId) ?? [];
        siblings.push(line);
        childrenByParentId.set(parentId, siblings);
    }

    const effectiveMinutesById = new Map<number, number>();

    const compute = (line: T): number => {
        const cached = effectiveMinutesById.get(line.id);

        if (cached !== undefined) {
            return cached;
        }

        if (hasChildrenById.has(line.id)) {
            const sum = (childrenByParentId.get(line.id) ?? []).reduce(
                (total, child) => total + compute(child),
                0,
            );
            effectiveMinutesById.set(line.id, sum);

            return sum;
        }

        const minutes = parseMinutes(line.estimated_minutes);
        effectiveMinutesById.set(line.id, minutes);

        return minutes;
    };

    for (const line of lines) {
        compute(line);
    }

    return {
        hasChildrenById,
        effectiveMinutesById,
    };
}
