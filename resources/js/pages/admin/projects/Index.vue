<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import ProjectController from '@/actions/App/Http/Controllers/Admin/ProjectController';
import ConfirmDestructiveDialog from '@/components/ConfirmDestructiveDialog.vue';
import DataTable from '@/components/dashboard/DataTable.vue';
import DataTableEmptyRow from '@/components/dashboard/DataTableEmptyRow.vue';
import DataTablePagination from '@/components/dashboard/DataTablePagination.vue';
import DataTableTd from '@/components/dashboard/DataTableTd.vue';
import DataTableTh from '@/components/dashboard/DataTableTh.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import TableRow from '@/components/dashboard/TableRow.vue';
import FormSelect from '@/components/FormSelect.vue';
import ListToolbar from '@/components/ListToolbar.vue';
import TableIconAction from '@/components/TableIconAction.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { routerReloadOnly, stripFilterParams } from '@/composables/useServerFilters';
import { index as projectCaseStudiesIndex } from '@/routes/admin/projects/case-studies/index';
import {
    create as projectsCreate,
    edit as projectsEdit,
    index as projectsIndex,
    show as projectsShow,
} from '@/routes/admin/projects/index';
import { index as projectNotesIndex } from '@/routes/admin/projects/notes/index';
import { index as projectPasswordsIndex } from '@/routes/admin/projects/passwords/index';
import { index as projectRequirementsIndex } from '@/routes/admin/projects/requirements/index';
import { index as projectTasksIndex } from '@/routes/admin/projects/tasks/index';

type ClientUserSummary = {
    id: number;
    name: string;
    email: string;
};

type ProjectRow = {
    id: number;
    name: string;
    code: string | null;
    description: string | null;
    teams_count: number;
    client_user: ClientUserSummary | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedProjects = {
    data: ProjectRow[];
    links: PaginationLink[];
};

type Props = {
    projects: PaginatedProjects;
    canManageProjects: boolean;
    filters: {
        search: string;
        team_id: string;
        lead_user_id: string;
    };
    filter_options: {
        teams: { value: number; label: string }[];
        leads: { value: number; label: string }[];
    };
    show_lead_filter: boolean;
    can_view_case_studies: boolean;
    can_view_notes: boolean;
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: projectsIndex.url() }],
    },
});

const props = defineProps<Props>();

const projectTableColspan = 5;

const deleteDialogOpen = ref(false);
const projectPendingDelete = ref<ProjectRow | null>(null);

const teamFilter = ref(props.filters.team_id);
const leadFilter = ref(props.filters.lead_user_id);

watch(
    () => props.filters,
    (f) => {
        teamFilter.value = f.team_id;
        leadFilter.value = f.lead_user_id;
    },
);

const teamSelectOptions = computed(() =>
    props.filter_options.teams.map((t) => ({
        value: String(t.value),
        label: t.label,
    })),
);

const leadSelectOptions = computed(() =>
    props.filter_options.leads.map((l) => ({
        value: String(l.value),
        label: l.label,
    })),
);

function reload(
    overrides: Partial<Record<'search' | 'team_id' | 'lead_user_id', string>> = {},
): void {
    routerReloadOnly(
        projectsIndex.url({
            query: stripFilterParams({
                search: props.filters.search,
                team_id: props.filters.team_id,
                lead_user_id: props.filters.lead_user_id,
                ...overrides,
                page: 1,
            }),
        }),
        ['projects', 'filters', 'filter_options', 'show_lead_filter'],
    );
}

function onSearch(search: string): void {
    reload({ search });
}

function onTeam(v: string): void {
    reload({ team_id: v });
}

function onLead(v: string): void {
    reload({ lead_user_id: v });
}

function openDeleteDialog(project: ProjectRow): void {
    projectPendingDelete.value = project;
    deleteDialogOpen.value = true;
}

function executeDelete(): void {
    const project = projectPendingDelete.value;

    if (project === null) {
        return;
    }

    router.delete(ProjectController.destroy.url(project.id));
    projectPendingDelete.value = null;
}

const deleteProjectDescription = computed(() => {
    const project = projectPendingDelete.value;

    if (project === null) {
        return '';
    }

    return `Delete "${project.name}"? This cannot be undone.`;
});
</script>

<template>

    <Head title="Projects" />

    <ConfirmDestructiveDialog v-model:open="deleteDialogOpen" title="Delete project?"
        :description="deleteProjectDescription" @confirm="executeDelete" />

    <div class="flex flex-col gap-6">
        <PageHeader title="Projects" description="Projects you can access based on your role and assignments">
            <template #actions>
                <Button v-if="canManageProjects" as-child>
                    <Link :href="projectsCreate()">Add project</Link>
                </Button>
            </template>
        </PageHeader>

        <ListToolbar :model-value="filters.search" placeholder="Search name, code, description…"
            @update:model-value="onSearch">
            <template #filters>
                <div class="flex flex-wrap items-end gap-3">
                    <div class="grid gap-1">
                        <Label class="text-xs text-muted-foreground" for="filter-team">Team</Label>
                        <FormSelect id="filter-team" name="team_id" class="w-[12rem]" :model-value="teamFilter"
                            :options="teamSelectOptions" placeholder="All teams" none-label="All teams"
                            exclude-from-submit @update:model-value="onTeam" />
                    </div>
                    <div v-if="show_lead_filter" class="grid gap-1">
                        <Label class="text-xs text-muted-foreground" for="filter-lead">Lead</Label>
                        <FormSelect id="filter-lead" name="lead_user_id" class="min-w-[14rem]" :model-value="leadFilter"
                            :options="leadSelectOptions" placeholder="All leads" none-label="All leads"
                            exclude-from-submit @update:model-value="onLead" />
                    </div>
                </div>
            </template>
        </ListToolbar>

        <DataTable>
            <thead>
                <tr class="border-b border-border/60 bg-muted/40 backdrop-blur-sm">
                    <DataTableTh>Name</DataTableTh>
                    <DataTableTh>Code</DataTableTh>
                    <DataTableTh>Client</DataTableTh>
                    <DataTableTh>Teams</DataTableTh>
                    <DataTableTh class="text-right">Actions</DataTableTh>
                </tr>
            </thead>
            <tbody>
                <TableRow v-for="project in projects.data" :key="project.id">
                    <DataTableTd label="Name" class="font-medium">
                        <Button variant="link" class="h-auto p-0 font-medium justify-start" as-child>
                            <Link :href="projectsShow.url(project.id)">{{ project.name }}</Link>
                        </Button>
                    </DataTableTd>
                    <DataTableTd label="Code">
                        {{ project.code ?? '-' }}
                    </DataTableTd>
                    <DataTableTd label="Client">
                        <span>
                            <template v-if="project.client_user">
                                {{ project.client_user.name }} <br />
                                <span class="text-xs">({{ project.client_user.email }})</span>
                            </template>
                            <template v-else>—</template>
                        </span>
                    </DataTableTd>
                    <DataTableTd label="Teams">
                        {{ project.teams_count }}
                    </DataTableTd>
                    <DataTableTd label="Actions" class="text-left md:text-right">
                        <div class="flex flex-wrap gap-1 justify-start md:justify-end">
                            <TableIconAction
                                icon="eye"
                                label="View project"
                                :href="projectsShow.url(project.id)"
                            />
                            <TableIconAction
                                icon="file-text"
                                label="Requirements"
                                :href="projectRequirementsIndex.url(project.id)"
                            />
                            <TableIconAction
                                icon="clipboard-list"
                                label="Tasks"
                                :href="projectTasksIndex.url(project.id)"
                            />
                            <TableIconAction
                                v-if="can_view_case_studies"
                                icon="book-open"
                                label="Case studies"
                                :href="projectCaseStudiesIndex.url(project.id)"
                            />
                            <TableIconAction
                                v-if="can_view_notes"
                                icon="list"
                                label="Notes"
                                :href="projectNotesIndex.url(project.id)"
                            />
                            <TableIconAction
                                icon="key-round"
                                label="Passwords"
                                :href="projectPasswordsIndex.url(project.id)"
                            />
                            <TableIconAction
                                v-if="canManageProjects"
                                icon="pencil"
                                label="Edit"
                                :href="projectsEdit.url(project.id)"
                            />
                            <TableIconAction
                                v-if="canManageProjects"
                                icon="trash"
                                label="Delete"
                                destructive
                                @click="openDeleteDialog(project)"
                            />
                        </div>
                    </DataTableTd>
                </TableRow>
                <DataTableEmptyRow
                    v-if="projects.data.length === 0"
                    :colspan="projectTableColspan"
                    message="No projects match this filter."
                />
            </tbody>
        </DataTable>

        <DataTablePagination :links="projects.links" />
    </div>
</template>
