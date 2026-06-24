<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { CornerDownRight } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import ProjectRequirementController from '@/actions/App/Http/Controllers/Admin/ProjectRequirementController';
import ProjectRequirementMessageController from '@/actions/App/Http/Controllers/Admin/ProjectRequirementMessageController';
import ProjectTaskController from '@/actions/App/Http/Controllers/Admin/ProjectTaskController';
import ConfirmDestructiveDialog from '@/components/ConfirmDestructiveDialog.vue';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import FormSelect from '@/components/FormSelect.vue';
import InputError from '@/components/InputError.vue';
import RequirementPhaseSettingsCard from '@/components/requirements/RequirementPhaseSettingsCard.vue';
import type {RequirementPhaseSettings} from '@/components/requirements/RequirementPhaseSettingsCard.vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
import RichTextViewer from '@/components/RichTextViewer.vue';
import TableIconAction from '@/components/TableIconAction.vue';
import { Badge } from '@/components/ui/badge';
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
import { buildPhaseSelectOptions } from '@/lib/requirementPhaseOptions';
import { emptyTipTapDocumentJson } from '@/lib/tiptapDocument';
import { cn, isCurrentUser } from '@/lib/utils';
import { index as projectsIndex, show as projectsShow } from '@/routes/admin/projects/index';
import { show as estimationShow } from '@/routes/admin/projects/requirements/estimation/index';
import {
    edit as requirementsEdit,
    index as requirementsIndex,
    show as requirementsShow,
} from '@/routes/admin/projects/requirements/index';
import {
    create as projectTasksCreate,
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
    phase: number | null;
    phase_label: string | null;
    children_count: number;
    tree_depth: number;
    can_update: boolean;
    can_delete: boolean;
    estimation_source: 'transferred' | 'ad_hoc' | null;
};

type EstimationSummary = {
    id: number;
    version: number;
    status: string;
    status_label: string;
    total_minutes: number;
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
    understanding_confirmed: boolean;
    can_open_estimation: boolean;
    can_create_estimation: boolean;
    estimation_summary: EstimationSummary | null;
    phase_settings: RequirementPhaseSettings;
    can_update_phase_settings: boolean;
    linked_proposals: {
        id: number;
        title: string;
        status: string;
        status_label: string;
        show_url: string;
    }[];
}>();

const page = usePage();

const reviewDialogOpen = ref(false);

const createTaskHref = computed(() =>
    projectTasksCreate.url(
        { project: props.project.id },
        {
            query: {
                requirement: String(props.requirement.id),
                return: requirementsShow.url({
                    project: props.project.id,
                    requirement: props.requirement.id,
                }),
            },
        },
    ),
);

function goToCreateTask(): void {
    if (!props.can_create_tasks) {
        return;
    }

    router.visit(createTaskHref.value);
}

function onEmptyTasksKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        goToCreateTask();
    }
}

const taskDeleteOpen = ref(false);
const taskPendingDelete = ref<RequirementTaskRow | null>(null);

const showRequirementPhaseField = computed(
    () => props.phase_settings.requires_phase_selection,
);

const taskPhaseFilter = ref('');

const taskPhaseFilterOptions = computed(() =>
    buildPhaseSelectOptions(props.phase_settings.max_generated_phase),
);

const filteredRequirementTasks = computed(() => {
    if (taskPhaseFilter.value === '') {
        return props.requirement_tasks;
    }

    const phase = Number(taskPhaseFilter.value);
    const matchingIds = new Set(
        props.requirement_tasks
            .filter((task) => task.phase === phase)
            .map((task) => task.id),
    );

    if (matchingIds.size === 0) {
        return [];
    }

    const byId = new Map(props.requirement_tasks.map((task) => [task.id, task]));
    const visibleIds = new Set(matchingIds);

    for (const id of matchingIds) {
        let cursor = byId.get(id);

        while (cursor?.parent_project_task_id !== null && cursor?.parent_project_task_id !== undefined) {
            visibleIds.add(cursor.parent_project_task_id);
            cursor = byId.get(cursor.parent_project_task_id);
        }
    }

    return props.requirement_tasks.filter((task) => visibleIds.has(task.id));
});

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

    const rawUrl = page.url;
    const queryPart = rawUrl.includes('?') ? rawUrl.slice(rawUrl.indexOf('?') + 1) : '';
    const params = new URLSearchParams(queryPart);

    if (params.get('add_task') === '1' && props.can_create_tasks) {
        goToCreateTask();
    }
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
                href: projectsShow.url(pageProps.project.id),
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
            <div class="flex flex-wrap gap-1">
                <TableIconAction
                    v-if="can_update"
                    icon="pencil"
                    label="Edit requirement"
                    :href="requirementsEdit.url({
                        project: project.id,
                        requirement: requirement.id,
                    })"
                />
                <TableIconAction
                    v-if="can_mark_reviewed"
                    variant="secondary"
                    icon="file-text"
                    :label="requirement.review_understanding ? 'Update review understanding' : 'Submit review understanding'"
                    @click="reviewDialogOpen = true"
                />
                <TableIconAction
                    icon="arrow-left"
                    label="Back to list"
                    :href="requirementsIndex.url(project.id)"
                />
            </div>
        </div>

        <GlassCard v-if="linked_proposals.length > 0" class="p-6">
            <div class="mb-4 space-y-1">
                <h2 class="text-lg font-semibold">Linked proposals</h2>
                <p class="text-sm text-muted-foreground">Proposals associated with this requirement.</p>
            </div>
            <ul class="space-y-2">
                <li v-for="proposal in linked_proposals" :key="proposal.id"
                    class="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border/60 px-3 py-2">
                    <Link class="font-medium underline-offset-4 hover:underline" :href="proposal.show_url">
                        {{ proposal.title }}
                    </Link>
                    <Badge variant="outline">{{ proposal.status_label }}</Badge>
                </li>
            </ul>
        </GlassCard>

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
                            <TableIconAction
                                icon="chevrons-up"
                                label="Load older messages"
                                :href="olderMessagesHref"
                            />
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
                                <TableIconAction
                                    type="submit"
                                    icon="send"
                                    label="Send message"
                                    :disabled="processing"
                                />
                            </Form>
                        </div>
                        <p v-else class="shrink-0 text-xs text-muted-foreground">
                            You can view this thread but cannot post.
                        </p>
                    </div>
                </GlassCard>

                <GlassCard v-if="understanding_confirmed" class="lg:col-span-12">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="space-y-1">
                            <h2 class="text-lg font-semibold">Estimation</h2>
                            <p v-if="estimation_summary" class="text-sm text-muted-foreground">
                                {{ estimation_summary.status_label }} · v{{ estimation_summary.version }}
                                · {{ formatTaskMinutes(estimation_summary.total_minutes) }}
                            </p>
                            <p v-else class="text-sm text-muted-foreground">
                                Plan work for this requirement before creating tasks.
                            </p>
                        </div>
                        <TableIconAction
                            v-if="can_open_estimation"
                            icon="calculator"
                            :label="can_create_estimation ? 'Start estimation' : 'Manage estimation'"
                            :href="estimationShow.url({
                                project: project.id,
                                requirement: requirement.id,
                            })"
                        />
                    </div>
                </GlassCard>

                <RequirementPhaseSettingsCard :project-id="project.id" :requirement-id="requirement.id"
                    :phase-settings="phase_settings" :can-update="can_update_phase_settings" />

                <GlassCard class="lg:col-span-12">
                    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="space-y-1">
                            <h2 class="text-lg font-semibold">Tasks</h2>
                            <p class="text-sm text-muted-foreground">
                                Work linked to this requirement. Estimates:
                                {{ project.estimation_required ? 'required on this project.' : 'optional.' }}
                            </p>
                        </div>
                        <div class="flex flex-wrap items-end gap-3">
                            <div v-if="requirement_tasks.length > 0" class="grid gap-1">
                                <Label class="text-xs text-muted-foreground" for="req-task-phase-filter">Phase</Label>
                                <FormSelect id="req-task-phase-filter" name="task_phase_filter"
                                    class="min-w-[10rem]" v-model="taskPhaseFilter" :options="taskPhaseFilterOptions"
                                    placeholder="Any phase" none-label="Any phase" exclude-from-submit />
                            </div>
                            <TableIconAction
                                v-if="can_create_tasks"
                                icon="plus"
                                label="Add task"
                                :href="createTaskHref"
                            />
                        </div>
                    </div>
                    <div class="md:overflow-x-auto">
                        <table data-responsive-table
                            class="data-table-responsive w-full table-fixed text-left text-sm md:min-w-[640px]"
                            style="--data-table-min-width: 640px">
                            <thead class="border-b bg-muted/40">
                                <tr>
                                    <th class="w-[30%] px-4 py-3 font-medium">Title</th>
                                    <th class="px-4 py-3 font-medium">Status</th>
                                    <th class="px-4 py-3 font-medium">Assignee</th>
                                    <th v-if="showRequirementPhaseField" class="px-4 py-3 font-medium">Phase</th>
                                    <th class="px-4 py-3 font-medium">Estimate</th>
                                    <th class="px-4 py-3 text-right font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="task in filteredRequirementTasks" :key="task.id"
                                    class="border-b border-border/60 last:border-0">
                                    <td data-label="Title" class="max-w-0 px-4 py-3 align-top" :style="{
                                        paddingLeft: `calc(0.75rem + ${task.tree_depth} * 1.25rem)`,
                                    }">
                                        <div class="flex min-w-0 items-start gap-1.5">
                                            <CornerDownRight v-if="task.tree_depth > 0"
                                                class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                                aria-hidden="true" />
                                            <div class="min-w-0 flex-1">
                                                <span v-if="task.estimation_source === 'transferred'"
                                                    class="mb-0.5 inline-block rounded bg-success/15 px-1.5 py-0.5 text-xs font-medium text-success">
                                                    From estimation
                                                </span>
                                                <span v-else-if="task.estimation_source === 'ad_hoc'"
                                                    class="mb-0.5 inline-block rounded bg-info/15 px-1.5 py-0.5 text-xs font-medium text-info">
                                                    New task
                                                </span>
                                                <p class="font-medium text-foreground line-clamp-2 break-words" :title="task.title">
                                                    {{ task.title }}
                                                </p>
                                                <span v-if="task.children_count > 0"
                                                    class="mt-0.5 block text-xs text-muted-foreground">
                                                    ({{ task.children_count }} subtasks)
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td data-label="Status" class="px-4 py-3 text-muted-foreground">{{ task.status_label
                                    }}</td>
                                    <td v-if="showRequirementPhaseField" data-label="Phase"
                                        class="px-4 py-3 text-muted-foreground">
                                        {{ task.phase_label ?? '—' }}
                                    </td>
                                    <td data-label="Assignee" class="px-4 py-3 text-muted-foreground">
                                        {{ task.assignee?.name ?? '—' }}
                                    </td>
                                    <td data-label="Estimate" class="px-4 py-3 text-muted-foreground">
                                        {{ formatTaskMinutes(task.estimated_minutes) }}
                                    </td>
                                    <td data-label="Actions" class="px-4 py-3 text-right">
                                        <div class="flex justify-end gap-1">
                                            <TableIconAction
                                                icon="eye"
                                                label="View task"
                                                :href="projectTasksShow.url({
                                                    project: project.id,
                                                    task: task.id,
                                                })"
                                            />
                                            <TableIconAction
                                                v-if="task.can_update"
                                                icon="external-link"
                                                label="Manage on list"
                                                :href="projectTasksIndex.url(project.id, {
                                                    query: { task_filter: 'linked' },
                                                })"
                                            />
                                            <TableIconAction
                                                v-if="task.can_delete"
                                                icon="trash"
                                                label="Delete"
                                                destructive
                                                @click="openTaskDelete(task)"
                                            />
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="requirement_tasks.length === 0">
                                    <td data-label="" colspan="5" class="px-4 py-8 text-center text-muted-foreground"
                                        :class="can_create_tasks ? 'cursor-pointer hover:bg-muted/30' : ''"
                                        :role="can_create_tasks ? 'button' : undefined"
                                        :tabindex="can_create_tasks ? 0 : undefined" @click="goToCreateTask"
                                        @keydown="onEmptyTasksKeydown">
                                        <template v-if="can_create_tasks">
                                            No tasks yet. Click here or use Add task to create one.
                                        </template>
                                        <template v-else>
                                            No tasks yet for this requirement.
                                        </template>
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
                        <RichTextViewer :json="requirement.review_understanding" />
                    </div>
                </GlassCard>

                <GlassCard v-if="can_confirm_understanding" class="lg:col-span-12">
                    <div class="mb-6 space-y-1">
                        <h2 class="text-lg font-semibold">Confirm understanding</h2>
                        <p class="text-sm text-muted-foreground">
                            Confirm that this matches your intent as the person who created this requirement.
                        </p>
                    </div>
                    <div>
                        <Form v-bind="ProjectRequirementController.confirmUnderstanding.form({
                            project: project.id,
                            requirement: requirement.id,
                        })
                            " class="flex flex-col gap-3" v-slot="{ processing }">
                            <TableIconAction
                                type="submit"
                                icon="check-circle"
                                label="Confirm understanding"
                                :disabled="processing"
                            />
                        </Form>
                    </div>
                </GlassCard>

                <GlassCard class="lg:col-span-12">
                    <div class="mb-6 space-y-1">
                        <h2 class="text-lg font-semibold">Description</h2>
                    </div>
                    <div>
                        <RichTextViewer v-if="requirement.description" :json="requirement.description" />
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
                        <RichTextEditor id="review-understanding-editor" v-model="reviewUnderstandingJson"
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
