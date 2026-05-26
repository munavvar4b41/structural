<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { CheckCircle, Columns3, Eye, GripVertical, List } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import ProjectTaskController from '@/actions/App/Http/Controllers/Admin/ProjectTaskController';
import TaskCompletionReviewController from '@/actions/App/Http/Controllers/Admin/TaskCompletionReviewController';
import DataTable from '@/components/dashboard/DataTable.vue';
import DataTableTd from '@/components/dashboard/DataTableTd.vue';
import DataTableTh from '@/components/dashboard/DataTableTh.vue';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import MyWorkSectionHeader from '@/components/my-work/MyWorkSectionHeader.vue';
import MyWorkTaskCard from '@/components/my-work/MyWorkTaskCard.vue';
import type { MyWorkTaskCardData } from '@/components/my-work/MyWorkTaskCard.vue';
import TaskFormSelect from '@/components/TaskFormSelect.vue';
import TaskShowPanel from '@/components/tasks/TaskShowPanel.vue';
import TaskTimerButton from '@/components/TaskTimerButton.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import Tooltip from '@/components/ui/tooltip/Tooltip.vue';
import TooltipContent from '@/components/ui/tooltip/TooltipContent.vue';
import TooltipTrigger from '@/components/ui/tooltip/TooltipTrigger.vue';
import { formatTaskMinutes } from '@/lib/formatTaskMinutes';
import { cn } from '@/lib/utils';
import { index as myWorkIndex } from '@/routes/admin/my-work/index';
import { index as projectsIndex } from '@/routes/admin/projects/index';
import { show as projectTasksShow } from '@/routes/admin/projects/tasks/index';
import type { TaskShowPayload } from '@/types/projectTaskShow';

type TaskCard = MyWorkTaskCardData;

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

type ViewMode = 'list' | 'board';

const MY_WORK_VIEW_KEY = 'my-work-view';
const MY_WORK_COLLAPSED_KEY = 'my-work-collapsed-sections';

const props = defineProps<{
    columns: Column[];
    status_options: StatusOption[];
    project_options: ProjectOption[];
    filters: { project_id: number | null };
    task_preview?: TaskShowPayload | null;
}>();

const page = usePage();

const projectValue = ref(
    props.filters.project_id !== null ? String(props.filters.project_id) : '',
);

const taskPreviewOpen = ref(false);
const taskPreviewLoading = ref(false);

const viewMode = ref<ViewMode>('board');
const dragTask = ref<TaskCard | null>(null);
const dropTargetStatus = ref<string | null>(null);
const collapsedStatuses = ref<Set<string>>(new Set());

function loadCollapsedSections(): void {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        const raw = window.localStorage.getItem(MY_WORK_COLLAPSED_KEY);

        if (raw === null) {
            return;
        }

        const parsed = JSON.parse(raw) as unknown;

        if (Array.isArray(parsed)) {
            collapsedStatuses.value = new Set(
                parsed.filter((s): s is string => typeof s === 'string'),
            );
        }
    } catch {
        collapsedStatuses.value = new Set();
    }
}

function persistCollapsedSections(): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.setItem(
        MY_WORK_COLLAPSED_KEY,
        JSON.stringify([...collapsedStatuses.value]),
    );
}

function isSectionCollapsed(status: string): boolean {
    return collapsedStatuses.value.has(status);
}

function toggleSectionCollapse(status: string): void {
    const next = new Set(collapsedStatuses.value);

    if (next.has(status)) {
        next.delete(status);
    } else {
        next.add(status);
    }

    collapsedStatuses.value = next;
    persistCollapsedSections();
}

function sectionContentId(status: string): string {
    return `my-work-section-${status}`;
}

function readStoredViewMode(): ViewMode {
    if (typeof window === 'undefined') {
        return 'list';
    }

    const stored = window.localStorage.getItem(MY_WORK_VIEW_KEY);

    return stored === 'list' ? 'list' : 'board';
}

function setViewMode(mode: ViewMode): void {
    viewMode.value = mode;

    if (typeof window !== 'undefined') {
        window.localStorage.setItem(MY_WORK_VIEW_KEY, mode);
    }
}

watch(
    () => props.filters.project_id,
    () => {
        projectValue.value =
            props.filters.project_id !== null
                ? String(props.filters.project_id)
                : '';
    },
);

watch(
    () => props.task_preview,
    (preview) => {
        if (preview) {
            taskPreviewOpen.value = true;
            taskPreviewLoading.value = false;
        }
    },
);

const projectSelectOptions = computed(() => [
    { value: '', label: 'All projects' },
    ...props.project_options.map((o) => ({
        value: String(o.value),
        label: o.label,
    })),
]);

const taskPreviewShowUrl = computed(() => {
    const preview = props.task_preview;

    if (preview === null || preview === undefined) {
        return null;
    }

    return projectTasksShow.url({
        project: preview.project.id,
        task: preview.task.id,
    });
});

const doneStatusValue = 'done';

function parseTaskIdFromUrl(): number | null {
    const url = new URL(page.url, window.location.origin);
    const id = Number.parseInt(url.searchParams.get('task_id') ?? '', 10);

    return id > 0 ? id : null;
}

function boardQuery(extra: Record<string, string | number> = {}): Record<string, string | number> {
    const query: Record<string, string | number> = { ...extra };

    if (props.filters.project_id !== null) {
        query.project_id = props.filters.project_id;
    }

    const url = new URL(page.url, window.location.origin);
    url.searchParams.forEach((value, key) => {
        if (key.startsWith('page_') && !(key in query)) {
            query[key] = value;
        }
    });

    return query;
}

function openTaskPreview(task: TaskCard): void {
    taskPreviewOpen.value = true;
    taskPreviewLoading.value = true;

    router.get(myWorkIndex.url(), boardQuery({ task_id: task.id }), {
        preserveState: true,
        preserveScroll: true,
        only: ['task_preview'],
        onFinish: () => {
            taskPreviewLoading.value = false;
        },
    });
}

function openTaskPreviewById(taskId: number): void {
    taskPreviewLoading.value = true;

    router.get(myWorkIndex.url(), boardQuery({ task_id: taskId }), {
        preserveState: true,
        preserveScroll: true,
        only: ['task_preview'],
        onFinish: () => {
            taskPreviewLoading.value = false;
        },
    });
}

function closeTaskPreview(): void {
    taskPreviewOpen.value = false;
    taskPreviewLoading.value = false;

    router.get(myWorkIndex.url(), boardQuery(), {
        preserveState: true,
        preserveScroll: true,
        only: ['task_preview'],
    });
}

function onTaskPreviewOpenChange(open: boolean): void {
    if (!open) {
        closeTaskPreview();
    }
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
                router.reload({ only: ['columns', 'task_preview'] });
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
                router.reload({ only: ['columns', 'task_preview'] });
            },
        },
    );
}

function canDropTaskOnColumn(task: TaskCard, targetStatus: string): boolean {
    if (task.status === targetStatus) {
        return false;
    }

    if (task.is_assignee_only_limited && targetStatus === doneStatusValue) {
        return false;
    }

    return true;
}

function onDragStart(event: DragEvent, task: TaskCard): void {
    dragTask.value = task;

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(task.id));
    }
}

function onDragEnd(): void {
    dragTask.value = null;
    dropTargetStatus.value = null;
}

function onColumnDragOver(event: DragEvent, col: Column): void {
    if (dragTask.value === null) {
        return;
    }

    if (!canDropTaskOnColumn(dragTask.value, col.status)) {
        return;
    }

    event.preventDefault();

    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }

    dropTargetStatus.value = col.status;
}

function onColumnDragLeave(col: Column): void {
    if (dropTargetStatus.value === col.status) {
        dropTargetStatus.value = null;
    }
}

function onColumnDrop(event: DragEvent, col: Column): void {
    event.preventDefault();

    const task = dragTask.value;

    dragTask.value = null;
    dropTargetStatus.value = null;

    if (task === null || !canDropTaskOnColumn(task, col.status)) {
        return;
    }

    patchTaskStatus(task, col.status);
}

onMounted(() => {
    loadCollapsedSections();
    viewMode.value = readStoredViewMode();

    const url = new URL(page.url, window.location.origin);
    const viewParam = url.searchParams.get('view');

    if (viewParam === 'board' || viewParam === 'list') {
        setViewMode(viewParam);
    }

    const taskId = parseTaskIdFromUrl();

    if (taskId !== null) {
        taskPreviewOpen.value = true;

        if (props.task_preview === null || props.task_preview === undefined) {
            taskPreviewLoading.value = true;
        }
    }
});
</script>

<template>

    <Head title="My work" />

    <Dialog :open="taskPreviewOpen" @update:open="onTaskPreviewOpenChange">
        <DialogScrollContent class="max-h-[90vh] overflow-y-auto sm:max-w-5xl">
            <DialogHeader>
                <DialogTitle>{{ task_preview?.task.title ?? 'Task details' }}</DialogTitle>
                <DialogDescription v-if="task_preview">
                    {{ task_preview.project.name }}
                    <span v-if="task_preview.project.code">({{ task_preview.project.code }})</span>
                </DialogDescription>
            </DialogHeader>

            <div v-if="taskPreviewLoading" class="flex flex-col gap-4 py-2">
                <div class="h-8 w-2/3 animate-pulse rounded-md bg-muted" />
                <div class="h-24 animate-pulse rounded-xl bg-muted/60" />
                <div class="h-32 animate-pulse rounded-xl bg-muted/60" />
                <div class="h-40 animate-pulse rounded-xl bg-muted/60" />
            </div>

            <TaskShowPanel v-else-if="task_preview" embedded :project="task_preview.project" :task="task_preview.task"
                :can_manage_project="task_preview.can_manage_project" :checklist="task_preview.checklist"
                :time_tracking="task_preview.time_tracking" @close="closeTaskPreview"
                @open-task="openTaskPreviewById" />

            <DialogFooter v-if="task_preview && taskPreviewShowUrl" class="gap-3 sm:justify-between">
                <Button variant="outline" as-child>
                    <Link :href="taskPreviewShowUrl">Open full page</Link>
                </Button>
            </DialogFooter>
        </DialogScrollContent>
    </Dialog>

    <div class="flex flex-col gap-8">
        <PageHeader title="My work"
            description="Tasks assigned to you, grouped by status. Drag tasks between sections or use the status control to update status." />

        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="grid gap-1">
                <Label class="text-xs text-muted-foreground" for="my-work-project-filter">
                    Project
                </Label>
                <TaskFormSelect id="my-work-project-filter" name="project_id" class="min-w-[12rem] sm:min-w-[16rem]"
                    :model-value="projectValue" :options="projectSelectOptions" placeholder="All projects"
                    none-label="All projects" exclude-from-submit @update:model-value="applyProjectFilter" />
            </div>

            <div class="inline-flex rounded-lg border border-border/80 bg-muted/30 p-0.5" role="group"
                aria-label="View mode">
                <Button type="button" size="sm" :variant="viewMode === 'board' ? 'default' : 'ghost'" class="gap-1.5"
                    @click="setViewMode('board')">
                    <Columns3 class="size-4" />
                    Board
                </Button>
                <Button type="button" size="sm" :variant="viewMode === 'list' ? 'default' : 'ghost'" class="gap-1.5"
                    @click="setViewMode('list')">
                    <List class="size-4" />
                    List
                </Button>
            </div>
        </div>

        <!-- List view -->
        <div v-if="viewMode === 'list'" class="flex flex-col gap-8">
            <section v-for="col in columns" :key="col.status" :class="cn(
                'flex flex-col gap-3 rounded-2xl p-1 transition-colors',
                dropTargetStatus === col.status && 'ring-2 ring-primary/50',
            )
                " @dragover="onColumnDragOver($event, col)" @dragleave="onColumnDragLeave(col)"
                @drop="onColumnDrop($event, col)">
                <MyWorkSectionHeader class="px-1" :label="col.label" :shown="col.tasks.length" :total="col.meta.total"
                    :collapsed="isSectionCollapsed(col.status)" :section-id="sectionContentId(col.status)"
                    @toggle="toggleSectionCollapse(col.status)" collapsible />

                <div v-show="!isSectionCollapsed(col.status)" :id="sectionContentId(col.status)"
                    class="flex flex-col gap-3">
                    <DataTable v-if="col.tasks.length > 0">
                        <thead>
                            <tr class="border-b border-border/60 bg-muted/40 backdrop-blur-sm">
                                <DataTableTh class="w-10" />
                                <DataTableTh>Task</DataTableTh>
                                <DataTableTh>Project</DataTableTh>
                                <DataTableTh>Requirement</DataTableTh>
                                <DataTableTh>Estimate</DataTableTh>
                                <DataTableTh>Status</DataTableTh>
                                <DataTableTh class="text-right">Actions</DataTableTh>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="task in col.tasks" :key="task.id" :class="cn(
                                'border-b border-border/40 transition-colors even:bg-muted/15 hover:bg-muted/30',
                                dragTask?.id === task.id && 'opacity-50',
                            )
                                ">
                                <DataTableTd label="" class="w-10 align-top px-2">
                                    <button type="button"
                                        class="cursor-grab touch-none rounded p-1 text-muted-foreground hover:bg-muted active:cursor-grabbing"
                                        draggable="true" aria-label="Drag to change status"
                                        @dragstart="onDragStart($event, task)" @dragend="onDragEnd">
                                        <GripVertical class="size-4" />
                                    </button>
                                </DataTableTd>
                                <DataTableTd label="Task" class="align-top font-medium">
                                    <button type="button" class="text-left hover:underline"
                                        @click="openTaskPreview(task)">
                                        {{ task.title }}
                                    </button>
                                </DataTableTd>
                                <DataTableTd label="Project" class="align-top text-muted-foreground">
                                    {{ task.project.name }}
                                    <span v-if="task.project.code" class="text-xs">
                                        ({{ task.project.code }})
                                    </span>
                                </DataTableTd>
                                <DataTableTd label="Requirement" class="align-top text-muted-foreground">
                                    <span v-if="task.requirement" :title="task.requirement.title">
                                        {{ task.requirement.title }}
                                    </span>
                                    <span v-else>—</span>
                                </DataTableTd>
                                <DataTableTd label="Estimate" class="align-top text-muted-foreground">
                                    {{ formatTaskMinutes(task.estimated_minutes) }}
                                </DataTableTd>
                                <DataTableTd label="Status" class="align-top">
                                    <TaskFormSelect :id="`list-st-${task.id}`" :name="`list-status-${task.id}`"
                                        class="min-w-[9rem] text-xs" :model-value="task.status" required
                                        placeholder="Status" :options="statusSelectOptionsForTask(task)"
                                        exclude-from-submit @update:model-value="patchTaskStatus(task, $event)" />
                                </DataTableTd>
                                <DataTableTd label="Actions" class="text-right align-top">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <Button v-if="task.can_submit_task_completion" variant="secondary"
                                                    size="sm" class="h-8 text-xs" type="button"
                                                    @click="submitForCompletion(task)">
                                                    <CheckCircle class="size-3.5" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>Submit for completion</TooltipContent>
                                        </Tooltip>
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <TaskTimerButton :project-id="task.project_id" :task-id="task.id"
                                                    size="sm" :timer-today-seconds="task.timer_today_seconds"
                                                    :timer-state="task.timer_state" />
                                            </TooltipTrigger>
                                            <TooltipContent>Timer</TooltipContent>
                                        </Tooltip>
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <Button variant="outline" size="sm" class="h-8 text-xs" type="button"
                                                    @click="openTaskPreview(task)">
                                                    <Eye class="size-3.5" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>View task</TooltipContent>
                                        </Tooltip>
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <Button variant="outline" size="sm" class="h-8 text-xs" as-child>
                                                    <Link :href="task.project_tasks_url">
                                                        <List class="size-3.5" />
                                                    </Link>
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>Task list</TooltipContent>
                                        </Tooltip>
                                    </div>
                                </DataTableTd>
                            </tr>
                        </tbody>
                    </DataTable>

                    <p v-else
                        class="min-h-[4rem] rounded-xl border border-dashed border-border/80 px-2 py-6 text-center text-xs text-muted-foreground">
                        No tasks — drop here to move
                    </p>

                    <Button v-if="col.tasks.length > 0 && col.meta.current_page < col.meta.last_page" variant="outline"
                        size="sm" class="w-full max-w-xs text-xs" type="button" @click="loadMoreColumn(col)">
                        Load more ({{ col.meta.total - col.tasks.length }} remaining)
                    </Button>
                </div>

                <p v-if="isSectionCollapsed(col.status) && dragTask !== null && dropTargetStatus === col.status"
                    class="px-1 text-xs text-primary">
                    Release to move here
                </p>
            </section>
        </div>

        <!-- Board view (default) -->
        <div v-if="viewMode === 'board'" class="flex gap-4 overflow-x-auto pb-2">
            <GlassCard v-for="col in columns" :key="col.status" :class="cn(
                'flex w-86 shrink-0 flex-col gap-3 p-4 transition-colors',
                dropTargetStatus === col.status && 'ring-2 ring-primary/50',
            )
                " @dragover="onColumnDragOver($event, col)" @dragleave="onColumnDragLeave(col)"
                @drop="onColumnDrop($event, col)">
                <MyWorkSectionHeader :label="col.label" :shown="col.tasks.length" :total="col.meta.total"
                    :collapsed="isSectionCollapsed(col.status)" :section-id="sectionContentId(col.status)"
                    @toggle="toggleSectionCollapse(col.status)" />
                <div v-show="!isSectionCollapsed(col.status)" :id="sectionContentId(col.status)"
                    class="flex flex-col gap-2">
                    <MyWorkTaskCard v-for="task in col.tasks" :key="task.id" :task="task"
                        :status-options="statusSelectOptionsForTask(task)" :show-status-select="false" draggable
                        :class="dragTask?.id === task.id && 'opacity-50'" @preview="openTaskPreview"
                        @status-change="patchTaskStatus" @submit-completion="submitForCompletion"
                        @drag-start="onDragStart" @drag-end="onDragEnd" />
                    <p v-if="col.tasks.length === 0"
                        class="rounded-xl border border-dashed border-border/80 px-2 py-6 text-center text-xs text-muted-foreground">
                        No tasks
                    </p>
                    <Button v-else-if="col.meta.current_page < col.meta.last_page" variant="outline" size="sm"
                        class="w-full text-xs" type="button" @click="loadMoreColumn(col)">
                        Load more ({{ col.meta.total - col.tasks.length }} remaining)
                    </Button>
                </div>

                <p v-if="isSectionCollapsed(col.status) && dragTask !== null && dropTargetStatus === col.status"
                    class="text-xs text-primary">
                    Release to move here
                </p>
            </GlassCard>
        </div>
    </div>
</template>
