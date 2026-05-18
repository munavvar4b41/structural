import { computed   } from 'vue';
import type {ComputedRef, Ref} from 'vue';

/**
 * Case-insensitive substring match helper for client-side list filtering.
 */
export function matchesNeedle(haystack: string | null | undefined, needle: string): boolean {
    if (needle === '') {
        return true;
    }

    return (haystack ?? '').toLowerCase().includes(needle);
}

type ClientFilterOptions<T> = {
    /** Search string (trimmed lowercasing done inside matchesSearch). */
    search: Ref<string>;
    /** Full list from props. */
    items: Ref<T[]>;
    /** Return true to include the row when search is non-empty. */
    matchesSearch: (item: T, needle: string) => boolean;
    /** Optional extra predicates (all must pass). */
    predicates?: Ref<((item: T) => boolean)[]>;
};

/**
 * Computed filtered list for client-only filtering (small datasets).
 */
export function useClientFilteredList<T>(options: ClientFilterOptions<T>): ComputedRef<T[]> {
    const { search, items, matchesSearch, predicates } = options;

    return computed(() => {
        const needle = search.value.trim().toLowerCase();
        const preds = predicates?.value ?? [];

        return items.value.filter((item) => {
            if (needle !== '' && !matchesSearch(item, needle)) {
                return false;
            }

            for (const p of preds) {
                if (!p(item)) {
                    return false;
                }
            }

            return true;
        });
    });
}
