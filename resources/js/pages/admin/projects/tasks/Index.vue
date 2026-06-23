<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { CornerDownRight } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import ProjectTaskController from '@/actions/App/Http/Controllers/Admin/ProjectTaskController';
import TaskCompletionReviewController from '@/actions/App/Http/Controllers/Admin/TaskCompletionReviewController';
import ConfirmDestructiveDialog from '@/components/ConfirmDestructiveDialog.vue';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import ListToolbar from '@/components/ListToolbar.vue';
import FormMultiSelect from '@/components/FormMultiSelect.vue';
import FormSelect from '@/components/FormSelect.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { routerReloadOnly, stripFilterParams } from '@/composables/useServerFilters';
import { requiresPhaseSelection } from '@/lib/requirementPhaseOptions';
import { formatTaskMinutes } from '@/lib/formatTaskMinutes';
import { index as projectsIndex, show as projectsShow } from '@/routes/admin/projects/index';
import {
    index as requirementsIndex,
    show as requirementsShow,
} from '@/routes/admin/projects/requirements/index';
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

const page = usePage();

const props = defineProps<{
    project: ProjectSummary;
    tasks: TaskRow[];
    task_filter: string;
    filters: {
        search: string;
        assignee_id: string;
        status: string[];
        estimation_source: string;
        phase: string;
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
}>();

const assigneeFilter = ref(props.filters.assignee_id);
const estimationSourceFilter = ref(props.filters.estimation_source);
const phaseFilter = ref(props.filters.phase);

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

function reloadTasks(overrides: Record<string, unknown> = {}): void {
    routerReloadOnly(
        projectTasksIndex.url(props.project.id, {
            query: stripFilterParams({
                task_filter: props.task_filter,
                search: props.filters.search,
                assignee_id: props.filters.assignee_id,
                status: props.filters.status,
                estimation_source: props.filters.estimation_source,
                phase: props.filters.phase,
                ...overrides,
            }),
        }),
        [
            'tasks',
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
            'project',
        ],
    );
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

defineOptions({
    layout: (pageProps: { project: ProjectSummary; can_manage_project: boolean }) => ({
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
    props.assignable_users.map((u) => ({ value: String(u.value), label: u.label })),
);

const showPhaseColumn = computed(() =>
    props.requirements.some((requirement) => requiresPhaseSelection(requirement.max_generated_phase)),
);

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
    const queryPart = rawUrl.includes('?') ? rawUrl.slice(rawUrl.indexOf('?') + 1) : '';
    const params = new URLSearchParams(queryPart);
    const rawId = params.get('edit_task');

    if (rawId === null || rawId === '') {
        return;
    }

    const task = props.tasks.find((t) => String(t.id) === rawId);

    if (task === undefined || !task.can_update) {
        return;
    }

    const indexOptions = {
        query: stripFilterParams({
            task_filter: props.task_filter,
            search: props.filters.search,
            assignee_id: props.filters.assignee_id,
            status: props.filters.status,
            phase: props.filters.phase,
        }),
    };

    router.visit(
        projectTasksEdit.url(
            { project: props.project.id, task: task.id },
            {
                query: {
                    return: projectTasksIndex.url(props.project.id, indexOptions),
                },
            },
        ),
        { replace: true },
    );
}

onMounted(() => {
    tryOpenEditFromQuery();
});
</script>

<template>

    <Head :title="`Tasks · ${project.name}`" />

    <ConfirmDestructiveDialog v-model:open="deleteDialogOpen" title="Delete task?" :description="deleteTaskDescription"
        @confirm="executeDelete" />

    <div class="flex flex-col gap-8">
        <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <PageHeader :title="`Tasks · ${project.name}`"
                    description="Project work items; optionally link each task to a requirement or a parent task." />
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" size="sm" :data-active="task_filter === 'all'"
                        :class="task_filter === 'all' ? 'border-primary' : ''" type="button" @click="setFilter('all')">
                        All
                    </Button>
                    <Button variant="outline" size="sm" :class="task_filter === 'linked' ? 'border-primary' : ''"
                        type="button" @click="setFilter('linked')">
                        Linked to requirement
                    </Button>
                    <Button variant="outline" size="sm" :class="task_filter === 'unlinked' ? 'border-primary' : ''"
                        type="button" @click="setFilter('unlinked')">
                        No requirement
                    </Button>
                    <Button v-if="can_create_tasks" as-child>
                        <Link :href="projectTasksCreate.url(project.id)">Add task</Link>
                    </Button>
                </div>
            </div>

            <ListToolbar :model-value="filters.search" placeholder="Search title or description…"
                @update:model-value="onSearch">
                <template #filters>
                    <div class="flex flex-col gap-3">
                        <div class="flex flex-wrap items-center gap-4">
                            <div class="grid gap-1">
                                <Label class="text-xs text-muted-foreground" for="filter-assignee">Assignee</Label>
                                <FormSelect id="filter-assignee" name="assignee_id" class="min-w-[12rem]"
                                    :model-value="assigneeFilter" :options="assigneeSelectOptions" placeholder="Anyone"
                                    none-label="Anyone" exclude-from-submit @update:model-value="onAssignee" />
                            </div>
                            <div class="grid gap-1">
                                <Label class="text-xs text-muted-foreground" for="filter-status">Status</Label>
                                <FormMultiSelect id="filter-status" :model-value="filters.status"
                                    :options="status_options" placeholder="All statuses" menu-label="Statuses"
                                    class="min-w-[12rem]" @update:model-value="onStatusFilter" />
                            </div>
                            <div v-if="can_filter_estimation_source" class="grid gap-1">
                                <Label class="text-xs text-muted-foreground" for="filter-estimation-source">Estimation
                                    source</Label>
                                <FormSelect id="filter-estimation-source" name="estimation_source"
                                    :model-value="estimationSourceFilter" :options="estimation_source_options"
                                    placeholder="Any" none-label="Any" exclude-from-submit
                                    @update:model-value="onEstimationSource" />
                            </div>
                            <div v-if="show_phase_filter" class="grid gap-1">
                                <Label class="text-xs text-muted-foreground" for="filter-phase">Phase</Label>
                                <FormSelect id="filter-phase" name="phase" class="min-w-[10rem]"
                                    :model-value="phaseFilter" :options="phase_filter_options" placeholder="Any phase"
                                    none-label="Any phase" exclude-from-submit @update:model-value="onPhase" />
                            </div>
                        </div>
                    </div>
                </template>
            </ListToolbar>
        </div>

        <GlassCard>
            <div class="mb-6 space-y-1">
                <h2 class="text-lg font-semibold">Task list</h2>
                <p class="text-sm text-muted-foreground">
                    Subtasks are shown under their parent; estimates are shown as hours and minutes.
                </p>
            </div>
            <div class="md:overflow-x-auto">
                <table data-responsive-table
                    class="data-table-responsive w-full table-fixed text-left text-sm md:min-w-[720px]"
                    style="--data-table-min-width: 720px">
                    <thead class="border-b bg-muted/40">
                        <tr>
                            <th class="w-[30%] px-4 py-3 font-medium">Title</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Assignee</th>
                            <th class="min-w-[25%] px-4 py-3 font-medium">Requirement</th>
                            <th v-if="showPhaseColumn" class="px-4 py-3 font-medium">Phase</th>
                            <th class="px-4 py-3 font-medium">Estimate</th>
                            <th class="px-4 py-3 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="task in tasks" :key="task.id" class="border-b border-border/60 last:border-0">
                            <td data-label="Title" class="max-w-0 px-4 py-3 align-middle" :style="{
                                paddingLeft: `calc(0.75rem + ${task.tree_depth} * 1.25rem)`,
                            }">
                                <div class="flex min-w-0 items-center gap-1.5">
                                    <CornerDownRight v-if="task.tree_depth > 0"
                                        class="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                                    <div class="min-w-0 flex-1 flex flex-col justify-center">
                                        <span v-if="task.estimation_source === 'transferred'"
                                            class="mb-0.5 w-fit rounded bg-emerald-500/15 px-1.5 py-0.5 text-xs font-medium text-emerald-800 dark:text-emerald-200">
                                            From estimation
                                        </span>
                                        <span v-else-if="task.estimation_source === 'ad_hoc'"
                                            class="mb-0.5 w-fit rounded bg-sky-500/15 px-1.5 py-0.5 text-xs font-medium text-sky-800 dark:text-sky-200">
                                            New task
                                        </span>
                                        <Button variant="link"
                                            class="h-auto w-full min-w-0 justify-start p-0 font-medium text-foreground"
                                            as-child>
                                            <Link
                                                class="block text-left text-foreground line-clamp-2 break-words hover:underline"
                                                :title="task.title" :href="projectTasksShow.url({
                                                    project: project.id,
                                                    task: task.id,
                                                })
                                                    ">
                                                {{ task.title }}
                                            </Link>
                                        </Button>
                                        <span v-if="task.children_count > 0"
                                            class="mt-0.5 block text-xs text-muted-foreground">
                                            ({{ task.children_count }} subtasks)
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <td data-label="Status" class="px-4 py-3 text-muted-foreground">{{ task.status_label }}</td>
                            <td data-label="Assignee" class="px-4 py-3 text-muted-foreground">
                                {{ task.assignee?.name ?? '—' }}
                            </td>
                            <td data-label="Requirement" class="px-4 py-3">
                                <template v-if="task.project_requirement_id">
                                    <Button variant="link" class="h-auto p-0" as-child>
                                        <Link :href="requirementsShow.url({
                                            project: project.id,
                                            requirement: task.project_requirement_id,
                                        })
                                            ">
                                            {{ task.requirement_title ?? 'View' }}
                                        </Link>
                                    </Button>
                                </template>
                                <template v-else>—</template>
                            </td>
                            <td v-if="showPhaseColumn" data-label="Phase" class="px-4 py-3 text-muted-foreground">
                                {{ task.phase_label ?? '—' }}
                            </td>
                            <td data-label="Estimate" class="px-4 py-3 text-muted-foreground">
                                {{ formatTaskMinutes(task.estimated_minutes) }}
                            </td>
                            <td data-label="Actions" class="px-4 py-3 text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <Button v-if="task.can_submit_task_completion" variant="secondary" size="sm"
                                        type="button" @click="submitForCompletionFromList(task)">
                                        Submit for completion
                                    </Button>
                                    <Button v-if="task.can_update" variant="outline" size="sm" as-child>
                                        <Link :href="projectTasksEdit.url({
                                            project: project.id,
                                            task: task.id,
                                        }, {
                                            query: {
                                                return: projectTasksIndex.url(project.id, {
                                                    query: stripFilterParams({
                                                        task_filter,
                                                        search: filters.search,
                                                        assignee_id: filters.assignee_id,
                                                        status: filters.status,
                                                        phase: filters.phase,
                                                    }),
                                                }),
                                            },
                                        })">
                                            Edit
                                        </Link>
                                    </Button>
                                    <Button v-if="task.can_delete" variant="outline" size="sm"
                                        class="text-destructive hover:bg-destructive/10" type="button"
                                        @click="openDeleteDialog(task)">
                                        Delete
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="tasks.length === 0">
                            <td data-label="" colspan="6" class="px-4 py-8 text-center text-muted-foreground">
                                No tasks match this filter.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </GlassCard>
    </div>
</template>
