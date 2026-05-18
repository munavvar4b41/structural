<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Square } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import TaskTimerController from '@/actions/App/Http/Controllers/Admin/TaskTimerController';
import { Button } from '@/components/ui/button';
import { formatSeconds } from '@/lib/formatSeconds';
import { show as projectTasksShow } from '@/routes/admin/projects/tasks/index';

const page = usePage();

const active = computed(() => page.props.active_time_entry);

const now = ref(Date.now());
let intervalId: number | undefined;

onMounted(() => {
    intervalId = window.setInterval(() => {
        now.value = Date.now();
    }, 1000);
});

onBeforeUnmount(() => {
    if (intervalId !== undefined) {
        window.clearInterval(intervalId);
    }
});

const elapsedSeconds = computed(() => {
    if (active.value === null) {
        return 0;
    }

    if (active.value.is_paused) {
        return active.value.elapsed_seconds;
    }

    const startedMs = Date.parse(active.value.started_at);

    if (Number.isNaN(startedMs)) {
        return active.value.elapsed_seconds;
    }

    const base = active.value.elapsed_seconds;
    const tick = Math.max(0, Math.floor((now.value - startedMs) / 1000));

    return Math.max(base, tick);
});

const elapsedLabel = computed(() => formatSeconds(elapsedSeconds.value, { withSeconds: true }));

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
    <div
        v-if="active !== null"
        class="flex min-w-0 items-center gap-2 rounded-full border border-emerald-300/70 bg-emerald-50 px-2 py-1 text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-300"
    >
        <span
            class="flex size-2 shrink-0 rounded-full bg-emerald-500"
            :class="{ 'motion-safe:animate-pulse': !active.is_paused }"
            aria-hidden="true"
        />
        <Link
            :href="
                projectTasksShow.url({
                    project: active.project_id,
                    task: active.task_id,
                })
            "
            class="min-w-0 truncate text-xs font-medium hover:underline"
            :title="`${active.task_title} · ${active.project_name}`"
        >
            {{ active.task_title }}
        </Link>
        <span class="shrink-0 font-mono text-xs tabular-nums">{{ elapsedLabel }}</span>
        <Button
            variant="ghost"
            size="icon"
            class="size-6 shrink-0 rounded-full text-emerald-700 hover:bg-emerald-200/60 hover:text-emerald-800 dark:text-emerald-300 dark:hover:bg-emerald-500/20"
            type="button"
            title="Stop timer"
            @click="stopTimer"
        >
            <Square class="size-3" />
        </Button>
    </div>
</template>
