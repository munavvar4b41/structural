<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CheckCircle, Eye, List } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import ProjectTaskController from '@/actions/App/Http/Controllers/Admin/ProjectTaskController';
import TaskCompletionReviewController from '@/actions/App/Http/Controllers/Admin/TaskCompletionReviewController';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import TaskFormSelect from '@/components/TaskFormSelect.vue';
import TaskTimerButton from '@/components/TaskTimerButton.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
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
    timer_today_seconds: number;
    timer_state: 'running' | 'paused' | 'idle';
};

type StatusOption = { value: string; label: string };
type ProjectOption = { value: number; label: string };

type ColumnMeta = {
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
};

type Column = {
    status: string;
    label: string;
    tasks: TaskCard[];
    meta: ColumnMeta;
};

const props = defineProps<{
    columns: Column[];
    status_options: StatusOption[];
    project_options: ProjectOption[];
    filters: { project_id: number | null };
}>();

const projectValue = ref(
    props.filters.project_id !== null ? String(props.filters.project_id) : '',
);

watch(
    () => props.filters.project_id,
    () => {
        projectValue.value =
            props.filters.project_id !== null
                ? String(props.filters.project_id)
                : '';
    },
);

const projectSelectOptions = computed(() => [
    { value: '', label: 'All projects' },
    ...props.project_options.map((o) => ({
        value: String(o.value),
        label: o.label,
    })),
]);

const doneStatusValue = 'done';

function boardQuery(extra: Record<string, string | number> = {}): Record<string, string | number> {
    const query: Record<string, string | number> = { ...extra };

    if (props.filters.project_id !== null) {
        query.project_id = props.filters.project_id;
    }

    return query;
}

function applyProjectFilter(value: string): void {
    projectValue.value = value;

    const query: Record<string, string | number> = {};

    if (value !== '') {
        query.project_id = Number(value);
    }

    router.get(myWorkIndex.url(), query, {
        preserveState: true,
        preserveScroll: true,
        only: ['columns', 'filters', 'project_options'],
    });
}

function loadMoreColumn(col: Column): void {
    if (col.meta.current_page >= col.meta.last_page) {
        return;
    }

    router.get(
        myWorkIndex.url(),
        boardQuery({
            [`page_${col.status}`]: col.meta.current_page + 1,
        }),
        {
            preserveState: true,
            preserveScroll: true,
            only: ['columns'],
        },
    );
}

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

        <div class="flex flex-wrap items-end gap-4">
            <div class="grid gap-1">
                <Label class="text-xs text-muted-foreground" for="my-work-project-filter">
                    Project
                </Label>
                <TaskFormSelect id="my-work-project-filter" name="project_id" class="min-w-[12rem] sm:min-w-[16rem]"
                    :model-value="projectValue" :options="projectSelectOptions" placeholder="All projects"
                    none-label="All projects" exclude-from-submit @update:model-value="applyProjectFilter" />
            </div>
        </div>

        <div class="flex gap-4 overflow-x-auto pb-2">
            <GlassCard v-for="col in columns" :key="col.status" class="flex w-86 shrink-0 flex-col gap-3 p-4">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold">{{ col.label }}</h2>
                    <span class="text-xs text-muted-foreground">
                        {{ col.tasks.length }} / {{ col.meta.total }}
                    </span>
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
                            <div class="flex max-w-full flex-wrap justify-between gap-2">
                                <Tooltip>
                                    <TooltipTrigger class="w-full flex-1">
                                        <Button v-if="task.can_submit_task_completion" variant="secondary" size="sm"
                                            class="h-8 w-full text-xs" type="button" @click="submitForCompletion(task)">
                                            <CheckCircle class="size-3.5" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent> Submit for completion </TooltipContent>
                                </Tooltip>
                                <Tooltip>
                                    <TooltipTrigger class="w-full flex-1">
                                        <TaskTimerButton :project-id="task.project_id" :task-id="task.id"
                                            :show-label="false" class="w-full"
                                            :timer-today-seconds="task.timer_today_seconds"
                                            :timer-state="task.timer_state" />
                                    </TooltipTrigger>
                                    <TooltipContent> Timer </TooltipContent>
                                </Tooltip>
                                <Tooltip>
                                    <TooltipTrigger class="w-full flex-1">
                                        <Button variant="outline" size="sm" class="h-8 w-full text-xs" as-child>
                                            <Link :href="task.task_show_url">
                                                <Eye class="size-3.5" />
                                            </Link>
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent> Open task </TooltipContent>
                                </Tooltip>
                                <Tooltip>
                                    <TooltipTrigger class="w-full flex-1">
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
                    <p v-if="col.tasks.length === 0"
                        class="rounded-xl border border-dashed border-border/80 px-2 py-6 text-center text-xs text-muted-foreground">
                        No tasks
                    </p>
                    <Button v-else-if="col.meta.current_page < col.meta.last_page" variant="outline" size="sm"
                        class="w-full text-xs" type="button" @click="loadMoreColumn(col)">
                        Load more ({{ col.meta.total - col.tasks.length }} remaining)
                    </Button>
                </div>
            </GlassCard>
        </div>
    </div>
</template>
