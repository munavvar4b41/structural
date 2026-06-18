<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ProjectProposalController from '@/actions/App/Http/Controllers/Admin/ProjectProposalController';
import ConfirmDestructiveDialog from '@/components/ConfirmDestructiveDialog.vue';
import DataTable from '@/components/dashboard/DataTable.vue';
import DataTablePagination from '@/components/dashboard/DataTablePagination.vue';
import DataTableTd from '@/components/dashboard/DataTableTd.vue';
import DataTableTh from '@/components/dashboard/DataTableTh.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import ListToolbar from '@/components/ListToolbar.vue';
import FormSelect from '@/components/FormSelect.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { routerReloadOnly, stripFilterParams } from '@/composables/useServerFilters';
import { index as projectsIndex } from '@/routes/admin/projects/index';
import { create as proposalsCreate, edit as proposalsEdit, show as proposalsShow } from '@/routes/admin/projects/proposals/index';
import { index as globalProposalsIndex } from '@/routes/admin/proposals/index';
import TableRow from '@/components/dashboard/TableRow.vue';

type UserBrief = {
    id: number;
    name: string;
    email: string;
} | null;

type ProposalRow = {
    id: number;
    title: string;
    description_preview: string | null;
    status: string;
    status_label: string;
    created_at: string | null;
    submitted_at: string | null;
    creator: UserBrief;
    linked_requirement: { id: number; title: string } | null;
    project: {
        id: number;
        name: string;
        code: string | null;
    };
    can_update: boolean;
    can_delete: boolean;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedProposals = {
    data: ProposalRow[];
    links: PaginationLink[];
};

type SelectedProject = {
    id: number;
    name: string;
    code: string | null;
    estimation_required: boolean;
} | null;

type FilterOption = { value: string | number; label: string };

const props = defineProps<{
    projects: FilterOption[];
    selected_project: SelectedProject;
    proposals: PaginatedProposals;
    can_create_proposals_for_selected_project: boolean;
    filters: {
        project_id: string;
        search: string;
        status: string;
    };
    status_options: FilterOption[];
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Projects', href: projectsIndex.url() },
            { title: 'Proposals', href: globalProposalsIndex.url() },
        ],
    }),
});

const statusFilter = ref(props.filters.status);


const statusSelectOptions = computed(() =>
    props.status_options.map((o) => ({
        value: String(o.value),
        label: o.label,
    })),
);

const projectSelectOptions = computed(() =>
    props.projects.map((o) => ({
        value: String(o.value),
        label: o.label,
    })),
);

function reloadProposals(overrides: Partial<Record<'project_id' | 'search' | 'status', string>> = {}): void {
    routerReloadOnly(
        globalProposalsIndex.url({
            query: stripFilterParams({
                project_id: props.filters.project_id,
                search: props.filters.search,
                status: props.filters.status,
                ...overrides,
                page: 1,
            }),
        }),
        [
            'projects',
            'selected_project',
            'proposals',
            'filters',
            'status_options',
            'can_create_proposals_for_selected_project',
        ],
    );
}

function onProject(v: string): void {
    reloadProposals({ project_id: v });
}

function onSearch(search: string): void {
    reloadProposals({ search });
}

function onStatus(v: string): void {
    reloadProposals({ status: v });
}

function formatProjectLabel(row: ProposalRow): string {
    if (row.project.code !== null && row.project.code !== '') {
        return `${row.project.name} (${row.project.code})`;
    }

    return row.project.name;
}

const deleteDialogOpen = ref(false);
const proposalPendingDelete = ref<ProposalRow | null>(null);

function openDeleteDialog(row: ProposalRow): void {
    proposalPendingDelete.value = row;
    deleteDialogOpen.value = true;
}

function executeDelete(): void {
    const row = proposalPendingDelete.value;

    if (row === null) {
        return;
    }

    router.delete(
        ProjectProposalController.destroy.url({
            project: row.project.id,
            proposal: row.id,
        }),
    );
    proposalPendingDelete.value = null;
}

const deleteProposalDescription = computed(() => {
    const row = proposalPendingDelete.value;

    if (row === null) {
        return '';
    }

    return `Delete "${row.title}"? This cannot be undone.`;
});

function statusBadgeVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'confirmed') {
        return 'default';
    }

    if (status === 'rejected') {
        return 'destructive';
    }

    if (status === 'pending') {
        return 'secondary';
    }

    return 'outline';
}
</script>

<template>

    <Head title="Proposals" />

    <ConfirmDestructiveDialog v-model:open="deleteDialogOpen" title="Delete proposal?"
        :description="deleteProposalDescription" @confirm="executeDelete" />

    <div class="flex flex-col gap-6">
        <PageHeader title="Proposals" description="Browse proposals across projects.">
            <template #actions>
                <Button v-if="selected_project !== null && can_create_proposals_for_selected_project" as-child>
                    <Link :href="proposalsCreate.url(selected_project.id)">Add proposal</Link>
                </Button>
            </template>
        </PageHeader>

        <ListToolbar :model-value="filters.search" placeholder="Search title…" @update:model-value="onSearch">
            <template #filters>
                <div class="flex flex-wrap items-end gap-4">
                    <div class="grid gap-1">
                        <Label class="text-xs text-muted-foreground" for="filter-project">Project</Label>
                        <FormSelect id="filter-project" name="project_id" class="min-w-[16rem]"
                            :model-value="filters.project_id" :options="projectSelectOptions" placeholder="All projects"
                            none-label="All projects" exclude-from-submit @update:model-value="onProject" />
                    </div>
                    <div class="grid gap-1">
                        <Label class="text-xs text-muted-foreground" for="filter-status">Status</Label>
                        <FormSelect id="filter-status" name="status" class="w-[14rem]" :model-value="statusFilter"
                            :options="statusSelectOptions" placeholder="All statuses" none-label="All statuses"
                            exclude-from-submit @update:model-value="onStatus" />
                    </div>
                </div>
            </template>
        </ListToolbar>

        <DataTable>
            <thead>
                <tr class="border-b border-border/60 bg-muted/40 backdrop-blur-sm">
                    <DataTableTh>Title</DataTableTh>
                    <DataTableTh>Project</DataTableTh>
                    <DataTableTh>Status</DataTableTh>
                    <DataTableTh>Linked requirement</DataTableTh>
                    <DataTableTh>Creator</DataTableTh>
                    <DataTableTh>Created</DataTableTh>
                    <DataTableTh class="text-right">Actions</DataTableTh>
                </tr>
            </thead>
            <tbody>
                <TableRow v-for="row in proposals.data" :key="row.id">
                    <DataTableTd label="Title" class="align-top">
                        <div class="font-medium">{{ row.title }}</div>
                        <p v-if="row.description_preview" class="mt-1 line-clamp-2 text-xs text-muted-foreground">
                            {{ row.description_preview }}
                        </p>
                    </DataTableTd>
                    <DataTableTd label="Project" class="text-muted-foreground">
                        {{ formatProjectLabel(row) }}
                    </DataTableTd>
                    <DataTableTd label="Status">
                        <Badge :variant="statusBadgeVariant(row.status)">{{ row.status_label }}</Badge>
                    </DataTableTd>
                    <DataTableTd label="Linked requirement" class="text-muted-foreground">
                        {{ row.linked_requirement?.title ?? '—' }}
                    </DataTableTd>
                    <DataTableTd label="Creator" class="text-muted-foreground">
                        {{ row.creator?.name ?? '—' }}
                    </DataTableTd>
                    <DataTableTd label="Created" class="text-muted-foreground">
                        {{ row.created_at ? new Date(row.created_at).toLocaleString() : '—' }}
                    </DataTableTd>
                    <DataTableTd label="Actions" class="text-left md:text-right">
                        <div class="flex gap-2 justify-start md:justify-end">
                            <Button variant="ghost" size="sm" as-child>
                                <Link :href="proposalsShow.url({
                                    project: row.project.id,
                                    proposal: row.id,
                                })
                                    ">
                                    View
                                </Link>
                            </Button>
                            <Button v-if="row.can_update" variant="ghost" size="sm" as-child>
                                <Link :href="proposalsEdit.url({
                                    project: row.project.id,
                                    proposal: row.id,
                                })
                                    ">
                                    Edit
                                </Link>
                            </Button>
                            <Button v-if="row.can_delete" variant="ghost" size="sm" class="text-destructive"
                                @click="openDeleteDialog(row)">
                                Delete
                            </Button>
                        </div>
                    </DataTableTd>
                </TableRow>
                <tr v-if="proposals.data.length === 0">
                    <DataTableTd label="" :colspan="7" class="py-8 text-center text-muted-foreground">
                        No proposals match this filter.
                    </DataTableTd>
                </tr>
            </tbody>
        </DataTable>

        <DataTablePagination v-if="proposals.links.length > 3" :links="proposals.links" />
    </div>
</template>
