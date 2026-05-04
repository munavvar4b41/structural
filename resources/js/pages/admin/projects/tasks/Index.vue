<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { CornerDownRight } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import ProjectTaskController from '@/actions/App/Http/Controllers/Admin/ProjectTaskController';
import ConfirmDestructiveDialog from '@/components/ConfirmDestructiveDialog.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import TaskFormSelect from '@/components/TaskFormSelect.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatTaskMinutes } from '@/lib/formatTaskMinutes';
import { edit as projectsEdit, index as projectsIndex } from '@/routes/admin/projects/index';
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
    children_count: number;
    tree_depth: number;
    can_update: boolean;
    can_delete: boolean;
};

type Option = { value: string; label: string };
type ReqOption = { value: number; label: string };
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
    status_options: Option[];
    assignable_users: UserOption[];
    requirements: ReqOption[];
    can_create_tasks: boolean;
    can_manage_project: boolean;
}>();

defineOptions({
    layout: (pageProps: { project: ProjectSummary; can_manage_project: boolean }) => ({
        breadcrumbs: [
            { title: 'Projects', href: projectsIndex.url() },
            {
                title: pageProps.project.name,
                href: pageProps.can_manage_project
                    ? projectsEdit.url(pageProps.project.id)
                    : requirementsIndex.url(pageProps.project.id),
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
const createParent = ref('');

const editStatus = ref('to_do');
const editAssignee = ref('');
const editRequirement = ref('');
const editParent = ref('');

watch(createOpen, (open) => {
    if (open) {
        createStatus.value = 'to_do';
        createAssignee.value = '';
        createRequirement.value = '';
        createParent.value = '';
    }
});

watch([editOpen, editingTask], () => {
    if (editOpen.value && editingTask.value) {
        const t = editingTask.value;
        editStatus.value = t.status;
        editAssignee.value = t.assignee_user_id !== null ? String(t.assignee_user_id) : '';
        editRequirement.value =
            t.project_requirement_id !== null ? String(t.project_requirement_id) : '';
        editParent.value =
            t.parent_project_task_id !== null ? String(t.parent_project_task_id) : '';
    }
});

const statusSelectOptions = computed(() =>
    props.status_options.map((o) => ({ value: o.value, label: o.label })),
);

const assigneeSelectOptions = computed(() =>
    props.assignable_users.map((u) => ({ value: String(u.value), label: u.label })),
);

const requirementSelectOptions = computed(() =>
    props.requirements.map((r) => ({ value: String(r.value), label: r.label })),
);

const parentSelectOptions = computed(() =>
    rootTasksForParent.value.map((t) => ({ value: String(t.id), label: t.title })),
);

const parentSelectOptionsForEdit = computed(() =>
    rootTasksForParent.value
        .filter((t) => editingTask.value === null || t.id !== editingTask.value.id)
        .map((t) => ({ value: String(t.id), label: t.title })),
);

function setFilter(filter: string): void {
    router.get(
        projectTasksIndex.url(props.project.id, {
            query: { task_filter: filter },
        }),
        {},
        { preserveState: true, preserveScroll: true },
    );
}

const rootTasksForParent = computed(() =>
    props.tasks.filter((t) => t.parent_project_task_id === null),
);

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

    const indexOptions =
        props.task_filter !== 'all'
            ? { query: { task_filter: props.task_filter } }
            : undefined;

    router.get(projectTasksIndex.url(props.project.id, indexOptions), {}, { replace: true, preserveState: true, preserveScroll: true });
}

onMounted(() => {
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
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <Heading
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
                    :class="task_filter === 'linked' ? 'border-primary' : ''"
                    type="button"
                    @click="setFilter('linked')"
                >
                    Linked to requirement
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    :class="task_filter === 'unlinked' ? 'border-primary' : ''"
                    type="button"
                    @click="setFilter('unlinked')"
                >
                    No requirement
                </Button>
                <Button v-if="can_create_tasks" type="button" @click="createOpen = true">
                    Add task
                </Button>
            </div>
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
                <Form
                    v-bind="ProjectTaskController.store.form({ project: project.id })"
                    class="grid gap-4"
                    @success="createOpen = false"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="create-title">Title</Label>
                        <Input id="create-title" name="title" type="text" required />
                        <InputError :message="errors.title" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="create-description">Description</Label>
                        <textarea
                            id="create-description"
                            name="description"
                            rows="3"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                        />
                        <InputError :message="errors.description" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="create-status">Status</Label>
                        <TaskFormSelect
                            id="create-status"
                            name="status"
                            v-model="createStatus"
                            required
                            placeholder="Status"
                            :options="statusSelectOptions"
                        />
                        <InputError :message="errors.status" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="create-assignee">Assignee</Label>
                        <TaskFormSelect
                            id="create-assignee"
                            name="assignee_user_id"
                            v-model="createAssignee"
                            none-label="Unassigned"
                            placeholder="Unassigned"
                            :options="assigneeSelectOptions"
                        />
                        <InputError :message="errors.assignee_user_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="create-requirement">Requirement</Label>
                        <TaskFormSelect
                            id="create-requirement"
                            name="project_requirement_id"
                            v-model="createRequirement"
                            placeholder="None"
                            :options="requirementSelectOptions"
                        />
                        <InputError :message="errors.project_requirement_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="create-parent">Parent task (subtask)</Label>
                        <TaskFormSelect
                            id="create-parent"
                            name="parent_project_task_id"
                            v-model="createParent"
                            placeholder="None"
                            :options="parentSelectOptions"
                        />
                        <InputError :message="errors.parent_project_task_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="create-estimate">Estimate (minutes)</Label>
                        <Input
                            id="create-estimate"
                            name="estimated_minutes"
                            type="number"
                            min="1"
                            step="1"
                            :required="project.estimation_required"
                        />
                        <InputError :message="errors.estimated_minutes" />
                    </div>
                    <DialogFooter class="gap-2 sm:gap-0">
                        <Button type="button" variant="outline" @click="createOpen = false">
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="processing">Create</Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>

        <Dialog :open="editOpen" @update:open="(v: boolean) => !v && closeEdit()">
            <DialogContent
                v-if="editingTask"
                class="max-h-[90vh] overflow-y-auto sm:max-w-lg"
            >
                <DialogHeader>
                    <DialogTitle>Edit task</DialogTitle>
                    <DialogDescription>{{ editingTask.title }}</DialogDescription>
                </DialogHeader>
                <Form
                    :key="editingTask.id"
                    v-bind="
                        ProjectTaskController.update.form({
                            project: project.id,
                            task: editingTask.id,
                        })
                    "
                    class="grid gap-4"
                    @success="closeEdit()"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="edit-title">Title</Label>
                        <Input
                            id="edit-title"
                            name="title"
                            type="text"
                            required
                            :default-value="editingTask.title"
                        />
                        <InputError :message="errors.title" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit-description">Description</Label>
                        <textarea
                            id="edit-description"
                            name="description"
                            rows="3"
                            :default-value="editingTask.description ?? ''"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                        />
                        <InputError :message="errors.description" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit-status">Status</Label>
                        <TaskFormSelect
                            id="edit-status"
                            name="status"
                            v-model="editStatus"
                            required
                            placeholder="Status"
                            :options="statusSelectOptions"
                        />
                        <InputError :message="errors.status" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit-assignee">Assignee</Label>
                        <TaskFormSelect
                            id="edit-assignee"
                            name="assignee_user_id"
                            v-model="editAssignee"
                            none-label="Unassigned"
                            placeholder="Unassigned"
                            :options="assigneeSelectOptions"
                        />
                        <InputError :message="errors.assignee_user_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit-requirement">Requirement</Label>
                        <TaskFormSelect
                            id="edit-requirement"
                            name="project_requirement_id"
                            v-model="editRequirement"
                            placeholder="None"
                            :options="requirementSelectOptions"
                        />
                        <InputError :message="errors.project_requirement_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit-parent">Parent task</Label>
                        <TaskFormSelect
                            id="edit-parent"
                            name="parent_project_task_id"
                            v-model="editParent"
                            placeholder="None"
                            :options="parentSelectOptionsForEdit"
                        />
                        <InputError :message="errors.parent_project_task_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit-estimate">Estimate (minutes)</Label>
                        <Input
                            id="edit-estimate"
                            name="estimated_minutes"
                            type="number"
                            min="1"
                            step="1"
                            :required="project.estimation_required"
                            :default-value="
                                editingTask.estimated_minutes !== null
                                    ? String(editingTask.estimated_minutes)
                                    : ''
                            "
                        />
                        <InputError :message="errors.estimated_minutes" />
                    </div>
                    <DialogFooter class="gap-2 sm:gap-0">
                        <Button type="button" variant="outline" @click="closeEdit()">
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="processing">Save</Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>

        <Card>
            <CardHeader>
                <CardTitle>Task list</CardTitle>
                <CardDescription>
                    Subtasks are shown under their parent; estimates are shown as hours and minutes.
                </CardDescription>
            </CardHeader>
            <CardContent class="overflow-x-auto p-0 sm:p-6">
                <table class="w-full min-w-[720px] table-fixed text-left text-sm">
                    <thead class="border-b bg-muted/40">
                        <tr>
                            <th class="w-[38%] px-4 py-3 font-medium">Title</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Assignee</th>
                            <th class="px-4 py-3 font-medium">Requirement</th>
                            <th class="px-4 py-3 font-medium">Estimate</th>
                            <th class="px-4 py-3 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="task in tasks"
                            :key="task.id"
                            class="border-b border-border/60 last:border-0"
                        >
                            <td
                                class="max-w-0 px-4 py-3 align-top"
                                :style="{
                                    paddingLeft: `calc(0.75rem + ${task.tree_depth} * 1.25rem)`,
                                }"
                            >
                                <div class="flex min-w-0 items-start gap-1.5">
                                    <CornerDownRight
                                        v-if="task.tree_depth > 0"
                                        class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <div class="min-w-0 flex-1">
                                        <Button
                                            variant="link"
                                            class="h-auto w-full min-w-0 justify-start p-0 font-medium text-foreground"
                                            as-child
                                        >
                                            <Link
                                                class="block text-left text-foreground line-clamp-2 break-words hover:underline"
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
                                            ({{ task.children_count }} subtasks)
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ task.status_label }}</td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ task.assignee?.name ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <template v-if="task.project_requirement_id">
                                    <Button variant="link" class="h-auto p-0" as-child>
                                        <Link
                                            :href="
                                                requirementsShow.url({
                                                    project: project.id,
                                                    requirement: task.project_requirement_id,
                                                })
                                            "
                                        >
                                            {{ task.requirement_title ?? 'View' }}
                                        </Link>
                                    </Button>
                                </template>
                                <template v-else>—</template>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ formatTaskMinutes(task.estimated_minutes) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <Button
                                        v-if="task.can_update"
                                        variant="outline"
                                        size="sm"
                                        type="button"
                                        @click="openEdit(task)"
                                    >
                                        Edit
                                    </Button>
                                    <Button
                                        v-if="task.can_delete"
                                        variant="outline"
                                        size="sm"
                                        class="text-destructive hover:bg-destructive/10"
                                        type="button"
                                        @click="openDeleteDialog(task)"
                                    >
                                        Delete
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="tasks.length === 0">
                            <td colspan="6" class="px-4 py-8 text-center text-muted-foreground">
                                No tasks match this filter.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>
    </div>
</template>
