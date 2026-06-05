<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { CornerDownRight, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import ProjectMetadataController from '@/actions/App/Http/Controllers/Admin/ProjectMetadataController';
import ProjectRequirementController from '@/actions/App/Http/Controllers/Admin/ProjectRequirementController';
import ProjectTagController from '@/actions/App/Http/Controllers/Admin/ProjectTagController';
import ProjectTaskController from '@/actions/App/Http/Controllers/Admin/ProjectTaskController';
import TaskTimeEntryController from '@/actions/App/Http/Controllers/Admin/TaskTimeEntryController';
import ConfirmDestructiveDialog from '@/components/ConfirmDestructiveDialog.vue';
import DataTable from '@/components/dashboard/DataTable.vue';
import DataTableTd from '@/components/dashboard/DataTableTd.vue';
import DataTableTh from '@/components/dashboard/DataTableTh.vue';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import InputError from '@/components/InputError.vue';
import RequirementRichTextEditor from '@/components/RequirementRichTextEditor.vue';
import TaskFormSelect from '@/components/TaskFormSelect.vue';
import TypeaheadInput from '@/components/TypeaheadInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { emptyTipTapDocumentJson } from '@/lib/tiptapDocument';
import { buildPhaseSelectOptions, requiresPhaseSelection } from '@/lib/requirementPhaseOptions';
import { formatTaskMinutes } from '@/lib/formatTaskMinutes';
import { edit as projectsEdit, index as projectsIndex, show as projectsShow } from '@/routes/admin/projects/index';
import {
    index as requirementsIndex,
    show as requirementsShow,
} from '@/routes/admin/projects/requirements/index';
import { index as projectTasksIndex, show as projectTasksShow } from '@/routes/admin/projects/tasks/index';

type UserBrief = {
    id: number;
    name: string;
    email: string;
} | null;

type TeamSummary = {
    id: number;
    name: string;
};

type ProjectDetail = {
    id: number;
    name: string;
    code: string | null;
    description: string | null;
    estimation_required: boolean;
    client_user: UserBrief;
    lead_user: UserBrief;
    teams: TeamSummary[];
};

type TagRow = {
    id: number;
    name: string;
};

type MetadataRow = {
    id: number;
    key: string;
    value: string;
};

type RequirementRow = {
    id: number;
    title: string;
    description_preview: string | null;
    reviewed_at: string | null;
    understanding_confirmed_at: string | null;
    created_at: string | null;
    creator: UserBrief;
    responsible_user: UserBrief;
    reviewer: UserBrief;
    can_update: boolean;
    can_delete: boolean;
};

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
};

type TimeEntryRow = {
    id: number;
    project_task_id: number;
    task_title: string | null | undefined;
    user: UserBrief;
    started_at: string | null;
    ended_at: string | null;
    duration_seconds: number;
    is_running: boolean;
    is_paused: boolean;
    source: string;
    notes: string | null;
    can_update: boolean;
    can_delete: boolean;
};

type Option = { value: string; label: string };
type UserOption = { value: number; label: string };
type RequirementOption = { value: number; label: string; max_generated_phase: number };
type AssignableUser = { id: number; name: string; email: string };

const props = defineProps<{
    project: ProjectDetail;
    tags: TagRow[];
    metadata: MetadataRow[];
    requirements: RequirementRow[];
    requirements_total: number;
    tasks: TaskRow[];
    tasks_total: number;
    time_entries: TimeEntryRow[];
    time_entries_total: number;
    can_create_requirements: boolean;
    can_create_tasks: boolean;
    can_manage_project: boolean;
    can_manage_tags_metadata: boolean;
    can_create_time_entries: boolean;
    assignable_responsibles: AssignableUser[];
    assignable_users: UserOption[];
    requirement_options: RequirementOption[];
    task_options: UserOption[];
    status_options: Option[];
    working_hours: {
        start: string;
        end: string;
    };
}>();

defineOptions({
    layout: (pageProps: { project: ProjectDetail }) => ({
        breadcrumbs: [
            { title: 'Projects', href: projectsIndex.url() },
            {
                title: pageProps.project.name,
                href: projectsShow.url(pageProps.project.id),
            },
        ],
    }),
});

const tagInput = ref('');
const tagOpen = ref(false);
const metadataKeyInput = ref('');
const metadataValueInput = ref('');
const metadataAddOpen = ref(false);
const metadataEditOpen = ref(false);
const editingMetadata = ref<MetadataRow | null>(null);
const metadataEditValue = ref('');

const requirementOpen = ref(false);
const requirementDescription = ref(emptyTipTapDocumentJson());
const responsibleUserId = ref('');
const requirementTitle = ref('');
const requirementMaxPhase = ref('1');

const taskOpen = ref(false);
const createStatus = ref('to_do');
const createAssignee = ref('');
const createRequirement = ref('');
const createPhase = ref('1');
const createParent = ref('');
const createTitle = ref('');
const createDisplayAfterAt = ref('');
const createNotifyAt = ref('');

const timeEntryOpen = ref(false);
const timeEntryTaskId = ref('');
const manualTimeOnly = ref(false);
const manualStart = ref('');
const manualEnd = ref('');
const manualNotes = ref('');
const manualDurationMinutes = ref('30');

const editEntryOpen = ref(false);
const editTimeOnly = ref(false);
const editingEntry = ref<TimeEntryRow | null>(null);
const editStart = ref('');
const editEnd = ref('');
const editNotes = ref('');
const editDurationMinutes = ref('');

const durationOnlyHint = computed(
    () => 'Duration is counted back from the current time. Start and end times are not required.',
);

const entryDeleteOpen = ref(false);
const entryPendingDelete = ref<TimeEntryRow | null>(null);

const statusSelectOptions = computed(() =>
    props.status_options.map((o) => ({ value: o.value, label: o.label })),
);

const assigneeSelectOptions = computed(() =>
    props.assignable_users.map((u) => ({ value: String(u.value), label: u.label })),
);

const requirementSelectOptions = computed(() =>
    props.requirement_options.map((r) => ({ value: String(r.value), label: r.label })),
);

function maxPhaseForRequirement(requirementId: string): number {
    if (requirementId === '') {
        return 1;
    }

    const requirement = props.requirement_options.find((r) => String(r.value) === requirementId);

    return requirement?.max_generated_phase ?? 1;
}

const createPhaseSelectOptions = computed(() =>
    buildPhaseSelectOptions(maxPhaseForRequirement(createRequirement.value)),
);

const showCreatePhaseField = computed(
    () => createRequirement.value !== '' && requiresPhaseSelection(maxPhaseForRequirement(createRequirement.value)),
);

const showPhaseColumn = computed(() =>
    props.requirement_options.some((requirement) => requiresPhaseSelection(requirement.max_generated_phase)),
);

const taskSelectOptions = computed(() =>
    props.task_options.map((t) => ({ value: String(t.value), label: t.label })),
);

const parentSelectOptions = computed(() =>
    props.tasks.map((task) => ({
        value: String(task.id),
        label: `${task.tree_depth > 0 ? '— '.repeat(task.tree_depth) : ''}${task.title}`,
    })),
);

const responsibleLabel = computed(() => {
    if (responsibleUserId.value === '') {
        return 'Use default (project lead / first team head)';
    }

    const user = props.assignable_responsibles.find(
        (candidate) => String(candidate.id) === responsibleUserId.value,
    );

    return user ? `${user.name} (${user.email})` : 'Use default (project lead / first team head)';
});

const timeEntryFormBinding = computed(() => {
    const taskId = Number(timeEntryTaskId.value);

    if (!Number.isFinite(taskId) || taskId <= 0) {
        return null;
    }

    return TaskTimeEntryController.store.form({
        project: props.project.id,
        task: taskId,
    });
});

const editEntryFormBinding = computed(() => {
    const entry = editingEntry.value;

    if (entry === null) {
        return null;
    }

    return TaskTimeEntryController.update.form({
        project: props.project.id,
        task: entry.project_task_id,
        time_entry: entry.id,
    });
});

function pad(value: number): string {
    return String(value).padStart(2, '0');
}

function toLocalInputValue(iso: string | null): string {
    if (iso === null) {
        return '';
    }

    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function formatDuration(seconds: number): string {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);

    if (hours <= 0) {
        return `${minutes}m`;
    }

    if (minutes === 0) {
        return `${hours}h`;
    }

    return `${hours}h ${minutes}m`;
}

function formatEntryRange(row: TimeEntryRow): string {
    if (row.started_at === null) {
        return '—';
    }

    const startLabel = new Date(row.started_at).toLocaleString();

    if (row.is_running) {
        return row.is_paused ? `${startLabel} → paused` : `${startLabel} → running`;
    }

    if (row.ended_at === null) {
        return startLabel;
    }

    return `${startLabel} → ${new Date(row.ended_at).toLocaleString()}`;
}

function removeTag(tag: TagRow): void {
    router.delete(
        ProjectTagController.destroy.url({
            project: props.project.id,
            tag: tag.id,
        }),
        { preserveScroll: true },
    );
}

function removeMetadata(row: MetadataRow): void {
    router.delete(
        ProjectMetadataController.destroy.url({
            project: props.project.id,
            metadata: row.id,
        }),
        { preserveScroll: true },
    );
}

function resetTagDialog(): void {
    tagInput.value = '';
}

function resetMetadataAddDialog(): void {
    metadataKeyInput.value = '';
    metadataValueInput.value = '';
}

function openMetadataEdit(row: MetadataRow): void {
    editingMetadata.value = row;
    metadataEditValue.value = row.value;
    metadataEditOpen.value = true;
}

function closeMetadataEdit(): void {
    metadataEditOpen.value = false;
    editingMetadata.value = null;
    metadataEditValue.value = '';
}

function resetRequirementDialog(): void {
    requirementTitle.value = '';
    requirementDescription.value = emptyTipTapDocumentJson();
    responsibleUserId.value = '';
    requirementMaxPhase.value = '1';
}

function resetTaskDialog(): void {
    createStatus.value = 'to_do';
    createAssignee.value = '';
    createRequirement.value = '';
    createPhase.value = '1';
    createParent.value = '';
    createTitle.value = '';
    createDisplayAfterAt.value = '';
    createNotifyAt.value = '';
}

function resetTimeEntryDialog(): void {
    timeEntryTaskId.value = '';
    manualTimeOnly.value = false;
    manualStart.value = '';
    manualEnd.value = '';
    manualNotes.value = '';
    manualDurationMinutes.value = '30';
}

function openTimeEntryDialog(): void {
    const nowDate = new Date();
    const startDate = new Date(nowDate.getTime() - 30 * 60 * 1000);
    manualTimeOnly.value = false;
    manualStart.value = toLocalInputValue(startDate.toISOString());
    manualEnd.value = toLocalInputValue(nowDate.toISOString());
    manualNotes.value = '';
    manualDurationMinutes.value = '30';
    timeEntryOpen.value = true;
}

function openEditEntry(row: TimeEntryRow): void {
    editingEntry.value = row;
    editTimeOnly.value = false;
    editStart.value = toLocalInputValue(row.started_at);
    editEnd.value = toLocalInputValue(row.ended_at);
    editNotes.value = row.notes ?? '';
    editDurationMinutes.value = String(Math.max(1, Math.round(row.duration_seconds / 60)));
    editEntryOpen.value = true;
}

function closeEditEntry(): void {
    editEntryOpen.value = false;
    editingEntry.value = null;
}

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
            task: row.project_task_id,
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

    return `Delete time entry from ${formatEntryRange(row)}? This cannot be undone.`;
});

watch(tagOpen, (open) => {
    if (open) {
        resetTagDialog();
    }
});

watch(metadataAddOpen, (open) => {
    if (open) {
        resetMetadataAddDialog();
    }
});

watch(requirementOpen, (open) => {
    if (open) {
        resetRequirementDialog();
    }
});

watch(taskOpen, (open) => {
    if (open) {
        resetTaskDialog();
    }
});

watch(createRequirement, () => {
    createPhase.value = '1';
});

watch(timeEntryOpen, (open) => {
    if (open) {
        resetTimeEntryDialog();
        openTimeEntryDialog();
    }
});
</script>

<template>

    <Head :title="project.name" />

    <ConfirmDestructiveDialog v-model:open="entryDeleteOpen" title="Delete time entry?"
        :description="entryDeleteDescription" @confirm="executeEntryDelete" />

    <div class="flex flex-col gap-8">
        <PageHeader :title="project.name"
            :description="project.description ?? 'Project overview, tags, metadata, requirements, tasks, and time entries.'">
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="requirementsIndex.url(project.id)">All requirements</Link>
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="projectTasksIndex.url(project.id)">All tasks</Link>
                </Button>
                <Button v-if="can_manage_project" variant="outline" as-child>
                    <Link :href="projectsEdit(project.id)">Edit project</Link>
                </Button>
            </template>
        </PageHeader>

        <GlassCard class="p-6">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <p class="text-xs text-muted-foreground">Code</p>
                    <p class="font-medium">{{ project.code ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">Client</p>
                    <p class="font-medium">{{ project.client_user?.name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">Lead</p>
                    <p class="font-medium">{{ project.lead_user?.name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">Teams</p>
                    <p class="font-medium">
                        {{project.teams.map((team) => team.name).join(', ') || '—'}}
                    </p>
                </div>
            </div>
        </GlassCard>

        <GlassCard class="p-6">
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold">Tags</h2>
                    <p class="text-sm text-muted-foreground">Labels such as manage-user or reusable-toggle-button.</p>
                </div>
                <Button v-if="can_manage_tags_metadata" type="button" @click="tagOpen = true">
                    Add tag
                </Button>
            </div>

            <div class="flex flex-wrap gap-2">
                <Badge v-for="tag in tags" :key="tag.id" variant="secondary" class="gap-1 pr-1">
                    {{ tag.name }}
                    <button
                        v-if="can_manage_tags_metadata"
                        type="button"
                        class="rounded-sm p-0.5 hover:bg-muted"
                        :aria-label="`Remove tag ${tag.name}`"
                        @click="removeTag(tag)"
                    >
                        <X class="size-3" />
                    </button>
                </Badge>
                <span v-if="tags.length === 0" class="text-sm text-muted-foreground">No tags yet.</span>
            </div>
        </GlassCard>

        <GlassCard class="p-6">
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold">Metadata</h2>
                    <p class="text-sm text-muted-foreground">Custom key-value pairs such as framework: laravel.</p>
                </div>
                <Button v-if="can_manage_tags_metadata" type="button" @click="metadataAddOpen = true">
                    Add metadata
                </Button>
            </div>

            <DataTable v-if="metadata.length > 0">
                <thead>
                    <tr class="border-b border-border/60 bg-muted/40 backdrop-blur-sm">
                        <DataTableTh>Key</DataTableTh>
                        <DataTableTh>Value</DataTableTh>
                        <DataTableTh v-if="can_manage_tags_metadata" class="text-right">Actions</DataTableTh>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in metadata"
                        :key="row.id"
                        class="border-b border-border/40 transition-colors even:bg-muted/15 hover:bg-muted/30"
                    >
                        <DataTableTd label="Key" class="font-medium">{{ row.key }}</DataTableTd>
                        <DataTableTd label="Value" class="text-muted-foreground">{{ row.value }}</DataTableTd>
                        <DataTableTd v-if="can_manage_tags_metadata" label="Actions" class="text-right">
                            <div class="flex justify-end gap-2">
                                <Button variant="ghost" size="sm" type="button" @click="openMetadataEdit(row)">
                                    Edit
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    type="button"
                                    class="text-destructive"
                                    @click="removeMetadata(row)"
                                >
                                    Remove
                                </Button>
                            </div>
                        </DataTableTd>
                    </tr>
                </tbody>
            </DataTable>
            <p v-else class="text-sm text-muted-foreground">No metadata yet.</p>
        </GlassCard>

        <section class="flex flex-col gap-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold">Requirements</h2>
                    <p class="text-sm text-muted-foreground">
                        Showing {{ requirements.length }} of {{ requirements_total }} requirements.
                    </p>
                </div>
                <Button v-if="can_create_requirements" type="button" @click="requirementOpen = true">
                    Add requirement
                </Button>
            </div>

            <DataTable>
                <thead>
                    <tr class="border-b border-border/60 bg-muted/40 backdrop-blur-sm">
                        <DataTableTh>Title</DataTableTh>
                        <DataTableTh>Responsible</DataTableTh>
                        <DataTableTh>Review</DataTableTh>
                        <DataTableTh class="text-right">Actions</DataTableTh>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in requirements" :key="row.id"
                        class="border-b border-border/40 transition-colors even:bg-muted/15 hover:bg-muted/30">
                        <DataTableTd label="Title" class="align-top">
                            <div class="font-medium">{{ row.title }}</div>
                            <p v-if="row.description_preview" class="mt-1 line-clamp-2 text-xs text-muted-foreground">
                                {{ row.description_preview }}
                            </p>
                        </DataTableTd>
                        <DataTableTd label="Responsible" class="text-muted-foreground">
                            {{ row.responsible_user?.name ?? '—' }}
                        </DataTableTd>
                        <DataTableTd label="Review" class="text-muted-foreground">
                            <span v-if="row.understanding_confirmed_at">Confirmed</span>
                            <span v-else-if="row.reviewed_at">Awaiting confirmation</span>
                            <span v-else>—</span>
                        </DataTableTd>
                        <DataTableTd label="Actions" class="text-right">
                            <Button variant="ghost" size="sm" as-child>
                                <Link :href="requirementsShow.url({
                                    project: project.id,
                                    requirement: row.id,
                                })">
                                    View
                                </Link>
                            </Button>
                        </DataTableTd>
                    </tr>
                </tbody>
            </DataTable>
        </section>

        <section class="flex flex-col gap-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold">Tasks</h2>
                    <p class="text-sm text-muted-foreground">
                        Showing {{ tasks.length }} of {{ tasks_total }} tasks.
                    </p>
                </div>
                <Button v-if="can_create_tasks" type="button" @click="taskOpen = true">
                    Add task
                </Button>
            </div>

            <DataTable>
                <thead>
                    <tr class="border-b border-border/60 bg-muted/40 backdrop-blur-sm">
                        <DataTableTh>Title</DataTableTh>
                        <DataTableTh>Status</DataTableTh>
                        <DataTableTh>Assignee</DataTableTh>
                        <DataTableTh v-if="showPhaseColumn">Phase</DataTableTh>
                        <DataTableTh>Estimate</DataTableTh>
                        <DataTableTh class="text-right">Actions</DataTableTh>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in tasks" :key="row.id"
                        class="border-b border-border/40 transition-colors even:bg-muted/15 hover:bg-muted/30">
                        <DataTableTd label="Title" class="align-top">
                            <div class="flex items-start gap-1 font-medium">
                                <CornerDownRight v-if="row.tree_depth > 0"
                                    class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                <span>{{ row.title }}</span>
                            </div>
                            <p v-if="row.requirement_title" class="mt-1 text-xs text-muted-foreground">
                                {{ row.requirement_title }}
                            </p>
                        </DataTableTd>
                        <DataTableTd label="Status">{{ row.status_label }}</DataTableTd>
                        <DataTableTd label="Assignee" class="text-muted-foreground">
                            {{ row.assignee?.name ?? '—' }}
                        </DataTableTd>
                        <DataTableTd v-if="showPhaseColumn" label="Phase" class="text-muted-foreground">
                            {{ row.phase_label ?? '—' }}
                        </DataTableTd>
                        <DataTableTd label="Estimate">{{ formatTaskMinutes(row.estimated_minutes) }}</DataTableTd>
                        <DataTableTd label="Actions" class="text-right">
                            <Button variant="ghost" size="sm" as-child>
                                <Link :href="projectTasksShow.url({
                                    project: project.id,
                                    task: row.id,
                                })">
                                    View
                                </Link>
                            </Button>
                        </DataTableTd>
                    </tr>
                </tbody>
            </DataTable>
        </section>

        <section class="flex flex-col gap-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold">Time entries</h2>
                    <p class="text-sm text-muted-foreground">
                        Showing {{ time_entries.length }} of {{ time_entries_total }} entries.
                    </p>
                </div>
                <Button v-if="can_create_time_entries" type="button" @click="timeEntryOpen = true">
                    Add time entry
                </Button>
            </div>

            <DataTable>
                <thead>
                    <tr class="border-b border-border/60 bg-muted/40 backdrop-blur-sm">
                        <DataTableTh>Task</DataTableTh>
                        <DataTableTh>User</DataTableTh>
                        <DataTableTh>When</DataTableTh>
                        <DataTableTh>Duration</DataTableTh>
                        <DataTableTh class="text-right">Actions</DataTableTh>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in time_entries" :key="row.id"
                        class="border-b border-border/40 transition-colors even:bg-muted/15 hover:bg-muted/30">
                        <DataTableTd label="Task">{{ row.task_title ?? '—' }}</DataTableTd>
                        <DataTableTd label="User" class="text-muted-foreground">
                            {{ row.user?.name ?? '—' }}
                        </DataTableTd>
                        <DataTableTd label="When" class="text-muted-foreground">
                            {{ formatEntryRange(row) }}
                        </DataTableTd>
                        <DataTableTd label="Duration">{{ formatDuration(row.duration_seconds) }}</DataTableTd>
                        <DataTableTd label="Actions" class="text-right">
                            <div class="flex justify-end gap-2">
                                <Button v-if="row.can_update" variant="ghost" size="sm" type="button"
                                    @click="openEditEntry(row)">
                                    Edit
                                </Button>
                                <Button v-if="row.can_delete" variant="ghost" size="sm" type="button"
                                    class="text-destructive" @click="openEntryDelete(row)">
                                    Delete
                                </Button>
                            </div>
                        </DataTableTd>
                    </tr>
                </tbody>
            </DataTable>
        </section>
    </div>

    <Dialog v-model:open="tagOpen">
        <DialogContent class="overflow-visible sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Add tag</DialogTitle>
                <DialogDescription>Add a label for this project.</DialogDescription>
            </DialogHeader>
            <Form
                v-bind="ProjectTagController.store.form({ project: project.id })"
                class="grid gap-4"
                preserve-scroll
                @success="tagOpen = false"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="project-tag">Tag</Label>
                    <TypeaheadInput
                        id="project-tag"
                        v-model="tagInput"
                        name="name"
                        type="tag"
                        placeholder="manage-user"
                        required
                    />
                    <InputError :message="errors.name" />
                </div>
                <DialogFooter class="gap-3">
                    <Button type="button" variant="outline" @click="tagOpen = false">Cancel</Button>
                    <Button type="submit" :disabled="processing">Add tag</Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="metadataAddOpen">
        <DialogContent class="overflow-visible sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Add metadata</DialogTitle>
                <DialogDescription>Add a custom key-value pair for this project.</DialogDescription>
            </DialogHeader>
            <Form
                v-bind="ProjectMetadataController.store.form({ project: project.id })"
                class="grid gap-4"
                preserve-scroll
                @success="metadataAddOpen = false"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="metadata-key">Key</Label>
                    <TypeaheadInput
                        id="metadata-key"
                        v-model="metadataKeyInput"
                        name="key"
                        type="metadata_key"
                        placeholder="framework"
                        required
                    />
                    <InputError :message="errors.key" />
                </div>
                <div class="grid gap-2">
                    <Label for="metadata-value">Value</Label>
                    <TypeaheadInput
                        id="metadata-value"
                        v-model="metadataValueInput"
                        name="value"
                        type="metadata_value"
                        :metadata-key="metadataKeyInput"
                        placeholder="laravel"
                        required
                    />
                    <InputError :message="errors.value" />
                </div>
                <DialogFooter class="gap-3">
                    <Button type="button" variant="outline" @click="metadataAddOpen = false">Cancel</Button>
                    <Button type="submit" :disabled="processing">Add metadata</Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>

    <Dialog :open="metadataEditOpen" @update:open="(value: boolean) => !value && closeMetadataEdit()">
        <DialogContent v-if="editingMetadata !== null" class="overflow-visible sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Edit metadata</DialogTitle>
                <DialogDescription>Update the value for {{ editingMetadata.key }}.</DialogDescription>
            </DialogHeader>
            <Form
                :key="editingMetadata.id"
                v-bind="ProjectMetadataController.update.form({
                    project: project.id,
                    metadata: editingMetadata.id,
                })"
                class="grid gap-4"
                preserve-scroll
                @success="closeMetadataEdit()"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label>Key</Label>
                    <p class="text-sm font-medium">{{ editingMetadata.key }}</p>
                </div>
                <div class="grid gap-2">
                    <Label for="metadata-edit-value">Value</Label>
                    <TypeaheadInput
                        id="metadata-edit-value"
                        v-model="metadataEditValue"
                        name="value"
                        type="metadata_value"
                        :metadata-key="editingMetadata.key"
                        required
                    />
                    <InputError :message="errors.value" />
                </div>
                <DialogFooter class="gap-3">
                    <Button type="button" variant="outline" @click="closeMetadataEdit()">Cancel</Button>
                    <Button type="submit" :disabled="processing">Save</Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="requirementOpen">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Add requirement</DialogTitle>
                <DialogDescription>Create a new requirement for this project.</DialogDescription>
            </DialogHeader>
            <Form v-bind="ProjectRequirementController.store.form({ project: project.id })" class="grid gap-4"
                preserve-scroll @success="requirementOpen = false" v-slot="{ errors, processing }">
                <input type="hidden" name="responsible_user_id" :value="responsibleUserId" />
                <div class="grid gap-2">
                    <Label for="requirement-title">Title</Label>
                    <TypeaheadInput id="requirement-title" v-model="requirementTitle" name="title" type="requirement_title" required />
                    <InputError :message="errors.title" />
                </div>
                <div class="grid gap-2">
                    <Label for="requirement-description">Description</Label>
                    <RequirementRichTextEditor id="requirement-description" v-model="requirementDescription"
                        input-name="description" />
                    <InputError :message="errors.description" />
                </div>
                <div class="grid gap-2">
                    <Label for="requirement-max-phase">Number of phases</Label>
                    <Input
                        id="requirement-max-phase"
                        name="max_generated_phase"
                        type="number"
                        min="1"
                        max="100"
                        v-model="requirementMaxPhase"
                        required
                    />
                    <InputError :message="errors.max_generated_phase" />
                </div>
                <div class="grid gap-2">
                    <Label>Responsible</Label>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="outline" type="button" class="justify-between">
                                {{ responsibleLabel }}
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" class="max-h-72 overflow-y-auto">
                            <DropdownMenuLabel>Responsible user</DropdownMenuLabel>
                            <DropdownMenuRadioGroup v-model="responsibleUserId">
                                <DropdownMenuRadioItem value="">
                                    Use default (project lead / first team head)
                                </DropdownMenuRadioItem>
                                <DropdownMenuRadioItem v-for="user in assignable_responsibles" :key="user.id"
                                    :value="String(user.id)">
                                    {{ user.name }} ({{ user.email }})
                                </DropdownMenuRadioItem>
                            </DropdownMenuRadioGroup>
                        </DropdownMenuContent>
                    </DropdownMenu>
                    <InputError :message="errors.responsible_user_id" />
                </div>
                <DialogFooter class="gap-3">
                    <Button type="button" variant="outline" @click="requirementOpen = false">Cancel</Button>
                    <Button type="submit" :disabled="processing">Add requirement</Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="taskOpen">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Add task</DialogTitle>
                <DialogDescription>
                    <span v-if="project.estimation_required">Time estimate (minutes) is required.</span>
                    <span v-else>Optional time estimate in minutes.</span>
                </DialogDescription>
            </DialogHeader>
            <Form v-bind="ProjectTaskController.store.form({ project: project.id })" class="grid gap-4" preserve-scroll
                @success="taskOpen = false" v-slot="{ errors, processing }">
                <div class="grid gap-2">
                    <Label for="create-title">Title</Label>
                    <TypeaheadInput id="create-title" v-model="createTitle" name="title" type="task_title" required />
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
                    <TaskFormSelect id="create-requirement" name="project_requirement_id" v-model="createRequirement"
                        placeholder="None" :options="requirementSelectOptions" />
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
                    <Label for="create-estimated-minutes">
                        Estimate (minutes)<span v-if="project.estimation_required"> *</span>
                    </Label>
                    <Input id="create-estimated-minutes" name="estimated_minutes" type="number" min="1" step="1"
                        :required="project.estimation_required" />
                    <InputError :message="errors.estimated_minutes" />
                </div>
                <div class="grid gap-2">
                    <Label for="create-display-after">Display after</Label>
                    <Input id="create-display-after" name="display_after_at" type="datetime-local"
                        v-model="createDisplayAfterAt" />
                    <InputError :message="errors.display_after_at" />
                </div>
                <div class="grid gap-2">
                    <Label for="create-notify">Reminder at</Label>
                    <Input id="create-notify" name="notify_at" type="datetime-local" v-model="createNotifyAt" />
                    <InputError :message="errors.notify_at" />
                </div>
                <DialogFooter class="gap-3">
                    <Button type="button" variant="outline" @click="taskOpen = false">Cancel</Button>
                    <Button type="submit" :disabled="processing">Add task</Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="timeEntryOpen">
        <DialogContent class="overflow-visible sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Add time entry</DialogTitle>
                <DialogDescription>Log past work on a project task.</DialogDescription>
            </DialogHeader>
            <div class="grid gap-4">
                <div class="grid gap-2">
                    <Label for="time-entry-task">Task</Label>
                    <TaskFormSelect id="time-entry-task" v-model="timeEntryTaskId" name="task_id" required placeholder="Select task"
                        :options="taskSelectOptions" />
                </div>
                <Form v-if="timeEntryFormBinding !== null" v-bind="timeEntryFormBinding" class="grid gap-4"
                    preserve-scroll @success="timeEntryOpen = false" v-slot="{ errors, processing }">
                    <div class="flex items-center justify-between gap-4">
                        <Label for="manual-time-only" class="cursor-pointer">Time only</Label>
                        <Switch id="manual-time-only" v-model="manualTimeOnly" />
                    </div>
                    <template v-if="manualTimeOnly">
                        <div class="grid gap-2">
                            <Label for="manual-duration">Duration (minutes)</Label>
                            <Input id="manual-duration" name="duration_minutes" type="number" min="1" step="1" required
                                v-model="manualDurationMinutes" />
                            <p class="text-xs text-muted-foreground">{{ durationOnlyHint }}</p>
                            <InputError :message="errors.duration_minutes" />
                        </div>
                    </template>
                    <template v-else>
                        <div class="grid gap-2">
                            <Label for="manual-start">Start</Label>
                            <Input id="manual-start" name="started_at" type="datetime-local" required
                                v-model="manualStart" />
                            <InputError :message="errors.started_at" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="manual-end">End</Label>
                            <Input id="manual-end" name="ended_at" type="datetime-local" required v-model="manualEnd" />
                            <InputError :message="errors.ended_at" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="manual-notes">Notes</Label>
                            <TypeaheadInput id="manual-notes" v-model="manualNotes" name="notes"
                                type="time_entry_notes" />
                            <InputError :message="errors.notes" />
                        </div>
                    </template>
                    <DialogFooter class="gap-3">
                        <Button type="button" variant="outline" @click="timeEntryOpen = false">Cancel</Button>
                        <Button type="submit" :disabled="processing">Add</Button>
                    </DialogFooter>
                </Form>
            </div>
        </DialogContent>
    </Dialog>

    <Dialog :open="editEntryOpen" @update:open="(value: boolean) => !value && closeEditEntry()">
        <DialogContent v-if="editingEntry !== null && editEntryFormBinding !== null" class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Edit time entry</DialogTitle>
                <DialogDescription>Adjust start/end and notes.</DialogDescription>
            </DialogHeader>
            <Form :key="editingEntry.id" v-bind="editEntryFormBinding" class="grid gap-4" preserve-scroll
                @success="closeEditEntry()" v-slot="{ errors, processing }">
                <div class="flex items-center justify-between gap-4">
                    <Label for="edit-time-only" class="cursor-pointer">Time only</Label>
                    <Switch id="edit-time-only" v-model="editTimeOnly" />
                </div>
                <template v-if="editTimeOnly">
                    <div class="grid gap-2">
                        <Label for="edit-duration">Duration (minutes)</Label>
                        <Input id="edit-duration" name="duration_minutes" type="number" min="1" step="1" required
                            v-model="editDurationMinutes" />
                        <p class="text-xs text-muted-foreground">{{ workingHoursHint }}</p>
                        <InputError :message="errors.duration_minutes" />
                    </div>
                </template>
                <template v-else>
                    <div class="grid gap-2">
                        <Label for="edit-entry-start">Start</Label>
                        <Input id="edit-entry-start" name="started_at" type="datetime-local" required
                            v-model="editStart" />
                        <InputError :message="errors.started_at" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit-entry-end">End</Label>
                        <Input id="edit-entry-end" name="ended_at" type="datetime-local" required v-model="editEnd" />
                        <InputError :message="errors.ended_at" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit-entry-notes">Notes</Label>
                        <TypeaheadInput id="edit-entry-notes" v-model="editNotes" name="notes"
                            type="time_entry_notes" />
                        <InputError :message="errors.notes" />
                    </div>
                </template>
                <DialogFooter class="gap-3">
                    <Button type="button" variant="outline" @click="closeEditEntry()">Cancel</Button>
                    <Button type="submit" :disabled="processing">Save</Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
