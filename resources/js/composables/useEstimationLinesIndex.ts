import { computed, type ComputedRef, type Ref } from 'vue';
import { depthFirstEstimationLines } from '@/lib/estimationLinesOrder';
import {
    buildEditableMinutesIndex,
    buildReadonlyMinutesIndex,
} from '@/lib/estimationMinutesRollup';

export type EstimationLineReadonly = {
    id: number;
    parent_estimation_item_id: number | null;
    title: string;
    description: string | null;
    estimated_minutes: number | null;
    sort_order: number;
    phase: number;
    tree_depth: number;
};

export type EstimationLineEditable = {
    id: number | null;
    client_key: string;
    parent_id: number | null;
    parent_client_key: string | null;
    title: string;
    description: string;
    estimated_minutes: string;
    sort_order: number;
    phase: number;
    tree_depth: number;
};

export function useEstimationLinesIndex(
    editableLines: Ref<EstimationLineEditable[]>,
    readonlyLines: Ref<EstimationLineReadonly[]>,
    isEditable: ComputedRef<boolean> | Ref<boolean>,
): {
    displayLines: ComputedRef<EstimationLineEditable[] | EstimationLineReadonly[]>;
    hasChildrenByKey: ComputedRef<Set<string>>;
    effectiveMinutesByKey: ComputedRef<Map<string, number>>;
    hasChildrenById: ComputedRef<Set<number>>;
    effectiveMinutesById: ComputedRef<Map<number, number>>;
    totalEffectiveMinutes: ComputedRef<number>;
} {
    const editableIndex = computed(() => {
        const lines = editableLines.value;
        const displayLines = depthFirstEstimationLines([...lines]);
        const minutesIndex = buildEditableMinutesIndex(lines);

        return {
            displayLines,
            ...minutesIndex,
        };
    });

    const readonlyIndex = computed(() => buildReadonlyMinutesIndex(readonlyLines.value));

    const displayLines = computed(() =>
        isEditable.value
            ? editableIndex.value.displayLines
            : readonlyLines.value,
    );

    const hasChildrenByKey = computed(() => editableIndex.value.hasChildrenByKey);

    const effectiveMinutesByKey = computed(
        () => editableIndex.value.effectiveMinutesByKey,
    );

    const hasChildrenById = computed(() => readonlyIndex.value.hasChildrenById);

    const effectiveMinutesById = computed(
        () => readonlyIndex.value.effectiveMinutesById,
    );

    const totalEffectiveMinutes = computed(() =>
        isEditable.value
            ? editableIndex.value.totalEffectiveMinutes
            : 0,
    );

    return {
        displayLines,
        hasChildrenByKey,
        effectiveMinutesByKey,
        hasChildrenById,
        effectiveMinutesById,
        totalEffectiveMinutes,
    };
}
