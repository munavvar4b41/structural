<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import LeaveRequestController from '@/actions/App/Http/Controllers/Admin/LeaveRequestController';
import DataTable from '@/components/dashboard/DataTable.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
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
import { Label } from '@/components/ui/label';
import {
    index as leaveRequestsIndex,
    manage as leaveRequestsManage,
} from '@/routes/admin/leave-requests/index';

type LeaveRow = {
    id: number;
    type: string;
    type_label: string;
    date: string;
    half_day_period: string | null;
    half_day_period_label: string | null;
    break_starts_at: string | null;
    break_ends_at: string | null;
    status: string;
    status_label: string;
    reason: string | null;
    reviewed_at: string | null;
    user: { id: number; name: string; email: string } | null;
    reviewed_by: { id: number; name: string } | null;
};

const props = defineProps<{
    leave_requests: LeaveRow[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Leave requests', href: leaveRequestsIndex() },
            { title: 'Approvals', href: leaveRequestsManage() },
        ],
    },
});

const searchText = ref('');
const statusFilter = ref('pending');
const typeFilter = ref('');
const userFilter = ref('');

function approve(row: LeaveRow): void {
    if (row.status !== 'pending') {
        return;
    }

    router.patch(LeaveRequestController.approve.url({ leaveRequest: row.id }));
}

function reject(row: LeaveRow): void {
    if (row.status !== 'pending') {
        return;
    }

    router.patch(LeaveRequestController.reject.url({ leaveRequest: row.id }));
}

function detailLabel(row: LeaveRow): string {
    if (row.type === 'half_day') {
        return row.half_day_period_label ?? '—';
    }

    if (row.type === 'break' && row.break_starts_at && row.break_ends_at) {
        const s = new Date(row.break_starts_at).toLocaleTimeString(undefined, {
            hour: '2-digit',
            minute: '2-digit',
        });
        const e = new Date(row.break_ends_at).toLocaleTimeString(undefined, {
            hour: '2-digit',
            minute: '2-digit',
        });

        return `${s} – ${e}`;
    }

    return '—';
}

const statusFilterOptions = [
    { value: 'pending', label: 'Pending' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'cancelled', label: 'Cancelled' },
];

const userFilterOptions = computed(() => {
    const map = new Map<number, string>();

    for (const r of props.leave_requests) {
        if (r.user !== null) {
            map.set(r.user.id, r.user.name);
        }
    }

    const opts = Array.from(map.entries())
        .sort((a, b) => a[1].localeCompare(b[1]))
        .map(([id, name]) => ({
            value: String(id),
            label: name,
        }));

    return opts;
});

const typeFilterOptions = computed(() => {
    const seen = new Map<string, string>();

    for (const r of props.leave_requests) {
        if (!seen.has(r.type)) {
            seen.set(r.type, r.type_label);
        }
    }

    return Array.from(seen.entries()).map(([value, label]) => ({
        value,
        label,
    }));
});

function setStatusFilter(v: string): void {
    statusFilter.value = v;
}

function setTypeFilter(v: string): void {
    typeFilter.value = v;
}

function setUserFilter(v: string): void {
    userFilter.value = v;
}

const filteredLeaveRequests = computed(() => {
    return props.leave_requests.filter((row) => {
        if (statusFilter.value !== '' && row.status !== statusFilter.value) {
            return false;
        }

        if (typeFilter.value !== '' && row.type !== typeFilter.value) {
            return false;
        }

        if (userFilter.value !== '') {
            const uid = Number.parseInt(userFilter.value, 10);

            if (row.user === null || row.user.id !== uid) {
                return false;
            }
        }

        const needle = searchText.value.trim().toLowerCase();

        if (needle === '') {
            return true;
        }

        const hay = [
            row.user?.name,
            row.user?.email,
            row.reason,
            row.date,
            row.type_label,
            row.status_label,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return hay.includes(needle);
    });
});
</script>

<template>
    <Head title="Leave approvals" />

    <div class="flex flex-col gap-6">
        <PageHeader
            title="Leave approvals"
            description="Approve or reject pending requests from staff and team heads."
        />

        <Card>
            <CardHeader>
                <CardTitle>All leave requests</CardTitle>
                <CardDescription>Pending items appear first.</CardDescription>
            </CardHeader>
            <CardContent>
                <div class="mb-4 flex flex-col gap-4">
                    <ListToolbar
                        v-model="searchText"
                        placeholder="Search requester, reason, date, type…"
                    >
                        <template #filters>
                            <div class="flex flex-wrap items-end gap-3">
                                <div class="grid gap-1">
                                    <Label class="text-xs text-muted-foreground" for="lm-status"
                                        >Status</Label
                                    >
                                    <TaskFormSelect
                                        id="lm-status"
                                        name="lm_filter_status"
                                        class="w-[11rem]"
                                        :model-value="statusFilter"
                                        :options="statusFilterOptions"
                                        placeholder="All statuses"
                                        none-label="All statuses"
                                        exclude-from-submit
                                        @update:model-value="setStatusFilter"
                                    />
                                </div>
                                <div class="grid gap-1">
                                    <Label class="text-xs text-muted-foreground" for="lm-type">Type</Label>
                                    <TaskFormSelect
                                        id="lm-type"
                                        name="lm_filter_type"
                                        class="w-[12rem]"
                                        :model-value="typeFilter"
                                        :options="typeFilterOptions"
                                        placeholder="All types"
                                        none-label="All types"
                                        exclude-from-submit
                                        @update:model-value="setTypeFilter"
                                    />
                                </div>
                                <div class="grid gap-1">
                                    <Label class="text-xs text-muted-foreground" for="lm-user">Requester</Label>
                                    <TaskFormSelect
                                        id="lm-user"
                                        name="lm_filter_user"
                                        class="min-w-[12rem]"
                                        :model-value="userFilter"
                                        :options="userFilterOptions"
                                        placeholder="Everyone"
                                        none-label="Everyone"
                                        exclude-from-submit
                                        @update:model-value="setUserFilter"
                                    />
                                </div>
                            </div>
                        </template>
                    </ListToolbar>
                </div>

                <DataTable min-width="880px">
                    <thead>
                        <tr class="border-b border-border/60 bg-muted/40 backdrop-blur-sm">
                            <th class="px-5 py-3.5 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                Requester
                            </th>
                            <th class="px-5 py-3.5 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                Date
                            </th>
                            <th class="px-5 py-3.5 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                Type
                            </th>
                            <th class="px-5 py-3.5 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                Detail
                            </th>
                            <th class="px-5 py-3.5 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                Status
                            </th>
                            <th class="px-5 py-3.5 text-xs font-medium uppercase tracking-wide text-muted-foreground" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in filteredLeaveRequests"
                            :key="row.id"
                            class="border-b border-border/40 transition-colors even:bg-muted/15 hover:bg-muted/30"
                        >
                            <td class="px-5 py-3.5">
                                <div class="font-medium">{{ row.user?.name ?? '—' }}</div>
                                <div class="text-xs text-muted-foreground">
                                    {{ row.user?.email }}
                                </div>
                            </td>
                            <td class="px-5 py-3.5">{{ row.date }}</td>
                            <td class="px-5 py-3.5">{{ row.type_label }}</td>
                            <td class="px-5 py-3.5">{{ detailLabel(row) }}</td>
                            <td class="px-5 py-3.5">{{ row.status_label }}</td>
                            <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                <template v-if="row.status === 'pending'">
                                    <Button
                                        type="button"
                                        size="sm"
                                        class="mr-2"
                                        @click="approve(row)"
                                    >
                                        Approve
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        @click="reject(row)"
                                    >
                                        Reject
                                    </Button>
                                </template>
                                <span v-else class="text-muted-foreground text-xs">
                                    <template v-if="row.reviewed_by">
                                        By {{ row.reviewed_by.name }}
                                    </template>
                                </span>
                            </td>
                        </tr>
                        <tr v-if="filteredLeaveRequests.length === 0">
                            <td colspan="6" class="px-5 py-8 text-center text-muted-foreground">
                                {{
                                    leave_requests.length === 0
                                        ? 'No leave requests yet.'
                                        : 'No requests match your filters.'
                                }}
                            </td>
                        </tr>
                    </tbody>
                </DataTable>
            </CardContent>
        </Card>
    </div>
</template>
