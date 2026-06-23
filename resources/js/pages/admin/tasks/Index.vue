<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CornerDownRight } from 'lucide-vue-next';
import { computed } from 'vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import ListToolbar from '@/components/ListToolbar.vue';
import FormMultiSelect from '@/components/FormMultiSelect.vue';
import FormSelect from '@/components/FormSelect.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { routerReloadOnly, stripFilterParams } from '@/composables/useServerFilters';
import { formatTaskMinutes } from '@/lib/formatTaskMinutes';
import { index as projectsIndex } from '@/routes/admin/projects/index';
import {
    create as projectTasksCreate,
    show as projectTasksShow,
} from '@/routes/admin/projects/tasks/index';
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

const assigneeSelectOptions = computed(() =>
    props.assignable_users.map((option) => ({ value: String(option.value), label: option.label })),
);
const projectSelectOptions = computed(() =>
    props.projects.map((option) => ({ value: String(option.value), label: option.label })),
);

const createTaskHref = computed(() =>
    props.selected_project !== null
        ? projectTasksCreate.url(props.selected_project.id)
        : null,
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
                <Button
                    v-if="createTaskHref !== null && can_create_tasks_for_selected_project"
                    as-child
                >
                    <Link :href="createTaskHref">Add task</Link>
                </Button>
                <Button
                    v-else
                    type="button"
                    disabled
                >
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
                        <td colspan="6" class="px-4 py-8 text-center text-muted-foreground">
                            No tasks match the current filters.
                        </td>
                    </tr>
                </tbody>
            </DataTable>
        </div>
    </div>
</template>
