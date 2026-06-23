<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ConfirmDestructiveDialog from '@/components/ConfirmDestructiveDialog.vue';
import DataTable from '@/components/dashboard/DataTable.vue';
import DataTablePagination from '@/components/dashboard/DataTablePagination.vue';
import DataTableTd from '@/components/dashboard/DataTableTd.vue';
import DataTableTh from '@/components/dashboard/DataTableTh.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import TableRow from '@/components/dashboard/TableRow.vue';
import ListToolbar from '@/components/ListToolbar.vue';
import FormSelect from '@/components/FormSelect.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { routerReloadOnly, stripFilterParams } from '@/composables/useServerFilters';
import { index as projectsIndex } from '@/routes/admin/projects/index';
import {
    create as caseStudiesCreate,
    destroy as caseStudiesDestroy,
    edit as caseStudiesEdit,
    show as caseStudiesShow,
} from '@/routes/admin/projects/case-studies/index';
import { index as globalCaseStudiesIndex } from '@/routes/admin/case-studies/index';

type UserBrief = {
    id: number;
    name: string;
    email: string;
} | null;

type CaseStudyRow = {
    id: number;
    title: string;
    summary_preview: string | null;
    created_at: string | null;
    creator: UserBrief;
    task: { id: number; title: string } | null;
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

type PaginatedCaseStudies = {
    data: CaseStudyRow[];
    links: PaginationLink[];
};

type SelectedProject = {
    id: number;
    name: string;
    code: string | null;
} | null;

type FilterOption = { value: string | number; label: string };

const props = defineProps<{
    projects: FilterOption[];
    selected_project: SelectedProject;
    case_studies: PaginatedCaseStudies;
    can_create_for_selected_project: boolean;
    filters: {
        project_id: string;
        search: string;
    };
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Projects', href: projectsIndex.url() },
            { title: 'Case studies', href: globalCaseStudiesIndex.url() },
        ],
    }),
});

const projectSelectOptions = computed(() =>
    props.projects.map((option) => ({
        value: String(option.value),
        label: option.label,
    })),
);

function reloadCaseStudies(overrides: Partial<Record<'project_id' | 'search', string>> = {}): void {
    routerReloadOnly(
        globalCaseStudiesIndex.url({
            query: stripFilterParams({
                project_id: props.filters.project_id,
                search: props.filters.search,
                ...overrides,
                page: 1,
            }),
        }),
        ['projects', 'selected_project', 'case_studies', 'filters', 'can_create_for_selected_project'],
    );
}

function onProject(value: string): void {
    reloadCaseStudies({ project_id: value });
}

function onSearch(search: string): void {
    reloadCaseStudies({ search });
}

function formatProjectLabel(row: CaseStudyRow): string {
    if (row.project.code !== null && row.project.code !== '') {
        return `${row.project.name} (${row.project.code})`;
    }

    return row.project.name;
}

const deleteDialogOpen = ref(false);
const caseStudyPendingDelete = ref<CaseStudyRow | null>(null);

function openDeleteDialog(row: CaseStudyRow): void {
    caseStudyPendingDelete.value = row;
    deleteDialogOpen.value = true;
}

function executeDelete(): void {
    const row = caseStudyPendingDelete.value;

    if (row === null) {
        return;
    }

    router.delete(
        caseStudiesDestroy.url({
            project: row.project.id,
            case_study: row.id,
        }),
    );
    caseStudyPendingDelete.value = null;
}

const deleteDescription = computed(() => {
    const row = caseStudyPendingDelete.value;

    if (row === null) {
        return '';
    }

    return `Delete "${row.title}"? This cannot be undone.`;
});
</script>

<template>
    <Head title="Case studies" />

    <ConfirmDestructiveDialog
        v-model:open="deleteDialogOpen"
        title="Delete case study?"
        :description="deleteDescription"
        @confirm="executeDelete"
    />

    <div class="flex flex-col gap-6">
        <PageHeader title="Case studies" description="Document client problems, solutions, and outcomes across projects.">
            <template #actions>
                <Button v-if="selected_project !== null && can_create_for_selected_project" as-child>
                    <Link :href="caseStudiesCreate.url(selected_project.id)">Add case study</Link>
                </Button>
            </template>
        </PageHeader>

        <ListToolbar :model-value="filters.search" placeholder="Search title or summary…" @update:model-value="onSearch">
            <template #filters>
                <div class="grid gap-1">
                    <Label class="text-xs text-muted-foreground" for="filter-project">Project</Label>
                    <FormSelect
                        id="filter-project"
                        name="project_id"
                        class="min-w-[16rem]"
                        :model-value="filters.project_id"
                        :options="projectSelectOptions"
                        placeholder="All projects"
                        none-label="All projects"
                        exclude-from-submit
                        @update:model-value="onProject"
                    />
                </div>
            </template>
        </ListToolbar>

        <DataTable>
            <thead>
                <tr class="border-b border-border/60 bg-muted/40 backdrop-blur-sm">
                    <DataTableTh>Title</DataTableTh>
                    <DataTableTh>Project</DataTableTh>
                    <DataTableTh>Task</DataTableTh>
                    <DataTableTh>Creator</DataTableTh>
                    <DataTableTh>Created</DataTableTh>
                    <DataTableTh class="text-right">Actions</DataTableTh>
                </tr>
            </thead>
            <tbody>
                <TableRow v-for="row in case_studies.data" :key="row.id">
                    <DataTableTd label="Title" class="align-top">
                        <div class="font-medium">{{ row.title }}</div>
                        <p
                            v-if="row.summary_preview"
                            class="mt-1 line-clamp-2 hidden text-xs text-muted-foreground md:block"
                        >
                            {{ row.summary_preview }}
                        </p>
                    </DataTableTd>
                    <DataTableTd label="Project" class="text-muted-foreground">
                        {{ formatProjectLabel(row) }}
                    </DataTableTd>
                    <DataTableTd label="Task" class="text-muted-foreground">
                        {{ row.task?.title ?? '—' }}
                    </DataTableTd>
                    <DataTableTd label="Creator" class="text-muted-foreground">
                        {{ row.creator?.name ?? '—' }}
                    </DataTableTd>
                    <DataTableTd label="Created" class="text-muted-foreground">
                        {{ row.created_at ? new Date(row.created_at).toLocaleString() : '—' }}
                    </DataTableTd>
                    <DataTableTd label="Actions" class="text-left md:text-right">
                        <div class="flex justify-start gap-2 md:justify-end">
                            <Button variant="ghost" size="sm" as-child>
                                <Link
                                    :href="
                                        caseStudiesShow.url({
                                            project: row.project.id,
                                            case_study: row.id,
                                        })
                                    "
                                >
                                    View
                                </Link>
                            </Button>
                            <Button v-if="row.can_update" variant="ghost" size="sm" as-child>
                                <Link
                                    :href="
                                        caseStudiesEdit.url({
                                            project: row.project.id,
                                            case_study: row.id,
                                        })
                                    "
                                >
                                    Edit
                                </Link>
                            </Button>
                            <Button
                                v-if="row.can_delete"
                                variant="ghost"
                                size="sm"
                                class="text-destructive"
                                @click="openDeleteDialog(row)"
                            >
                                Delete
                            </Button>
                        </div>
                    </DataTableTd>
                </TableRow>
                <tr v-if="case_studies.data.length === 0">
                    <DataTableTd label="" :colspan="6" class="py-8 text-center text-muted-foreground">
                        No case studies match this filter.
                    </DataTableTd>
                </tr>
            </tbody>
        </DataTable>

        <DataTablePagination v-if="case_studies.links.length > 3" :links="case_studies.links" />
    </div>
</template>
