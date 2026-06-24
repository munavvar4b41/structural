<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import JobPostingController from '@/actions/App/Http/Controllers/Admin/JobPostingController';
import ConfirmDestructiveDialog from '@/components/ConfirmDestructiveDialog.vue';
import DataTable from '@/components/dashboard/DataTable.vue';
import DataTablePagination from '@/components/dashboard/DataTablePagination.vue';
import DataTableTd from '@/components/dashboard/DataTableTd.vue';
import DataTableTh from '@/components/dashboard/DataTableTh.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import ListToolbar from '@/components/ListToolbar.vue';
import FormSelect from '@/components/FormSelect.vue';
import TableIconAction from '@/components/TableIconAction.vue';
import { Button } from '@/components/ui/button';
import { routerReloadOnly, stripFilterParams } from '@/composables/useServerFilters';
import {
    applications as jobPostingsApplications,
    create as jobPostingsCreate,
    edit as jobPostingsEdit,
    index as jobPostingsIndex,
} from '@/routes/admin/job-postings/index';;

type JobPostingRow = {
    id: number;
    slug: string;
    title: string;
    location: string;
    employment_type_label: string;
    status: string;
    status_label: string;
    team: { id: number; name: string } | null;
    applications_count: number;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedPostings = {
    data: JobPostingRow[];
    links: PaginationLink[];
};

type StatusOption = {
    value: string;
    label: string;
};

const props = defineProps<{
    job_postings: PaginatedPostings;
    filters: {
        search: string;
        status: string;
    };
    status_options: StatusOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Job postings', href: jobPostingsIndex() }],
    },
});

const deleteDialogOpen = ref(false);
const postingPendingDelete = ref<JobPostingRow | null>(null);

function reloadSearch(search: string): void {
    routerReloadOnly(
        jobPostingsIndex.url({
            query: stripFilterParams({
                search,
                status: props.filters.status,
                page: 1,
            }),
        }),
        ['job_postings', 'filters'],
    );
}

function reloadStatus(status: string): void {
    routerReloadOnly(
        jobPostingsIndex.url({
            query: stripFilterParams({
                search: props.filters.search,
                status,
                page: 1,
            }),
        }),
        ['job_postings', 'filters'],
    );
}

function openDeleteDialog(posting: JobPostingRow): void {
    postingPendingDelete.value = posting;
    deleteDialogOpen.value = true;
}

function executeDelete(): void {
    const posting = postingPendingDelete.value;

    if (posting === null) {
        return;
    }

    router.delete(JobPostingController.destroy.url(posting.id));
    postingPendingDelete.value = null;
}

const deleteDescription = computed(() => {
    const posting = postingPendingDelete.value;

    if (posting === null) {
        return '';
    }

    return `Delete "${posting.title}"? This cannot be undone.`;
});
</script>

<template>

    <Head title="Job postings" />

    <ConfirmDestructiveDialog v-model:open="deleteDialogOpen" title="Delete job posting?"
        :description="deleteDescription" @confirm="executeDelete" />

    <div class="flex flex-col gap-6">
        <PageHeader title="Job postings" description="Manage open positions and review applications">
            <template #actions>
                <Button as-child>
                    <Link :href="jobPostingsCreate()">Add posting</Link>
                </Button>
            </template>
        </PageHeader>

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
            <ListToolbar class="flex-1" :model-value="filters.search" placeholder="Search title, slug, location…"
                @update:model-value="reloadSearch" />
            <FormSelect id="status" name="status" :model-value="filters.status"
                :options="[{ value: '', label: 'All statuses' }, ...status_options]" placeholder="Status"
                @update:model-value="reloadStatus" />
        </div>

        <DataTable>
            <thead class="border-b border-border/60 bg-muted/40 backdrop-blur-sm">
                <tr>
                    <DataTableTh>Title</DataTableTh>
                    <DataTableTh>Location</DataTableTh>
                    <DataTableTh>Type</DataTableTh>
                    <DataTableTh>Status</DataTableTh>
                    <DataTableTh>Applications</DataTableTh>
                    <DataTableTh class="text-right">Actions</DataTableTh>
                </tr>
            </thead>
            <tbody>
                <tr v-for="posting in job_postings.data" :key="posting.id">
                    <DataTableTd :label="posting.title">
                        <div class="font-medium">{{ posting.title }}</div>
                        <div v-if="posting.team" class="text-xs text-muted-foreground">
                            {{ posting.team.name }}
                        </div>
                    </DataTableTd>
                    <DataTableTd :label="posting.location">{{ posting.location }}</DataTableTd>
                    <DataTableTd :label="posting.employment_type_label">{{ posting.employment_type_label }}
                    </DataTableTd>
                    <DataTableTd :label="posting.status_label">{{ posting.status_label }}</DataTableTd>
                    <DataTableTd :label="`${posting.applications_count} applications`">{{ posting.applications_count }}
                    </DataTableTd>
                    <DataTableTd label="Actions" class="text-right">
                        <div class="flex justify-end gap-1">
                            <TableIconAction
                                icon="users"
                                label="Applications"
                                :href="jobPostingsApplications(posting.id)"
                            />
                            <TableIconAction
                                icon="pencil"
                                label="Edit"
                                :href="jobPostingsEdit(posting.id)"
                            />
                            <TableIconAction
                                icon="trash"
                                label="Delete"
                                destructive
                                @click="openDeleteDialog(posting)"
                            />
                        </div>
                    </DataTableTd>
                </tr>
                <tr v-if="job_postings.data.length === 0">
                    <DataTableTd :colspan="6" label="No job postings found" class="text-center text-muted-foreground">
                        No job postings found.
                    </DataTableTd>
                </tr>
            </tbody>
        </DataTable>

        <DataTablePagination :links="job_postings.links" />
    </div>
</template>
