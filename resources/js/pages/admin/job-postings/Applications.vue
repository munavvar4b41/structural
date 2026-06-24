<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import DataTable from '@/components/dashboard/DataTable.vue';
import DataTableTd from '@/components/dashboard/DataTableTd.vue';
import DataTableTh from '@/components/dashboard/DataTableTh.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import FormSelect from '@/components/FormSelect.vue';
import TableIconAction from '@/components/TableIconAction.vue';
import { routerReloadOnly, stripFilterParams } from '@/composables/useServerFilters';
import { show as jobApplicationsShow } from '@/routes/admin/job-applications/index';
import {
    applications as jobPostingsApplications,
    index as jobPostingsIndex,
} from '@/routes/admin/job-postings/index';

type ApplicationRow = {
    id: number;
    candidate_name: string;
    candidate_email: string;
    status: string;
    status_label: string;
    years_of_experience: number;
    applied_at: string;
    can_advance: boolean;
};

type StatusOption = {
    value: string;
    label: string;
};

type JobPostingSummary = {
    id: number;
    title: string;
    slug: string;
    status: string;
    status_label: string;
};

const props = defineProps<{
    job_posting: JobPostingSummary;
    applications: ApplicationRow[];
    filters: {
        status: string;
    };
    status_options: StatusOption[];
}>();

defineOptions({
    layout: (pageProps: { job_posting: JobPostingSummary }) => ({
        breadcrumbs: [
            { title: 'Job postings', href: jobPostingsIndex() },
            {
                title: 'Applications',
                href: jobPostingsApplications.url(pageProps.job_posting.id),
            },
        ],
    }),
});

function reloadStatus(status: string): void {
    routerReloadOnly(
        jobPostingsApplications.url(props.job_posting.id, {
            query: stripFilterParams({ status }),
        }),
        ['applications', 'filters'],
    );
}

function formatDate(iso: string): string {
    return new Date(iso).toLocaleString();
}
</script>

<template>

    <Head :title="`Applications · ${job_posting.title}`" />

    <div class="flex flex-col gap-6">
        <PageHeader :title="`Applications: ${job_posting.title}`"
            :description="`Posting status: ${job_posting.status_label}`">
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="jobPostingsIndex()">Back to postings</Link>
                </Button>
            </template>
        </PageHeader>

        <FormSelect id="filter-status" name="status" class="max-w-xs" :model-value="filters.status"
            :options="[{ value: '', label: 'All statuses' }, ...status_options]" placeholder="Filter by status"
            @update:model-value="reloadStatus" />

        <DataTable>
            <thead class="border-b border-border/60 bg-muted/40 backdrop-blur-sm">
                <tr>
                    <DataTableTh>Candidate</DataTableTh>
                    <DataTableTh>Status</DataTableTh>
                    <DataTableTh>Experience</DataTableTh>
                    <DataTableTh>Applied</DataTableTh>
                    <DataTableTh class="text-right">Actions</DataTableTh>
                </tr>
            </thead>
            <tbody>
                <tr v-for="application in applications" :key="application.id">
                    <DataTableTd label="Candidate">
                        <div class="font-medium">{{ application.candidate_name }}</div>
                        <div class="text-xs text-muted-foreground">
                            {{ application.candidate_email }}
                        </div>
                    </DataTableTd>
                    <DataTableTd label="Status">{{ application.status_label }}</DataTableTd>
                    <DataTableTd label="Experience">{{ application.years_of_experience }} years</DataTableTd>
                    <DataTableTd label="Applied">{{ formatDate(application.applied_at) }}</DataTableTd>
                    <DataTableTd label="Actions" class="text-right">
                        <TableIconAction
                            icon="eye"
                            label="View"
                            :href="jobApplicationsShow(application.id)"
                        />
                    </DataTableTd>
                </tr>
                <tr v-if="applications.length === 0">
                    <DataTableTd :colspan="5" label="No applications" class="text-center text-muted-foreground">
                        No applications yet.
                    </DataTableTd>
                </tr>
            </tbody>
        </DataTable>
    </div>
</template>
