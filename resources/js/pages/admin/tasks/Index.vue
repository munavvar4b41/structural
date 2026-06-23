<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { CornerDownRight } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import ProjectTaskController from '@/actions/App/Http/Controllers/Admin/ProjectTaskController';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import InputError from '@/components/InputError.vue';
import ListToolbar from '@/components/ListToolbar.vue';
import FormMultiSelect from '@/components/FormMultiSelect.vue';
import FormSelect from '@/components/FormSelect.vue';
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
import { formatTaskMinutes } from '@/lib/formatTaskMinutes';
import { index as projectsIndex } from '@/routes/admin/projects/index';
import { show as projectTasksShow } from '@/routes/admin/projects/tasks/index';
import { index as tasksIndex } from '@/routes/admin/tasks/index';
import TableRow from '@/components/dashboard/TableRow.vue';
import DataTable from '@/components/dashboard/DataTable.vue';

type Option = {
    value: string | number;
    label: string;
};

type UserBrief = {
    id: number;
    name: string;
    email: string;
} | null;

type SelectedProject = {
    id: number;
    name: string;
    code: string | null;
    estimation_required: boolean;
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
    display_after_at: string | null;
    notify_at: string | null;
    children_count: number;
    tree_depth: number;
    project: {
        id: number;
        name: string;
        code: string | null;
        estimation_required: boolean;
    };
    can_update: boolean;
    can_delete: boolean;
};

const props = defineProps<{
    projects: Option[];
    selected_project: SelectedProject;
    tasks: TaskRow[];
    task_filter: string;
    filters: {
        project_id: string;
        search: string;
        assignee_id: string;
        status: string[];
    };
    status_options: { value: string; label: string }[];
    assignable_users: Option[];
    requirements: Option[];
    parent_tasks: Option[];
    can_create_tasks_for_selected_project: boolean;
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Projects', href: projectsIndex.url() },
            { title: 'Tasks', href: tasksIndex.url() },
        ],
    }),
});

const createOpen = ref(false);
const createStatus = ref('to_do');
const createAssignee = ref('');
const createRequirement = ref('');
const createParent = ref('');
const createDisplayAfterAt = ref('');
const createNotifyAt = ref('');

watch(createOpen, (open) => {
    if (open) {
        createStatus.value = 'to_do';
        createAssignee.value = '';
        createRequirement.value = '';
        createParent.value = '';
        createDisplayAfterAt.value = '';
        createNotifyAt.value = '';
    }
});

const statusSelectOptions = computed(() =>
    props.status_options.map((option) => ({ value: String(option.value), label: option.label })),
);
const assigneeSelectOptions = computed(() =>
    props.assignable_users.map((option) => ({ value: String(option.value), label: option.label })),
);
const requirementSelectOptions = computed(() =>
    props.requirements.map((option) => ({ value: String(option.value), label: option.label })),
);
const parentSelectOptions = computed(() =>
    props.parent_tasks.map((option) => ({ value: String(option.value), label: option.label })),
);
const projectSelectOptions = computed(() =>
    props.projects.map((option) => ({ value: String(option.value), label: option.label })),
);

function reloadTasks(overrides: Record<string, unknown> = {}): void {
    routerReloadOnly(
        tasksIndex.url({
            query: stripFilterParams({
                project_id: props.filters.project_id,
                task_filter: props.task_filter,
                search: props.filters.search,
                assignee_id: props.filters.assignee_id,
                status: props.filters.status,
                ...overrides,
            }),
        }),
        [
            'projects',
            'selected_project',
            'tasks',
            'task_filter',
            'filters',
            'status_options',
            'assignable_users',
            'requirements',
            'parent_tasks',
            'can_create_tasks_for_selected_project',
        ],
    );
}

function onProject(v: string): void {
    reloadTasks({ project_id: v, assignee_id: '', status: [], task_filter: 'all' });
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

function setFilter(filter: string): void {
    reloadTasks({ task_filter: filter });
}

function formatProjectLabel(task: TaskRow): string {
    if (task.project.code !== null && task.project.code !== '') {
        return `${task.project.name} (${task.project.code})`;
    }

    return task.project.name;
}

function ensureSelectedProjectForCreate(): void {
    if (props.selected_project === null) {
        return;
    }

    createOpen.value = true;
}

function onCreateSuccess(): void {
    createOpen.value = false;
    reloadTasks();
}
</script>

<template>

    <Head title="Tasks" />

    <div class="flex flex-col gap-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <PageHeader title="Tasks"
                description="Browse tasks across projects. Select a project to create new tasks." />
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" size="sm" :class="task_filter === 'all' ? 'border-primary' : ''" type="button"
                    @click="setFilter('all')">
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
                <Button type="button" :disabled="selected_project === null || !can_create_tasks_for_selected_project"
                    @click="ensureSelectedProjectForCreate()">
                    Add task
                </Button>
            </div>
        </div>

        <ListToolbar :model-value="filters.search" placeholder="Search title or description…"
            @update:model-value="onSearch">
            <template #filters>
                <div class="flex flex-wrap items-end gap-4">
                    <div class="grid gap-1">
                        <Label class="text-xs text-muted-foreground" for="filter-project">Project</Label>
                        <FormSelect id="filter-project" name="project_id" class="min-w-[16rem]"
                            :model-value="filters.project_id" :options="projectSelectOptions" placeholder="All projects"
                            none-label="All projects" exclude-from-submit @update:model-value="onProject" />
                    </div>
                    <div class="grid gap-1">
                        <Label class="text-xs text-muted-foreground" for="filter-assignee">Assignee</Label>
                        <FormSelect id="filter-assignee" name="assignee_id" class="min-w-[12rem]"
                            :model-value="filters.assignee_id" :options="assigneeSelectOptions" placeholder="Anyone"
                            none-label="Anyone" exclude-from-submit @update:model-value="onAssignee" />
                    </div>
                    <div class="grid gap-1">
                        <Label class="text-xs text-muted-foreground" for="filter-status">Status</Label>
                        <FormMultiSelect id="filter-status" :model-value="filters.status" :options="status_options"
                            placeholder="All statuses" menu-label="Statuses" class="min-w-[12rem]"
                            @update:model-value="onStatusFilter" />
                    </div>
                </div>
            </template>
        </ListToolbar>

        <Dialog v-model:open="createOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Add task</DialogTitle>
                    <DialogDescription>
                        <span v-if="selected_project === null">Select a project first.</span>
                        <span v-else-if="selected_project.estimation_required">
                            Time estimate (minutes) is required for {{ selected_project.name }}.
                        </span>
                        <span v-else>Optional time estimate in minutes for {{ selected_project.name }}.</span>
                    </DialogDescription>
                </DialogHeader>
                <Form v-if="selected_project !== null"
                    v-bind="ProjectTaskController.store.form({ project: selected_project.id })" class="grid gap-4"
                    @success="onCreateSuccess" v-slot="{ errors, processing }">
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
                        <FormSelect id="create-status" name="status" v-model="createStatus" required
                            placeholder="Status" :options="statusSelectOptions" />
                        <InputError :message="errors.status" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="create-assignee">Assignee</Label>
                        <FormSelect id="create-assignee" name="assignee_user_id" v-model="createAssignee"
                            none-label="Unassigned" placeholder="Unassigned" :options="assigneeSelectOptions" />
                        <InputError :message="errors.assignee_user_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="create-requirement">Requirement</Label>
                        <FormSelect id="create-requirement" name="project_requirement_id" v-model="createRequirement"
                            placeholder="None" :options="requirementSelectOptions" />
                        <InputError :message="errors.project_requirement_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="create-parent">Parent task (subtask)</Label>
                        <FormSelect id="create-parent" name="parent_project_task_id" v-model="createParent"
                            placeholder="None" :options="parentSelectOptions" />
                        <InputError :message="errors.parent_project_task_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="create-estimate">Estimate (minutes)</Label>
                        <Input id="create-estimate" name="estimated_minutes" type="number" min="1" step="1"
                            :required="selected_project.estimation_required" />
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
                        <Button type="button" variant="outline" @click="createOpen = false">Cancel</Button>
                        <Button type="submit" :disabled="processing">Create</Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>

        <div>
            <h2 class="text-lg font-semibold">Task list</h2>
            <p class="text-sm text-muted-foreground mb-4">
                Tasks across visible projects. Select a project to create a new task.
            </p>
            <DataTable data-responsive-table>
                <thead class="border-b bg-muted/40">
                    <tr>
                        <th class="w-[30%] px-4 py-3 font-medium">Title</th>
                        <th class="px-4 py-3 font-medium">Project</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Assignee</th>
                        <th class="min-w-[25%] px-4 py-3 font-medium">Requirement</th>
                        <th class="px-4 py-3 font-medium">Estimate</th>
                    </tr>
                </thead>
                <tbody>
                    <TableRow v-for="task in tasks" :key="task.id">
                        <td data-label="Title" class="px-4 py-3 text-muted-foreground"
                            :style="{ paddingLeft: `calc(0.75rem + ${task.tree_depth} * 1rem)` }">
                            <div class="flex min-w-0 items-center gap-1.5">
                                <CornerDownRight v-if="task.tree_depth > 0"
                                    class="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                                <div class="min-w-0 flex-1">
                                    <Button variant="link"
                                        class="h-auto w-full min-w-0 justify-start p-0 font-medium text-foreground"
                                        as-child>
                                        <Link
                                            class="block text-left text-foreground line-clamp-2 break-words hover:underline"
                                            :title="task.title"
                                            :href="projectTasksShow.url({ project: task.project.id, task: task.id })">
                                            {{ task.title }}
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </td>
                        <td data-label="Project" class="px-4 py-3 text-muted-foreground">
                            {{ formatProjectLabel(task) }}
                        </td>
                        <td data-label="Status" class="px-4 py-3 text-muted-foreground">
                            {{ task.status_label }}
                        </td>
                        <td data-label="Assignee" class="px-4 py-3 text-muted-foreground">
                            {{ task.assignee?.name ?? '—' }}
                        </td>
                        <td data-label="Requirement" class="px-4 py-3 text-muted-foreground">
                            {{ task.requirement_title ?? '—' }}
                        </td>
                        <td data-label="Estimate" class="px-4 py-3 text-muted-foreground">
                            {{ formatTaskMinutes(task.estimated_minutes) }}
                        </td>
                    </TableRow>
                    <tr v-if="tasks.length === 0">
                        <td data-label="" colspan="6" class="px-4 py-8 text-center text-muted-foreground">
                            No tasks match this filter.
                        </td>
                    </tr>
                </tbody>
            </DataTable>
        </div>
    </div>
</template>
