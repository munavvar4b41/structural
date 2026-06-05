<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { CornerDownRight } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import ProjectTaskController from '@/actions/App/Http/Controllers/Admin/ProjectTaskController';
import TaskCompletionReviewController from '@/actions/App/Http/Controllers/Admin/TaskCompletionReviewController';
import ConfirmDestructiveDialog from '@/components/ConfirmDestructiveDialog.vue';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import InputError from '@/components/InputError.vue';
import ListToolbar from '@/components/ListToolbar.vue';
import MultiSelectDropdown from '@/components/MultiSelectDropdown.vue';
import TaskFormSelect from '@/components/TaskFormSelect.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { routerReloadOnly, stripFilterParams } from '@/composables/useServerFilters';
import { buildPhaseSelectOptions, requiresPhaseSelection } from '@/lib/requirementPhaseOptions';
import { formatTaskMinutes } from '@/lib/formatTaskMinutes';
import { index as projectsIndex, show as projectsShow } from '@/routes/admin/projects/index';
import {
    index as requirementsIndex,
    show as requirementsShow,
} from '@/routes/admin/projects/requirements/index';
import {
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
    };
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

function reloadTasks(overrides: Record<string, unknown> = {}): void {
    routerReloadOnly(
        projectTasksIndex.url(props.project.id, {
            query: stripFilterParams({
                task_filter: props.task_filter,
                search: props.filters.search,
                assignee_id: props.filters.assignee_id,
                status: props.filters.status,
                estimation_source: props.filters.estimation_source,
                ...overrides,
            }),
        }),
        [
            'tasks',
            'filters',
            'task_filter',
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

const createOpen = ref(false);
const editOpen = ref(false);
const editingTask = ref<TaskRow | null>(null);

const createStatus = ref('to_do');
const createAssignee = ref('');
const createRequirement = ref('');
const createPhase = ref('1');
const createParent = ref('');
const createDisplayAfterAt = ref('');
const createNotifyAt = ref('');

const editStatus = ref('to_do');
const editAssignee = ref('');
const editRequirement = ref('');
const editPhase = ref('1');
const editParent = ref('');
const editDisplayAfterAt = ref('');
const editNotifyAt = ref('');

function toDatetimeLocalValue(value: string | null): string {
    if (value === null) {
        return '';
    }

    const asDate = new Date(value);
   
    if (Number.isNaN(asDate.getTime())) {
        return '';
    }

    const local = new Date(asDate.getTime() - asDate.getTimezoneOffset() * 60000);

    return local.toISOString().slice(0, 16);
}

watch(createOpen, (open) => {
    if (open) {
        createStatus.value = 'to_do';
        createAssignee.value = '';
        createRequirement.value = '';
        createPhase.value = '1';
        createParent.value = '';
        createDisplayAfterAt.value = '';
        createNotifyAt.value = '';
    }
});

watch([editOpen, editingTask], () => {
    if (editOpen.value && editingTask.value) {
        const t = editingTask.value;
        editStatus.value = t.status;
        editAssignee.value = t.assignee_user_id !== null ? String(t.assignee_user_id) : '';
        editRequirement.value =
            t.project_requirement_id !== null ? String(t.project_requirement_id) : '';
        editPhase.value = t.phase !== null ? String(t.phase) : '1';
        editParent.value =
            t.parent_project_task_id !== null ? String(t.parent_project_task_id) : '';
        editDisplayAfterAt.value = toDatetimeLocalValue(t.display_after_at);
        editNotifyAt.value = toDatetimeLocalValue(t.notify_at);
    }
});

const statusSelectOptions = computed(() =>
    props.status_options.map((o) => ({ value: o.value, label: o.label })),
);

const editStatusSelectOptions = computed(() => {
    const base = statusSelectOptions.value;
    const row = editingTask.value;

    if (row !== null && row.is_assignee_only_limited) {
        return base.filter((o) => o.value !== 'done');
    }

    return base;
});

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

const assigneeSelectOptions = computed(() =>
    props.assignable_users.map((u) => ({ value: String(u.value), label: u.label })),
);

const requirementSelectOptions = computed(() =>
    props.requirements.map((r) => ({ value: String(r.value), label: r.label })),
);

function requirementMaxPhase(requirementId: string): number {
    if (requirementId === '') {
        return 1;
    }

    const requirement = props.requirements.find((r) => String(r.value) === requirementId);

    return requirement?.max_generated_phase ?? 1;
}

const createPhaseSelectOptions = computed(() =>
    buildPhaseSelectOptions(requirementMaxPhase(createRequirement.value)),
);

const editPhaseSelectOptions = computed(() =>
    buildPhaseSelectOptions(requirementMaxPhase(editRequirement.value)),
);

const showCreatePhaseField = computed(
    () => createRequirement.value !== '' && requiresPhaseSelection(requirementMaxPhase(createRequirement.value)),
);

const showEditPhaseField = computed(
    () => editRequirement.value !== '' && requiresPhaseSelection(requirementMaxPhase(editRequirement.value)),
);

const showPhaseColumn = computed(() =>
    props.requirements.some((requirement) => requiresPhaseSelection(requirement.max_generated_phase)),
);

watch(createRequirement, () => {
    createPhase.value = '1';
});

watch(editRequirement, () => {
    editPhase.value = '1';
});

function formatParentTaskLabel(task: TaskRow): string {
    if (task.tree_depth <= 0) {
        return task.title;
    }

    return `${'— '.repeat(task.tree_depth)}${task.title}`;
}

const taskChildrenByParentId = computed(() => {
    const byParent = new Map<number, number[]>();

    for (const task of props.tasks) {
        if (task.parent_project_task_id === null) {
            continue;
        }

        const children = byParent.get(task.parent_project_task_id) ?? [];
        children.push(task.id);
        byParent.set(task.parent_project_task_id, children);
    }

    return byParent;
});

function collectDescendantTaskIds(taskId: number): Set<number> {
    const descendantIds = new Set<number>();
    const queue = [...(taskChildrenByParentId.value.get(taskId) ?? [])];

    while (queue.length > 0) {
        const currentId = queue.shift();

        if (currentId === undefined || descendantIds.has(currentId)) {
            continue;
        }

        descendantIds.add(currentId);

        const children = taskChildrenByParentId.value.get(currentId) ?? [];
        queue.push(...children);
    }

    return descendantIds;
}

const parentSelectOptions = computed(() =>
    props.tasks.map((task) => ({
        value: String(task.id),
        label: formatParentTaskLabel(task),
    })),
);

const parentSelectOptionsForEdit = computed(() => {
    if (editingTask.value === null) {
        return parentSelectOptions.value;
    }

    const blockedIds = collectDescendantTaskIds(editingTask.value.id);
    blockedIds.add(editingTask.value.id);

    return props.tasks
        .filter((task) => !blockedIds.has(task.id))
        .map((task) => ({
            value: String(task.id),
            label: formatParentTaskLabel(task),
        }));
});

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

function openEdit(task: TaskRow): void {
    editingTask.value = task;
    editOpen.value = true;
}

function closeEdit(): void {
    editOpen.value = false;
    editingTask.value = null;
}

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

    openEdit(task);

    const indexOptions = {
        query: stripFilterParams({
            task_filter: props.task_filter,
            search: props.filters.search,
            assignee_id: props.filters.assignee_id,
            status: props.filters.status,
        }),
    };

    router.get(projectTasksIndex.url(props.project.id, indexOptions), {}, { replace: true, preserveState: true, preserveScroll: true });
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
                    <Button v-if="can_create_tasks" type="button" @click="createOpen = true">
                        Add task
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
                                <TaskFormSelect id="filter-assignee" name="assignee_id" class="min-w-[12rem]"
                                    :model-value="assigneeFilter" :options="assigneeSelectOptions" placeholder="Anyone"
                                    none-label="Anyone" exclude-from-submit @update:model-value="onAssignee" />
                            </div>
                            <div class="grid gap-1">
                                <Label class="text-xs text-muted-foreground" for="filter-status">Status</Label>
                                <MultiSelectDropdown id="filter-status" :model-value="filters.status"
                                    :options="status_options" placeholder="All statuses" menu-label="Statuses"
                                    @update:model-value="onStatusFilter" />
                            </div>
                            <div v-if="can_filter_estimation_source" class="grid gap-1">
                                <Label class="text-xs text-muted-foreground" for="filter-estimation-source">Estimation source</Label>
                                <TaskFormSelect
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
                        </div>
                    </div>
                </template>
            </ListToolbar>
        </div>

        <Dialog v-model:open="createOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Add task</DialogTitle>
                    <DialogDescription>
                        <span v-if="project.estimation_required">Time estimate (minutes) is required.</span>
                        <span v-else>Optional time estimate in minutes.</span>
                    </DialogDescription>
                </DialogHeader>
                <Form v-bind="ProjectTaskController.store.form({ project: project.id })" class="grid gap-4"
                    @success="createOpen = false" v-slot="{ errors, processing }">
                    <div class="grid gap-2">
                        <Label for="create-title">Title</Label>
                        <Input id="create-title" name="title" type="text" required />
                        <InputError :message="errors.title" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="create-description">Description</Label>
                        <textarea id="create-description" name="description" rows="3"
                            class="w-full rounded-xl border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30" />
                        <InputError :message="errors.description" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="create-status">Status</Label>
                        <TaskFormSelect id="create-status" name="status" v-model="createStatus" required
                            placeholder="Status" :options="statusSelectOptions" />
                        <InputError :message="errors.status" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="create-assignee">Assignee</Label>
                        <TaskFormSelect id="create-assignee" name="assignee_user_id" v-model="createAssignee"
                            none-label="Unassigned" placeholder="Unassigned" :options="assigneeSelectOptions" />
                        <InputError :message="errors.assignee_user_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="create-requirement">Requirement</Label>
                        <TaskFormSelect id="create-requirement" name="project_requirement_id"
                            v-model="createRequirement" placeholder="None" :options="requirementSelectOptions" />
                        <InputError :message="errors.project_requirement_id" />
                    </div>
                    <div v-if="showCreatePhaseField" class="grid gap-2">
                        <Label for="create-phase">Phase</Label>
                        <TaskFormSelect
                            id="create-phase"
                            name="phase"
                            v-model="createPhase"
                            required
                            placeholder="Phase"
                            :options="createPhaseSelectOptions"
                        />
                        <InputError :message="errors.phase" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="create-parent">Parent task (subtask)</Label>
                        <TaskFormSelect id="create-parent" name="parent_project_task_id" v-model="createParent"
                            placeholder="None" :options="parentSelectOptions" />
                        <InputError :message="errors.parent_project_task_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="create-estimate">Estimate (minutes)</Label>
                        <Input id="create-estimate" name="estimated_minutes" type="number" min="1" step="1"
                            :required="project.estimation_required" />
                        <InputError :message="errors.estimated_minutes" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="create-display-after-at">Display after</Label>
                        <Input id="create-display-after-at" name="display_after_at" type="datetime-local"
                            v-model="createDisplayAfterAt" />
                        <InputError :message="errors.display_after_at" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="create-notify-at">Notify task at</Label>
                        <Input id="create-notify-at" name="notify_at" type="datetime-local" v-model="createNotifyAt" />
                        <InputError :message="errors.notify_at" />
                    </div>
                    <DialogFooter class="gap-3">
                        <Button type="button" variant="outline" @click="createOpen = false">
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="processing">Create</Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>

        <Dialog :open="editOpen" @update:open="(v: boolean) => !v && closeEdit()">
            <DialogContent v-if="editingTask" class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Edit task</DialogTitle>
                    <DialogDescription>{{ editingTask.title }}</DialogDescription>
                </DialogHeader>
                <Form :key="editingTask.id" v-bind="ProjectTaskController.update.form({
                    project: project.id,
                    task: editingTask.id,
                })
                    " class="grid gap-4" @success="closeEdit()" v-slot="{ errors, processing }">
                    <div class="grid gap-2">
                        <Label for="edit-title">Title</Label>
                        <Input id="edit-title" name="title" type="text" required :default-value="editingTask.title" />
                        <InputError :message="errors.title" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit-description">Description</Label>
                        <textarea id="edit-description" name="description" rows="3" v-model="editingTask.description"
                            class="w-full rounded-xl border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30" />
                        <InputError :message="errors.description" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit-status">Status</Label>
                        <TaskFormSelect id="edit-status" name="status" v-model="editStatus" required
                            placeholder="Status" :options="editStatusSelectOptions" />
                        <InputError :message="errors.status" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit-assignee">Assignee</Label>
                        <TaskFormSelect id="edit-assignee" name="assignee_user_id" v-model="editAssignee"
                            none-label="Unassigned" placeholder="Unassigned" :options="assigneeSelectOptions" />
                        <InputError :message="errors.assignee_user_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit-requirement">Requirement</Label>
                        <TaskFormSelect id="edit-requirement" name="project_requirement_id" v-model="editRequirement"
                            placeholder="None" :options="requirementSelectOptions" />
                        <InputError :message="errors.project_requirement_id" />
                    </div>
                    <div v-if="showEditPhaseField" class="grid gap-2">
                        <Label for="edit-phase">Phase</Label>
                        <TaskFormSelect
                            id="edit-phase"
                            name="phase"
                            v-model="editPhase"
                            required
                            placeholder="Phase"
                            :options="editPhaseSelectOptions"
                        />
                        <InputError :message="errors.phase" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit-parent">Parent task</Label>
                        <TaskFormSelect id="edit-parent" name="parent_project_task_id" v-model="editParent"
                            placeholder="None" :options="parentSelectOptionsForEdit" />
                        <InputError :message="errors.parent_project_task_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit-estimate">Estimate (minutes)</Label>
                        <Input id="edit-estimate" name="estimated_minutes" type="number" min="1" step="1"
                            :required="project.estimation_required" :default-value="editingTask.estimated_minutes !== null
                                ? String(editingTask.estimated_minutes)
                                : ''
                                " />
                        <InputError :message="errors.estimated_minutes" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit-display-after-at">Display after</Label>
                        <Input id="edit-display-after-at" name="display_after_at" type="datetime-local"
                            v-model="editDisplayAfterAt" />
                        <InputError :message="errors.display_after_at" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit-notify-at">Notify task at</Label>
                        <Input id="edit-notify-at" name="notify_at" type="datetime-local" v-model="editNotifyAt" />
                        <InputError :message="errors.notify_at" />
                    </div>
                    <DialogFooter class="gap-3">
                        <Button type="button" variant="outline" @click="closeEdit()">
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="processing">Save</Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>

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
                                        <span
                                            v-if="task.estimation_source === 'transferred'"
                                            class="mb-0.5 w-fit rounded bg-emerald-500/15 px-1.5 py-0.5 text-xs font-medium text-emerald-800 dark:text-emerald-200"
                                        >
                                            From estimation
                                        </span>
                                        <span
                                            v-else-if="task.estimation_source === 'ad_hoc'"
                                            class="mb-0.5 w-fit rounded bg-sky-500/15 px-1.5 py-0.5 text-xs font-medium text-sky-800 dark:text-sky-200"
                                        >
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
                                    <Button v-if="task.can_update" variant="outline" size="sm" type="button"
                                        @click="openEdit(task)">
                                        Edit
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
