<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import DataTable from '@/components/dashboard/DataTable.vue';
import DataTableTd from '@/components/dashboard/DataTableTd.vue';
import DataTableTh from '@/components/dashboard/DataTableTh.vue';
import DataTableEmptyRow from '@/components/dashboard/DataTableEmptyRow.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import FormSelect from '@/components/FormSelect.vue';
import ListToolbar from '@/components/ListToolbar.vue';
import TableIconAction from '@/components/TableIconAction.vue';
import { index as projectsIndex } from '@/routes/admin/projects/index';
import { index as understandingReviewsIndex } from '@/routes/admin/understanding-reviews/index';

type UserBrief = {
    id: number;
    name: string;
    email: string;
};

type QueueRequirement = {
    id: number;
    title: string;
    review_stage: 'pending_review' | 'awaiting_confirmation';
    reviewed_at: string | null;
    project: { id: number; name: string; code: string | null };
    creator: UserBrief | null;
    reviewer: UserBrief | null;
    responsible_user: UserBrief | null;
    requirement_show_url: string;
};

const props = defineProps<{
    requirements: QueueRequirement[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Projects', href: projectsIndex.url() },
            { title: 'Understanding reviews', href: understandingReviewsIndex.url() },
        ],
    },
});

const searchText = ref('');
const stageFilter = ref('');

const stageFilterOptions = [
    { value: 'pending_review', label: 'Pending review' },
    { value: 'awaiting_confirmation', label: 'Awaiting confirmation' },
];

function stageLabel(stage: QueueRequirement['review_stage']): string {
    return stage === 'pending_review' ? 'Pending review' : 'Awaiting confirmation';
}

const filteredRequirements = computed(() => {
    return props.requirements.filter((row) => {
        if (stageFilter.value !== '' && row.review_stage !== stageFilter.value) {
            return false;
        }

        const needle = searchText.value.trim().toLowerCase();

        if (needle === '') {
            return true;
        }

        const haystack = [
            row.title,
            row.project.name,
            row.project.code ?? '',
            row.creator?.name ?? '',
            row.reviewer?.name ?? '',
            row.responsible_user?.name ?? '',
        ]
            .join(' ')
            .toLowerCase();

        return haystack.includes(needle);
    });
});
</script>

<template>
    <Head title="Understanding reviews" />

    <div class="flex flex-col gap-8">
        <PageHeader
            title="Understanding reviews"
            description="Requirements awaiting your review or confirmation of reviewer understanding."
        />

        <ListToolbar
            v-model="searchText"
            placeholder="Search requirement, project, or people…"
        >
            <template #filters>
                <FormSelect
                    id="filter-understanding-stage"
                    name="review_stage"
                    class="w-[14rem]"
                    :model-value="stageFilter"
                    none-label="All stages"
                    placeholder="All stages"
                    :options="stageFilterOptions"
                    exclude-from-submit
                    @update:model-value="(v) => (stageFilter = v)"
                />
            </template>
        </ListToolbar>

        <DataTable>
            <thead>
                <tr class="border-b border-border/60 bg-muted/40 backdrop-blur-sm">
                    <DataTableTh>Requirement</DataTableTh>
                    <DataTableTh>Project</DataTableTh>
                    <DataTableTh>Stage</DataTableTh>
                    <DataTableTh>Responsible</DataTableTh>
                    <DataTableTh class="text-right">Action</DataTableTh>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in filteredRequirements" :key="row.id">
                    <DataTableTd label="Requirement">
                        <span class="font-medium">{{ row.title }}</span>
                    </DataTableTd>
                    <DataTableTd label="Project">
                        {{ row.project.name }}
                        <span v-if="row.project.code" class="text-muted-foreground">
                            ({{ row.project.code }})
                        </span>
                    </DataTableTd>
                    <DataTableTd label="Stage">
                        <span
                            class="inline-flex w-fit items-center rounded-md border border-input bg-background px-2 py-0.5 text-xs font-medium"
                        >
                            {{ stageLabel(row.review_stage) }}
                        </span>
                    </DataTableTd>
                    <DataTableTd label="Responsible">
                        {{ row.responsible_user?.name ?? '—' }}
                    </DataTableTd>
                    <DataTableTd label="Action" class="text-right">
                        <TableIconAction
                            :href="row.requirement_show_url"
                            label="Review"
                            icon="arrow-right"
                        />
                    </DataTableTd>
                </tr>
                <DataTableEmptyRow
                    v-if="filteredRequirements.length === 0"
                    :colspan="5"
                    :message="requirements.length === 0
                        ? 'No requirements awaiting your action.'
                        : 'No requirements match your filters.'"
                />
            </tbody>
        </DataTable>
    </div>
</template>
