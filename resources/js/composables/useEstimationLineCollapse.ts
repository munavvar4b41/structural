import { computed, ref, type ComputedRef, type Ref } from 'vue';
import {
    buildDirectChildCountByParentKey,
    filterVisibleEstimationLines,
    type EstimationLineTreeNode,
} from '@/lib/estimationLinesVisibility';

export function useEstimationLineCollapse<T extends EstimationLineTreeNode>(
    displayLines: ComputedRef<readonly T[]>,
    parentKeysWithChildren: ComputedRef<ReadonlySet<string>>,
): {
    collapsedKeys: Ref<Set<string>>;
    visibleLines: ComputedRef<T[]>;
    directChildCountByKey: ComputedRef<Map<string, number>>;
    isCollapsed: (lineKey: string) => boolean;
    toggleCollapsed: (lineKey: string) => void;
    expandLine: (lineKey: string) => void;
    expandAll: () => void;
    collapseAllParents: () => void;
    anyCollapsed: ComputedRef<boolean>;
} {
    const collapsedKeys = ref<Set<string>>(new Set());

    const directChildCountByKey = computed(() =>
        buildDirectChildCountByParentKey(displayLines.value),
    );

    const visibleLines = computed(() =>
        filterVisibleEstimationLines(displayLines.value, collapsedKeys.value),
    );

    const anyCollapsed = computed(() => collapsedKeys.value.size > 0);

    const isCollapsed = (lineKey: string): boolean => collapsedKeys.value.has(lineKey);

    const toggleCollapsed = (lineKey: string): void => {
        const next = new Set(collapsedKeys.value);

        if (next.has(lineKey)) {
            next.delete(lineKey);
        } else {
            next.add(lineKey);
        }

        collapsedKeys.value = next;
    };

    const expandLine = (lineKey: string): void => {
        if (!collapsedKeys.value.has(lineKey)) {
            return;
        }

        const next = new Set(collapsedKeys.value);
        next.delete(lineKey);
        collapsedKeys.value = next;
    };

    const expandAll = (): void => {
        collapsedKeys.value = new Set();
    };

    const collapseAllParents = (): void => {
        collapsedKeys.value = new Set(parentKeysWithChildren.value);
    };

    return {
        collapsedKeys,
        visibleLines,
        directChildCountByKey,
        isCollapsed,
        toggleCollapsed,
        expandLine,
        expandAll,
        collapseAllParents,
        anyCollapsed,
    };
}
