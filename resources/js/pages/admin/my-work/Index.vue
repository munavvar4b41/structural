<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProjectTaskController from '@/actions/App/Http/Controllers/Admin/ProjectTaskController';
import TaskFormSelect from '@/components/TaskFormSelect.vue';
import TaskTimerButton from '@/components/TaskTimerButton.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { formatTaskMinutes } from '@/lib/formatTaskMinutes';
import { index as myWorkIndex } from '@/routes/admin/my-work/index';
import { index as projectsIndex } from '@/routes/admin/projects/index';

type TaskCard = {
    id: number;
    project_id: number;
    title: string;
    status: string;
    estimated_minutes: number | null;
    project: { id: number; name: string; code: string | null };
    requirement: { id: number; title: string } | null;
    project_tasks_url: string;
    task_show_url: string;
};

type StatusOption = { value: string; label: string };

type Column = {
    status: string;
    label: string;
    tasks: TaskCard[];
};

const props = defineProps<{
    columns: Column[];
    status_options: StatusOption[];
}>();

const statusSelectOptions = computed(() =>
    props.status_options.map((o) => ({ value: o.value, label: o.label })),
);

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Projects', href: projectsIndex.url() },
            { title: 'My work', href: myWorkIndex.url() },
        ],
    },
});

function patchTaskStatus(task: TaskCard, status: string): void {
    if (task.status === status) {
        return;
    }

    router.patch(
        ProjectTaskController.update.url({
            project: task.project_id,
            task: task.id,
        }),
        { status },
        {
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: ['columns'], preserveScroll: true });
            },
        },
    );
}
</script>

<template>
    <Head title="My work" />

    <div class="flex flex-col gap-8">
        <Heading
            title="My work"
            description="Tasks assigned to you, grouped by status. Click a card to open the task, or use Move to to change status."
        />

        <div class="flex gap-4 overflow-x-auto pb-2">
            <div
                v-for="col in columns"
                :key="col.status"
                class="flex w-72 shrink-0 flex-col gap-3 rounded-xl border border-sidebar-border/70 bg-muted/20 p-3 dark:border-sidebar-border"
            >
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold">{{ col.label }}</h2>
                    <span class="text-xs text-muted-foreground">{{ col.tasks.length }}</span>
                </div>
                <div class="flex flex-col gap-2">
                    <div
                        v-for="task in col.tasks"
                        :key="task.id"
                        class="rounded-lg border border-border bg-card shadow-xs"
                    >
                        <Link
                            class="block min-w-0 p-3 pb-0 hover:bg-muted/40"
                            :href="task.task_show_url"
                        >
                            <p
                                class="line-clamp-2 break-words text-sm font-medium leading-snug text-foreground"
                                :title="task.title"
                            >
                                {{ task.title }}
                            </p>
                            <p class="mt-1 line-clamp-2 break-words text-xs text-muted-foreground">
                                {{ task.project.name }}
                                <span v-if="task.project.code">({{ task.project.code }})</span>
                            </p>
                            <p
                                v-if="task.requirement"
                                class="mt-1 line-clamp-2 break-words text-xs text-muted-foreground"
                                :title="task.requirement.title"
                            >
                                {{ task.requirement.title }}
                            </p>
                            <p class="mt-2 text-xs text-muted-foreground">
                                Est.: {{ formatTaskMinutes(task.estimated_minutes) }}
                            </p>
                        </Link>
                        <div class="flex flex-col gap-2 p-3 pt-2" @click.stop>
                            <label class="text-xs font-medium text-muted-foreground" :for="`st-${task.id}`"
                                >Move to</label
                            >
                            <TaskFormSelect
                                :id="`st-${task.id}`"
                                :name="`status-${task.id}`"
                                class="text-xs"
                                :model-value="task.status"
                                required
                                placeholder="Status"
                                :options="statusSelectOptions"
                                @update:model-value="patchTaskStatus(task, $event)"
                            />
                            <div class="flex flex-wrap gap-2">
                                <TaskTimerButton
                                    :project-id="task.project_id"
                                    :task-id="task.id"
                                />
                                <Button variant="outline" size="sm" class="h-8 flex-1 text-xs" as-child>
                                    <Link :href="task.task_show_url">Open task</Link>
                                </Button>
                                <Button variant="outline" size="sm" class="h-8 flex-1 text-xs" as-child>
                                    <Link :href="task.project_tasks_url">Task list</Link>
                                </Button>
                            </div>
                        </div>
                    </div>
                    <p
                        v-if="col.tasks.length === 0"
                        class="rounded-md border border-dashed border-border/80 px-2 py-6 text-center text-xs text-muted-foreground"
                    >
                        No tasks
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
