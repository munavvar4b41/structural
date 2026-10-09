<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { CornerDownRight, GripVertical } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import ProjectTaskController from '@/actions/App/Http/Controllers/Admin/ProjectTaskController';
import TaskCompletionReviewController from '@/actions/App/Http/Controllers/Admin/TaskCompletionReviewController';
import ConfirmDestructiveDialog from '@/components/ConfirmDestructiveDialog.vue';
import DataTable from '@/components/dashboard/DataTable.vue';
import DataTableEmptyRow from '@/components/dashboard/DataTableEmptyRow.vue';
import DataTableTd from '@/components/dashboard/DataTableTd.vue';
import DataTableTh from '@/components/dashboard/DataTableTh.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import TableRow from '@/components/dashboard/TableRow.vue';
import FormMultiSelect from '@/components/FormMultiSelect.vue';
import FormSelect from '@/components/FormSelect.vue';
import ListToolbar from '@/components/ListToolbar.vue';
import MyWorkSectionHeader from '@/components/my-work/MyWorkSectionHeader.vue';
import TableIconAction from '@/components/TableIconAction.vue';
import TaskPriorityBadge from '@/components/tasks/TaskPriorityBadge.vue';
import type { TaskPriorityBadgeData } from '@/components/tasks/TaskPriorityBadge.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    routerReloadOnly,
    stripFilterParams,
} from '@/composables/useServerFilters';
import { formatTaskMinutes } from '@/lib/formatTaskMinutes';
import { requiresPhaseSelection } from '@/lib/requirementPhaseOptions';
import { cn } from '@/lib/utils';
import {
    index as projectsIndex,
    show as projectsShow,
} from '@/routes/admin/projects/index';
import { show as requirementsShow } from '@/routes/admin/projects/requirements/index';
import {
    create as projectTasksCreate,
    edit as projectTasksEdit,
    index as projectTasksIndex,
    show as projectTasksShow,
} from '@/routes/admin/projects/tasks/index';

type UserBrief = {
    id: number;
    name: string;
    email: string;
} | null;

type TaskRow = {
    id: number;
    title: string;
    description: string | null;
    status: string;
    status_label: string;
    priority: TaskPriorityBadgeData | null;
    assignee_user_id: number | null;
    assignee: UserBrief;
    project_requirement_id: number | null;
    requirement_title: string | null | undefined;
    parent_project_task_id: number | null;
    estimated_minutes: number | null;
    phase: number | null;
    phase_label: string | null;
    display_after_at: string | null;
    notify_at: string | null;
    children_count: number;
    tree_depth: number;
    can_update: boolean;
    can_delete: boolean;
    is_assignee_only_limited: boolean;
    can_submit_task_completion: boolean;
    can_confirm_task_completion: boolean;
    estimation_source: 'transferred' | 'ad_hoc' | null;
};

type Option = { value: string; label: string };
type ReqOption = { value: number; label: string; max_generated_phase: number };
type UserOption = { value: number; label: string };

type ProjectSummary = {
    id: number;
    name: string;
    code: string | null;
    estimation_required: boolean;
};

type StatusMeta = {
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
};

type StatusSection = {
    status: string;
    label: string;
    tasks: TaskRow[];
    meta: StatusMeta;
};

const COLLAPSED_SECTIONS_KEY = 'project-tasks-collapsed-sections';
const doneStatusValue = 'done';

const page = usePage();

const props = defineProps<{
    project: ProjectSummary;
    tasks: TaskRow[];
    status_meta: Record<string, StatusMeta>;
    task_filter: string;
    filters: {
        search: string;
        assignee_id: string;
        status: string[];
        estimation_source: string;
        phase: string;
        priority: string[];
    };
    show_phase_filter: boolean;
    phase_filter_options: Option[];
    status_options: Option[];
    assignable_users: UserOption[];
    requirements: ReqOption[];
    can_create_tasks: boolean;
    can_manage_project: boolean;
    can_filter_estimation_source: boolean;
    estimation_source_options: Option[];
    priority_filter_options: Option[];
}>();

const assigneeFilter = ref(props.filters.assignee_id);
const estimationSourceFilter = ref(props.filters.estimation_source);
const phaseFilter = ref(props.filters.phase);
const dragTask = ref<TaskRow | null>(null);
const dropTargetStatus = ref<string | null>(null);
const collapsedStatuses = ref<Set<string>>(new Set());

watch(
    () => props.filters.assignee_id,
    (v) => {
        assigneeFilter.value = v;
    },
);

watch(
    () => props.filters.estimation_source,
    (v) => {
        estimationSourceFilter.value = v;
    },
);

watch(
    () => props.filters.phase,
    (v) => {
        phaseFilter.value = v;
    },
);

function collapsedStorageKey(): string {
    return `${COLLAPSED_SECTIONS_KEY}:${props.project.id}`;
}

function loadCollapsedSections(): void {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        const raw = window.localStorage.getItem(collapsedStorageKey());

        if (raw === null) {
            collapsedStatuses.value = new Set();

            return;
        }

        const parsed = JSON.parse(raw) as unknown;

        if (Array.isArray(parsed)) {
            collapsedStatuses.value = new Set(
                parsed.filter(
                    (status): status is string => typeof status === 'string',
                ),
            );

            return;
        }

        collapsedStatuses.value = new Set();
    } catch {
        collapsedStatuses.value = new Set();
    }
}

function persistCollapsedSections(): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.setItem(
        collapsedStorageKey(),
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
    return `project-tasks-section-${props.project.id}-${status}`;
}

function explicitAssignee(value: unknown): string {
    if (value === '' || value === null || value === undefined) {
        return 'all';
    }

    return String(value);
}

function explicitStatus(value: unknown): string | string[] {
    if (Array.isArray(value)) {
        return value.length === 0 ? 'all' : value.map(String);
    }

    if (value === '' || value === null || value === undefined) {
        return 'all';
    }

    return String(value);
}

function indexReturnOptions(): {
    query: Record<string, string | number | boolean | (string | number)[]>;
} {
    return {
        query: stripFilterParams({
            task_filter: props.task_filter,
            search: props.filters.search,
            assignee_id: explicitAssignee(props.filters.assignee_id),
            status: explicitStatus(props.filters.status),
            phase: props.filters.phase,
            priority: props.filters.priority,
        }),
    };
}

function editHref(task: TaskRow): string {
    return projectTasksEdit.url(
        { project: props.project.id, task: task.id },
        {
            query: {
                return: projectTasksIndex.url(
                    props.project.id,
                    indexReturnOptions(),
                ),
            },
        },
    );
}

function reloadTasks(overrides: Record<string, unknown> = {}): void {
    const merged: Record<string, unknown> = {
        task_filter: props.task_filter,
        search: props.filters.search,
        assignee_id: props.filters.assignee_id,
        status: props.filters.status,
        estimation_source: props.filters.estimation_source,
        phase: props.filters.phase,
        priority: props.filters.priority,
        ...overrides,
    };

    routerReloadOnly(
        projectTasksIndex.url(props.project.id, {
            query: stripFilterParams({
                ...merged,
                assignee_id: explicitAssignee(merged.assignee_id),
                status: explicitStatus(merged.status),
            }),
        }),
        [
            'tasks',
            'status_meta',
            'filters',
            'task_filter',
            'show_phase_filter',
            'phase_filter_options',
            'status_options',
            'assignable_users',
            'requirements',
            'can_create_tasks',
            'can_manage_project',
            'can_filter_estimation_source',
            'estimation_source_options',
            'priority_filter_options',
            'project',
        ],
    );
}

function currentPageParams(): Record<string, string> {
    const params: Record<string, string> = {};
    const url = new URL(page.url, window.location.origin);

    url.searchParams.forEach((value, key) => {
        if (key.startsWith('page_')) {
            params[key] = value;
        }
    });

    return params;
}

function loadMoreSection(section: StatusSection): void {
    if (section.meta.current_page >= section.meta.last_page) {
        return;
    }

    reloadTasks({
        ...currentPageParams(),
        [`page_${section.status}`]: section.meta.current_page + 1,
    });
}

function onPhase(v: string): void {
    reloadTasks({ phase: v });
}

function onEstimationSource(v: string): void {
    reloadTasks({ estimation_source: v });
}

function onSearch(search: string): void {
    reloadTasks({ search });
}

function onAssignee(v: string): void {
    reloadTasks({ assignee_id: v });
}

function onStatusFilter(status: string[]): void {
    reloadTasks({ status });
}

function onPriorityFilter(priority: string[]): void {
    reloadTasks({ priority });
}

defineOptions({
    layout: (pageProps: {
        project: ProjectSummary;
        can_manage_project: boolean;
    }) => ({
        breadcrumbs: [
            { title: 'Projects', href: projectsIndex.url() },
            {
                title: pageProps.project.name,
                href: projectsShow.url(pageProps.project.id),
            },
            {
                title: 'Tasks',
                href: projectTasksIndex.url(pageProps.project.id),
            },
        ],
    }),
});

const assigneeSelectOptions = computed(() =>
    props.assignable_users.map((u) => ({
        value: String(u.value),
        label: u.label,
    })),
);

const showPhaseColumn = computed(() =>
    props.requirements.some((requirement) =>
        requiresPhaseSelection(requirement.max_generated_phase),
    ),
);

const tableColumnCount = computed(() => (showPhaseColumn.value ? 8 : 7));

const sections = computed((): StatusSection[] => {
    const selected = props.filters.status;
    const options =
        selected.length > 0
            ? props.status_options.filter((option) =>
                  selected.includes(option.value),
              )
            : props.status_options;

    return options.map((option) => ({
        status: option.value,
        label: option.label,
        tasks: props.tasks.filter((task) => task.status === option.value),
        meta: props.status_meta[option.value] ?? {
            total: 0,
            current_page: 1,
            last_page: 1,
            per_page: 20,
        },
    }));
});

function statusSelectOptionsForTask(task: TaskRow): Option[] {
    if (task.is_assignee_only_limited) {
        return props.status_options.filter(
            (option) => option.value !== doneStatusValue,
        );
    }

    return props.status_options;
}

function submitForCompletionFromList(task: TaskRow): void {
    router.post(
        TaskCompletionReviewController.submit.url({
            project: props.project.id,
            task: task.id,
        }),
        {},
        { preserveScroll: true },
    );
}

function setFilter(filter: string): void {
    reloadTasks({ task_filter: filter });
}

function patchTaskStatus(task: TaskRow, status: string): void {
    if (!canDropTaskOnSection(task, status)) {
        return;
    }

    router.patch(
        ProjectTaskController.update.url({
            project: props.project.id,
            task: task.id,
        }),
        { status },
        {
            preserveScroll: true,
            onSuccess: () => {
                reloadTasks(currentPageParams());
            },
        },
    );
}

function canDropTaskOnSection(task: TaskRow, targetStatus: string): boolean {
    if (!task.can_update || task.status === targetStatus) {
        return false;
    }

    if (task.is_assignee_only_limited && targetStatus === doneStatusValue) {
        return false;
    }

    return true;
}

function onDragStart(event: DragEvent, task: TaskRow): void {
    if (!task.can_update) {
        return;
    }

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

function onSectionDragOver(event: DragEvent, section: StatusSection): void {
    if (
        dragTask.value === null ||
        !canDropTaskOnSection(dragTask.value, section.status)
    ) {
        return;
    }

    event.preventDefault();

    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }

    dropTargetStatus.value = section.status;
}

function onSectionDragLeave(section: StatusSection): void {
    if (dropTargetStatus.value === section.status) {
        dropTargetStatus.value = null;
    }
}

function onSectionDrop(event: DragEvent, section: StatusSection): void {
    event.preventDefault();

    const task = dragTask.value;

    dragTask.value = null;
    dropTargetStatus.value = null;

    if (task === null) {
        return;
    }

    patchTaskStatus(task, section.status);
}

const deleteDialogOpen = ref(false);
const taskPendingDelete = ref<TaskRow | null>(null);

function openDeleteDialog(row: TaskRow): void {
    taskPendingDelete.value = row;
    deleteDialogOpen.value = true;
}

function executeDelete(): void {
    const row = taskPendingDelete.value;

    if (row === null) {
        return;
    }

    router.delete(
        ProjectTaskController.destroy.url({
            project: props.project.id,
            task: row.id,
        }),
    );
    taskPendingDelete.value = null;
}

const deleteTaskDescription = computed(() => {
    const row = taskPendingDelete.value;

    if (row === null) {
        return '';
    }

    return `Delete "${row.title}"? This cannot be undone.`;
});

function tryOpenEditFromQuery(): void {
    const rawUrl = page.url;
    const queryPart = rawUrl.includes('?')
        ? rawUrl.slice(rawUrl.indexOf('?') + 1)
        : '';
    const params = new URLSearchParams(queryPart);
    const rawId = params.get('edit_task');

    if (rawId === null || rawId === '') {
        return;
    }

    const task = props.tasks.find((t) => String(t.id) === rawId);

    if (task === undefined || !task.can_update) {
        return;
    }

    router.visit(editHref(task), { replace: true });
}

watch(
    () => props.project.id,
    () => {
        loadCollapsedSections();
    },
);

onMounted(() => {
    loadCollapsedSections();
    tryOpenEditFromQuery();
});
</script>

<template>
    <Head :title="`Tasks · ${project.name}`" />

    <ConfirmDestructiveDialog
        v-model:open="deleteDialogOpen"
        title="Delete task?"
        :description="deleteTaskDescription"
        @confirm="executeDelete"
    />

    <div class="flex flex-col gap-8">
        <div class="flex flex-col gap-4">
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <PageHeader
                    :title="`Tasks · ${project.name}`"
                    description="Project work items; optionally link each task to a requirement or a parent task."
                />
                <div class="flex flex-wrap gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        :data-active="task_filter === 'all'"
                        :class="task_filter === 'all' ? 'border-primary' : ''"
                        type="button"
                        @click="setFilter('all')"
                    >
                        All
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        :class="
                            task_filter === 'linked' ? 'border-primary' : ''
                        "
                        type="button"
                        @click="setFilter('linked')"
                    >
                        Linked to requirement
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        :class="
                            task_filter === 'unlinked' ? 'border-primary' : ''
                        "
                        type="button"
                        @click="setFilter('unlinked')"
                    >
                        No requirement
                    </Button>
                    <Button v-if="can_create_tasks" as-child>
                        <Link :href="projectTasksCreate.url(project.id)"
                            >Add task</Link
                        >
                    </Button>
                </div>
            </div>

            <ListToolbar
                :model-value="filters.search"
                placeholder="Search title or description…"
                @update:model-value="onSearch"
            >
                <template #filters>
                    <div class="flex flex-col gap-3">
                        <div class="flex flex-wrap items-center gap-4">
                            <div class="grid gap-1">
                                <Label
                                    class="text-xs text-muted-foreground"
                                    for="filter-assignee"
                                    >Assignee</Label
                                >
                                <FormSelect
                                    id="filter-assignee"
                                    name="assignee_id"
                                    class="min-w-[12rem]"
                                    :model-value="assigneeFilter"
                                    :options="assigneeSelectOptions"
                                    placeholder="Anyone"
                                    none-label="Anyone"
                                    exclude-from-submit
                                    @update:model-value="onAssignee"
                                />
                            </div>
                            <div class="grid gap-1">
                                <Label
                                    class="text-xs text-muted-foreground"
                                    for="filter-status"
                                    >Status</Label
                                >
                                <FormMultiSelect
                                    id="filter-status"
                                    :model-value="filters.status"
                                    :options="status_options"
                                    placeholder="All statuses"
                                    menu-label="Statuses"
                                    class="min-w-[12rem]"
                                    @update:model-value="onStatusFilter"
                                />
                            </div>
                            <div class="grid gap-1">
                                <Label
                                    class="text-xs text-muted-foreground"
                                    for="filter-priority"
                                    >Priority</Label
                                >
                                <FormMultiSelect
                                    id="filter-priority"
                                    :model-value="filters.priority"
                                    :options="priority_filter_options"
                                    placeholder="All priorities"
                                    menu-label="Priorities"
                                    class="min-w-[12rem]"
                                    @update:model-value="onPriorityFilter"
                                />
                            </div>
                            <div
                                v-if="can_filter_estimation_source"
                                class="grid gap-1"
                            >
                                <Label
                                    class="text-xs text-muted-foreground"
                                    for="filter-estimation-source"
                                    >Estimation source</Label
                                >
                                <FormSelect
                                    id="filter-estimation-source"
                                    name="estimation_source"
                                    :model-value="estimationSourceFilter"
                                    :options="estimation_source_options"
                                    placeholder="Any"
                                    none-label="Any"
                                    exclude-from-submit
                                    @update:model-value="onEstimationSource"
                                />
                            </div>
                            <div v-if="show_phase_filter" class="grid gap-1">
                                <Label
                                    class="text-xs text-muted-foreground"
                                    for="filter-phase"
                                    >Phase</Label
                                >
                                <FormSelect
                                    id="filter-phase"
                                    name="phase"
                                    class="min-w-[10rem]"
                                    :model-value="phaseFilter"
                                    :options="phase_filter_options"
                                    placeholder="Any phase"
                                    none-label="Any phase"
                                    exclude-from-submit
                                    @update:model-value="onPhase"
                                />
                            </div>
                        </div>
                    </div>
                </template>
            </ListToolbar>
        </div>

        <div class="flex flex-col gap-8">
            <section
                v-for="section in sections"
                :key="section.status"
                :class="
                    cn(
                        'flex flex-col gap-3 rounded-2xl p-1 transition-colors',
                        dropTargetStatus === section.status &&
                            'ring-2 ring-primary/50',
                    )
                "
                @dragover="onSectionDragOver($event, section)"
                @dragleave="onSectionDragLeave(section)"
                @drop="onSectionDrop($event, section)"
            >
                <MyWorkSectionHeader
                    class="px-1"
                    :label="section.label"
                    :shown="section.tasks.length"
                    :total="section.meta.total"
                    :collapsed="isSectionCollapsed(section.status)"
                    :section-id="sectionContentId(section.status)"
                    collapsible
                    @toggle="toggleSectionCollapse(section.status)"
                />

                <div
                    v-show="!isSectionCollapsed(section.status)"
                    :id="sectionContentId(section.status)"
                >
                    <DataTable min-width="960px">
                        <thead>
                            <tr
                                class="border-b border-border/60 bg-muted/40 backdrop-blur-sm"
                            >
                                <DataTableTh class="w-10" />
                                <DataTableTh>Title</DataTableTh>
                                <DataTableTh>Status</DataTableTh>
                                <DataTableTh>Assignee</DataTableTh>
                                <DataTableTh>Requirement</DataTableTh>
                                <DataTableTh v-if="showPhaseColumn"
                                    >Phase</DataTableTh
                                >
                                <DataTableTh>Estimate</DataTableTh>
                                <DataTableTh class="text-right"
                                    >Actions</DataTableTh
                                >
                            </tr>
                        </thead>
                        <tbody>
                            <TableRow
                                v-for="task in section.tasks"
                                :key="task.id"
                                :class="
                                    cn(dragTask?.id === task.id && 'opacity-50')
                                "
                            >
                                <DataTableTd
                                    label=""
                                    class="w-10 px-2 align-middle"
                                >
                                    <button
                                        v-if="task.can_update"
                                        type="button"
                                        class="cursor-grab touch-none rounded p-1 text-muted-foreground hover:bg-muted active:cursor-grabbing"
                                        draggable="true"
                                        aria-label="Drag to change status"
                                        @dragstart="onDragStart($event, task)"
                                        @dragend="onDragEnd"
                                    >
                                        <GripVertical class="size-4" />
                                    </button>
                                </DataTableTd>
                                <DataTableTd label="Title" class="align-middle">
                                    <div
                                        class="flex min-w-0 items-center gap-1.5"
                                        :style="{
                                            paddingLeft: `${task.tree_depth * 1.25}rem`,
                                        }"
                                    >
                                        <CornerDownRight
                                            v-if="task.tree_depth > 0"
                                            class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                        <div
                                            class="flex min-w-0 flex-1 flex-col justify-center"
                                        >
                                            <span
                                                v-if="
                                                    task.estimation_source ===
                                                    'transferred'
                                                "
                                                class="mb-0.5 w-fit rounded bg-success/15 px-1.5 py-0.5 text-xs font-medium text-success"
                                            >
                                                From estimation
                                            </span>
                                            <span
                                                v-else-if="
                                                    task.estimation_source ===
                                                    'ad_hoc'
                                                "
                                                class="mb-0.5 w-fit rounded bg-info/15 px-1.5 py-0.5 text-xs font-medium text-info"
                                            >
                                                New task
                                            </span>
                                            <TaskPriorityBadge
                                                class="mb-1"
                                                :priority="task.priority"
                                            />
                                            <Button
                                                variant="link"
                                                class="h-auto w-full min-w-0 justify-start p-0 font-medium text-foreground"
                                                as-child
                                            >
                                                <Link
                                                    class="line-clamp-2 block text-left break-words text-foreground hover:underline"
                                                    :title="task.title"
                                                    :href="
                                                        projectTasksShow.url({
                                                            project: project.id,
                                                            task: task.id,
                                                        })
                                                    "
                                                >
                                                    {{ task.title }}
                                                </Link>
                                            </Button>
                                            <span
                                                v-if="task.children_count > 0"
                                                class="mt-0.5 block text-xs text-muted-foreground"
                                            >
                                                ({{
                                                    task.children_count
                                                }}
                                                subtasks)
                                            </span>
                                        </div>
                                    </div>
                                </DataTableTd>
                                <DataTableTd
                                    label="Status"
                                    class="align-middle"
                                >
                                    <FormSelect
                                        v-if="task.can_update"
                                        :id="`list-st-${task.id}`"
                                        :name="`list-status-${task.id}`"
                                        class="min-w-[9rem] text-xs"
                                        :model-value="task.status"
                                        required
                                        placeholder="Status"
                                        :options="
                                            statusSelectOptionsForTask(task)
                                        "
                                        exclude-from-submit
                                        @update:model-value="
                                            patchTaskStatus(task, $event)
                                        "
                                    />
                                    <span
                                        v-else
                                        class="text-muted-foreground"
                                        >{{ task.status_label }}</span
                                    >
                                </DataTableTd>
                                <DataTableTd
                                    label="Assignee"
                                    class="align-middle text-muted-foreground"
                                >
                                    {{ task.assignee?.name ?? '—' }}
                                </DataTableTd>
                                <DataTableTd
                                    label="Requirement"
                                    class="align-middle"
                                >
                                    <template
                                        v-if="task.project_requirement_id"
                                    >
                                        <Button
                                            variant="link"
                                            class="h-auto p-0"
                                            as-child
                                        >
                                            <Link
                                                :href="
                                                    requirementsShow.url({
                                                        project: project.id,
                                                        requirement:
                                                            task.project_requirement_id,
                                                    })
                                                "
                                            >
                                                {{
                                                    task.requirement_title ??
                                                    'View'
                                                }}
                                            </Link>
                                        </Button>
                                    </template>
                                    <template v-else>—</template>
                                </DataTableTd>
                                <DataTableTd
                                    v-if="showPhaseColumn"
                                    label="Phase"
                                    class="align-middle text-muted-foreground"
                                >
                                    {{ task.phase_label ?? '—' }}
                                </DataTableTd>
                                <DataTableTd
                                    label="Estimate"
                                    class="align-middle text-muted-foreground"
                                >
                                    {{
                                        formatTaskMinutes(
                                            task.estimated_minutes,
                                        )
                                    }}
                                </DataTableTd>
                                <DataTableTd
                                    label="Actions"
                                    class="text-right align-middle"
                                >
                                    <div
                                        class="flex flex-wrap justify-end gap-1"
                                    >
                                        <TableIconAction
                                            v-if="
                                                task.can_submit_task_completion
                                            "
                                            variant="secondary"
                                            icon="check-circle"
                                            label="Submit for completion"
                                            @click="
                                                submitForCompletionFromList(
                                                    task,
                                                )
                                            "
                                        />
                                        <TableIconAction
                                            v-if="task.can_update"
                                            icon="pencil"
                                            label="Edit"
                                            :href="editHref(task)"
                                        />
                                        <TableIconAction
                                            v-if="task.can_delete"
                                            icon="trash"
                                            label="Delete"
                                            destructive
                                            @click="openDeleteDialog(task)"
                                        />
                                    </div>
                                </DataTableTd>
                            </TableRow>
                            <DataTableEmptyRow
                                v-if="section.tasks.length === 0"
                                :colspan="tableColumnCount"
                                message="No tasks — drop here to move"
                            />
                        </tbody>
                    </DataTable>

                    <div
                        v-if="
                            section.tasks.length > 0 &&
                            section.meta.current_page < section.meta.last_page
                        "
                        class="flex justify-center pt-3"
                    >
                        <Button
                            variant="outline"
                            size="sm"
                            class="w-full max-w-xs text-xs"
                            type="button"
                            @click="loadMoreSection(section)"
                        >
                            Load more ({{
                                section.meta.total - section.tasks.length
                            }}
                            remaining)
                        </Button>
                    </div>
                </div>

                <p
                    v-if="
                        isSectionCollapsed(section.status) &&
                        dragTask !== null &&
                        dropTargetStatus === section.status
                    "
                    class="px-1 text-xs text-primary"
                >
                    Release to move here
                </p>
            </section>
        </div>
    </div>
</template>
