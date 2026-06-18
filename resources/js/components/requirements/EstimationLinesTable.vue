<script setup lang="ts">
import { useWindowVirtualizer } from '@tanstack/vue-virtual';
import { useResizeObserver } from '@vueuse/core';
import { computed, nextTick, onMounted, ref } from 'vue';
import EstimationLineRow from '@/components/requirements/EstimationLineRow.vue';
import { Button } from '@/components/ui/button';
import type { EstimationDisplayLine } from '@/composables/useEstimationDisplayLines';
import type {
    EstimationLineEditable,
    EstimationLineReadonly,
} from '@/composables/useEstimationLinesIndex';

const ROW_HEIGHT_PX = 72;

const props = defineProps<{
    isEditable: boolean;
    visibleLines: EstimationDisplayLine[];
    totalLineCount: number;
    hasChildrenByKey: Set<string>;
    effectiveMinutesByKey: Map<string, number>;
    hasChildrenById: Set<number>;
    effectiveMinutesById: Map<number, number>;
    directChildCountByKey: Map<string, number>;
    isCollapsed: (lineKey: string) => boolean;
    canRemoveLine: boolean;
    anyCollapsed: boolean;
    savingModuleKey: string | null;
    showPhaseColumn: boolean;
    phaseSelectOptions: { value: string; label: string }[];
}>();

const emit = defineEmits<{
    addSubtask: [line: EstimationLineEditable];
    remove: [line: EstimationLineEditable];
    saveModule: [line: EstimationLineEditable];
    toggleCollapse: [lineKey: string];
    expandAll: [];
    collapseAll: [];
}>();

const isMounted = ref(false);
const listRef = ref<HTMLElement | null>(null);
const scrollMargin = ref(0);

onMounted(() => {
    isMounted.value = true;
});

useResizeObserver(listRef, () => {
    scrollMargin.value = listRef.value?.offsetTop ?? 0;
});

const placeholderHeight = computed(
    () => props.visibleLines.length * ROW_HEIGHT_PX,
);

const rowVirtualizer = useWindowVirtualizer(
    computed(() => ({
        count: isMounted.value ? props.visibleLines.length : 0,
        estimateSize: () => ROW_HEIGHT_PX,
        overscan: 10,
        scrollMargin: scrollMargin.value,
    })),
);

const virtualRows = computed(() =>
    isMounted.value ? rowVirtualizer.value.getVirtualItems() : [],
);

const totalHeight = computed(() =>
    isMounted.value ? rowVirtualizer.value.getTotalSize() : placeholderHeight.value,
);

const showingSummary = computed(() => {
    const visible = props.visibleLines.length;
    const total = props.totalLineCount;

    if (visible === total) {
        return `${total} lines`;
    }

    return `Showing ${visible} of ${total} lines`;
});

function lineAt(index: number): EstimationDisplayLine | undefined {
    return props.visibleLines[index];
}

function editableLineAt(index: number): EstimationLineEditable | undefined {
    if (!props.isEditable) {
        return undefined;
    }

    const line = lineAt(index);

    return line as EstimationLineEditable | undefined;
}

function readonlyLineAt(index: number): EstimationLineReadonly | undefined {
    if (props.isEditable) {
        return undefined;
    }

    const line = lineAt(index);

    return line as EstimationLineReadonly | undefined;
}

function hasChildrenAt(index: number): boolean {
    const line = lineAt(index);

    if (line === undefined) {
        return false;
    }

    if (props.isEditable) {
        return props.hasChildrenByKey.has(line.lineKey);
    }

    return props.hasChildrenById.has(Number(line.lineKey));
}

function effectiveMinutesAt(index: number): number {
    const line = lineAt(index);

    if (line === undefined) {
        return 0;
    }

    if (props.isEditable) {
        return props.effectiveMinutesByKey.get(line.lineKey) ?? 0;
    }

    return props.effectiveMinutesById.get(Number(line.lineKey)) ?? 0;
}

function childCountAt(index: number): number {
    const line = lineAt(index);

    if (line === undefined) {
        return 0;
    }

    return props.directChildCountByKey.get(line.lineKey) ?? 0;
}

function onAddSubtask(index: number): void {
    const line = editableLineAt(index);

    if (line !== undefined) {
        emit('addSubtask', line);
    }
}

function onRemove(index: number): void {
    const line = editableLineAt(index);

    if (line !== undefined) {
        emit('remove', line);
    }
}

function onSaveModule(index: number): void {
    const line = editableLineAt(index);

    if (line !== undefined) {
        emit('saveModule', line);
    }
}

async function scrollToEnd(): Promise<void> {
    await nextTick();

    if (!isMounted.value) {
        listRef.value?.scrollIntoView({ block: 'end', behavior: 'smooth' });

        return;
    }

    const lastIndex = props.visibleLines.length - 1;

    if (lastIndex < 0) {
        return;
    }

    rowVirtualizer.value.scrollToIndex(lastIndex, { align: 'start' });
}

defineExpose({
    scrollToEnd,
});
</script>

<template>
    <div class="flex flex-col gap-2">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs text-muted-foreground">
                {{ showingSummary }}
                <span v-if="anyCollapsed"> · collapsed subtrees hidden</span>
            </p>
            <div class="flex flex-wrap gap-2">
                <Button type="button" variant="outline" size="sm" @click="emit('expandAll')">
                    Expand all
                </Button>
                <Button type="button" variant="outline" size="sm" @click="emit('collapseAll')">
                    Collapse all
                </Button>
            </div>
        </div>

        <div ref="listRef" class="md:overflow-x-auto">
            <div class="w-full text-left text-sm md:min-w-[720px]" role="table" aria-label="Estimation lines">
                <div
                    class="grid border-b bg-muted/40 text-sm"
                    :class="showPhaseColumn
                        ? 'grid-cols-[minmax(0,1.1fr)_minmax(0,1.2fr)_minmax(0,0.5fr)_minmax(0,0.6fr)_minmax(0,0.85fr)]'
                        : 'grid-cols-[minmax(0,1.1fr)_minmax(0,1.4fr)_minmax(0,0.6fr)_minmax(0,0.85fr)]'"
                    role="row">
                    <div role="columnheader" class="px-3 py-3 font-medium">Title</div>
                    <div role="columnheader" class="px-3 py-3 font-medium">Description</div>
                    <div v-if="showPhaseColumn" role="columnheader" class="px-3 py-3 font-medium">Phase</div>
                    <div role="columnheader" class="px-3 py-3 font-medium">Minutes</div>
                    <div role="columnheader" class="px-3 py-3 text-right font-medium">
                        Actions
                    </div>
                </div>

                <div :style="{
                    height: `${totalHeight}px`,
                    width: '100%',
                    position: 'relative',
                }" role="rowgroup" :aria-busy="!isMounted">
                    <template v-if="isMounted">
                        <div v-for="virtualRow in virtualRows"
                            :key="lineAt(virtualRow.index)?.lineKey ?? virtualRow.index" :style="{
                                position: 'absolute',
                                top: 0,
                                left: 0,
                                width: '100%',
                                height: `${virtualRow.size}px`,
                                transform: `translateY(${virtualRow.start - scrollMargin}px)`,
                            }">
                            <EstimationLineRow v-if="lineAt(virtualRow.index) !== undefined" :is-editable="isEditable"
                                :tree-depth="lineAt(virtualRow.index)?.tree_depth ?? 0"
                                :has-children="hasChildrenAt(virtualRow.index)"
                                :is-collapsed="isCollapsed(lineAt(virtualRow.index)!.lineKey)"
                                :child-count="childCountAt(virtualRow.index)"
                                :effective-minutes="effectiveMinutesAt(virtualRow.index)" :can-remove="canRemoveLine"
                                :editable-line="editableLineAt(virtualRow.index)"
                                :readonly-line="readonlyLineAt(virtualRow.index)"
                                :show-module-save="isEditable && (lineAt(virtualRow.index)?.tree_depth ?? 0) === 0"
                                :saving-module="savingModuleKey === lineAt(virtualRow.index)?.lineKey"
                                :show-phase-column="showPhaseColumn"
                                :phase-select-options="phaseSelectOptions"
                                @toggle-collapse="
                                    emit('toggleCollapse', lineAt(virtualRow.index)!.lineKey)
                                    " @add-subtask="onAddSubtask(virtualRow.index)" @remove="onRemove(virtualRow.index)"
                                @save-module="onSaveModule(virtualRow.index)" />
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
