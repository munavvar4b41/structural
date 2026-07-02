<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Pause, Play, Square } from 'lucide-vue-next';
import { computed } from 'vue';
import TaskTimerController from '@/actions/App/Http/Controllers/Admin/TaskTimerController';
import { Button } from '@/components/ui/button';
import {
    useLiveTaskTodaySeconds,
    useTimerClock,
} from '@/composables/useLiveTaskTodaySeconds';
import { formatSeconds } from '@/lib/formatSeconds';
import { show as projectTasksShow } from '@/routes/admin/projects/tasks/index';

const page = usePage();

const active = computed(() => page.props.active_time_entry);

const now = useTimerClock();

const elapsedSeconds = useLiveTaskTodaySeconds(active, now);

const elapsedLabel = computed(() => formatSeconds(elapsedSeconds.value, { withSeconds: true }));

function pauseTimer(): void {
    router.post(
        TaskTimerController.pause.url(),
        {},
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
}

function resumeTimer(): void {
    router.post(
        TaskTimerController.resume.url(),
        {},
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
}

function stopTimer(): void {
    router.post(
        TaskTimerController.stop.url(),
        {},
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
}
</script>

<template>
    <div v-if="active !== null"
        class="flex min-w-0 items-center gap-2 rounded-full border border-success/40 bg-success/10 px-2 py-1 text-success">
        <span class="flex size-2 shrink-0 rounded-full bg-success"
            :class="{ 'motion-safe:animate-pulse': !active.is_paused }" aria-hidden="true" />
        <Link :href="projectTasksShow.url({
            project: active.project_id,
            task: active.task_id,
        })
            " class="min-w-0 truncate text-xs font-medium hover:underline"
            :title="`${active.task_title} · ${active.project_name}`">
            {{ active.task_title }}
        </Link>
        <span class="shrink-0 font-mono text-xs tabular-nums">{{ elapsedLabel }}</span>
        <Button v-if="!active.is_paused" variant="ghost" size="icon"
            class="size-6 shrink-0 rounded-full text-success hover:bg-success/20 hover:text-success"
            type="button" title="Pause timer" @click="pauseTimer">
            <Pause class="size-3" />
        </Button>
        <Button v-else variant="ghost" size="icon"
            class="size-6 shrink-0 rounded-full text-success hover:bg-success/20 hover:text-success"
            type="button" title="Resume timer" @click="resumeTimer">
            <Play class="size-3" />
        </Button>
        <Button variant="ghost" size="icon"
            class="size-6 shrink-0 rounded-full text-success hover:bg-success/20 hover:text-success"
            type="button" title="Stop timer" @click="stopTimer">
            <Square class="size-3" />
        </Button>
    </div>
</template>
