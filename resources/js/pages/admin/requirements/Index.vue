<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import ProjectRequirementController from '@/actions/App/Http/Controllers/Admin/ProjectRequirementController';
import ConfirmDestructiveDialog from '@/components/ConfirmDestructiveDialog.vue';
import DataTable from '@/components/dashboard/DataTable.vue';
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
import { index as projectsIndex } from '@/routes/admin/projects/index';
import {
    create as requirementsCreate,
    edit as requirementsEdit,
    show as requirementsShow,
} from '@/routes/admin/projects/requirements/index';
import { index as globalRequirementsIndex } from '@/routes/admin/requirements/index';

type UserBrief = {
    id: number;
    name: string;
    email: string;
} | null;

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

type PaginatedRequirements = {
    data: RequirementRow[];
    links: PaginationLink[];
};

type SelectedProject = {
    id: number;
    name: string;
    code: string | null;
    estimation_required: boolean;
} | null;

type FilterOption = { value: string; label: string };

const props = defineProps<{
    projects: { value: number; label: string }[];
    selected_project: SelectedProject;
    requirements: PaginatedRequirements;
    can_create_requirements_for_selected_project: boolean;
    filters: {
        project_id: string;
        search: string;
        review_status: string;
        responsible_user_id: string;
    };
    filter_options: {
        review_status: FilterOption[];
        responsibles: { value: number; label: string }[];
    };
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Projects', href: projectsIndex.url() },
            { title: 'Requirements', href: globalRequirementsIndex.url() },
        ],
    }),
});

const reviewStatusFilter = ref(props.filters.review_status);
const responsibleFilter = ref(props.filters.responsible_user_id);

watch(
    () => props.filters,
    (filters) => {
        reviewStatusFilter.value = filters.review_status;
        responsibleFilter.value = filters.responsible_user_id;
    },
);

const reviewStatusSelectOptions = computed(() =>
    props.filter_options.review_status.map((o) => ({
        value: o.value,
        label: o.label,
    })),
);

const responsibleSelectOptions = computed(() =>
    props.filter_options.responsibles.map((o) => ({
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

function reloadRequirements(
    overrides: Partial<Record<'project_id' | 'search' | 'review_status' | 'responsible_user_id', string>> = {},
): void {
    routerReloadOnly(
        globalRequirementsIndex.url({
            query: stripFilterParams({
                project_id: props.filters.project_id,
                search: props.filters.search,
                review_status: props.filters.review_status,
                responsible_user_id: props.filters.responsible_user_id,
                ...overrides,
                page: 1,
            }),
        }),
        [
            'projects',
            'selected_project',
            'requirements',
            'filters',
            'filter_options',
            'can_create_requirements_for_selected_project',
        ],
    );
}

function onProject(v: string): void {
    reloadRequirements({ project_id: v, responsible_user_id: '' });
}

function onSearch(search: string): void {
    reloadRequirements({ search });
}

function onReviewStatus(v: string): void {
    reloadRequirements({ review_status: v });
}

function onResponsible(v: string): void {
    reloadRequirements({ responsible_user_id: v });
}

function formatProjectLabel(row: RequirementRow): string {
    if (row.project.code !== null && row.project.code !== '') {
        return `${row.project.name} (${row.project.code})`;
    }

    return row.project.name;
}

const deleteDialogOpen = ref(false);
const requirementPendingDelete = ref<RequirementRow | null>(null);

function openDeleteDialog(row: RequirementRow): void {
    requirementPendingDelete.value = row;
    deleteDialogOpen.value = true;
}

function executeDelete(): void {
    const row = requirementPendingDelete.value;

    if (row === null) {
        return;
    }

    router.delete(
        ProjectRequirementController.destroy.url({
            project: row.project.id,
            requirement: row.id,
        }),
    );
    requirementPendingDelete.value = null;
}

const deleteRequirementDescription = computed(() => {
    const row = requirementPendingDelete.value;

    if (row === null) {
        return '';
    }

    return `Delete "${row.title}"? This cannot be undone.`;
});
</script>

<template>

    <Head title="Requirements" />

    <ConfirmDestructiveDialog v-model:open="deleteDialogOpen" title="Delete requirement?"
        :description="deleteRequirementDescription" @confirm="executeDelete" />

    <div class="flex flex-col gap-6">
        <PageHeader title="Requirements" description="Browse requirements across projects.">
            <template #actions>
                <Button v-if="selected_project !== null && can_create_requirements_for_selected_project" as-child>
                    <Link :href="requirementsCreate.url(selected_project.id)">Add requirement</Link>
                </Button>
            </template>
        </PageHeader>

        <ListToolbar :model-value="filters.search" placeholder="Search title or description…"
            @update:model-value="onSearch">
            <template #filters>
                <div class="flex flex-wrap items-end gap-4">
                    <div class="grid gap-1">
                        <Label class="text-xs text-muted-foreground" for="filter-project">Project</Label>
                        <FormSelect id="filter-project" name="project_id" class="min-w-[16rem]"
                            :model-value="filters.project_id" :options="projectSelectOptions" placeholder="All projects"
                            none-label="All projects" exclude-from-submit @update:model-value="onProject" />
                    </div>
                    <div class="grid gap-1">
                        <Label class="text-xs text-muted-foreground" for="filter-review-status">Stage</Label>
                        <FormSelect id="filter-review-status" name="review_status" class="w-[14rem]"
                            :model-value="reviewStatusFilter" :options="reviewStatusSelectOptions"
                            placeholder="All stages" none-label="All stages" exclude-from-submit
                            @update:model-value="onReviewStatus" />
                    </div>
                    <div v-if="filters.project_id !== ''" class="grid gap-1">
                        <Label class="text-xs text-muted-foreground" for="filter-responsible">Responsible</Label>
                        <FormSelect id="filter-responsible" name="responsible_user_id" class="min-w-[14rem]"
                            :model-value="responsibleFilter" :options="responsibleSelectOptions" placeholder="Anyone"
                            none-label="Anyone" exclude-from-submit @update:model-value="onResponsible" />
                    </div>
                </div>
            </template>
        </ListToolbar>

        <DataTable>
            <thead>
                <tr class="border-b border-border/60 bg-muted/40 backdrop-blur-sm">
                    <DataTableTh>Title</DataTableTh>
                    <DataTableTh>Project</DataTableTh>
                    <DataTableTh>Responsible</DataTableTh>
                    <DataTableTh>Reviewer</DataTableTh>
                    <DataTableTh>Review</DataTableTh>
                    <DataTableTh class="text-right">Actions</DataTableTh>
                </tr>
            </thead>
            <tbody>
                <TableRow v-for="row in requirements.data" :key="row.id">
                    <DataTableTd label="Title" class="align-middle">
                        <div class="font-medium">{{ row.title }}</div>
                        <p v-if="row.description_preview"
                            class="mt-1 line-clamp-2 hidden text-xs text-muted-foreground md:block">
                            {{ row.description_preview }}
                        </p>
                    </DataTableTd>
                    <DataTableTd label="Project" class="text-muted-foreground">
                        {{ formatProjectLabel(row) }}
                    </DataTableTd>
                    <DataTableTd label="Responsible" class="text-muted-foreground">
                        {{ row.responsible_user?.name ?? '—' }}
                    </DataTableTd>
                    <DataTableTd label="Reviewer" class="text-muted-foreground">
                        {{ row.reviewer?.name ?? '—' }}
                    </DataTableTd>
                    <DataTableTd label="Review" class="text-muted-foreground">
                        <div class="flex flex-col gap-1">
                            <span v-if="row.understanding_confirmed_at"
                                class="inline-flex w-fit items-center rounded-md border border-transparent bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground">
                                Confirmed
                            </span>
                            <span v-else-if="row.reviewed_at"
                                class="inline-flex w-fit items-center rounded-md border border-input bg-background px-2 py-0.5 text-xs font-medium">
                                Awaiting confirmation
                            </span>
                            <span v-else class="text-xs">—</span>
                            <span v-if="row.reviewed_at" class="text-xs">
                                {{ new Date(row.reviewed_at).toLocaleString() }}
                            </span>
                        </div>
                    </DataTableTd>
                    <DataTableTd label="Actions" class="text-left md:text-right">
                        <div class="flex justify-start gap-1 md:justify-end">
                            <TableIconAction
                                variant="ghost"
                                icon="eye"
                                label="View"
                                :href="requirementsShow.url({
                                    project: row.project.id,
                                    requirement: row.id,
                                })"
                            />
                            <TableIconAction
                                v-if="row.can_update"
                                variant="ghost"
                                icon="pencil"
                                label="Edit"
                                :href="requirementsEdit.url({
                                    project: row.project.id,
                                    requirement: row.id,
                                })"
                            />
                            <TableIconAction
                                v-if="row.can_delete"
                                variant="ghost"
                                icon="trash"
                                label="Delete"
                                destructive
                                @click="openDeleteDialog(row)"
                            />
                        </div>
                    </DataTableTd>
                </TableRow>
                <tr v-if="requirements.data.length === 0">
                    <DataTableTd label="" :colspan="6" class="py-8 text-center text-muted-foreground">
                        No requirements match this filter.
                    </DataTableTd>
                </tr>
            </tbody>
        </DataTable>

        <DataTablePagination v-if="requirements.links.length > 3" :links="requirements.links" />
    </div>
</template>
