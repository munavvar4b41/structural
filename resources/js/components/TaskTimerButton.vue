<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Pause, Play } from 'lucide-vue-next';
import { computed } from 'vue';
import TaskTimerController from '@/actions/App/Http/Controllers/Admin/TaskTimerController';
import { Button } from '@/components/ui/button';
import { formatSeconds } from '@/lib/formatSeconds';

type Props = {
    projectId: number;
    taskId: number;
    label?: string;
    showLabel?: boolean;
    size?: 'sm' | 'default';
    timerTodaySeconds?: number;
    timerState?: 'running' | 'paused' | 'idle';
};

const props = withDefaults(defineProps<Props>(), {
    label: undefined,
    size: 'sm',
    showLabel: true,
    timerTodaySeconds: 0,
    timerState: 'idle',
});

const page = usePage();

const active = computed(() => page.props.active_time_entry);

const isRunningForThisTask = computed(
    () =>
        active.value !== null
        && active.value.task_id === props.taskId
        && !active.value.is_paused,
);

const isPausedForThisTask = computed(
    () =>
        active.value !== null
        && active.value.task_id === props.taskId
        && active.value.is_paused,
);

const isRunningElsewhere = computed(
    () =>
        active.value !== null
        && active.value.task_id !== props.taskId
        && !active.value.is_paused,
);

const todayLabel = computed(() => {
    const seconds =
        active.value !== null && active.value.task_id === props.taskId
            ? active.value.task_today_seconds
            : props.timerTodaySeconds;

    if (seconds <= 0) {
        return null;
    }

    return formatSeconds(seconds, { withSeconds: true });
});

function start(): void {
    router.post(
        TaskTimerController.start.url({
            project: props.projectId,
            task: props.taskId,
        }),
        {},
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
}

function pause(): void {
    router.post(
        TaskTimerController.pause.url(),
        {},
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
}

function onClick(): void {
    if (isRunningForThisTask.value) {
        pause();

        return;
    }

    start();
}

const buttonTitle = computed(() => {
    if (isRunningForThisTask.value) {
        return 'Pause timer';
    }

    if (isPausedForThisTask.value) {
        return 'Resume timer';
    }

    if (isRunningElsewhere.value) {
        return 'Switch timer to this task';
    }

    return 'Start timer';
});

const actionLabel = computed(() => {
    if (isRunningForThisTask.value) {
        return props.label ?? 'Pause';
    }

    if (isPausedForThisTask.value) {
        return props.label ?? 'Resume';
    }

    if (props.label) {
        return props.label;
    }

    return isRunningElsewhere.value ? 'Switch' : 'Start';
});

const buttonClass = computed(() => {
    const sizeClass = props.showLabel ? '' : 'h-8 flex-1 w-full';
    
    if (isRunningForThisTask.value) {
        return `gap-1.5 border-emerald-300 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-300 dark:hover:bg-emerald-500/20 ${sizeClass}`;
    }

    return `gap-1.5 ${sizeClass}`;
});
</script>

<template>
    <Button variant="outline" :size="size" type="button" :class="buttonClass" :title="buttonTitle"
        @click.stop="onClick">
        <Pause v-if="isRunningForThisTask" class="size-3.5" />
        <Play v-else class="size-3.5" />
        <span v-if="showLabel">{{ actionLabel }}</span>
        <span v-if="showLabel && todayLabel" class="font-mono text-[10px] tabular-nums text-muted-foreground"
            :title="`Time today: ${todayLabel}`">
            {{ todayLabel }}
        </span>
    </Button>
</template>
