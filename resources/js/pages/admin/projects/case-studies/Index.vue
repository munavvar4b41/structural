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
import TableIconAction from '@/components/TableIconAction.vue';
import { Button } from '@/components/ui/button';
import { routerReloadOnly } from '@/composables/useServerFilters';
import { index as globalCaseStudiesIndex } from '@/routes/admin/case-studies/index';
import {
    create as caseStudiesCreate,
    destroy as caseStudiesDestroy,
    edit as caseStudiesEdit,
    index as projectCaseStudiesIndex,
    show as caseStudiesShow,
} from '@/routes/admin/projects/case-studies/index';
import { index as projectsIndex, show as projectsShow } from '@/routes/admin/projects/index';

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
    can_update: boolean;
    can_delete: boolean;
};

type ProjectSummary = {
    id: number;
    name: string;
    code: string | null;
    estimation_required: boolean;
};

const props = defineProps<{
    project: ProjectSummary;
    case_studies: {
        data: CaseStudyRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    can_create: boolean;
    filters: {
        search: string;
    };
}>();

defineOptions({
    layout: (pageProps: { project: ProjectSummary }) => ({
        breadcrumbs: [
            { title: 'Projects', href: projectsIndex.url() },
            { title: pageProps.project.name, href: projectsShow.url(pageProps.project.id) },
            { title: 'Case studies', href: projectCaseStudiesIndex.url(pageProps.project.id) },
        ],
    }),
});

function onSearch(search: string): void {
    routerReloadOnly(
        projectCaseStudiesIndex.url({
            project: props.project.id,
            query: { search, page: 1 },
        }),
        ['project', 'case_studies', 'can_create', 'filters'],
    );
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
            project: props.project.id,
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
    <Head :title="`Case studies · ${project.name}`" />

    <ConfirmDestructiveDialog
        v-model:open="deleteDialogOpen"
        title="Delete case study?"
        :description="deleteDescription"
        @confirm="executeDelete"
    />

    <div class="flex flex-col gap-6">
        <PageHeader title="Case studies" :description="`For project ${project.name}`">
            <template #actions>
                <Button v-if="can_create" as-child>
                    <Link :href="caseStudiesCreate.url(project.id)">Add case study</Link>
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="globalCaseStudiesIndex.url({ query: { project_id: project.id } })">
                        All projects
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <ListToolbar
            :model-value="filters.search"
            placeholder="Search title or summary…"
            @update:model-value="onSearch"
        />

        <DataTable>
            <thead>
                <tr class="border-b border-border/60 bg-muted/40 backdrop-blur-sm">
                    <DataTableTh>Title</DataTableTh>
                    <DataTableTh>Task</DataTableTh>
                    <DataTableTh>Creator</DataTableTh>
                    <DataTableTh>Created</DataTableTh>
                    <DataTableTh class="text-right">Actions</DataTableTh>
                </tr>
            </thead>
            <tbody>
                <TableRow v-for="row in case_studies.data" :key="row.id">
                    <DataTableTd label="Title" class="align-middle">
                        <div class="font-medium">{{ row.title }}</div>
                        <p
                            v-if="row.summary_preview"
                            class="mt-1 line-clamp-2 hidden text-xs text-muted-foreground md:block"
                        >
                            {{ row.summary_preview }}
                        </p>
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
                        <div class="flex justify-start gap-1 md:justify-end">
                            <TableIconAction
                                variant="ghost"
                                icon="eye"
                                label="View"
                                :href="caseStudiesShow.url({
                                    project: project.id,
                                    case_study: row.id,
                                })"
                            />
                            <TableIconAction
                                v-if="row.can_update"
                                variant="ghost"
                                icon="pencil"
                                label="Edit"
                                :href="caseStudiesEdit.url({
                                    project: project.id,
                                    case_study: row.id,
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
                <tr v-if="case_studies.data.length === 0">
                    <DataTableTd label="" :colspan="5" class="py-8 text-center text-muted-foreground">
                        No case studies for this project yet.
                    </DataTableTd>
                </tr>
            </tbody>
        </DataTable>

        <DataTablePagination v-if="case_studies.links.length > 3" :links="case_studies.links" />
    </div>
</template>
