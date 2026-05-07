<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
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
import { formatSeconds } from '@/lib/formatSeconds';
import { index as timeReportIndex } from '@/routes/admin/time-report/index';

type SelectOption = { value: number; label: string };

type PerDayProjectRow = {
    project_id: number;
    project_name: string | null;
    total_seconds: number;
};

type PerDayRow = {
    date: string;
    total_seconds: number;
    projects: PerDayProjectRow[];
};

type PerProjectRow = {
    project_id: number;
    project_name: string | null;
    project_code: string | null;
    total_seconds: number;
    task_count: number;
};

type PerTaskRow = {
    task_id: number;
    task_title: string | null;
    project_id: number;
    project_name: string | null;
    total_seconds: number;
};

type EntryRow = {
    id: number;
    project_id: number;
    project_name: string | null;
    project_code: string | null;
    task_id: number;
    task_title: string | null;
    started_at: string | null;
    ended_at: string | null;
    duration_seconds: number | null;
    source: string;
    notes: string | null;
};

const props = defineProps<{
    filters: {
        from: string;
        to: string;
        user_id: number;
        project_id: number | null;
    };
    target_user: { id: number; name: string; email: string };
    can_view_other_users: boolean;
    user_options: SelectOption[];
    project_options: SelectOption[];
    per_day: PerDayRow[];
    per_project: PerProjectRow[];
    per_task: PerTaskRow[];
    totals: { seconds: number; entries: number };
    entries: EntryRow[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Time report', href: timeReportIndex.url() },
        ],
    },
});

const fromValue = ref(props.filters.from);
const toValue = ref(props.filters.to);
const userValue = ref(String(props.filters.user_id));
const projectValue = ref(
    props.filters.project_id !== null ? String(props.filters.project_id) : '',
);

watch(
    () => [props.filters.from, props.filters.to, props.filters.user_id, props.filters.project_id],
    () => {
        fromValue.value = props.filters.from;
        toValue.value = props.filters.to;
        userValue.value = String(props.filters.user_id);
        projectValue.value =
            props.filters.project_id !== null ? String(props.filters.project_id) : '';
    },
);

const userSelectOptions = computed(() =>
    props.user_options.map((o) => ({ value: String(o.value), label: o.label })),
);

const projectSelectOptions = computed(() =>
    props.project_options.map((o) => ({ value: String(o.value), label: o.label })),
);

function applyFilters(): void {
    router.get(
        timeReportIndex.url({
            query: {
                from: fromValue.value,
                to: toValue.value,
                user_id: userValue.value,
                project_id: projectValue.value === '' ? undefined : projectValue.value,
            },
        }),
        {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function applyPreset(preset: 'today' | 'week' | 'month'): void {
    const now = new Date();
    const pad = (n: number) => String(n).padStart(2, '0');
    const fmt = (d: Date): string =>
        `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

    let start = new Date(now);
    const end = new Date(now);

    if (preset === 'today') {
        // start = now (truncated)
    } else if (preset === 'week') {
        const day = now.getDay();
        const diff = (day + 6) % 7;
        start = new Date(now);
        start.setDate(now.getDate() - diff);
    } else {
        start = new Date(now.getFullYear(), now.getMonth(), 1);
    }

    fromValue.value = fmt(start);
    toValue.value = fmt(end);
    applyFilters();
}

function formatDateLabel(date: string): string {
    try {
        return new Date(`${date}T00:00:00`).toLocaleDateString(undefined, {
            weekday: 'short',
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    } catch {
        return date;
    }
}

function formatEntryWhen(start: string | null, end: string | null): string {
    if (start === null) {
        return '—';
    }

    const startLabel = new Date(start).toLocaleString();

    if (end === null) {
        return `${startLabel} → running`;
    }

    return `${startLabel} → ${new Date(end).toLocaleString()}`;
}
</script>

<template>
    <Head title="Time report" />

    <div class="flex flex-col gap-6">
        <Heading
            title="Time report"
            description="Aggregate of completed time entries by day, project, and task."
        />

        <Card>
            <CardHeader>
                <CardTitle>Filters</CardTitle>
                <CardDescription>
                    Pick a date range, user, and optional project. Defaults to today's entries.
                </CardDescription>
            </CardHeader>
            <CardContent class="grid gap-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="grid gap-2">
                        <Label for="filter-from">From</Label>
                        <Input
                            id="filter-from"
                            type="date"
                            v-model="fromValue"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="filter-to">To</Label>
                        <Input
                            id="filter-to"
                            type="date"
                            v-model="toValue"
                        />
                    </div>
                    <div v-if="can_view_other_users" class="grid gap-2">
                        <Label for="filter-user">User</Label>
                        <TaskFormSelect
                            id="filter-user"
                            name="user_id"
                            v-model="userValue"
                            required
                            placeholder="User"
                            :options="userSelectOptions"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="filter-project">Project</Label>
                        <TaskFormSelect
                            id="filter-project"
                            name="project_id"
                            v-model="projectValue"
                            none-label="All projects"
                            placeholder="All projects"
                            :options="projectSelectOptions"
                        />
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Button type="button" variant="outline" size="sm" @click="applyPreset('today')">
                        Today
                    </Button>
                    <Button type="button" variant="outline" size="sm" @click="applyPreset('week')">
                        This week
                    </Button>
                    <Button type="button" variant="outline" size="sm" @click="applyPreset('month')">
                        This month
                    </Button>
                    <Button type="button" class="ml-auto" @click="applyFilters">Apply filters</Button>
                </div>
            </CardContent>
        </Card>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-lg border border-border/60 bg-muted/20 p-4">
                <p class="text-xs text-muted-foreground">Total time</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ formatSeconds(totals.seconds) }}
                </p>
            </div>
            <div class="rounded-lg border border-border/60 bg-muted/20 p-4">
                <p class="text-xs text-muted-foreground">Entries</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ totals.entries }}</p>
            </div>
            <div class="rounded-lg border border-border/60 bg-muted/20 p-4">
                <p class="text-xs text-muted-foreground">User</p>
                <p class="mt-1 text-sm font-medium">{{ target_user.name }}</p>
                <p class="text-xs text-muted-foreground">{{ target_user.email }}</p>
            </div>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Per day</CardTitle>
                <CardDescription>Total time logged each day, broken down by project.</CardDescription>
            </CardHeader>
            <CardContent class="overflow-x-auto">
                <table class="w-full min-w-[480px] text-left text-sm">
                    <thead class="border-b bg-muted/40">
                        <tr>
                            <th class="px-3 py-2 font-medium">Date</th>
                            <th class="px-3 py-2 font-medium">Total</th>
                            <th class="px-3 py-2 font-medium">Projects</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in per_day"
                            :key="row.date"
                            class="border-b border-border/60 last:border-0 align-top"
                        >
                            <td class="px-3 py-2">{{ formatDateLabel(row.date) }}</td>
                            <td class="px-3 py-2 font-medium tabular-nums">
                                {{ formatSeconds(row.total_seconds) }}
                            </td>
                            <td class="px-3 py-2 text-muted-foreground">
                                <ul class="grid gap-1">
                                    <li v-for="p in row.projects" :key="p.project_id">
                                        <span class="font-medium text-foreground">
                                            {{ p.project_name ?? `Project #${p.project_id}` }}
                                        </span>
                                        <span class="ml-2 tabular-nums">
                                            {{ formatSeconds(p.total_seconds) }}
                                        </span>
                                    </li>
                                </ul>
                            </td>
                        </tr>
                        <tr v-if="per_day.length === 0">
                            <td colspan="3" class="px-3 py-8 text-center text-muted-foreground">
                                No time tracked in this range.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Per project</CardTitle>
                <CardDescription>Total time spent per project across the date range.</CardDescription>
            </CardHeader>
            <CardContent class="overflow-x-auto">
                <table class="w-full min-w-[480px] text-left text-sm">
                    <thead class="border-b bg-muted/40">
                        <tr>
                            <th class="px-3 py-2 font-medium">Project</th>
                            <th class="px-3 py-2 font-medium">Tasks</th>
                            <th class="px-3 py-2 font-medium">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in per_project"
                            :key="row.project_id"
                            class="border-b border-border/60 last:border-0"
                        >
                            <td class="px-3 py-2">
                                {{ row.project_name ?? `Project #${row.project_id}` }}
                                <span v-if="row.project_code" class="ml-1 text-xs text-muted-foreground">
                                    ({{ row.project_code }})
                                </span>
                            </td>
                            <td class="px-3 py-2 text-muted-foreground tabular-nums">
                                {{ row.task_count }}
                            </td>
                            <td class="px-3 py-2 font-medium tabular-nums">
                                {{ formatSeconds(row.total_seconds) }}
                            </td>
                        </tr>
                        <tr v-if="per_project.length === 0">
                            <td colspan="3" class="px-3 py-8 text-center text-muted-foreground">
                                No project totals.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Per task</CardTitle>
                <CardDescription>Total time spent per task in the date range.</CardDescription>
            </CardHeader>
            <CardContent class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-left text-sm">
                    <thead class="border-b bg-muted/40">
                        <tr>
                            <th class="px-3 py-2 font-medium">Task</th>
                            <th class="px-3 py-2 font-medium">Project</th>
                            <th class="px-3 py-2 font-medium">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in per_task"
                            :key="row.task_id"
                            class="border-b border-border/60 last:border-0"
                        >
                            <td class="px-3 py-2">{{ row.task_title ?? `Task #${row.task_id}` }}</td>
                            <td class="px-3 py-2 text-muted-foreground">
                                {{ row.project_name ?? `Project #${row.project_id}` }}
                            </td>
                            <td class="px-3 py-2 font-medium tabular-nums">
                                {{ formatSeconds(row.total_seconds) }}
                            </td>
                        </tr>
                        <tr v-if="per_task.length === 0">
                            <td colspan="3" class="px-3 py-8 text-center text-muted-foreground">
                                No task totals.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Entries</CardTitle>
                <CardDescription>
                    Up to 500 most recent entries in this range.
                </CardDescription>
            </CardHeader>
            <CardContent class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-left text-sm">
                    <thead class="border-b bg-muted/40">
                        <tr>
                            <th class="px-3 py-2 font-medium">When</th>
                            <th class="px-3 py-2 font-medium">Project</th>
                            <th class="px-3 py-2 font-medium">Task</th>
                            <th class="px-3 py-2 font-medium">Duration</th>
                            <th class="px-3 py-2 font-medium">Source</th>
                            <th class="px-3 py-2 font-medium">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="entry in entries"
                            :key="entry.id"
                            class="border-b border-border/60 last:border-0"
                        >
                            <td class="px-3 py-2 text-muted-foreground">
                                {{ formatEntryWhen(entry.started_at, entry.ended_at) }}
                            </td>
                            <td class="px-3 py-2 text-muted-foreground">
                                {{ entry.project_name ?? `Project #${entry.project_id}` }}
                            </td>
                            <td class="px-3 py-2">
                                {{ entry.task_title ?? `Task #${entry.task_id}` }}
                            </td>
                            <td class="px-3 py-2 font-medium tabular-nums">
                                {{ formatSeconds(entry.duration_seconds) }}
                            </td>
                            <td class="px-3 py-2 text-muted-foreground capitalize">
                                {{ entry.source }}
                            </td>
                            <td class="px-3 py-2 text-muted-foreground line-clamp-2 break-words">
                                {{ entry.notes ?? '—' }}
                            </td>
                        </tr>
                        <tr v-if="entries.length === 0">
                            <td colspan="6" class="px-3 py-8 text-center text-muted-foreground">
                                No entries.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>
    </div>
</template>
