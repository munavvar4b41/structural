<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import ChartCard from '@/components/dashboard/ChartCard.vue';
import DataTable from '@/components/dashboard/DataTable.vue';
import DataTableEmptyRow from '@/components/dashboard/DataTableEmptyRow.vue';
import DataTableTd from '@/components/dashboard/DataTableTd.vue';
import DataTableTh from '@/components/dashboard/DataTableTh.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import TableRow from '@/components/dashboard/TableRow.vue';
import FormSelect from '@/components/FormSelect.vue';
import ListToolbar from '@/components/ListToolbar.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as projectsIndex } from '@/routes/admin/projects/index';
import { index as taskRatingsReportIndex } from '@/routes/admin/task-ratings-report/index';

type SelectOption = { value: number; label: string };

type AggregateRow = {
    user_id: number;
    name: string;
    assignee_avg: number | null;
    assignee_count: number;
    creator_avg: number | null;
    creator_count: number;
};

type RecentReview = {
    id: number;
    created_at: string;
    task_title: string | null;
    project_name: string | null;
    project_code: string | null;
    task_rating: number;
    assignee_rating: number | null;
    creator_rating: number | null;
};

const props = defineProps<{
    filters: {
        from: string;
        to: string;
        project_id: number | null;
        user_id: number | null;
    };
    project_options: SelectOption[];
    user_options: SelectOption[];
    rows: AggregateRow[];
    recent_reviews: RecentReview[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Projects', href: projectsIndex.url() },
            { title: 'Task ratings', href: taskRatingsReportIndex.url() },
        ],
    },
});

const fromValue = ref(props.filters.from);
const toValue = ref(props.filters.to);
const projectValue = ref(
    props.filters.project_id !== null ? String(props.filters.project_id) : '',
);
const userValue = ref(props.filters.user_id !== null ? String(props.filters.user_id) : '');

watch(
    () => [props.filters.from, props.filters.to, props.filters.project_id, props.filters.user_id],
    () => {
        fromValue.value = props.filters.from;
        toValue.value = props.filters.to;
        projectValue.value =
            props.filters.project_id !== null ? String(props.filters.project_id) : '';
        userValue.value = props.filters.user_id !== null ? String(props.filters.user_id) : '';
    },
);

const projectSelectOptions = computed(() =>
    props.project_options.map((o) => ({ value: String(o.value), label: o.label })),
);

const userSelectOptions = computed(() =>
    props.user_options.map((o) => ({ value: String(o.value), label: o.label })),
);

const localSearch = ref('');

const filteredRows = computed(() => {
    const needle = localSearch.value.trim().toLowerCase();

    if (needle === '') {
        return props.rows;
    }

    return props.rows.filter((row) => row.name.toLowerCase().includes(needle));
});

const filteredRecentReviews = computed(() => {
    const needle = localSearch.value.trim().toLowerCase();

    if (needle === '') {
        return props.recent_reviews;
    }

    return props.recent_reviews.filter((r) => {
        const parts = [r.task_title, r.project_name, r.project_code]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return parts.includes(needle);
    });
});

function applyFilters(): void {
    router.get(
        taskRatingsReportIndex.url({
            query: {
                from: fromValue.value,
                to: toValue.value,
                ...(projectValue.value !== '' ? { project_id: projectValue.value } : {}),
                ...(userValue.value !== '' ? { user_id: userValue.value } : {}),
            },
        }),
        {},
        { preserveScroll: true },
    );
}

function avgLabel(v: number | null): string {
    if (v === null) {
        return '—';
    }

    return String(v);
}

const ratingsChartSeries = computed(() => [
    {
        name: 'As assignee',
        data: filteredRows.value.map((row) => row.assignee_avg ?? 0),
    },
    {
        name: 'As creator',
        data: filteredRows.value.map((row) => row.creator_avg ?? 0),
    },
]);

const ratingsChartOptions = computed(() => ({
    chart: { type: 'bar' as const },
    plotOptions: {
        bar: {
            horizontal: true,
            borderRadius: 6,
        },
    },
    xaxis: {
        categories: filteredRows.value.map((row) => row.name),
        max: 5,
    },
}));
</script>

<template>

    <Head title="Task ratings report" />

    <div class="flex flex-col gap-6">
        <PageHeader title="Task ratings report"
            description="Averages from completed task reviews. Filter by date, project, or staff involved." />

        <ListToolbar v-model="localSearch" placeholder="Narrow by staff name, task, or project (table results)…" />

        <Card>
            <CardHeader>
                <CardTitle>Filters</CardTitle>
                <CardDescription>Reviews are counted by the date the reviewer confirmed the task.</CardDescription>
            </CardHeader>
            <CardContent class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end">
                <div class="grid gap-2">
                    <Label for="tr-from">From</Label>
                    <Input id="tr-from" v-model="fromValue" type="date" class="w-full sm:w-40" />
                </div>
                <div class="grid gap-2">
                    <Label for="tr-to">To</Label>
                    <Input id="tr-to" v-model="toValue" type="date" class="w-full sm:w-40" />
                </div>
                <div class="grid min-w-[200px] flex-1 gap-2">
                    <Label for="tr-project">Project</Label>
                    <FormSelect id="tr-project" v-model="projectValue" name="project_id" none-label="All projects"
                        placeholder="All projects" :options="projectSelectOptions" />
                </div>
                <div class="grid min-w-[200px] flex-1 gap-2">
                    <Label for="tr-user">Staff involved</Label>
                    <FormSelect id="tr-user" v-model="userValue" name="user_id" none-label="Everyone"
                        placeholder="Everyone" :options="userSelectOptions" />
                </div>
                <Button type="button" class="w-full sm:w-auto" @click="applyFilters">Apply</Button>
            </CardContent>
        </Card>

        <ChartCard v-if="filteredRows.length > 0" title="Rating averages"
            description="Compare assignee vs creator scores by staff member" type="bar" :series="ratingsChartSeries"
            :options="ratingsChartOptions" :height="320" />

        <Card>
            <CardHeader>
                <CardTitle>By staff member</CardTitle>
                <CardDescription>
                    Separate averages when someone was rated as assignee vs. as task creator/owner.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <DataTable>
                    <thead>
                        <tr class="border-b border-border/60 bg-muted/40 backdrop-blur-sm">
                            <DataTableTh>Name</DataTableTh>
                            <DataTableTh>Avg as assignee</DataTableTh>
                            <DataTableTh>Reviews (assignee)</DataTableTh>
                            <DataTableTh>Avg as creator</DataTableTh>
                            <DataTableTh>Reviews (creator)</DataTableTh>
                        </tr>
                    </thead>
                    <tbody>
                        <TableRow v-for="row in filteredRows" :key="row.user_id">
                            <DataTableTd label="Name" class="font-medium">{{ row.name }}</DataTableTd>
                            <DataTableTd label="Avg as assignee" class="tabular-nums text-muted-foreground">
                                {{ avgLabel(row.assignee_avg) }}
                            </DataTableTd>
                            <DataTableTd label="Reviews (assignee)" class="tabular-nums text-muted-foreground">
                                {{ row.assignee_count }}
                            </DataTableTd>
                            <DataTableTd label="Avg as creator" class="tabular-nums text-muted-foreground">
                                {{ avgLabel(row.creator_avg) }}
                            </DataTableTd>
                            <DataTableTd label="Reviews (creator)" class="tabular-nums text-muted-foreground">
                                {{ row.creator_count }}
                            </DataTableTd>
                        </TableRow>
                        <DataTableEmptyRow
                            v-if="filteredRows.length === 0"
                            :colspan="5"
                            :message="rows.length === 0
                                ? 'No ratings in this range.'
                                : 'No rows match your search.'"
                        />
                    </tbody>
                </DataTable>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Recent reviews</CardTitle>
                <CardDescription>Latest confirmed tasks (up to 100 in this filter).</CardDescription>
            </CardHeader>
            <CardContent>
                <DataTable>
                    <thead>
                        <tr class="border-b border-border/60 bg-muted/40 backdrop-blur-sm">
                            <DataTableTh>Date</DataTableTh>
                            <DataTableTh>Task</DataTableTh>
                            <DataTableTh>Project</DataTableTh>
                            <DataTableTh>Task rating</DataTableTh>
                            <DataTableTh>Assignee rating</DataTableTh>
                            <DataTableTh>Creator rating</DataTableTh>
                        </tr>
                    </thead>
                    <tbody>
                        <TableRow v-for="r in filteredRecentReviews" :key="r.id">
                            <DataTableTd label="Date" class="text-muted-foreground">
                                {{ new Date(r.created_at).toLocaleString() }}
                            </DataTableTd>
                            <DataTableTd label="Task">{{ r.task_title ?? '—' }}</DataTableTd>
                            <DataTableTd label="Project" class="text-muted-foreground">
                                {{ r.project_name ?? '—' }}
                                <span v-if="r.project_code">({{ r.project_code }})</span>
                            </DataTableTd>
                            <DataTableTd label="Task rating" class="tabular-nums">{{ r.task_rating }}</DataTableTd>
                            <DataTableTd label="Assignee rating" class="tabular-nums text-muted-foreground">
                                {{ r.assignee_rating ?? '—' }}
                            </DataTableTd>
                            <DataTableTd label="Creator rating" class="tabular-nums text-muted-foreground">
                                {{ r.creator_rating ?? '—' }}
                            </DataTableTd>
                        </TableRow>
                        <DataTableEmptyRow
                            v-if="filteredRecentReviews.length === 0"
                            :colspan="6"
                            :message="recent_reviews.length === 0
                                ? 'No reviews in this range.'
                                : 'No reviews match your search.'"
                        />
                    </tbody>
                </DataTable>
            </CardContent>
        </Card>
    </div>
</template>
