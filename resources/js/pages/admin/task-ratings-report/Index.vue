<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import ListToolbar from '@/components/ListToolbar.vue';
import TaskFormSelect from '@/components/TaskFormSelect.vue';
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
</script>

<template>
    <Head title="Task ratings report" />

    <div class="flex flex-col gap-8">
        <div class="flex flex-col gap-3">
            <Heading
                title="Task ratings report"
                description="Averages from completed task reviews. Filter by date, project, or staff involved."
            />
            <ListToolbar
                v-model="localSearch"
                placeholder="Narrow by staff name, task, or project (table results)…"
            />
        </div>

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
                    <TaskFormSelect
                        id="tr-project"
                        v-model="projectValue"
                        name="project_id"
                        none-label="All projects"
                        placeholder="All projects"
                        :options="projectSelectOptions"
                    />
                </div>
                <div class="grid min-w-[200px] flex-1 gap-2">
                    <Label for="tr-user">Staff involved</Label>
                    <TaskFormSelect
                        id="tr-user"
                        v-model="userValue"
                        name="user_id"
                        none-label="Everyone"
                        placeholder="Everyone"
                        :options="userSelectOptions"
                    />
                </div>
                <Button type="button" class="w-full sm:w-auto" @click="applyFilters">Apply</Button>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>By staff member</CardTitle>
                <CardDescription>
                    Separate averages when someone was rated as assignee vs. as task creator/owner.
                </CardDescription>
            </CardHeader>
            <CardContent class="overflow-x-auto">
                <table v-if="filteredRows.length > 0" class="w-full min-w-[640px] text-sm">
                    <thead>
                        <tr class="border-b border-border text-left text-xs font-medium text-muted-foreground">
                            <th class="pb-3 pr-4">Name</th>
                            <th class="pb-3 pr-4">Avg as assignee</th>
                            <th class="pb-3 pr-4">Reviews (assignee)</th>
                            <th class="pb-3 pr-4">Avg as creator</th>
                            <th class="pb-3 pr-4">Reviews (creator)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in filteredRows"
                            :key="row.user_id"
                            class="border-b border-border/60 align-top last:border-0"
                        >
                            <td class="py-3 pr-4 font-medium">{{ row.name }}</td>
                            <td class="py-3 pr-4 tabular-nums text-muted-foreground">
                                {{ avgLabel(row.assignee_avg) }}
                            </td>
                            <td class="py-3 pr-4 tabular-nums text-muted-foreground">
                                {{ row.assignee_count }}
                            </td>
                            <td class="py-3 pr-4 tabular-nums text-muted-foreground">
                                {{ avgLabel(row.creator_avg) }}
                            </td>
                            <td class="py-3 pr-4 tabular-nums text-muted-foreground">
                                {{ row.creator_count }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="text-sm text-muted-foreground">
                    {{ rows.length === 0 ? 'No ratings in this range.' : 'No rows match your search.' }}
                </p>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Recent reviews</CardTitle>
                <CardDescription>Latest confirmed tasks (up to 100 in this filter).</CardDescription>
            </CardHeader>
            <CardContent class="overflow-x-auto">
                <table v-if="filteredRecentReviews.length > 0" class="w-full min-w-[720px] text-sm">
                    <thead>
                        <tr class="border-b border-border text-left text-xs font-medium text-muted-foreground">
                            <th class="pb-3 pr-4">Date</th>
                            <th class="pb-3 pr-4">Task</th>
                            <th class="pb-3 pr-4">Project</th>
                            <th class="pb-3 pr-4">Task rating</th>
                            <th class="pb-3 pr-4">Assignee rating</th>
                            <th class="pb-3 pr-4">Creator rating</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="r in filteredRecentReviews"
                            :key="r.id"
                            class="border-b border-border/60 last:border-0"
                        >
                            <td class="py-3 pr-4 text-muted-foreground">
                                {{ new Date(r.created_at).toLocaleString() }}
                            </td>
                            <td class="py-3 pr-4">{{ r.task_title ?? '—' }}</td>
                            <td class="py-3 pr-4 text-muted-foreground">
                                {{ r.project_name ?? '—' }}
                                <span v-if="r.project_code">({{ r.project_code }})</span>
                            </td>
                            <td class="py-3 pr-4 tabular-nums">{{ r.task_rating }}</td>
                            <td class="py-3 pr-4 tabular-nums text-muted-foreground">
                                {{ r.assignee_rating ?? '—' }}
                            </td>
                            <td class="py-3 pr-4 tabular-nums text-muted-foreground">
                                {{ r.creator_rating ?? '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="text-sm text-muted-foreground">
                    {{
                        recent_reviews.length === 0
                            ? 'No reviews in this range.'
                            : 'No reviews match your search.'
                    }}
                </p>
            </CardContent>
        </Card>
    </div>
</template>
