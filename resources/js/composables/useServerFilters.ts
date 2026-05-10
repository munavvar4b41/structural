import { router } from '@inertiajs/vue3';

/**
 * Strip empty values so the query string stays clean. Arrays pass through when non-empty.
 */
export function stripFilterParams(
    params: Record<string, unknown>,
): Record<string, string | number | boolean | (string | number)[]> {
    const out: Record<string, string | number | boolean | (string | number)[]> = {};

    for (const [key, value] of Object.entries(params)) {
        if (value === undefined || value === null) {
            continue;
        }

        if (value === '') {
            continue;
        }

        if (Array.isArray(value) && value.length === 0) {
            continue;
        }

        out[key] = value as string | number | boolean | (string | number)[];
    }

    return out;
}

/**
 * GET visit preserving partial reload props (wrap your route helper `*.url({ query: stripFilterParams(...) })`).
 */
export function routerReloadOnly(fullUrl: string, only: string[]): void {
    router.get(fullUrl, {}, {
        only,
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}
