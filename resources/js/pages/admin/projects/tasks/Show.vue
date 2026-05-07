<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { CornerDownRight } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import ProjectTaskController from '@/actions/App/Http/Controllers/Admin/ProjectTaskController';
import TaskTimeEntryController from '@/actions/App/Http/Controllers/Admin/TaskTimeEntryController';
import ConfirmDestructiveDialog from '@/components/ConfirmDestructiveDialog.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import TaskTimerButton from '@/components/TaskTimerButton.vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatSeconds } from '@/lib/formatSeconds';
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

type TaskParentBrief = {
    id: number;
    title: string;
} | null;

type SubtaskRow = {
    id: number;
    title: string;
    status: string;
    status_label: string;
    assignee_user_id: number | null;
    assignee: UserBrief;
    project_requirement_id: number | null;
    requirement_title: string | null | undefined;
    estimated_minutes: number | null;
    children_count: number;
    tree_depth: number;
    can_update: boolean;
    can_delete: boolean;
};

type TaskDetail = {
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
    parent: TaskParentBrief;
    estimated_minutes: number | null;
    children_count: number;
    subtasks: SubtaskRow[];
    can_update: boolean;
    can_delete: boolean;
};

type ProjectSummary = {
    id: number;
    name: string;
    code: string | null;
    estimation_required: boolean;
};

type TimeEntryRow = {
    id: number;
    user_id: number;
    user_name: string | null;
    started_at: string | null;
    ended_at: string | null;
    duration_seconds: number | null;
    is_running: boolean;
    source: string;
    source_label: string;
    notes: string | null;
    can_update: boolean;
    can_delete: boolean;
};

type TimeTracking = {
    can_track: boolean;
    totals: {
        my_today_seconds: number;
        my_all_time_seconds: number;
        task_all_time_seconds: number;
    };
    entries: TimeEntryRow[];
};

const props = defineProps<{
    project: ProjectSummary;
    task: TaskDetail;
    can_manage_project: boolean;
    time_tracking: TimeTracking;
}>();

defineOptions({
    layout: (pageProps: {
        project: ProjectSummary;
        task: TaskDetail;
        can_manage_project: boolean;
        time_tracking: TimeTracking;
    }) => ({
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
            {
                title: pageProps.task.title,
                href: projectTasksShow.url({
                    project: pageProps.project.id,
                    task: pageProps.task.id,
                }),
            },
        ],
    }),
});

const deleteDialogOpen = ref(false);

const deleteTaskDescription = computed(
    () => `Delete "${props.task.title}"? This cannot be undone.`,
);

function executeDelete(): void {
    router.delete(
        ProjectTaskController.destroy.url({
            project: props.project.id,
            task: props.task.id,
        }),
    );
}

const subtaskDeleteOpen = ref(false);
const subtaskPendingDelete = ref<SubtaskRow | null>(null);

function openSubtaskDelete(row: SubtaskRow): void {
    subtaskPendingDelete.value = row;
    subtaskDeleteOpen.value = true;
}

function executeSubtaskDelete(): void {
    const row = subtaskPendingDelete.value;

    if (row === null) {
        return;
    }

    router.delete(
        ProjectTaskController.destroy.url({
            project: props.project.id,
            task: row.id,
        }),
    );
    subtaskPendingDelete.value = null;
}

const subtaskDeleteDescription = computed(() => {
    const row = subtaskPendingDelete.value;

    if (row === null) {
        return '';
    }

    return `Delete "${row.title}"? This cannot be undone.`;
});

function toLocalInputValue(iso: string | null | undefined): string {
    if (iso === null || iso === undefined || iso === '') {
        return '';
    }

    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) {
        return '';
    }

    const pad = (n: number) => String(n).padStart(2, '0');

    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function formatEntryRange(start: string | null, end: string | null, isRunning: boolean): string {
    if (start === null) {
        return '—';
    }

    const startLabel = new Date(start).toLocaleString();

    if (isRunning) {
        return `${startLabel} → running`;
    }

    if (end === null) {
        return startLabel;
    }

    return `${startLabel} → ${new Date(end).toLocaleString()}`;
}

const now = ref(Date.now());
let nowInterval: number | undefined;

onMounted(() => {
    nowInterval = window.setInterval(() => {
        now.value = Date.now();
    }, 1000);
});

onBeforeUnmount(() => {
    if (nowInterval !== undefined) {
        window.clearInterval(nowInterval);
    }
});

function entryDurationSeconds(entry: TimeEntryRow): number {
    if (!entry.is_running) {
        return entry.duration_seconds ?? 0;
    }

    if (entry.started_at === null) {
        return 0;
    }

    const startedMs = Date.parse(entry.started_at);
    if (Number.isNaN(startedMs)) {
        return 0;
    }

    return Math.max(0, Math.floor((now.value - startedMs) / 1000));
}

const manualOpen = ref(false);
const manualStart = ref('');
const manualEnd = ref('');
const manualNotes = ref('');

function openManualDialog(): void {
    const nowDate = new Date();
    const startDate = new Date(nowDate.getTime() - 30 * 60 * 1000);
    manualStart.value = toLocalInputValue(startDate.toISOString());
    manualEnd.value = toLocalInputValue(nowDate.toISOString());
    manualNotes.value = '';
    manualOpen.value = true;
}

const editEntryOpen = ref(false);
const editingEntry = ref<TimeEntryRow | null>(null);
const editStart = ref('');
const editEnd = ref('');
const editNotes = ref('');

function openEditEntry(row: TimeEntryRow): void {
    editingEntry.value = row;
    editStart.value = toLocalInputValue(row.started_at);
    editEnd.value = toLocalInputValue(row.ended_at);
    editNotes.value = row.notes ?? '';
    editEntryOpen.value = true;
}

function closeEditEntry(): void {
    editEntryOpen.value = false;
    editingEntry.value = null;
}

const entryDeleteOpen = ref(false);
const entryPendingDelete = ref<TimeEntryRow | null>(null);

function openEntryDelete(row: TimeEntryRow): void {
    entryPendingDelete.value = row;
    entryDeleteOpen.value = true;
}

function executeEntryDelete(): void {
    const row = entryPendingDelete.value;

    if (row === null) {
        return;
    }

    router.delete(
        TaskTimeEntryController.destroy.url({
            project: props.project.id,
            task: props.task.id,
            time_entry: row.id,
        }),
        { preserveScroll: true },
    );
    entryPendingDelete.value = null;
}

const entryDeleteDescription = computed(() => {
    const row = entryPendingDelete.value;

    if (row === null) {
        return '';
    }

    return `Delete time entry from ${formatEntryRange(row.started_at, row.ended_at, row.is_running)}? This cannot be undone.`;
});
</script>

<template>
    <Head :title="`${task.title} · Tasks`" />

    <ConfirmDestructiveDialog
        v-model:open="deleteDialogOpen"
        title="Delete task?"
        :description="deleteTaskDescription"
        @confirm="executeDelete"
    />

    <ConfirmDestructiveDialog
        v-model:open="subtaskDeleteOpen"
        title="Delete subtask?"
        :description="subtaskDeleteDescription"
        @confirm="executeSubtaskDelete"
    />

    <ConfirmDestructiveDialog
        v-model:open="entryDeleteOpen"
        title="Delete time entry?"
        :description="entryDeleteDescription"
        @confirm="executeEntryDelete"
    />

    <Dialog v-model:open="manualOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Add time entry</DialogTitle>
                <DialogDescription>Log past work on this task.</DialogDescription>
            </DialogHeader>
            <Form
                v-bind="
                    TaskTimeEntryController.store.form({
                        project: project.id,
                        task: task.id,
                    })
                "
                class="grid gap-4"
                @success="manualOpen = false"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="manual-start">Start</Label>
                    <Input
                        id="manual-start"
                        name="started_at"
                        type="datetime-local"
                        required
                        v-model="manualStart"
                    />
                    <InputError :message="errors.started_at" />
                </div>
                <div class="grid gap-2">
                    <Label for="manual-end">End</Label>
                    <Input
                        id="manual-end"
                        name="ended_at"
                        type="datetime-local"
                        required
                        v-model="manualEnd"
                    />
                    <InputError :message="errors.ended_at" />
                </div>
                <div class="grid gap-2">
                    <Label for="manual-notes">Notes</Label>
                    <Input
                        id="manual-notes"
                        name="notes"
                        type="text"
                        maxlength="500"
                        v-model="manualNotes"
                    />
                    <InputError :message="errors.notes" />
                </div>
                <DialogFooter class="gap-2 sm:gap-0">
                    <Button type="button" variant="outline" @click="manualOpen = false">
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="processing">Add</Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>

    <Dialog :open="editEntryOpen" @update:open="(v: boolean) => !v && closeEditEntry()">
        <DialogContent v-if="editingEntry" class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Edit time entry</DialogTitle>
                <DialogDescription>Adjust start/end and notes.</DialogDescription>
            </DialogHeader>
            <Form
                :key="editingEntry.id"
                v-bind="
                    TaskTimeEntryController.update.form({
                        project: project.id,
                        task: task.id,
                        time_entry: editingEntry.id,
                    })
                "
                class="grid gap-4"
                @success="closeEditEntry()"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="edit-entry-start">Start</Label>
                    <Input
                        id="edit-entry-start"
                        name="started_at"
                        type="datetime-local"
                        required
                        v-model="editStart"
                    />
                    <InputError :message="errors.started_at" />
                </div>
                <div class="grid gap-2">
                    <Label for="edit-entry-end">End</Label>
                    <Input
                        id="edit-entry-end"
                        name="ended_at"
                        type="datetime-local"
                        required
                        v-model="editEnd"
                    />
                    <InputError :message="errors.ended_at" />
                </div>
                <div class="grid gap-2">
                    <Label for="edit-entry-notes">Notes</Label>
                    <Input
                        id="edit-entry-notes"
                        name="notes"
                        type="text"
                        maxlength="500"
                        v-model="editNotes"
                    />
                    <InputError :message="errors.notes" />
                </div>
                <DialogFooter class="gap-2 sm:gap-0">
                    <Button type="button" variant="outline" @click="closeEditEntry()">
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="processing">Save</Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>

    <div class="flex flex-col gap-8">
        <div class="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <Heading
                :title="task.title"
                :description="`Project ${project.name}`"
                title-line-clamp
            />
            <div class="flex flex-wrap gap-2">
                <TaskTimerButton
                    v-if="time_tracking.can_track"
                    :project-id="project.id"
                    :task-id="task.id"
                    size="default"
                />
                <Button variant="outline" as-child>
                    <Link :href="projectTasksIndex.url(project.id)">Back to task list</Link>
                </Button>
                <Button v-if="task.can_update" variant="outline" as-child>
                    <Link
                        :href="
                            projectTasksIndex.url(project.id, {
                                query: { edit_task: String(task.id) },
                            })
                        "
                    >
                        Edit on list
                    </Link>
                </Button>
                <Button
                    v-if="task.can_delete"
                    variant="outline"
                    class="text-destructive hover:bg-destructive/10"
                    type="button"
                    @click="deleteDialogOpen = true"
                >
                    Delete
                </Button>
            </div>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Details</CardTitle>
                <CardDescription>Status, ownership, and links for this task.</CardDescription>
            </CardHeader>
            <CardContent class="grid gap-6 text-sm">
                <div class="grid gap-1">
                    <span class="text-xs font-medium text-muted-foreground">Title</span>
                    <p
                        class="max-w-md text-sm font-medium leading-snug text-foreground line-clamp-2 break-words"
                        :title="task.title"
                    >
                        {{ task.title }}
                    </p>
                </div>
                <div class="grid gap-1">
                    <span class="text-xs font-medium text-muted-foreground">Status</span>
                    <span>{{ task.status_label }}</span>
                </div>
                <div class="grid gap-1">
                    <span class="text-xs font-medium text-muted-foreground">Assignee</span>
                    <span>{{ task.assignee?.name ?? '—' }}</span>
                </div>
                <div class="grid gap-1">
                    <span class="text-xs font-medium text-muted-foreground">Estimate</span>
                    <span>{{ formatTaskMinutes(task.estimated_minutes) }}</span>
                </div>
                <div class="grid gap-1">
                    <span class="text-xs font-medium text-muted-foreground">Requirement</span>
                    <template v-if="task.project_requirement_id">
                        <Button variant="link" class="h-auto justify-start p-0" as-child>
                            <Link
                                :href="
                                    requirementsShow.url({
                                        project: project.id,
                                        requirement: task.project_requirement_id,
                                    })
                                "
                            >
                                {{ task.requirement_title ?? 'View requirement' }}
                            </Link>
                        </Button>
                    </template>
                    <template v-else>
                        <span>—</span>
                    </template>
                </div>
                <div class="grid gap-1">
                    <span class="text-xs font-medium text-muted-foreground">Parent task</span>
                    <template v-if="task.parent">
                        <Button variant="link" class="h-auto max-w-full min-w-0 justify-start p-0" as-child>
                            <Link
                                class="block truncate text-left"
                                :title="task.parent.title"
                                :href="
                                    projectTasksShow.url({
                                        project: project.id,
                                        task: task.parent.id,
                                    })
                                "
                            >
                                {{ task.parent.title }}
                            </Link>
                        </Button>
                    </template>
                    <template v-else>
                        <span>—</span>
                    </template>
                </div>
                <div v-if="task.description" class="grid gap-1">
                    <span class="text-xs font-medium text-muted-foreground">Description</span>
                    <p class="whitespace-pre-wrap text-muted-foreground">{{ task.description }}</p>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <CardTitle>Time tracked</CardTitle>
                    <CardDescription>
                        Start a timer or log past work. Totals reflect closed entries only.
                    </CardDescription>
                </div>
                <div class="flex flex-wrap gap-2">
                    <TaskTimerButton
                        v-if="time_tracking.can_track"
                        :project-id="project.id"
                        :task-id="task.id"
                    />
                    <Button
                        v-if="time_tracking.can_track"
                        variant="outline"
                        size="sm"
                        type="button"
                        @click="openManualDialog"
                    >
                        Add manual entry
                    </Button>
                </div>
            </CardHeader>
            <CardContent class="grid gap-6">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="rounded-lg border border-border/60 bg-muted/20 p-3">
                        <p class="text-xs text-muted-foreground">My time today</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums">
                            {{ formatSeconds(time_tracking.totals.my_today_seconds) }}
                        </p>
                    </div>
                    <div class="rounded-lg border border-border/60 bg-muted/20 p-3">
                        <p class="text-xs text-muted-foreground">My time on this task</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums">
                            {{ formatSeconds(time_tracking.totals.my_all_time_seconds) }}
                        </p>
                    </div>
                    <div class="rounded-lg border border-border/60 bg-muted/20 p-3">
                        <p class="text-xs text-muted-foreground">Total on this task (all users)</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums">
                            {{ formatSeconds(time_tracking.totals.task_all_time_seconds) }}
                        </p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-left text-sm">
                        <thead class="border-b bg-muted/40">
                            <tr>
                                <th class="px-3 py-2 font-medium">User</th>
                                <th class="px-3 py-2 font-medium">When</th>
                                <th class="px-3 py-2 font-medium">Duration</th>
                                <th class="px-3 py-2 font-medium">Source</th>
                                <th class="px-3 py-2 font-medium">Notes</th>
                                <th class="px-3 py-2 font-medium text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="entry in time_tracking.entries"
                                :key="entry.id"
                                class="border-b border-border/60 last:border-0"
                            >
                                <td class="px-3 py-2 align-top text-muted-foreground">
                                    {{ entry.user_name ?? '—' }}
                                </td>
                                <td class="px-3 py-2 align-top text-muted-foreground">
                                    {{ formatEntryRange(entry.started_at, entry.ended_at, entry.is_running) }}
                                </td>
                                <td class="px-3 py-2 align-top tabular-nums">
                                    <span v-if="entry.is_running" class="text-emerald-600 dark:text-emerald-400">
                                        {{ formatSeconds(entryDurationSeconds(entry), { withSeconds: true }) }}
                                    </span>
                                    <span v-else>
                                        {{ formatSeconds(entry.duration_seconds) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 align-top text-muted-foreground">
                                    {{ entry.source_label }}
                                </td>
                                <td class="px-3 py-2 align-top text-muted-foreground line-clamp-2 break-words">
                                    {{ entry.notes ?? '—' }}
                                </td>
                                <td class="px-3 py-2 align-top text-right">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            v-if="entry.can_update && !entry.is_running"
                                            variant="outline"
                                            size="sm"
                                            type="button"
                                            @click="openEditEntry(entry)"
                                        >
                                            Edit
                                        </Button>
                                        <Button
                                            v-if="entry.can_delete"
                                            variant="outline"
                                            size="sm"
                                            class="text-destructive hover:bg-destructive/10"
                                            type="button"
                                            @click="openEntryDelete(entry)"
                                        >
                                            Delete
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="time_tracking.entries.length === 0">
                                <td colspan="6" class="px-3 py-8 text-center text-muted-foreground">
                                    No time entries yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Subtasks</CardTitle>
                <CardDescription>
                    Direct children of this task. Same layout as the project task list.
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
                            v-for="sub in task.subtasks"
                            :key="sub.id"
                            class="border-b border-border/60 last:border-0"
                        >
                            <td
                                class="max-w-0 px-4 py-3 align-top"
                                :style="{
                                    paddingLeft: `calc(0.75rem + ${sub.tree_depth} * 1.25rem)`,
                                }"
                            >
                                <div class="flex min-w-0 items-start gap-1.5">
                                    <CornerDownRight
                                        v-if="sub.tree_depth > 0"
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
                                                :title="sub.title"
                                                :href="
                                                    projectTasksShow.url({
                                                        project: project.id,
                                                        task: sub.id,
                                                    })
                                                "
                                            >
                                                {{ sub.title }}
                                            </Link>
                                        </Button>
                                        <span
                                            v-if="sub.children_count > 0"
                                            class="mt-0.5 block text-xs text-muted-foreground"
                                        >
                                            ({{ sub.children_count }} subtasks)
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ sub.status_label }}</td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ sub.assignee?.name ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <template v-if="sub.project_requirement_id">
                                    <Button variant="link" class="h-auto p-0" as-child>
                                        <Link
                                            :href="
                                                requirementsShow.url({
                                                    project: project.id,
                                                    requirement: sub.project_requirement_id,
                                                })
                                            "
                                        >
                                            {{ sub.requirement_title ?? 'View' }}
                                        </Link>
                                    </Button>
                                </template>
                                <template v-else>—</template>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ formatTaskMinutes(sub.estimated_minutes) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <Button v-if="sub.can_update" variant="outline" size="sm" as-child>
                                        <Link
                                            :href="
                                                projectTasksIndex.url(project.id, {
                                                    query: { edit_task: String(sub.id) },
                                                })
                                            "
                                        >
                                            Edit
                                        </Link>
                                    </Button>
                                    <Button
                                        v-if="sub.can_delete"
                                        variant="outline"
                                        size="sm"
                                        class="text-destructive hover:bg-destructive/10"
                                        type="button"
                                        @click="openSubtaskDelete(sub)"
                                    >
                                        Delete
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="task.subtasks.length === 0">
                            <td colspan="6" class="px-4 py-8 text-center text-muted-foreground">
                                No subtasks yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>
    </div>
</template>
