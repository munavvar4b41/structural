<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import DataTable from '@/components/dashboard/DataTable.vue';
import DataTableEmptyRow from '@/components/dashboard/DataTableEmptyRow.vue';
import DataTableTd from '@/components/dashboard/DataTableTd.vue';
import DataTableTh from '@/components/dashboard/DataTableTh.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import ListToolbar from '@/components/ListToolbar.vue';
import TableIconAction from '@/components/TableIconAction.vue';
import { index as estimationReviewsIndex } from '@/routes/admin/estimation-reviews/index';
import { index as projectsIndex } from '@/routes/admin/projects/index';

type UserBrief = {
    id: number;
    name: string;
    email: string;
};

type QueueEstimation = {
    id: number;
    version: number;
    submitted_at: string | null;
    submission_notes: string | null;
    requirement: { id: number; title: string };
    project: { id: number; name: string; code: string | null };
    creator: UserBrief | null;
    requirement_show_url: string;
};

const props = defineProps<{
    estimations: QueueEstimation[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Projects', href: projectsIndex.url() },
            { title: 'Estimation reviews', href: estimationReviewsIndex.url() },
        ],
    },
});

const searchText = ref('');

const filteredEstimations = computed(() => {
    const needle = searchText.value.trim().toLowerCase();

    if (needle === '') {
        return props.estimations;
    }

    return props.estimations.filter((row) => {
        const haystack = [
            row.requirement.title,
            row.project.name,
            row.project.code ?? '',
            row.creator?.name ?? '',
            row.creator?.email ?? '',
        ]
            .join(' ')
            .toLowerCase();

        return haystack.includes(needle);
    });
});
</script>

<template>
    <Head title="Estimation reviews" />

    <div class="flex flex-col gap-8">
        <PageHeader
            title="Estimation reviews"
            description="Requirement estimations awaiting your approval."
        />

        <ListToolbar
            v-model="searchText"
            placeholder="Search requirement, project, or submitter…"
        />

        <DataTable>
            <thead>
                <tr class="border-b border-border/60 bg-muted/40 backdrop-blur-sm">
                    <DataTableTh>Requirement</DataTableTh>
                    <DataTableTh>Project</DataTableTh>
                    <DataTableTh>Submitted</DataTableTh>
                    <DataTableTh>By</DataTableTh>
                    <DataTableTh class="text-right">Action</DataTableTh>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in filteredEstimations" :key="row.id">
                    <DataTableTd label="Requirement">
                        <span class="font-medium">{{ row.requirement.title }}</span>
                        <span class="mt-0.5 block text-xs text-muted-foreground">v{{ row.version }}</span>
                    </DataTableTd>
                    <DataTableTd label="Project">
                        {{ row.project.name }}
                        <span v-if="row.project.code" class="text-muted-foreground">
                            ({{ row.project.code }})
                        </span>
                    </DataTableTd>
                    <DataTableTd label="Submitted">
                        {{
                            row.submitted_at
                                ? new Date(row.submitted_at).toLocaleString()
                                : '—'
                        }}
                    </DataTableTd>
                    <DataTableTd label="By">
                        {{ row.creator?.name ?? '—' }}
                    </DataTableTd>
                    <DataTableTd label="Action" class="text-right">
                        <TableIconAction
                            icon="arrow-right"
                            label="Review"
                            :href="row.requirement_show_url"
                        />
                    </DataTableTd>
                </tr>
                <DataTableEmptyRow
                    v-if="filteredEstimations.length === 0"
                    :colspan="5"
                    :message="estimations.length === 0
                        ? 'No estimations pending your approval.'
                        : 'No estimations match your search.'"
                />
            </tbody>
        </DataTable>
    </div>
</template>
