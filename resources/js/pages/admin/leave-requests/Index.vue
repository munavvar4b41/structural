<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import LeaveRequestController from '@/actions/App/Http/Controllers/Admin/LeaveRequestController';
import DataTable from '@/components/dashboard/DataTable.vue';
import DataTableTd from '@/components/dashboard/DataTableTd.vue';
import DataTableTh from '@/components/dashboard/DataTableTh.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import TableRow from '@/components/dashboard/TableRow.vue';
import FormSelect from '@/components/FormSelect.vue';
import InputError from '@/components/InputError.vue';
import ListToolbar from '@/components/ListToolbar.vue';
import TableIconAction from '@/components/TableIconAction.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
const leaveRequestOpen = ref(false);

function pad2(n: number): string {
    return String(n).padStart(2, '0');
}

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

    <div class="flex flex-col gap-6">
        <PageHeader title="Leave requests"
            description="Request time off. Super admins and admins must approve before leave is authorized.">
            <template #actions>
                <Button variant="outline" size="sm" type="button" @click="leaveRequestOpen = true">
                    Add leave request
                </Button>
            </template>
        </PageHeader>

        <Dialog v-model:open="leaveRequestOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Add leave request</DialogTitle>
                    <DialogDescription>Request time off.</DialogDescription>
                </DialogHeader>

                <Form v-bind="LeaveRequestController.store.form()" class="grid gap-4"
                    @success="leaveRequestOpen = false" #default="{ errors, processing }">
                    <div class="grid gap-2">
                        <Label for="leave-type">Type</Label>
                        <FormSelect id="leave-type" v-model="leaveType" name="type" required :options="type_options" />
                        <InputError :message="errors.type" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="leave-date">Date</Label>
                        <Input id="leave-date" name="date" type="date" required :min="minDate" />
                        <InputError :message="errors.date" />
                    </div>

                    <div class="grid gap-2" v-if="leaveType === 'half_day'">
                        <Label for="half-day-period">Half day period</Label>
                        <FormSelect id="half-day-period" v-model="halfDayPeriod" name="half_day_period"
                            :required="leaveType === 'half_day'" :disabled="leaveType !== 'half_day'"
                            :exclude-from-submit="leaveType !== 'half_day'" :options="half_day_period_options" />
                        <InputError :message="errors.half_day_period" />
                    </div>

                    <div class="grid gap-2" v-if="leaveType === 'break'">
                        <Label for="break-start">Break starts</Label>
                        <Input id="break-start" v-model="breakStartsAt" name="break_starts_at" type="datetime-local"
                            :disabled="leaveType !== 'break'" :required="leaveType === 'break'" />
                        <InputError :message="errors.break_starts_at" />
                    </div>

                    <div class="grid gap-2" v-if="leaveType === 'break'">
                        <Label for="break-end">Break ends</Label>
                        <Input id="break-end" v-model="breakEndsAt" name="break_ends_at" type="datetime-local"
                            :disabled="leaveType !== 'break'" :required="leaveType === 'break'" />
                        <p class="text-muted-foreground text-sm">
                            Start and end must be on the selected date.
                        </p>
                        <InputError :message="errors.break_ends_at" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="leave-reason">Reason (optional)</Label>
                        <textarea id="leave-reason" name="reason" rows="3" :class="cn(
                            'border-input placeholder:text-muted-foreground min-h-[80px] w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs md:text-sm',
                            'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                        )
                            " />
                        <InputError :message="errors.reason" />
                    </div>

                    <DialogFooter class="gap-3">
                        <Button type="button" variant="outline" @click="leaveRequestOpen = false">
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="processing">Submit request</Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>

        <Card>
            <CardHeader>
                <CardTitle>Your requests</CardTitle>
                <CardDescription>Track status and cancel pending requests.</CardDescription>
            </CardHeader>
            <CardContent>
                <div class="mb-4 flex flex-col gap-4">
                    <ListToolbar v-model="searchText" placeholder="Search reason, date, type, status…">
                        <template #filters>
                            <div class="flex flex-wrap items-end gap-3">
                                <div class="grid gap-1">
                                    <Label class="text-xs text-muted-foreground" for="lr-status">Status</Label>
                                    <FormSelect id="lr-status" name="lr_filter_status" class="w-[11rem]"
                                        :model-value="statusFilter" :options="statusFilterOptions"
                                        placeholder="All statuses" none-label="All statuses" exclude-from-submit
                                        @update:model-value="setStatusFilter" />
                                </div>
                                <div class="grid gap-1">
                                    <Label class="text-xs text-muted-foreground" for="lr-type">Type</Label>
                                    <FormSelect id="lr-type" name="lr_filter_type" class="w-[12rem]"
                                        :model-value="typeFilter" :options="type_options" placeholder="All types"
                                        none-label="All types" exclude-from-submit
                                        @update:model-value="setTypeFilter" />
                                </div>
                            </div>
                        </template>
                    </ListToolbar>
                </div>
                <DataTable>
                    <thead>
                        <tr class="border-b border-border/60 bg-muted/40 backdrop-blur-sm">
                            <DataTableTh>Date</DataTableTh>
                            <DataTableTh>Type</DataTableTh>
                            <DataTableTh>Detail</DataTableTh>
                            <DataTableTh>Status</DataTableTh>
                            <DataTableTh />
                        </tr>
                    </thead>
                    <tbody>
                        <TableRow v-for="row in filteredLeaveRequests" :key="row.id">
                            <DataTableTd label="Date">{{ row.date }}</DataTableTd>
                            <DataTableTd label="Type">{{ row.type_label }}</DataTableTd>
                            <DataTableTd label="Detail">
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
                            </DataTableTd>
                            <DataTableTd label="Status">{{ row.status_label }}</DataTableTd>
                            <DataTableTd label="Actions" class="text-left md:text-right">
                                <TableIconAction
                                    v-if="row.status === 'pending'"
                                    icon="x"
                                    label="Cancel"
                                    @click="cancelRequest(row)"
                                />
                            </DataTableTd>
                        </TableRow>
                        <tr v-if="filteredLeaveRequests.length === 0">
                            <DataTableTd label="" :colspan="5" class="py-8 text-center text-muted-foreground">
                                {{
                                    leave_requests.length === 0
                                        ? 'No requests yet.'
                                        : 'No requests match your filters.'
                                }}
                            </DataTableTd>
                        </tr>
                    </tbody>
                </DataTable>
            </CardContent>
        </Card>
    </div>
</template>
