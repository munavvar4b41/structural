import { computed, type ComputedRef } from 'vue';
import type {
    EstimationLineEditable,
    EstimationLineReadonly,
} from '@/composables/useEstimationLinesIndex';

export type EstimationDisplayLine =
    | (EstimationLineEditable & { lineKey: string; parentKey: string | null })
    | (EstimationLineReadonly & { lineKey: string; parentKey: string | null });

export function useEstimationDisplayLines(
    displayLines: ComputedRef<EstimationLineEditable[] | EstimationLineReadonly[]>,
    isEditable: ComputedRef<boolean>,
): {
    treeLines: ComputedRef<EstimationDisplayLine[]>;
    parentKeysWithChildren: ComputedRef<Set<string>>;
} {
    const treeLines = computed((): EstimationDisplayLine[] => {
        if (isEditable.value) {
            return (displayLines.value as EstimationLineEditable[]).map(
                (line): EstimationDisplayLine =>
                    Object.assign(line, {
                        lineKey: line.client_key,
                        parentKey: line.parent_client_key,
                    }) as EstimationDisplayLine,
            );
        }

        return (displayLines.value as EstimationLineReadonly[]).map((line) => ({
            ...line,
            lineKey: String(line.id),
            parentKey:
                line.parent_estimation_item_id !== null
                    ? String(line.parent_estimation_item_id)
                    : null,
        }));
    });

    const parentKeysWithChildren = computed(() => {
        const keys = new Set<string>();

        for (const line of treeLines.value) {
            const parentKey = line.parentKey;

            if (parentKey !== null && parentKey !== '') {
                keys.add(parentKey);
            }
        }

        return keys;
    });

    return {
        treeLines,
        parentKeysWithChildren,
    };
}
