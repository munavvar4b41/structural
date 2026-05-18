<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CheckCircle, Eye, List } from 'lucide-vue-next';
import ProjectTaskController from '@/actions/App/Http/Controllers/Admin/ProjectTaskController';
import TaskCompletionReviewController from '@/actions/App/Http/Controllers/Admin/TaskCompletionReviewController';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import TaskFormSelect from '@/components/TaskFormSelect.vue';
import TaskTimerButton from '@/components/TaskTimerButton.vue';
import { Button } from '@/components/ui/button';
import Tooltip from '@/components/ui/tooltip/Tooltip.vue';
import TooltipContent from '@/components/ui/tooltip/TooltipContent.vue';
import TooltipTrigger from '@/components/ui/tooltip/TooltipTrigger.vue';
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
    is_assignee_only_limited: boolean;
    can_submit_task_completion: boolean;
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

const doneStatusValue = 'done';

function statusSelectOptionsForTask(task: TaskCard) {
    const base = props.status_options.map((o) => ({ value: o.value, label: o.label }));

    if (task.is_assignee_only_limited) {
        return base.filter((o) => o.value !== doneStatusValue);
    }

    return base;
}

function submitForCompletion(task: TaskCard): void {
    router.post(
        TaskCompletionReviewController.submit.url({
            project: task.project_id,
            task: task.id,
        }),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: ['columns'] });
            },
        },
    );
}

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
                router.reload({ only: ['columns'] });
            },
        },
    );
}
</script>

<template>

    <Head title="My work" />

    <div class="flex flex-col gap-8">
        <PageHeader title="My work"
            description="Tasks assigned to you, grouped by status. Click a card to open the task, or use Move to to change status." />

        <div class="flex gap-4 overflow-x-auto pb-2">
            <GlassCard v-for="col in columns" :key="col.status" class="flex w-86 shrink-0 flex-col gap-3 p-4">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold">{{ col.label }}</h2>
                    <span class="text-xs text-muted-foreground">{{ col.tasks.length }}</span>
                </div>
                <div class="flex flex-col gap-2">
                    <GlassCard v-for="task in col.tasks" :key="task.id" class="overflow-hidden p-0" hover>
                        <Link class="block min-w-0 p-3 pb-0 hover:bg-muted/40" :href="task.task_show_url">
                            <p class="line-clamp-2 break-words text-sm font-medium leading-snug text-foreground"
                                :title="task.title">
                                {{ task.title }}
                            </p>
                            <p class="mt-1 line-clamp-2 break-words text-xs text-muted-foreground">
                                {{ task.project.name }}
                                <span v-if="task.project.code">({{ task.project.code }})</span>
                            </p>
                            <p v-if="task.requirement"
                                class="mt-1 line-clamp-2 break-words text-xs text-muted-foreground"
                                :title="task.requirement.title">
                                {{ task.requirement.title }}
                            </p>
                            <p class="mt-2 text-xs text-muted-foreground">
                                Est.: {{ formatTaskMinutes(task.estimated_minutes) }}
                            </p>
                        </Link>
                        <div class="flex flex-col gap-2 p-3 pt-2" @click.stop>
                            <TaskFormSelect :id="`st-${task.id}`" :name="`status-${task.id}`" class="text-xs"
                                :model-value="task.status" required placeholder="Status"
                                :options="statusSelectOptionsForTask(task)"
                                @update:model-value="patchTaskStatus(task, $event)" />
                            <div class="flex flex-wrap gap-2 justify-between max-w-full">
                                <Tooltip>
                                    <TooltipTrigger class="flex-1 w-full">
                                        <Button v-if="task.can_submit_task_completion" variant="secondary" size="sm"
                                            class="h-8 text-xs w-full" type="button" @click="submitForCompletion(task)">
                                            <CheckCircle class="size-3.5" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        Submit for completion
                                    </TooltipContent>
                                </Tooltip>
                                <Tooltip>
                                    <TooltipTrigger class="flex-1 w-full">
                                        <TaskTimerButton :project-id="task.project_id" :task-id="task.id"
                                            :show-label="false" class="w-full" />
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        Start timer
                                    </TooltipContent>
                                </Tooltip>
                                <Tooltip>
                                    <TooltipTrigger class="flex-1 w-full">
                                        <Button variant="outline" size="sm" class="h-8 text-xs w-full" as-child>
                                            <Link :href="task.task_show_url">
                                                <Eye class="size-3.5" />
                                            </Link>
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        Open task
                                    </TooltipContent>
                                </Tooltip>
                                <Tooltip>
                                    <TooltipTrigger class="flex-1 w-full">
                                        <Button variant="outline" size="sm" class="h-8 text-xs w-full" as-child>
                                            <Link :href="task.project_tasks_url">
                                                <List class="size-3.5" />
                                            </Link>
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        Task list
                                    </TooltipContent>
                                </Tooltip>
                            </div>
                        </div>
                    </GlassCard>
                    <p v-if="col.tasks.length === 0"
                        class="rounded-xl border border-dashed border-border/80 px-2 py-6 text-center text-xs text-muted-foreground">
                        No tasks
                    </p>
                </div>
            </GlassCard>
        </div>
    </div>
</template>
