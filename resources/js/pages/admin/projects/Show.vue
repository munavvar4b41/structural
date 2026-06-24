<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { CornerDownRight, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import ProjectMetadataController from '@/actions/App/Http/Controllers/Admin/ProjectMetadataController';
import ProjectRequirementController from '@/actions/App/Http/Controllers/Admin/ProjectRequirementController';
import ProjectTagController from '@/actions/App/Http/Controllers/Admin/ProjectTagController';
import TaskTimeEntryController from '@/actions/App/Http/Controllers/Admin/TaskTimeEntryController';
import ConfirmDestructiveDialog from '@/components/ConfirmDestructiveDialog.vue';
import DataTable from '@/components/dashboard/DataTable.vue';
import DataTableTd from '@/components/dashboard/DataTableTd.vue';
import DataTableTh from '@/components/dashboard/DataTableTh.vue';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import InputError from '@/components/InputError.vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
import FormSelect from '@/components/FormSelect.vue';
import DataTableEmptyRow from '@/components/dashboard/DataTableEmptyRow.vue';
import TableIconAction from '@/components/TableIconAction.vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { emptyTipTapDocumentJson } from '@/lib/tiptapDocument';
import { requiresPhaseSelection } from '@/lib/requirementPhaseOptions';
import { formatTaskMinutes } from '@/lib/formatTaskMinutes';
import { edit as projectsEdit, index as projectsIndex, show as projectsShow } from '@/routes/admin/projects/index';
import {
    index as requirementsIndex,
    show as requirementsShow,
} from '@/routes/admin/projects/requirements/index';
import {
    create as proposalsCreate,
    index as proposalsIndex,
    show as proposalsShow,
} from '@/routes/admin/projects/proposals/index';
import {
    create as caseStudiesCreate,
    index as projectCaseStudiesIndex,
    show as caseStudiesShow,
} from '@/routes/admin/projects/case-studies/index';
import { index as projectTasksIndex, create as projectTasksCreate, show as projectTasksShow } from '@/routes/admin/projects/tasks/index';
import TableRow from '@/components/dashboard/TableRow.vue';

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

type ProposalRow = {
    id: number;
    title: string;
    description_preview: string | null;
    status: string;
    status_label: string;
    created_at: string | null;
    creator: UserBrief;
};

type CaseStudyRow = {
    id: number;
    title: string;
    summary_preview: string | null;
    created_at: string | null;
    creator: UserBrief;
    task: { id: number; title: string } | null;
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
    proposals: ProposalRow[];
    proposals_total: number;
    can_create_proposals: boolean;
    case_studies: CaseStudyRow[];
    case_studies_total: number;
    can_create_case_studies: boolean;
    can_view_case_studies: boolean;
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

const showPhaseColumn = computed(() =>
    props.requirement_options.some((requirement) => requiresPhaseSelection(requirement.max_generated_phase)),
);

const projectTasksTableColspan = computed(() => (showPhaseColumn.value ? 6 : 5));

const metadataTableColspan = computed(() => (props.can_manage_tags_metadata ? 3 : 2));

const taskSelectOptions = computed(() =>
    props.task_options.map((t) => ({ value: String(t.value), label: t.label })),
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
                <div class="flex flex-wrap gap-1">
                    <TableIconAction icon="file-text" tone="view" label="All requirements"
                        :href="requirementsIndex.url(project.id)" />
                    <TableIconAction icon="file-check" label="All proposals" :href="proposalsIndex.url(project.id)" />
                    <TableIconAction v-if="can_view_case_studies" icon="book-open" label="All case studies"
                        :href="projectCaseStudiesIndex.url(project.id)" />
                    <TableIconAction icon="clipboard-list" label="All tasks"
                        :href="projectTasksIndex.url(project.id)" />
                    <TableIconAction v-if="can_manage_project" icon="pencil" label="Edit project"
                        :href="projectsEdit.url(project.id)" />
                </div>
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
                    <button v-if="can_manage_tags_metadata" type="button" class="rounded-sm p-0.5 hover:bg-muted"
                        :aria-label="`Remove tag ${tag.name}`" @click="removeTag(tag)">
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

            <DataTable>
                <thead>
                    <tr class="border-b border-border/60 bg-muted/40 backdrop-blur-sm">
                        <DataTableTh>Key</DataTableTh>
                        <DataTableTh>Value</DataTableTh>
                        <DataTableTh v-if="can_manage_tags_metadata" class="text-right">Actions</DataTableTh>
                    </tr>
                </thead>
                <tbody>
                    <TableRow v-for="row in metadata" :key="row.id">
                        <DataTableTd label="Key" class="font-medium">{{ row.key }}</DataTableTd>
                        <DataTableTd label="Value" class="text-muted-foreground">{{ row.value }}</DataTableTd>
                        <DataTableTd v-if="can_manage_tags_metadata" label="Actions" class="text-left md:text-right">
                            <div class="flex gap-1 justify-start md:justify-end">
                                <TableIconAction variant="ghost" icon="pencil" label="Edit"
                                    @click="openMetadataEdit(row)" />
                                <TableIconAction variant="ghost" icon="trash" label="Remove" destructive
                                    @click="removeMetadata(row)" />
                            </div>
                        </DataTableTd>
                    </TableRow>
                    <DataTableEmptyRow v-if="metadata.length === 0" :colspan="metadataTableColspan"
                        message="No metadata yet." />
                </tbody>
            </DataTable>
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
                    <TableRow v-for="row in requirements" :key="row.id">
                        <DataTableTd label="Title" class="align-middle">
                            <div class="font-medium">{{ row.title }}</div>
                            <p v-if="row.description_preview"
                                class="hidden md:block mt-1 line-clamp-2 text-xs text-muted-foreground">
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
                        <DataTableTd label="Actions" class="text-left md:text-right">
                            <TableIconAction icon="file-text" tone="view" label="View requirement" :href="requirementsShow.url({
                                project: project.id,
                                requirement: row.id,
                            })" />
                        </DataTableTd>
                    </TableRow>
                    <DataTableEmptyRow v-if="requirements.length === 0" :colspan="4" message="No requirements yet." />
                </tbody>
            </DataTable>
        </section>

        <section class="flex flex-col gap-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold">Proposals</h2>
                    <p class="text-sm text-muted-foreground">
                        Showing {{ proposals.length }} of {{ proposals_total }} proposals.
                    </p>
                </div>
                <Button v-if="can_create_proposals" as-child>
                    <Link :href="proposalsCreate.url(project.id)">Add proposal</Link>
                </Button>
            </div>

            <DataTable>
                <thead>
                    <tr class="border-b border-border/60 bg-muted/40 backdrop-blur-sm">
                        <DataTableTh>Title</DataTableTh>
                        <DataTableTh>Status</DataTableTh>
                        <DataTableTh>Creator</DataTableTh>
                        <DataTableTh class="text-right">Actions</DataTableTh>
                    </tr>
                </thead>
                <tbody>
                    <TableRow v-for="row in proposals" :key="row.id">
                        <DataTableTd label="Title" class="align-middle">
                            <div class="font-medium">{{ row.title }}</div>
                            <p v-if="row.description_preview"
                                class="hidden md:block mt-1 line-clamp-2 text-xs text-muted-foreground">
                                {{ row.description_preview }}
                            </p>
                        </DataTableTd>
                        <DataTableTd label="Status" class="text-muted-foreground">
                            {{ row.status_label }}
                        </DataTableTd>
                        <DataTableTd label="Creator" class="text-muted-foreground">
                            {{ row.creator?.name ?? '—' }}
                        </DataTableTd>
                        <DataTableTd label="Actions" class="text-left md:text-right">
                            <TableIconAction icon="file-check" label="View proposal" :href="proposalsShow.url({
                                project: project.id,
                                proposal: row.id,
                            })" />
                        </DataTableTd>
                    </TableRow>
                    <DataTableEmptyRow v-if="proposals.length === 0" :colspan="4" message="No proposals yet." />
                </tbody>
            </DataTable>
        </section>

        <section v-if="can_view_case_studies" class="flex flex-col gap-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold">Case studies</h2>
                    <p class="text-sm text-muted-foreground">
                        Showing {{ case_studies.length }} of {{ case_studies_total }} case studies.
                    </p>
                </div>
                <Button v-if="can_create_case_studies" as-child>
                    <Link :href="caseStudiesCreate.url(project.id)">Add case study</Link>
                </Button>
            </div>

            <DataTable>
                <thead>
                    <tr class="border-b border-border/60 bg-muted/40 backdrop-blur-sm">
                        <DataTableTh>Title</DataTableTh>
                        <DataTableTh>Task</DataTableTh>
                        <DataTableTh>Creator</DataTableTh>
                        <DataTableTh class="text-right">Actions</DataTableTh>
                    </tr>
                </thead>
                <tbody>
                    <TableRow v-for="row in case_studies" :key="row.id">
                        <DataTableTd label="Title" class="align-middle">
                            <div class="font-medium">{{ row.title }}</div>
                            <p v-if="row.summary_preview"
                                class="hidden md:block mt-1 line-clamp-2 text-xs text-muted-foreground">
                                {{ row.summary_preview }}
                            </p>
                        </DataTableTd>
                        <DataTableTd label="Task" class="text-muted-foreground">
                            {{ row.task?.title ?? '—' }}
                        </DataTableTd>
                        <DataTableTd label="Creator" class="text-muted-foreground">
                            {{ row.creator?.name ?? '—' }}
                        </DataTableTd>
                        <DataTableTd label="Actions" class="text-left md:text-right">
                            <TableIconAction icon="book-open" label="View case study" :href="caseStudiesShow.url({
                                project: project.id,
                                case_study: row.id,
                            })" />
                        </DataTableTd>
                    </TableRow>
                    <DataTableEmptyRow v-if="case_studies.length === 0" :colspan="4" message="No case studies yet." />
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
                <Button v-if="can_create_tasks" as-child>
                    <Link :href="projectTasksCreate.url(project.id)">Add task</Link>
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
                    <TableRow v-for="row in tasks" :key="row.id">
                        <DataTableTd label="Title" class="align-middle">
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
                        <DataTableTd label="Actions" class="text-left md:text-right">
                            <TableIconAction icon="clipboard-list" label="View task" :href="projectTasksShow.url({
                                project: project.id,
                                task: row.id,
                            })" />
                        </DataTableTd>
                    </TableRow>
                    <DataTableEmptyRow v-if="tasks.length === 0" :colspan="projectTasksTableColspan"
                        message="No tasks yet." />
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
                    <TableRow v-for="row in time_entries" :key="row.id">
                        <DataTableTd label="Task">{{ row.task_title ?? '—' }}</DataTableTd>
                        <DataTableTd label="User" class="text-muted-foreground">
                            {{ row.user?.name ?? '—' }}
                        </DataTableTd>
                        <DataTableTd label="When" class="text-muted-foreground">
                            {{ formatEntryRange(row) }}
                        </DataTableTd>
                        <DataTableTd label="Duration">{{ formatDuration(row.duration_seconds) }}</DataTableTd>
                        <DataTableTd label="Actions" class="text-left md:text-right">
                            <div class="flex gap-1 justify-start md:justify-end">
                                <TableIconAction v-if="row.can_update" variant="ghost" icon="pencil" label="Edit"
                                    @click="openEditEntry(row)" />
                                <TableIconAction v-if="row.can_delete" variant="ghost" icon="trash" label="Delete"
                                    destructive @click="openEntryDelete(row)" />
                            </div>
                        </DataTableTd>
                    </TableRow>
                    <DataTableEmptyRow v-if="time_entries.length === 0" :colspan="5" message="No time entries yet." />
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
            <Form v-bind="ProjectTagController.store.form({ project: project.id })" class="grid gap-4" preserve-scroll
                @success="tagOpen = false" v-slot="{ errors, processing }">
                <div class="grid gap-2">
                    <Label for="project-tag">Tag</Label>
                    <TypeaheadInput id="project-tag" v-model="tagInput" name="name" type="tag" placeholder="manage-user"
                        required />
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
            <Form v-bind="ProjectMetadataController.store.form({ project: project.id })" class="grid gap-4"
                preserve-scroll @success="metadataAddOpen = false" v-slot="{ errors, processing }">
                <div class="grid gap-2">
                    <Label for="metadata-key">Key</Label>
                    <TypeaheadInput id="metadata-key" v-model="metadataKeyInput" name="key" type="metadata_key"
                        placeholder="framework" required />
                    <InputError :message="errors.key" />
                </div>
                <div class="grid gap-2">
                    <Label for="metadata-value">Value</Label>
                    <TypeaheadInput id="metadata-value" v-model="metadataValueInput" name="value" type="metadata_value"
                        :metadata-key="metadataKeyInput" placeholder="laravel" required />
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
            <Form :key="editingMetadata.id" v-bind="ProjectMetadataController.update.form({
                project: project.id,
                metadata: editingMetadata.id,
            })" class="grid gap-4" preserve-scroll @success="closeMetadataEdit()" v-slot="{ errors, processing }">
                <div class="grid gap-2">
                    <Label>Key</Label>
                    <p class="text-sm font-medium">{{ editingMetadata.key }}</p>
                </div>
                <div class="grid gap-2">
                    <Label for="metadata-edit-value">Value</Label>
                    <TypeaheadInput id="metadata-edit-value" v-model="metadataEditValue" name="value"
                        type="metadata_value" :metadata-key="editingMetadata.key" required />
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
                    <TypeaheadInput id="requirement-title" v-model="requirementTitle" name="title"
                        type="requirement_title" required />
                    <InputError :message="errors.title" />
                </div>
                <div class="grid gap-2">
                    <Label for="requirement-description">Description</Label>
                    <RichTextEditor id="requirement-description" v-model="requirementDescription"
                        input-name="description" />
                    <InputError :message="errors.description" />
                </div>
                <div class="grid gap-2">
                    <Label for="requirement-max-phase">Number of phases</Label>
                    <Input id="requirement-max-phase" name="max_generated_phase" type="number" min="1" max="100"
                        v-model="requirementMaxPhase" required />
                    <InputError :message="errors.max_generated_phase" />
                </div>
                <div class="grid gap-2">
                    <Label>Responsible</Label>
                    <FormSelect id="requirement-responsible" name="responsible_user_id" v-model="responsibleUserId"
                        noneLabel="Use default (project lead / first team head)" :options="assignable_responsibles.map(u => ({
                            value: String(u.id),
                            label: `${u.name} (${u.email})`,
                        }))" />
                    <InputError :message="errors.responsible_user_id" />
                </div>
                <DialogFooter class="gap-3">
                    <Button type="button" variant="outline" @click="requirementOpen = false">Cancel</Button>
                    <Button type="submit" :disabled="processing">Add requirement</Button>
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
                    <FormSelect id="time-entry-task" v-model="timeEntryTaskId" name="task_id" required
                        placeholder="Select task" :options="taskSelectOptions" />
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
                        <p class="text-xs text-muted-foreground">{{ durationOnlyHint }}</p>
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
