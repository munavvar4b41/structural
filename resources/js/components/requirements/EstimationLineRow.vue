<script setup lang="ts">
import { ChevronDown, ChevronRight, CornerDownRight, FileText } from 'lucide-vue-next';
import { ref } from 'vue';
import FormSelect from '@/components/FormSelect.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import type {
    EstimationLineEditable,
    EstimationLineReadonly,
} from '@/composables/useEstimationLinesIndex';
import { formatTaskMinutes } from '@/lib/formatTaskMinutes';

const props = defineProps<{
    isEditable: boolean;
    treeDepth: number;
    hasChildren: boolean;
    isCollapsed: boolean;
    childCount: number;
    effectiveMinutes: number;
    canRemove: boolean;
    showModuleSave?: boolean;
    savingModule?: boolean;
    editableLine?: EstimationLineEditable;
    readonlyLine?: EstimationLineReadonly;
    showPhaseColumn?: boolean;
    phaseSelectOptions?: { value: string; label: string }[];
}>();

const emit = defineEmits<{
    addSubtask: [];
    remove: [];
    saveModule: [];
    toggleCollapse: [];
}>();

const notesDialogOpen = ref(false);
</script>

<template>
    <div v-memo="[
        isEditable,
        treeDepth,
        hasChildren,
        isCollapsed,
        childCount,
        effectiveMinutes,
        canRemove,
        showModuleSave,
        savingModule,
        editableLine?.client_key,
        editableLine?.title,
        editableLine?.description,
        editableLine?.estimated_minutes,
        readonlyLine?.id,
        readonlyLine?.title,
        readonlyLine?.description,
        readonlyLine?.estimated_minutes,
        editableLine?.phase,
        readonlyLine?.phase,
        showPhaseColumn,
    ]" class="grid h-[72px] w-full items-center border-b border-border/60 text-sm" :class="showPhaseColumn
        ? 'grid-cols-[minmax(0,1.1fr)_minmax(0,1.2fr)_minmax(0,0.5fr)_minmax(0,0.6fr)_minmax(0,0.85fr)]'
        : 'grid-cols-[minmax(0,1.1fr)_minmax(0,1.4fr)_minmax(0,0.6fr)_minmax(0,0.85fr)]'" role="row">
        <div role="cell" class="flex min-w-0 items-center gap-1 px-3"
            :style="{ paddingLeft: `calc(0.75rem + ${treeDepth} * 1rem)` }">
            <Button v-if="hasChildren" type="button" variant="ghost" size="icon-sm" class="shrink-0"
                :title="isCollapsed ? 'Expand subtasks' : 'Collapse subtasks'" :aria-expanded="!isCollapsed"
                @click="emit('toggleCollapse')">
                <ChevronDown v-if="!isCollapsed" class="size-4" aria-hidden="true" />
                <ChevronRight v-else class="size-4" aria-hidden="true" />
                <span class="sr-only">
                    {{ isCollapsed ? 'Expand subtasks' : 'Collapse subtasks' }}
                </span>
            </Button>
            <span v-if="treeDepth > 0 && !hasChildren" class="size-4 shrink-0" aria-hidden="true" />
            <CornerDownRight v-if="treeDepth > 0" class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
            <Input v-if="isEditable && editableLine !== undefined" v-model="editableLine.title" type="text"
                placeholder="Task title" class="min-w-0 h-9" />
            <span v-else-if="readonlyLine !== undefined" class="truncate font-medium">
                {{ readonlyLine.title }}
            </span>
            <span v-if="hasChildren && isCollapsed && childCount > 0" class="shrink-0 text-xs text-muted-foreground">
                ({{ childCount }} {{ childCount === 1 ? 'subtask' : 'subtasks' }})
            </span>
        </div>

        <div role="cell" class="min-w-0 px-3">
            <div v-if="isEditable && editableLine !== undefined" class="flex min-w-0 items-center gap-1">
                <Input v-model="editableLine.description" type="text" placeholder="Optional notes"
                    class="min-w-0 h-9" />
                <Button type="button" variant="ghost" size="icon-sm" class="shrink-0" title="Edit full notes"
                    @click="notesDialogOpen = true">
                    <FileText class="size-4" aria-hidden="true" />
                    <span class="sr-only">Edit full notes</span>
                </Button>
            </div>
            <span v-else-if="readonlyLine !== undefined" class="block truncate text-muted-foreground"
                :title="readonlyLine.description ?? undefined">
                {{ readonlyLine.description || '—' }}
            </span>
        </div>

        <div v-if="showPhaseColumn" role="cell" class="px-3">
            <FormSelect v-if="isEditable && editableLine !== undefined"
                :id="`estimation-phase-${editableLine.client_key}`" :name="`lines.${editableLine.client_key}.phase`"
                :model-value="String(editableLine.phase)" :options="phaseSelectOptions ?? []" placeholder="Phase"
                exclude-from-submit required @update:model-value="editableLine.phase = Number($event)" />
            <span v-else-if="readonlyLine !== undefined" class="text-muted-foreground">
                Phase {{ readonlyLine.phase }}
            </span>
        </div>

        <div role="cell" class="px-3">
            <div v-if="hasChildren" class="flex flex-col gap-0.5">
                <span class="tabular-nums" :class="isEditable ? 'text-muted-foreground' : ''">
                    {{ formatTaskMinutes(effectiveMinutes) }}
                </span>
                <span class="text-xs text-muted-foreground">Sum of subtasks</span>
            </div>
            <Input v-else-if="isEditable && editableLine !== undefined" v-model="editableLine.estimated_minutes"
                type="number" min="1" step="1" placeholder="min" class="h-9" />
            <span v-else-if="readonlyLine !== undefined" class="tabular-nums">
                {{ formatTaskMinutes(readonlyLine.estimated_minutes) }}
            </span>
        </div>

        <div role="cell" class="px-3 text-right">
            <div v-if="isEditable && editableLine !== undefined" class="flex flex-wrap justify-end gap-1">
                <Button v-if="showModuleSave" type="button" variant="default" size="sm" :disabled="savingModule"
                    @click="emit('saveModule')">
                    Save
                </Button>
                <Button type="button" variant="outline" size="sm" @click="emit('addSubtask')">
                    Subtask
                </Button>
                <Button type="button" variant="outline" size="sm" class="text-destructive" :disabled="!canRemove"
                    @click="emit('remove')">
                    Remove
                </Button>
            </div>
        </div>
    </div>

    <Dialog v-if="isEditable && editableLine !== undefined" v-model:open="notesDialogOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Line notes</DialogTitle>
                <DialogDescription>
                    Optional description for this estimation line.
                </DialogDescription>
            </DialogHeader>
            <textarea v-model="editableLine.description" rows="6"
                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                placeholder="Optional" />
            <DialogFooter>
                <DialogClose as-child>
                    <Button type="button" variant="outline">Done</Button>
                </DialogClose>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
