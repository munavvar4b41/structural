<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CheckCircle, Eye, List } from 'lucide-vue-next';
import type { HTMLAttributes } from 'vue';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import FormSelect from '@/components/FormSelect.vue';
import TaskTimerButton from '@/components/TaskTimerButton.vue';
import { Button } from '@/components/ui/button';
import Tooltip from '@/components/ui/tooltip/Tooltip.vue';
import TooltipContent from '@/components/ui/tooltip/TooltipContent.vue';
import TooltipTrigger from '@/components/ui/tooltip/TooltipTrigger.vue';
import { formatTaskMinutes } from '@/lib/formatTaskMinutes';

export type MyWorkTaskCardData = {
    id: number;
    project_id: number;
    title: string;
    status: string;
    estimated_minutes: number | null;
    project: { id: number; name: string; code: string | null };
    requirement: { id: number; title: string } | null;
    project_tasks_url: string;
    task_show_url: string;
    is_assignee_only_limited: boolean;
    can_submit_task_completion: boolean;
    timer_today_seconds: number;
    timer_state: 'running' | 'paused' | 'idle';
    children_count?: number;
};

const props = withDefaults(
    defineProps<{
        task: MyWorkTaskCardData;
        statusOptions: { value: string; label: string }[];
        showStatusSelect?: boolean;
        draggable?: boolean;
        class?: HTMLAttributes['class'];
    }>(),
    {
        showStatusSelect: true,
    },
);

const emit = defineEmits<{
    preview: [task: MyWorkTaskCardData];
    statusChange: [task: MyWorkTaskCardData, status: string];
    submitCompletion: [task: MyWorkTaskCardData];
    dragStart: [event: DragEvent, task: MyWorkTaskCardData];
    dragEnd: [];
}>();

function onCardKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        emit('preview', props.task);
    }
}

function onDragStart(event: DragEvent): void {
    emit('dragStart', event, props.task);
}
</script>

<template>
    <GlassCard :class="['overflow-hidden p-0', props.class]" hover :draggable="draggable" @dragstart="onDragStart"
        @dragend="emit('dragEnd')">
        <button type="button" class="block min-w-0 w-full cursor-pointer p-3 pb-0 text-left hover:bg-muted/40"
            @click="emit('preview', task)" @keydown="onCardKeydown">
            <p class="line-clamp-2 break-words text-sm font-medium leading-snug text-foreground" :title="task.title">
                {{ task.title }}
            </p>
            <p class="mt-1 line-clamp-2 break-words text-xs text-muted-foreground">
                {{ task.project.name }}
                <span v-if="task.project.code">({{ task.project.code }})</span>
            </p>
            <p v-if="task.requirement" class="mt-1 line-clamp-2 break-words text-xs text-muted-foreground"
                :title="task.requirement.title">
                {{ task.requirement.title }}
            </p>
            <p class="mt-2 text-xs text-muted-foreground">
                Est.: {{ formatTaskMinutes(task.estimated_minutes) }}
                <span v-if="task.children_count && task.children_count > 0" class="ml-2">
                    · {{ task.children_count }} subtasks
                </span>
            </p>
        </button>
        <div class="flex flex-col gap-2 p-3 pt-2" @click.stop>
            <FormSelect v-if="showStatusSelect" :id="`st-${task.id}`" :name="`status-${task.id}`" class="text-xs"
                :model-value="task.status" required placeholder="Status" :options="statusOptions"
                @update:model-value="emit('statusChange', task, $event)" />
            <div class="flex max-w-full flex-wrap justify-between gap-2">
                <Tooltip>
                    <TooltipTrigger as-child class="w-full flex-1">
                        <Button v-if="task.can_submit_task_completion" variant="secondary" size="sm"
                            class="h-8 w-full text-xs" type="button" @click="emit('submitCompletion', task)">
                            <CheckCircle class="size-3.5" />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent> Submit for completion </TooltipContent>
                </Tooltip>
                <Tooltip>
                    <TooltipTrigger as-child class="w-full flex-1">
                        <TaskTimerButton :project-id="task.project_id" :task-id="task.id" :show-label="false"
                            :timer-today-seconds="task.timer_today_seconds" :timer-state="task.timer_state" />
                    </TooltipTrigger>
                    <TooltipContent> Timer </TooltipContent>
                </Tooltip>
                <Tooltip>
                    <TooltipTrigger as-child class="w-full flex-1">
                        <Button variant="outline" size="sm" class="h-8 w-full text-xs" type="button"
                            @click="emit('preview', task)">
                            <Eye class="size-3.5" />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent> View task </TooltipContent>
                </Tooltip>
                <Tooltip>
                    <TooltipTrigger as-child class="w-full flex-1">
                        <Button variant="outline" size="sm" class="h-8 w-full text-xs" as-child>
                            <Link :href="task.project_tasks_url">
                                <List class="size-3.5" />
                            </Link>
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent> Task list </TooltipContent>
                </Tooltip>
            </div>
        </div>
    </GlassCard>
</template>
