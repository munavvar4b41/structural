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
