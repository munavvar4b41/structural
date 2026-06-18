<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Calculator } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import DataTable from '@/components/dashboard/DataTable.vue';
import DataTableTd from '@/components/dashboard/DataTableTd.vue';
import DataTableTh from '@/components/dashboard/DataTableTh.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import ListToolbar from '@/components/ListToolbar.vue';
import { Button } from '@/components/ui/button';
import { index as projectsIndex } from '@/routes/admin/projects/index';
import { index as estimationReviewsIndex } from '@/routes/admin/estimation-reviews/index';

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

        <DataTable v-if="filteredEstimations.length > 0">
            <template #head>
                <tr>
                    <DataTableTh>Requirement</DataTableTh>
                    <DataTableTh>Project</DataTableTh>
                    <DataTableTh>Submitted</DataTableTh>
                    <DataTableTh>By</DataTableTh>
                    <DataTableTh class="text-right">Action</DataTableTh>
                </tr>
            </template>
            <template #body>
                <tr v-for="row in filteredEstimations" :key="row.id">
                    <DataTableTd data-label="Requirement">
                        <span class="font-medium">{{ row.requirement.title }}</span>
                        <span class="mt-0.5 block text-xs text-muted-foreground">v{{ row.version }}</span>
                    </DataTableTd>
                    <DataTableTd data-label="Project">
                        {{ row.project.name }}
                        <span v-if="row.project.code" class="text-muted-foreground">
                            ({{ row.project.code }})
                        </span>
                    </DataTableTd>
                    <DataTableTd data-label="Submitted">
                        {{
                            row.submitted_at
                                ? new Date(row.submitted_at).toLocaleString()
                                : '—'
                        }}
                    </DataTableTd>
                    <DataTableTd data-label="By">
                        {{ row.creator?.name ?? '—' }}
                    </DataTableTd>
                    <DataTableTd data-label="Action" class="text-right">
                        <Button variant="outline" size="sm" as-child>
                            <Link :href="row.requirement_show_url">Review</Link>
                        </Button>
                    </DataTableTd>
                </tr>
            </template>
        </DataTable>

        <div
            v-else
            class="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-border py-16 text-center"
        >
            <Calculator class="size-10 text-muted-foreground" aria-hidden="true" />
            <p class="text-sm text-muted-foreground">No estimations pending your approval.</p>
        </div>
    </div>
</template>
