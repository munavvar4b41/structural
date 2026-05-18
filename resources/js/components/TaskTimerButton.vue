<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Play, Square } from 'lucide-vue-next';
import { computed } from 'vue';
import TaskTimerController from '@/actions/App/Http/Controllers/Admin/TaskTimerController';
import { Button } from '@/components/ui/button';

type Props = {
    projectId: number;
    taskId: number;
    label?: string;
    size?: 'sm' | 'default';
};

const props = withDefaults(defineProps<Props>(), {
    label: undefined,
    size: 'sm',
});

const page = usePage();

const active = computed(() => page.props.active_time_entry);

const isRunningForThisTask = computed(
    () => active.value !== null && active.value.task_id === props.taskId,
);

const isRunningElsewhere = computed(
    () => active.value !== null && active.value.task_id !== props.taskId,
);

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

function stop(): void {
    router.post(
        TaskTimerController.stop.url(),
        {},
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
}

const buttonTitle = computed(() => {
    if (isRunningForThisTask.value) {
        return 'Stop timer';
    }

    if (isRunningElsewhere.value) {
        return 'Switch timer to this task';
    }

    return 'Start timer';
});
</script>

<template>
    <Button
        v-if="isRunningForThisTask"
        variant="outline"
        :size="size"
        type="button"
        class="gap-1.5 border-emerald-300 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-300 dark:hover:bg-emerald-500/20"
        :title="buttonTitle"
        @click.stop="stop"
    >
        <Square class="size-3.5" />
        <span v-if="label">{{ label ?? 'Stop' }}</span>
        <span v-else>Stop</span>
    </Button>
    <Button
        v-else
        variant="outline"
        :size="size"
        type="button"
        class="gap-1.5"
        :title="buttonTitle"
        @click.stop="start"
    >
        <Play class="size-3.5" />
        <span v-if="label">{{ label }}</span>
        <span v-else>{{ isRunningElsewhere ? 'Switch' : 'Start' }}</span>
    </Button>
</template>
