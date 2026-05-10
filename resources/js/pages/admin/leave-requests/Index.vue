<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import LeaveRequestController from '@/actions/App/Http/Controllers/Admin/LeaveRequestController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
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
import { cn } from '@/lib/utils';
import { index as leaveRequestsIndex } from '@/routes/admin/leave-requests/index';

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

type Option = { value: string; label: string };

const props = defineProps<{
    leave_requests: LeaveRow[];
    type_options: Option[];
    half_day_period_options: Option[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Leave requests', href: leaveRequestsIndex() }],
    },
});

const leaveType = ref(props.type_options[0]?.value ?? 'full_day');
const halfDayPeriod = ref(props.half_day_period_options[0]?.value ?? 'first_half');
const breakStartsAt = ref('');
const breakEndsAt = ref('');

function pad2(n: number): string {
    return String(n).padStart(2, '0');
}

function toDatetimeLocalValue(d: Date): string {
    return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}T${pad2(d.getHours())}:${pad2(d.getMinutes())}`;
}

watch(breakStartsAt, (v) => {
    if (!v) {
        breakEndsAt.value = '';

        return;
    }

    const d = new Date(v);
    if (Number.isNaN(d.getTime())) {
        return;
    }

    d.setHours(d.getHours() + 1);
    breakEndsAt.value = toDatetimeLocalValue(d);
});

watch(leaveType, (t) => {
    if (t !== 'half_day' || halfDayPeriod.value !== '') {
        return;
    }

    halfDayPeriod.value = props.half_day_period_options[0]?.value ?? 'first_half';
});

const minDate = computed(() => {
    const t = new Date();

    return `${t.getFullYear()}-${pad2(t.getMonth() + 1)}-${pad2(t.getDate())}`;
});

function cancelRequest(row: LeaveRow): void {
    if (row.status !== 'pending') {
        return;
    }

    router.delete(LeaveRequestController.destroy.url({ leave_request: row.id }));
}

const searchText = ref('');
const statusFilter = ref('');
const typeFilter = ref('');

function setStatusFilter(v: string): void {
    statusFilter.value = v;
}

function setTypeFilter(v: string): void {
    typeFilter.value = v;
}

const statusFilterOptions = [
    { value: 'pending', label: 'Pending' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'cancelled', label: 'Cancelled' },
];

const filteredLeaveRequests = computed(() => {
    return props.leave_requests.filter((row) => {
        if (statusFilter.value !== '' && row.status !== statusFilter.value) {
            return false;
        }

        if (typeFilter.value !== '' && row.type !== typeFilter.value) {
            return false;
        }

        const needle = searchText.value.trim().toLowerCase();

        if (needle === '') {
            return true;
        }

        const hay = [
            row.reason,
            row.type_label,
            row.date,
            row.status_label,
            row.half_day_period_label,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return hay.includes(needle);
    });
});
</script>

<template>

    <Head title="Leave requests" />

    <div class="flex flex-col gap-8">
        <Heading title="Leave requests"
            description="Request time off. Super admins and admins must approve before leave is authorized." />

        <Card>
            <CardHeader>
                <CardTitle>New request</CardTitle>
                <CardDescription>
                    Full day, half day (morning or afternoon), or a one-hour break window on a single date.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Form v-bind="LeaveRequestController.store.form()" class="flex max-w-xl flex-col gap-6"
                    #default="{ errors, processing }">
                    <div class="grid gap-2">
                        <Label for="leave-type">Type</Label>
                        <TaskFormSelect id="leave-type" v-model="leaveType" name="type" required
                            :options="type_options" />
                        <InputError class="mt-1" :message="errors.type" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="leave-date">Date</Label>
                        <Input id="leave-date" name="date" type="date" required :min="minDate" />
                        <InputError class="mt-1" :message="errors.date" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="half-day-period">Half day period</Label>
                        <TaskFormSelect id="half-day-period" v-model="halfDayPeriod" name="half_day_period"
                            :required="leaveType === 'half_day'" :disabled="leaveType !== 'half_day'"
                            :exclude-from-submit="leaveType !== 'half_day'" :options="half_day_period_options" />
                        <InputError class="mt-1" :message="errors.half_day_period" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="break-start">Break starts</Label>
                        <Input id="break-start" v-model="breakStartsAt" name="break_starts_at" type="datetime-local"
                            :disabled="leaveType !== 'break'" :required="leaveType === 'break'" />
                        <InputError class="mt-1" :message="errors.break_starts_at" />
                        <input v-model="breakEndsAt" type="hidden" name="break_ends_at"
                            :disabled="leaveType !== 'break'" />
                        <p class="text-muted-foreground text-sm">
                            End time is set automatically to one hour after the start.
                        </p>
                        <InputError class="mt-1" :message="errors.break_ends_at" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="leave-reason">Reason (optional)</Label>
                        <textarea id="leave-reason" name="reason" rows="3" :class="cn(
                            'border-input placeholder:text-muted-foreground min-h-[80px] w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs md:text-sm',
                            'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                        )
                            " />
                        <InputError class="mt-1" :message="errors.reason" />
                    </div>

                    <div>
                        <Button type="submit" :disabled="processing">Submit request</Button>
                    </div>
                </Form>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Your requests</CardTitle>
                <CardDescription>Track status and cancel pending requests.</CardDescription>
            </CardHeader>
            <CardContent class="overflow-x-auto">
                <div class="mb-4 flex flex-col gap-4">
                    <ListToolbar v-model="searchText" placeholder="Search reason, date, type, status…">
                        <template #filters>
                            <div class="flex flex-wrap items-end gap-3">
                                <div class="grid gap-1">
                                    <Label class="text-xs text-muted-foreground" for="lr-status">Status</Label>
                                    <TaskFormSelect id="lr-status" name="lr_filter_status" class="w-[11rem]"
                                        :model-value="statusFilter" :options="statusFilterOptions"
                                        placeholder="All statuses" none-label="All statuses" exclude-from-submit
                                        @update:model-value="setStatusFilter" />
                                </div>
                                <div class="grid gap-1">
                                    <Label class="text-xs text-muted-foreground" for="lr-type">Type</Label>
                                    <TaskFormSelect id="lr-type" name="lr_filter_type" class="w-[12rem]"
                                        :model-value="typeFilter" :options="type_options" placeholder="All types"
                                        none-label="All types" exclude-from-submit
                                        @update:model-value="setTypeFilter" />
                                </div>
                            </div>
                        </template>
                    </ListToolbar>
                </div>
                <table class="w-full min-w-[640px] text-left text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="p-2 font-medium">Date</th>
                            <th class="p-2 font-medium">Type</th>
                            <th class="p-2 font-medium">Detail</th>
                            <th class="p-2 font-medium">Status</th>
                            <th class="p-2 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in filteredLeaveRequests" :key="row.id" class="border-b">
                            <td class="p-2">{{ row.date }}</td>
                            <td class="p-2">{{ row.type_label }}</td>
                            <td class="p-2">
                                <span v-if="row.type === 'half_day'">{{
                                    row.half_day_period_label
                                    }}</span>
                                <span v-else-if="row.type === 'break' && row.break_starts_at">
                                    {{
                                        new Date(row.break_starts_at).toLocaleTimeString(undefined, {
                                            hour: '2-digit',
                                            minute: '2-digit',
                                        })
                                    }}
                                    –
                                    {{
                                        new Date(row.break_ends_at ?? '').toLocaleTimeString(
                                            undefined,
                                            { hour: '2-digit', minute: '2-digit' },
                                        )
                                    }}
                                </span>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                            <td class="p-2">{{ row.status_label }}</td>
                            <td class="p-2 text-right">
                                <Button v-if="row.status === 'pending'" type="button" variant="outline" size="sm"
                                    @click="cancelRequest(row)">
                                    Cancel
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="filteredLeaveRequests.length === 0">
                            <td colspan="5" class="text-muted-foreground p-4 text-center">
                                {{
                                    leave_requests.length === 0
                                        ? 'No requests yet.'
                                        : 'No requests match your filters.'
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>
    </div>
</template>
