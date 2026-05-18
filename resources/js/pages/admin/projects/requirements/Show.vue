<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { CornerDownRight } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import ProjectRequirementController from '@/actions/App/Http/Controllers/Admin/ProjectRequirementController';
import ProjectRequirementMessageController from '@/actions/App/Http/Controllers/Admin/ProjectRequirementMessageController';
import ProjectTaskController from '@/actions/App/Http/Controllers/Admin/ProjectTaskController';
import ConfirmDestructiveDialog from '@/components/ConfirmDestructiveDialog.vue';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import InputError from '@/components/InputError.vue';
import RequirementRichTextEditor from '@/components/RequirementRichTextEditor.vue';
import RequirementRichTextViewer from '@/components/RequirementRichTextViewer.vue';
import TaskFormSelect from '@/components/TaskFormSelect.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatTaskMinutes } from '@/lib/formatTaskMinutes';
import { emptyTipTapDocumentJson } from '@/lib/tiptapDocument';
import { cn, isCurrentUser } from '@/lib/utils';
import { edit as projectsEdit, index as projectsIndex } from '@/routes/admin/projects/index';
import {
    edit as requirementsEdit,
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

type ProjectSummary = {
    id: number;
    name: string;
    code: string | null;
    estimation_required: boolean;
};

type RequirementTaskRow = {
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

type TaskStatusOption = { value: string; label: string };
type TaskUserOption = { value: number; label: string };

type ChatMessageRow = {
    id: number;
    body: string;
    created_at: string | null;
    user: UserBrief;
};

type PaginatedChatMessages = {
    data: ChatMessageRow[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

type RequirementDetail = {
    id: number;
    title: string;
    description: string | null;
    review_understanding: string | null;
    reviewed_at: string | null;
    understanding_confirmed_at: string | null;
    understanding_confirmed_by: UserBrief;
    created_at: string | null;
    updated_at: string | null;
    creator: UserBrief;
    responsible_user: UserBrief;
    reviewer: UserBrief;
};

const props = defineProps<{
    project: ProjectSummary;
    requirement: RequirementDetail;
    requirement_chat_messages: PaginatedChatMessages;
    requirement_tasks: RequirementTaskRow[];
    task_status_options: TaskStatusOption[];
    task_assignable_users: TaskUserOption[];
    can_post_requirement_chat: boolean;
    can_update: boolean;
    can_mark_reviewed: boolean;
    can_confirm_understanding: boolean;
    can_manage_project: boolean;
    can_create_tasks: boolean;
}>();

const createTaskOpen = ref(false);
const reviewDialogOpen = ref(false);

const taskDeleteOpen = ref(false);
const taskPendingDelete = ref<RequirementTaskRow | null>(null);

function formatParentTaskLabel(task: RequirementTaskRow): string {
    if (task.tree_depth <= 0) {
        return task.title;
    }

    return `${'— '.repeat(task.tree_depth)}${task.title}`;
}

const createTaskStatus = ref('to_do');
const createTaskAssignee = ref('');
const createTaskParent = ref('');

watch(createTaskOpen, (open) => {
    if (open) {
        createTaskStatus.value = 'to_do';
        createTaskAssignee.value = '';
        createTaskParent.value = '';
    }
});

const taskStatusSelectOptions = computed(() =>
    props.task_status_options.map((o) => ({ value: o.value, label: o.label })),
);

const taskAssigneeSelectOptions = computed(() =>
    props.task_assignable_users.map((u) => ({ value: String(u.value), label: u.label })),
);

const taskParentSelectOptions = computed(() =>
    props.requirement_tasks.map((task) => ({
        value: String(task.id),
        label: formatParentTaskLabel(task),
    })),
);

function openTaskDelete(row: RequirementTaskRow): void {
    taskPendingDelete.value = row;
    taskDeleteOpen.value = true;
}

function executeTaskDelete(): void {
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
const chatScrollEl = ref<HTMLElement | null>(null);
const chatBody = ref('');
let chatPollTimer: ReturnType<typeof setInterval> | null = null;

function scrollChatToBottom(): void {
    nextTick(() => {
        const el = chatScrollEl.value;

        if (el) {
            el.scrollTop = el.scrollHeight;
        }
    });
}

function reloadChatMessages(): void {
    router.reload({ only: ['requirement_chat_messages'] });
}

function onChatMessagePosted(): void {
    chatBody.value = '';
    reloadChatMessages();
}

watch(
    () => props.requirement_chat_messages.data,
    () => {
        scrollChatToBottom();
    },
    { deep: true },
);

onMounted(() => {
    scrollChatToBottom();
    chatPollTimer = window.setInterval(() => {
        reloadChatMessages();
    }, 30_000);
});

onUnmounted(() => {
    if (chatPollTimer !== null) {
        window.clearInterval(chatPollTimer);
        chatPollTimer = null;
    }
});

const olderMessagesHref = computed((): string | null => {
    const { current_page: currentPage } = props.requirement_chat_messages;

    if (currentPage <= 1) {
        return null;
    }

    return requirementsShow.url(
        { project: props.project.id, requirement: props.requirement.id },
        { query: { chat_page: currentPage - 1 } },
    );
});

const reviewUnderstandingJson = ref(
    props.requirement.review_understanding ?? emptyTipTapDocumentJson(),
);

watch(
    () => props.requirement.review_understanding,
    (v) => {
        reviewUnderstandingJson.value = v ?? emptyTipTapDocumentJson();
    },
);

watch(reviewDialogOpen, (open) => {
    if (open) {
        reviewUnderstandingJson.value =
            props.requirement.review_understanding ?? emptyTipTapDocumentJson();
    }
});

defineOptions({
    layout: (pageProps: {
        project: ProjectSummary;
        requirement: RequirementDetail;
        can_manage_project: boolean;
    }) => ({
        breadcrumbs: [
            { title: 'Projects', href: projectsIndex.url() },
            {
                title: pageProps.project.name,
                href: pageProps.can_manage_project
                    ? projectsEdit.url(pageProps.project.id)
                    : requirementsIndex.url(pageProps.project.id),
            },
            { title: 'Requirements', href: requirementsIndex.url(pageProps.project.id) },
            {
                title: pageProps.requirement.title,
                href: requirementsShow.url({
                    project: pageProps.project.id,
                    requirement: pageProps.requirement.id,
                }),
            },
        ],
    }),
});
</script>

<template>

    <Head :title="`${requirement.title} · ${project.name}`" />

    <ConfirmDestructiveDialog v-model:open="taskDeleteOpen" title="Delete task?" :description="deleteTaskDescription"
        @confirm="executeTaskDelete" />

    <div class="flex flex-col gap-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <PageHeader :title="requirement.title" :description="`Project ${project.name}`" />
            <div class="flex flex-wrap gap-2">
                <Button v-if="can_update" as-child>
                    <Link :href="requirementsEdit.url({
                        project: project.id,
                        requirement: requirement.id,
                    })
                        ">
                        Edit
                    </Link>
                </Button>
                <Button v-if="can_mark_reviewed" type="button" variant="secondary" @click="reviewDialogOpen = true">
                    {{ requirement.review_understanding ? 'Update review understanding' : 'Submit review understanding'
                    }}
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="requirementsIndex.url(project.id)">Back to list</Link>
                </Button>
            </div>
        </div>

        <div class="mx-auto flex w-full flex-col gap-8">
            <div class="grid min-w-0 gap-6 lg:grid-cols-12 lg:items-stretch">
                <GlassCard class="flex h-full min-h-0 flex-col lg:col-span-7">
                    <div class="mb-6 space-y-1">
                        <h2 class="text-lg font-semibold">People</h2>
                        <p class="text-sm text-muted-foreground">
                            Ownership and review
                        </p>
                    </div>
                    <div class="grid flex-1 gap-3 text-sm">
                        <div>
                            <span class="text-muted-foreground">Created by</span>
                            {{ requirement.creator?.name ?? '—' }}
                        </div>
                        <div>
                            <span class="text-muted-foreground">Responsible</span>
                            {{ requirement.responsible_user?.name ?? '—' }}
                        </div>
                        <div>
                            <span class="text-muted-foreground">Reviewer</span>
                            {{ requirement.reviewer?.name ?? '—' }}
                        </div>
                        <div>
                            <span class="text-muted-foreground">Reviewed at</span>
                            {{
                                requirement.reviewed_at
                                    ? new Date(requirement.reviewed_at).toLocaleString()
                                    : '—'
                            }}
                        </div>
                        <div>
                            <span class="text-muted-foreground">Understanding confirmed</span>
                            <template v-if="requirement.understanding_confirmed_at">
                                {{ new Date(requirement.understanding_confirmed_at).toLocaleString() }}
                                <span class="text-muted-foreground">
                                    ({{ requirement.understanding_confirmed_by?.name ?? '—' }})
                                </span>
                            </template>
                            <template v-else>—</template>
                        </div>
                        <div class="text-muted-foreground">
                            Created {{ requirement.created_at ? new Date(requirement.created_at).toLocaleString() : '—'
                            }}
                            · Updated
                            {{ requirement.updated_at ? new Date(requirement.updated_at).toLocaleString() : '—' }}
                        </div>
                    </div>
                </GlassCard>

                <GlassCard class="flex h-full min-h-0 flex-col lg:col-span-5">
                    <div class="mb-6 space-y-1">
                        <h2 class="text-lg font-semibold">Clarification discussion</h2>
                        <p class="text-sm text-muted-foreground">
                            Ask questions and align with the team before finalizing review understanding.
                        </p>
                    </div>
                    <div class="flex min-h-0 flex-1 flex-col gap-4">
                        <div v-if="olderMessagesHref" class="flex shrink-0 justify-center border-b border-border pb-3">
                            <Button variant="outline" size="sm" as-child>
                                <Link :href="olderMessagesHref">Load older messages</Link>
                            </Button>
                        </div>
                        <div ref="chatScrollEl"
                            class="min-h-32 min-w-0 flex-1 space-y-3 overflow-y-auto rounded-xl border border-input bg-muted/20 p-3 text-sm lg:max-h-[min(32rem,calc(100vh-14rem))]">
                            <p v-if="requirement_chat_messages.data.length === 0" class="text-muted-foreground">
                                No messages yet. Start the thread below.
                            </p>
                            <div v-for="row in requirement_chat_messages.data" :key="row.id"
                                class="rounded-md bg-background p-2 shadow-xs"
                                :class="isCurrentUser(row.user?.id ?? 0) ? 'ms-5' : 'me-5'">
                                <div
                                    class="flex flex-wrap items-baseline justify-between gap-2 text-xs text-muted-foreground">
                                    <span class="font-medium text-foreground">{{ row.user?.name ?? 'Unknown' }}</span>
                                    <span>{{
                                        row.created_at ? new Date(row.created_at).toLocaleString() : '—'
                                        }}</span>
                                </div>
                                <p class="mt-1 whitespace-pre-wrap break-words">{{ row.body }}</p>
                            </div>
                        </div>
                        <div v-if="can_post_requirement_chat" class="shrink-0">
                            <Form v-bind="ProjectRequirementMessageController.store.form({
                                project: project.id,
                                requirement: requirement.id,
                            })
                                " class="grid gap-2" @success="onChatMessagePosted" v-slot="{ errors, processing }">
                                <Label for="requirement-chat-body">Message</Label>
                                <textarea id="requirement-chat-body" v-model="chatBody" name="body" rows="3" required
                                    :class="cn(
                                        'placeholder:text-muted-foreground border-input w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none',
                                        'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                        'aria-invalid:border-destructive',
                                    )
                                        " placeholder="Ask for clarification or share context…" />
                                <InputError :message="errors.body" />
                                <Button type="submit" :disabled="processing" class="w-fit">Send</Button>
                            </Form>
                        </div>
                        <p v-else class="shrink-0 text-xs text-muted-foreground">
                            You can view this thread but cannot post.
                        </p>
                    </div>
                </GlassCard>

                <GlassCard class="lg:col-span-12">
                    <div class="mb-6 space-y-1">
                        <h2 class="text-lg font-semibold">Tasks</h2>
                        <p class="text-sm text-muted-foreground">
                            Work linked to this requirement. Estimates:
                            {{ project.estimation_required ? 'required on this project.' : 'optional.' }}
                        </p>
                    </div>
                    <div class="overflow-x-auto">
                        <Dialog v-model:open="createTaskOpen">
                            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                                <DialogHeader>
                                    <DialogTitle>Add task</DialogTitle>
                                    <DialogDescription>
                                        This task will be linked to this requirement.
                                    </DialogDescription>
                                </DialogHeader>
                                <Form v-bind="ProjectTaskController.store.form({ project: project.id })"
                                    class="grid gap-4" @success="createTaskOpen = false"
                                    v-slot="{ errors, processing }">
                                    <input type="hidden" name="project_requirement_id" :value="requirement.id" />
                                    <div class="grid gap-2">
                                        <Label for="req-task-title">Title</Label>
                                        <Input id="req-task-title" name="title" type="text" required />
                                        <InputError :message="errors.title" />
                                    </div>
                                    <div class="grid gap-2">
                                        <Label for="req-task-description">Description</Label>
                                        <textarea id="req-task-description" name="description" rows="3"
                                            class="w-full rounded-xl border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30" />
                                        <InputError :message="errors.description" />
                                    </div>
                                    <div class="grid gap-2">
                                        <Label for="req-task-status">Status</Label>
                                        <TaskFormSelect id="req-task-status" name="status" v-model="createTaskStatus"
                                            required placeholder="Status" :options="taskStatusSelectOptions" />
                                        <InputError :message="errors.status" />
                                    </div>
                                    <div class="grid gap-2">
                                        <Label for="req-task-assignee">Assignee</Label>
                                        <TaskFormSelect id="req-task-assignee" name="assignee_user_id"
                                            v-model="createTaskAssignee" none-label="Unassigned"
                                            placeholder="Unassigned" :options="taskAssigneeSelectOptions" />
                                        <InputError :message="errors.assignee_user_id" />
                                    </div>
                                    <div class="grid gap-2">
                                        <Label for="req-task-parent">Parent task (subtask)</Label>
                                        <TaskFormSelect id="req-task-parent" name="parent_project_task_id"
                                            v-model="createTaskParent" placeholder="None"
                                            :options="taskParentSelectOptions" />
                                        <InputError :message="errors.parent_project_task_id" />
                                    </div>
                                    <div class="grid gap-2">
                                        <Label for="req-task-estimate">Estimate (minutes)</Label>
                                        <Input id="req-task-estimate" name="estimated_minutes" type="number" min="1"
                                            step="1" :required="project.estimation_required" />
                                        <InputError :message="errors.estimated_minutes" />
                                    </div>
                                    <DialogFooter class="gap-3">
                                        <Button type="button" variant="outline" @click="createTaskOpen = false">
                                            Cancel
                                        </Button>
                                        <Button type="submit" :disabled="processing">Create</Button>
                                    </DialogFooter>
                                </Form>
                            </DialogContent>
                        </Dialog>

                        <table class="w-full min-w-[640px] table-fixed text-left text-sm">
                            <thead class="border-b bg-muted/40">
                                <tr>
                                    <th class="w-[38%] px-4 py-3 font-medium">Title</th>
                                    <th class="px-4 py-3 font-medium">Status</th>
                                    <th class="px-4 py-3 font-medium">Assignee</th>
                                    <th class="px-4 py-3 font-medium">Estimate</th>
                                    <th class="px-4 py-3 text-right font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="task in requirement_tasks" :key="task.id"
                                    class="border-b border-border/60 last:border-0">
                                    <td class="max-w-0 px-4 py-3 align-top" :style="{
                                        paddingLeft: `calc(0.75rem + ${task.tree_depth} * 1.25rem)`,
                                    }">
                                        <div class="flex min-w-0 items-start gap-1.5">
                                            <CornerDownRight v-if="task.tree_depth > 0"
                                                class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                                aria-hidden="true" />
                                            <div class="min-w-0 flex-1">
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
                                    <td class="px-4 py-3 text-muted-foreground">{{ task.status_label }}</td>
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{ task.assignee?.name ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{ formatTaskMinutes(task.estimated_minutes) }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex justify-end gap-2">
                                            <Button v-if="task.can_update" variant="outline" size="sm" as-child>
                                                <Link :href="projectTasksIndex.url(project.id, {
                                                    query: { task_filter: 'linked' },
                                                })
                                                    ">
                                                    Manage on list
                                                </Link>
                                            </Button>
                                            <Button v-if="task.can_delete" variant="outline" size="sm"
                                                class="text-destructive hover:bg-destructive/10" type="button"
                                                @click="openTaskDelete(task)">
                                                Delete
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="requirement_tasks.length === 0">
                                    <td colspan="5" class="px-4 py-8 text-center text-muted-foreground">
                                        No tasks yet for this requirement.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </GlassCard>

                <GlassCard v-if="requirement.review_understanding" class="lg:col-span-12">
                    <div class="mb-6 space-y-1">
                        <h2 class="text-lg font-semibold">Review understanding</h2>
                        <p class="text-sm text-muted-foreground">
                            What the reviewing party recorded about this requirement
                        </p>
                    </div>
                    <div>
                        <RequirementRichTextViewer :json="requirement.review_understanding" />
                    </div>
                </GlassCard>

                <GlassCard v-if="can_confirm_understanding" class="lg:col-span-12">
                    <div class="mb-6 space-y-1">
                        <h2 class="text-lg font-semibold">Confirm understanding</h2>
                        <p class="text-sm text-muted-foreground">
                            Confirm that this matches your intent as creator or responsible person.
                        </p>
                    </div>
                    <div>
                        <Form v-bind="ProjectRequirementController.confirmUnderstanding.form({
                            project: project.id,
                            requirement: requirement.id,
                        })
                            " class="flex flex-col gap-3" v-slot="{ processing }">
                            <Button type="submit" :disabled="processing">Confirm understanding</Button>
                        </Form>
                    </div>
                </GlassCard>

                <GlassCard class="lg:col-span-12">
                    <div class="mb-6 space-y-1">
                        <h2 class="text-lg font-semibold">Description</h2>
                    </div>
                    <div>
                        <RequirementRichTextViewer v-if="requirement.description" :json="requirement.description" />
                        <p v-else class="text-sm text-muted-foreground">No description.</p>
                    </div>
                </GlassCard>
            </div>
        </div>

        <Dialog v-if="can_mark_reviewed" v-model:open="reviewDialogOpen">
            <DialogContent class="sm:max-w-2xl" :show-close-button="true">
                <DialogHeader>
                    <DialogTitle>Review understanding</DialogTitle>
                    <DialogDescription>
                        Describe how you interpret this requirement. Saving records the time of review and clears any
                        prior confirmation until the owner confirms again.
                    </DialogDescription>
                </DialogHeader>
                <Form v-bind="ProjectRequirementController.markReviewed.form({
                    project: project.id,
                    requirement: requirement.id,
                })
                    " class="grid gap-4" @success="reviewDialogOpen = false" v-slot="{ errors, processing }">
                    <div class="grid gap-2">
                        <Label for="review-understanding-editor">Your understanding</Label>
                        <RequirementRichTextEditor id="review-understanding-editor" v-model="reviewUnderstandingJson"
                            input-name="review_understanding" />
                        <InputError :message="errors.review_understanding" />
                    </div>
                    <DialogFooter class="gap-2 sm:justify-end">
                        <DialogClose as-child>
                            <Button type="button" variant="outline">Cancel</Button>
                        </DialogClose>
                        <Button type="submit" :disabled="processing">Save</Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
