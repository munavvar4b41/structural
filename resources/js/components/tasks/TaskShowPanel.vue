<script setup lang="ts">
import { Form, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { CornerDownRight } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import ProjectTaskChecklistItemController from '@/actions/App/Http/Controllers/Admin/ProjectTaskChecklistItemController';
import ProjectTaskController from '@/actions/App/Http/Controllers/Admin/ProjectTaskController';
import TaskCompletionReviewController from '@/actions/App/Http/Controllers/Admin/TaskCompletionReviewController';
import TaskTimeEntryController from '@/actions/App/Http/Controllers/Admin/TaskTimeEntryController';
import ConfirmDestructiveDialog from '@/components/ConfirmDestructiveDialog.vue';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import InputError from '@/components/InputError.vue';
import TaskFormSelect from '@/components/TaskFormSelect.vue';
import TaskTimerButton from '@/components/TaskTimerButton.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { show as requirementsShow } from '@/routes/admin/projects/requirements/index';
import {
    index as projectTasksIndex,
    show as projectTasksShow,
} from '@/routes/admin/projects/tasks/index';
import type {
    Checklist,
    ChecklistItemRow,
    ProjectSummary,
    SubtaskRow,
    TaskDetail,
    TimeEntryRow,
    TimeTracking,
} from '@/types/projectTaskShow';

const props = withDefaults(
    defineProps<{
        project: ProjectSummary;
        task: TaskDetail;
        can_manage_project: boolean;
        checklist: Checklist;
        time_tracking: TimeTracking;
        embedded?: boolean;
    }>(),
    {
        embedded: false,
    },
);

const emit = defineEmits<{
    close: [];
    openTask: [taskId: number];
}>();

function reloadAfterMutation(): void {
    if (props.embedded) {
        router.reload({ only: ['task_preview', 'columns'] });
    } else {
        router.reload();
    }
}

function mutationOptions(extra: Record<string, unknown> = {}): Record<string, unknown> {
    const extraOnSuccess = extra.onSuccess as (() => void) | undefined;
    const { ...rest } = extra;

    return {
        preserveScroll: true,
        ...rest,
        onSuccess: () => {
            extraOnSuccess?.();
            reloadAfterMutation();
        },
    };
}

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
        {
            data: props.embedded ? { from_my_work: true } : undefined,
            preserveScroll: true,
            onSuccess: () => {
                if (props.embedded) {
                    emit('close');
                }
            },
        },
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
        {
            data: props.embedded ? { from_my_work: true } : undefined,
            ...mutationOptions(),
        },
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

function formatEntryRange(
    start: string | null,
    end: string | null,
    isRunning: boolean,
    isPaused: boolean,
): string {
    if (start === null) {
        return '—';
    }

    const startLabel = new Date(start).toLocaleString();

    if (isRunning) {
        if (isPaused) {
            return `${startLabel} → paused`;
        }

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

const page = usePage();

const activeTimeEntry = computed(() => page.props.active_time_entry);

const myAllTimeAnchorMs = ref(Date.now());

watch(
    () => [
        props.time_tracking.totals.my_all_time_seconds,
        activeTimeEntry.value,
    ],
    () => {
        myAllTimeAnchorMs.value = Date.now();
    },
    { deep: true },
);

const liveMyAllTimeSeconds = computed(() => {
    const active = activeTimeEntry.value;

    if (active !== null && active.task_id === props.task.id) {
        if (active.is_paused) {
            return active.my_all_time_seconds;
        }

        const delta = Math.max(
            0,
            Math.floor((now.value - myAllTimeAnchorMs.value) / 1000),
        );

        return active.my_all_time_seconds + delta;
    }

    return props.time_tracking.totals.my_all_time_seconds;
});

const liveMyTodaySeconds = computed(() => {
    const active = activeTimeEntry.value;

    if (active !== null && active.task_id === props.task.id) {
        if (active.is_paused) {
            return active.task_today_seconds;
        }

        const delta = Math.max(
            0,
            Math.floor((now.value - myAllTimeAnchorMs.value) / 1000),
        );

        return active.task_today_seconds + delta;
    }

    return props.time_tracking.totals.my_today_seconds;
});

const liveRemainingSeconds = computed(() => {
    const remaining = props.time_tracking.totals.remaining_seconds;

    if (remaining === null) {
        return null;
    }

    const spentDelta =
        liveMyAllTimeSeconds.value
        - props.time_tracking.totals.my_all_time_seconds;

    return Math.max(0, remaining - spentDelta);
});

function entryDurationSeconds(entry: TimeEntryRow): number {
    if (!entry.is_running) {
        return entry.duration_seconds ?? 0;
    }

    const active = activeTimeEntry.value;

    if (active !== null && active.id === entry.id) {
        if (active.is_paused) {
            return active.elapsed_seconds;
        }

        const delta = Math.max(
            0,
            Math.floor((now.value - myAllTimeAnchorMs.value) / 1000),
        );

        return active.elapsed_seconds + delta;
    }

    return entry.elapsed_seconds ?? 0;
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
        mutationOptions(),
    );
    entryPendingDelete.value = null;
}

const entryDeleteDescription = computed(() => {
    const row = entryPendingDelete.value;

    if (row === null) {
        return '';
    }

    return `Delete time entry from ${formatEntryRange(row.started_at, row.ended_at, row.is_running, row.is_paused)}? This cannot be undone.`;
});

const ratingOptions = [1, 2, 3, 4, 5].map((n) => ({
    value: String(n),
    label: String(n),
}));

const confirmCompletionOpen = ref(false);

const confirmForm = useForm({
    review_notes: '',
    task_rating: '5',
    assignee_rating: '5',
    creator_rating: '5',
    task: '',
});

watch(confirmCompletionOpen, (open) => {
    if (open) {
        confirmForm.reset();
        confirmForm.clearErrors();
        confirmForm.task_rating = '5';
        confirmForm.assignee_rating = '5';
        confirmForm.creator_rating = '5';

        if (props.task.assignee_user_id === null) {
            confirmForm.assignee_rating = '';
        }
    }
});

const showAssigneeRatingOnConfirm = computed(() => props.task.assignee_user_id !== null);

function submitForCompletion(): void {
    router.post(
        TaskCompletionReviewController.submit.url({
            project: props.project.id,
            task: props.task.id,
        }),
        {},
        mutationOptions(),
    );
}

function submitConfirmCompletion(): void {
    confirmForm
        .transform((data) => ({
            review_notes: data.review_notes.trim() === '' ? null : data.review_notes,
            task_rating: Number.parseInt(String(data.task_rating), 10),
            assignee_rating:
                props.task.assignee_user_id === null || data.assignee_rating === ''
                    ? null
                    : Number.parseInt(String(data.assignee_rating), 10),
            creator_rating: Number.parseInt(String(data.creator_rating), 10),
        }))
        .post(
            TaskCompletionReviewController.confirm.url({
                project: props.project.id,
                task: props.task.id,
            }),
            mutationOptions({
                onSuccess: () => {
                    confirmCompletionOpen.value = false;
                },
            }),
        );
}

function submitForCompletionSubtask(row: SubtaskRow): void {
    router.post(
        TaskCompletionReviewController.submit.url({
            project: props.project.id,
            task: row.id,
        }),
        {},
        mutationOptions(),
    );
}

const newChecklistTitle = ref('');

const editingChecklistId = ref<number | null>(null);
const editChecklistTitle = ref('');

function openEditChecklistItem(row: ChecklistItemRow): void {
    editingChecklistId.value = row.id;
    editChecklistTitle.value = row.title;
}

function closeEditChecklistItem(): void {
    editingChecklistId.value = null;
    editChecklistTitle.value = '';
}

function saveChecklistItem(row: ChecklistItemRow): void {
    const title = editChecklistTitle.value.trim();

    if (title === '') {
        return;
    }

    router.patch(
        ProjectTaskChecklistItemController.update.url({
            project: props.project.id,
            task: props.task.id,
            checklist_item: row.id,
        }),
        { title },
        mutationOptions({
            onSuccess: () => closeEditChecklistItem(),
        }),
    );
}

function toggleChecklistItem(row: ChecklistItemRow, completed: boolean | 'indeterminate'): void {
    if (!props.checklist.can_manage || completed === 'indeterminate') {
        return;
    }

    if (row.is_completed === completed) {
        return;
    }

    router.patch(
        ProjectTaskChecklistItemController.update.url({
            project: props.project.id,
            task: props.task.id,
            checklist_item: row.id,
        }),
        { is_completed: completed },
        mutationOptions(),
    );
}

const checklistDeleteOpen = ref(false);
const checklistPendingDelete = ref<ChecklistItemRow | null>(null);

function openChecklistDelete(row: ChecklistItemRow): void {
    checklistPendingDelete.value = row;
    checklistDeleteOpen.value = true;
}

function executeChecklistDelete(): void {
    const row = checklistPendingDelete.value;

    if (row === null) {
        return;
    }

    router.delete(
        ProjectTaskChecklistItemController.destroy.url({
            project: props.project.id,
            task: props.task.id,
            checklist_item: row.id,
        }),
        mutationOptions(),
    );
    checklistPendingDelete.value = null;
}

const checklistDeleteDescription = computed(() => {
    const row = checklistPendingDelete.value;

    if (row === null) {
        return '';
    }

    return `Delete "${row.title}"? This cannot be undone.`;
});
</script>

<template>
    <ConfirmDestructiveDialog v-model:open="deleteDialogOpen" title="Delete task?" :description="deleteTaskDescription"
        @confirm="executeDelete" />

    <ConfirmDestructiveDialog v-model:open="subtaskDeleteOpen" title="Delete subtask?"
        :description="subtaskDeleteDescription" @confirm="executeSubtaskDelete" />

    <ConfirmDestructiveDialog v-model:open="entryDeleteOpen" title="Delete time entry?"
        :description="entryDeleteDescription" @confirm="executeEntryDelete" />

    <ConfirmDestructiveDialog v-model:open="checklistDeleteOpen" title="Delete checklist item?"
        :description="checklistDeleteDescription" @confirm="executeChecklistDelete" />

    <Dialog v-model:open="manualOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Add time entry</DialogTitle>
                <DialogDescription>Log past work on this task.</DialogDescription>
            </DialogHeader>
            <Form v-bind="TaskTimeEntryController.store.form({
                project: project.id,
                task: task.id,
            })
                " class="grid gap-4" @success="() => { manualOpen = false; reloadAfterMutation(); }"
                v-slot="{ errors, processing }">
                <div class="grid gap-2">
                    <Label for="manual-start">Start</Label>
                    <Input id="manual-start" name="started_at" type="datetime-local" required v-model="manualStart" />
                    <InputError :message="errors.started_at" />
                </div>
                <div class="grid gap-2">
                    <Label for="manual-end">End</Label>
                    <Input id="manual-end" name="ended_at" type="datetime-local" required v-model="manualEnd" />
                    <InputError :message="errors.ended_at" />
                </div>
                <div class="grid gap-2">
                    <Label for="manual-notes">Notes</Label>
                    <Input id="manual-notes" name="notes" type="text" maxlength="500" v-model="manualNotes" />
                    <InputError :message="errors.notes" />
                </div>
                <DialogFooter class="gap-3">
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
            <Form :key="editingEntry.id" v-bind="TaskTimeEntryController.update.form({
                project: project.id,
                task: task.id,
                time_entry: editingEntry.id,
            })
                " class="grid gap-4" @success="() => { closeEditEntry(); reloadAfterMutation(); }"
                v-slot="{ errors, processing }">
                <div class="grid gap-2">
                    <Label for="edit-entry-start">Start</Label>
                    <Input id="edit-entry-start" name="started_at" type="datetime-local" required v-model="editStart" />
                    <InputError :message="errors.started_at" />
                </div>
                <div class="grid gap-2">
                    <Label for="edit-entry-end">End</Label>
                    <Input id="edit-entry-end" name="ended_at" type="datetime-local" required v-model="editEnd" />
                    <InputError :message="errors.ended_at" />
                </div>
                <div class="grid gap-2">
                    <Label for="edit-entry-notes">Notes</Label>
                    <Input id="edit-entry-notes" name="notes" type="text" maxlength="500" v-model="editNotes" />
                    <InputError :message="errors.notes" />
                </div>
                <DialogFooter class="gap-3">
                    <Button type="button" variant="outline" @click="closeEditEntry()">
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="processing">Save</Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>

    <Dialog :open="confirmCompletionOpen" @update:open="(v: boolean) => (confirmCompletionOpen = v)">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Confirm completion</DialogTitle>
                <DialogDescription>
                    Add optional notes and ratings (1–5). The task will be marked done.
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-4" @submit.prevent="submitConfirmCompletion">

                <InputError :message="confirmForm.errors.task" />

                <div class="grid gap-2">
                    <Label for="show-review-notes">Review notes</Label>
                    <textarea id="show-review-notes" v-model="confirmForm.review_notes" rows="3" maxlength="10000"
                        class="flex min-h-[80px] w-full rounded-xl border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-transparent"
                        placeholder="Optional feedback for the team" />
                    <InputError :message="confirmForm.errors.review_notes" />
                </div>

                <div class="grid gap-2">
                    <Label for="show-task-rating">Task quality (1–5)</Label>
                    <TaskFormSelect id="show-task-rating" v-model="confirmForm.task_rating" name="task_rating" required
                        :options="ratingOptions" />
                    <InputError :message="confirmForm.errors.task_rating" />
                </div>

                <div v-if="showAssigneeRatingOnConfirm" class="grid gap-2">
                    <Label for="show-assignee-rating">Assignee performance (1–5)</Label>
                    <TaskFormSelect id="show-assignee-rating" v-model="confirmForm.assignee_rating"
                        name="assignee_rating" required :options="ratingOptions" />
                    <InputError :message="confirmForm.errors.assignee_rating" />
                </div>

                <div class="grid gap-2">
                    <Label for="show-creator-rating">Task owner / creator (1–5)</Label>
                    <TaskFormSelect id="show-creator-rating" v-model="confirmForm.creator_rating" name="creator_rating"
                        required :options="ratingOptions" />
                    <InputError :message="confirmForm.errors.creator_rating" />
                </div>

                <DialogFooter class="gap-3">

                    <Button type="button" variant="outline" @click="confirmCompletionOpen = false">
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="confirmForm.processing">Confirm & mark done</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <div class="flex flex-col gap-8">
        <div class="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <PageHeader v-if="!embedded" :title="task.title" :description="`Project ${project.name}`" />
            <div class="flex flex-wrap gap-2" :class="{ 'w-full': embedded }">
                <Button v-if="task.can_submit_task_completion" variant="secondary" type="button"
                    @click="submitForCompletion()">
                    Submit for completion
                </Button>
                <Button v-if="task.can_confirm_task_completion" type="button" @click="confirmCompletionOpen = true">
                    Confirm completion
                </Button>
                <TaskTimerButton v-if="time_tracking.can_track" :project-id="project.id" :task-id="task.id"
                    size="default" :reload-props-on-mutation="['time_tracking']" />
                <Button v-if="!embedded" variant="outline" as-child>
                    <Link :href="projectTasksIndex.url(project.id)">Back to task list</Link>
                </Button>
                <Button v-if="task.can_update" variant="outline" as-child>
                    <Link :href="projectTasksIndex.url(project.id, {
                        query: { edit_task: String(task.id) },
                    })
                        ">
                        Edit on list
                    </Link>
                </Button>
                <Button v-if="task.can_delete" variant="outline" class="text-destructive hover:bg-destructive/10"
                    type="button" @click="deleteDialogOpen = true">
                    Delete
                </Button>
            </div>
        </div>

        <GlassCard v-if="task.status === 'review'"
            class="border-amber-200/80 bg-amber-50/40 dark:border-amber-500/35 dark:bg-amber-500/10">
            <div class="space-y-1">
                <h2 class="text-lg font-semibold">Awaiting review</h2>
                <p class="text-sm text-muted-foreground">
                    This task was submitted for completion
                    <template v-if="task.completion_submitted_at">
                        on {{ new Date(task.completion_submitted_at).toLocaleString() }}
                    </template>
                    <template v-if="task.completion_submitted_by">
                        by {{ task.completion_submitted_by.name }}.
                    </template>
                </p>
            </div>

        </GlassCard>

        <GlassCard>
            <div class="mb-6 space-y-1">
                <h2 class="text-lg font-semibold">Details</h2>
                <p class="text-sm text-muted-foreground">
                    Status, ownership, and links for this task.
                </p>
            </div>
            <div class="grid gap-6 text-sm">
                <div class="grid gap-1">
                    <span class="text-xs font-medium text-muted-foreground">Title</span>
                    <p class="max-w-md text-sm font-medium leading-snug text-foreground line-clamp-2 break-words"
                        :title="task.title">
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
                <div v-if="time_tracking.totals.remaining_seconds !== null" class="grid gap-1">
                    <span class="text-xs font-medium text-muted-foreground">Remaining</span>
                    <span class="tabular-nums">
                        {{
                            liveRemainingSeconds === 0
                                ? 'No time left'
                                : formatSeconds(liveRemainingSeconds ?? 0)
                        }}
                    </span>
                </div>
                <div class="grid gap-1">
                    <span class="text-xs font-medium text-muted-foreground">Requirement</span>
                    <template v-if="task.project_requirement_id">
                        <Button variant="link" class="h-auto justify-start p-0" as-child>
                            <Link :href="requirementsShow.url({
                                project: project.id,
                                requirement: task.project_requirement_id,
                            })
                                ">
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
                        <Button v-if="embedded" variant="link"
                            class="h-auto max-w-full min-w-0 justify-start p-0 text-left" type="button"
                            :title="task.parent.title" @click="emit('openTask', task.parent.id)">
                            {{ task.parent.title }}
                        </Button>
                        <Button v-else variant="link" class="h-auto max-w-full min-w-0 justify-start p-0" as-child>
                            <Link class="block truncate text-left" :title="task.parent.title" :href="projectTasksShow.url({
                                project: project.id,
                                task: task.parent.id,
                            })
                                ">
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
            </div>
        </GlassCard>

        <GlassCard>
            <div class="mb-6 space-y-1">
                <h2 class="text-lg font-semibold">Checklist</h2>
                <p class="text-sm text-muted-foreground">
                    Simple steps for this task. Check items off as you complete them.
                </p>
            </div>
            <ul v-if="checklist.items.length > 0" class="space-y-2">
                <li v-for="item in checklist.items" :key="item.id"
                    class="flex gap-3 rounded-lg border border-border/60 px-3 py-2 items-center">
                    <Checkbox :id="`checklist-${item.id}`" :model-value="item.is_completed"
                        :disabled="!checklist.can_manage" class="mt-0.5"
                        @update:model-value="(v) => toggleChecklistItem(item, v)" />
                    <div v-if="editingChecklistId === item.id"
                        class="flex min-w-0 flex-1 flex-col gap-2 sm:flex-row sm:items-center">
                        <Input :id="`edit-checklist-${item.id}`" v-model="editChecklistTitle" type="text"
                            maxlength="500" class="h-8" placeholder="Checklist item title"
                            @keydown.enter.prevent="saveChecklistItem(item)" />
                        <div class="flex shrink-0 gap-1">
                            <Button type="button" size="sm" @click="saveChecklistItem(item)">
                                Save
                            </Button>
                            <Button type="button" variant="outline" size="sm" @click="closeEditChecklistItem()">
                                Cancel
                            </Button>
                        </div>
                    </div>
                    <div v-else class="min-w-0 flex-1">
                        <label :for="`checklist-${item.id}`" class="block cursor-pointer text-sm" :class="item.is_completed
                            ? 'text-muted-foreground line-through'
                            : 'text-foreground'
                            " @click="checklist.can_manage && toggleChecklistItem(item, !item.is_completed)">
                            {{ item.title }}
                        </label>
                    </div>
                    <div v-if="checklist.can_manage && editingChecklistId !== item.id" class="flex shrink-0 gap-1">
                        <Button type="button" variant="outline" size="sm" @click="openEditChecklistItem(item)">
                            Edit
                        </Button>
                        <Button type="button" variant="outline" size="sm"
                            class="text-destructive hover:bg-destructive/10" @click="openChecklistDelete(item)">
                            Delete
                        </Button>
                    </div>
                </li>
            </ul>
            <p v-else class="text-sm text-muted-foreground">
                No checklist items yet.
            </p>
            <Form v-if="checklist.can_manage" :action="ProjectTaskChecklistItemController.store.url({
                project: project.id,
                task: task.id,
            })
                " method="post" class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-end"
                @success="() => { newChecklistTitle = ''; reloadAfterMutation(); }" v-slot="{ errors, processing }">
                <div class="grid flex-1 gap-2">
                    <Label for="new-checklist-title" class="sr-only">New item</Label>
                    <Input id="new-checklist-title" name="title" type="text" maxlength="500" required
                        placeholder="Add a checklist item…" v-model="newChecklistTitle" />
                    <InputError :message="errors.title" />
                </div>
                <Button type="submit" :disabled="processing">Add</Button>
            </Form>
        </GlassCard>

        <GlassCard>
            <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="space-y-1">
                    <h2 class="text-lg font-semibold">Time tracked</h2>
                    <p class="text-sm text-muted-foreground">
                        Start a timer or log past work. Totals reflect closed entries only.
                    </p>
                </div>
                <Button type="button" variant="outline" size="sm" @click="openManualDialog">
                    Log time
                </Button>
            </div>
            <div class="grid gap-6">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="rounded-lg border border-border/60 bg-muted/20 p-3">
                        <p class="text-xs text-muted-foreground">My time today</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums">
                            {{ formatSeconds(liveMyTodaySeconds) }}
                        </p>
                    </div>
                    <div class="rounded-lg border border-border/60 bg-muted/20 p-3">
                        <p class="text-xs text-muted-foreground">My time on this task</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums">
                            {{ formatSeconds(liveMyAllTimeSeconds) }}
                        </p>
                    </div>
                    <div class="rounded-lg border border-border/60 bg-muted/20 p-3">
                        <p class="text-xs text-muted-foreground">Total on this task (all users)</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums">
                            {{ formatSeconds(time_tracking.totals.task_all_time_seconds) }}
                        </p>
                    </div>
                </div>

                <div class="md:overflow-x-auto">
                    <table data-responsive-table class="data-table-responsive w-full text-left text-sm md:min-w-[640px]"
                        style="--data-table-min-width: 640px">
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
                            <tr v-for="entry in time_tracking.entries" :key="entry.id"
                                class="border-b border-border/60 last:border-0">
                                <td data-label="User" class="px-3 py-2 align-top text-muted-foreground">
                                    {{ entry.user_name ?? '—' }}
                                </td>
                                <td data-label="When" class="px-3 py-2 align-top text-muted-foreground">
                                    {{ formatEntryRange(entry.started_at, entry.ended_at, entry.is_running,
                                        entry.is_paused) }}
                                </td>
                                <td data-label="Duration" class="px-3 py-2 align-top tabular-nums">
                                    <span v-if="entry.is_running" :class="entry.is_paused
                                        ? 'text-amber-600 dark:text-amber-400'
                                        : 'text-emerald-600 dark:text-emerald-400'">
                                        {{ formatSeconds(entryDurationSeconds(entry), { withSeconds: true }) }}
                                    </span>
                                    <span v-else>
                                        {{ formatSeconds(entry.duration_seconds) }}
                                    </span>
                                </td>
                                <td data-label="Source" class="px-3 py-2 align-top text-muted-foreground">
                                    {{ entry.source_label }}
                                </td>
                                <td data-label="Notes"
                                    class="px-3 py-2 align-top text-muted-foreground line-clamp-2 break-words">
                                    {{ entry.notes ?? '—' }}
                                </td>
                                <td data-label="Actions" class="px-3 py-2 align-top text-right">
                                    <div class="flex justify-end gap-2">
                                        <Button v-if="entry.can_update && !entry.is_running" variant="outline" size="sm"
                                            type="button" @click="openEditEntry(entry)">
                                            Edit
                                        </Button>
                                        <Button v-if="entry.can_delete" variant="outline" size="sm"
                                            class="text-destructive hover:bg-destructive/10" type="button"
                                            @click="openEntryDelete(entry)">
                                            Delete
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="time_tracking.entries.length === 0">
                                <td data-label="" colspan="6" class="px-3 py-8 text-center text-muted-foreground">
                                    No time entries yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </GlassCard>

        <GlassCard>
            <div class="mb-6 space-y-1">
                <h2 class="text-lg font-semibold">Subtasks</h2>
                <p class="text-sm text-muted-foreground">
                    Direct children of this task. Same layout as the project task list.
                </p>
            </div>
            <div class="md:overflow-x-auto">
                <table data-responsive-table
                    class="data-table-responsive w-full table-fixed text-left text-sm md:min-w-[720px]"
                    style="--data-table-min-width: 720px">
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
                        <tr v-for="sub in task.subtasks" :key="sub.id" class="border-b border-border/60 last:border-0">
                            <td data-label="Title" class="max-w-0 px-4 py-3 align-top" :style="{
                                paddingLeft: `calc(0.75rem + ${sub.tree_depth} * 1.25rem)`,
                            }">
                                <div class="flex min-w-0 items-start gap-1.5">
                                    <CornerDownRight v-if="sub.tree_depth > 0"
                                        class="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                                    <div class="min-w-0 flex-1">
                                        <Button v-if="embedded" variant="link"
                                            class="h-auto w-full min-w-0 justify-start p-0 text-left font-medium text-foreground line-clamp-2 break-words hover:underline"
                                            type="button" :title="sub.title" @click="emit('openTask', sub.id)">
                                            {{ sub.title }}
                                        </Button>
                                        <Button v-else variant="link"
                                            class="h-auto w-full min-w-0 justify-start p-0 font-medium text-foreground"
                                            as-child>
                                            <Link
                                                class="block text-left text-foreground line-clamp-2 break-words hover:underline"
                                                :title="sub.title" :href="projectTasksShow.url({
                                                    project: project.id,
                                                    task: sub.id,
                                                })
                                                    ">
                                                {{ sub.title }}
                                            </Link>
                                        </Button>
                                        <span v-if="sub.children_count > 0"
                                            class="mt-0.5 block text-xs text-muted-foreground">
                                            ({{ sub.children_count }} subtasks)
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Status" class="px-4 py-3 text-muted-foreground">{{ sub.status_label }}</td>
                            <td data-label="Assignee" class="px-4 py-3 text-muted-foreground">
                                {{ sub.assignee?.name ?? '—' }}
                            </td>
                            <td data-label="Requirement" class="px-4 py-3">
                                <template v-if="sub.project_requirement_id">
                                    <Button variant="link" class="h-auto p-0" as-child>
                                        <Link :href="requirementsShow.url({
                                            project: project.id,
                                            requirement: sub.project_requirement_id,
                                        })
                                            ">
                                            {{ sub.requirement_title ?? 'View' }}
                                        </Link>
                                    </Button>
                                </template>
                                <template v-else>—</template>
                            </td>
                            <td data-label="Estimate" class="px-4 py-3 text-muted-foreground">
                                {{ formatTaskMinutes(sub.estimated_minutes) }}
                            </td>
                            <td data-label="Actions" class="px-4 py-3 text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <Button v-if="sub.can_submit_task_completion" variant="secondary" size="sm"
                                        type="button" @click="submitForCompletionSubtask(sub)">
                                        Submit for completion
                                    </Button>
                                    <Button v-if="sub.can_update" variant="outline" size="sm" as-child>
                                        <Link :href="projectTasksIndex.url(project.id, {
                                            query: { edit_task: String(sub.id) },
                                        })
                                            ">
                                            Edit
                                        </Link>
                                    </Button>
                                    <Button v-if="sub.can_delete" variant="outline" size="sm"
                                        class="text-destructive hover:bg-destructive/10" type="button"
                                        @click="openSubtaskDelete(sub)">
                                        Delete
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="task.subtasks.length === 0">
                            <td data-label="" colspan="6" class="px-4 py-8 text-center text-muted-foreground">
                                No subtasks yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </GlassCard>
    </div>
</template>
