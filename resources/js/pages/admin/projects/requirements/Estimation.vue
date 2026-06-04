<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CornerDownRight, Plus, Send } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import RequirementEstimationAnalyticsCards, {
    type EstimationAnalytics,
} from '@/components/requirements/RequirementEstimationAnalyticsCards.vue';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import InputError from '@/components/InputError.vue';
import TaskFormSelect from '@/components/TaskFormSelect.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    depthFirstEstimationLines,
    insertIndexAfterSubtree,
} from '@/lib/estimationLinesOrder';
import {
    effectiveMinutes,
    effectiveMinutesById,
    lineHasChildren,
    lineHasChildrenById,
} from '@/lib/estimationMinutesRollup';
import { formatTaskMinutes } from '@/lib/formatTaskMinutes';
import { index as projectsIndex, show as projectsShow } from '@/routes/admin/projects/index';
import {
    index as requirementsIndex,
    show as requirementsShow,
} from '@/routes/admin/projects/requirements/index';
import {
    approve,
    lines,
    reject,
    requestChanges,
    requestRevision,
    show as estimationShow,
    store,
    submit,
    transfer,
} from '@/routes/admin/projects/requirements/estimation/index';

type UserBrief = {
    id: number;
    name: string;
    email: string;
} | null;

type EstimationLine = {
    id: number;
    parent_estimation_item_id: number | null;
    title: string;
    description: string | null;
    estimated_minutes: number | null;
    sort_order: number;
    tree_depth: number;
};

type EstimationDetail = {
    id: number;
    version: number;
    status: string;
    status_label: string;
    submitted_at: string | null;
    submission_notes: string | null;
    reviewed_at: string | null;
    review_notes: string | null;
    transferred_at: string | null;
    creator: UserBrief;
    submitted_to: UserBrief;
    reviewed_by: UserBrief;
    transferred_by: UserBrief;
};

type EditableLine = {
    id: number | null;
    client_key: string;
    parent_id: number | null;
    parent_client_key: string | null;
    title: string;
    description: string;
    estimated_minutes: string;
    sort_order: number;
    tree_depth: number;
};

const props = defineProps<{
    project: { id: number; name: string; code: string | null };
    requirement: { id: number; title: string };
    understanding_confirmed: boolean;
    estimation: EstimationDetail | null;
    estimation_lines: EstimationLine[];
    analytics: EstimationAnalytics;
    total_minutes: number;
    approver_options: { value: number; label: string }[];
    can_manage_estimation: boolean;
    can_create_estimation: boolean;
    can_sync_lines: boolean;
    can_submit: boolean;
    can_approve: boolean;
    can_reject: boolean;
    can_request_changes: boolean;
    can_request_revision: boolean;
    can_transfer: boolean;
}>();

defineOptions({
    layout: (pageProps: {
        project: { id: number; name: string };
        requirement: { id: number; title: string };
    }) => ({
        breadcrumbs: [
            { title: 'Projects', href: projectsIndex.url() },
            { title: pageProps.project.name, href: projectsShow.url(pageProps.project.id) },
            {
                title: 'Requirements',
                href: requirementsIndex.url(pageProps.project.id),
            },
            {
                title: pageProps.requirement.title,
                href: requirementsShow.url({
                    project: pageProps.project.id,
                    requirement: pageProps.requirement.id,
                }),
            },
            {
                title: 'Estimation',
                href: estimationShow.url({
                    project: pageProps.project.id,
                    requirement: pageProps.requirement.id,
                }),
            },
        ],
    }),
});

const routeBase = computed(() => ({
    project: props.project.id,
    requirement: props.requirement.id,
}));

const estimationRoute = computed(() =>
    props.estimation === null
        ? null
        : {
              ...routeBase.value,
              estimation: props.estimation.id,
          },
);

const isEditable = computed(
    () => props.can_sync_lines && props.estimation !== null,
);

function newClientKey(): string {
    return `new-${crypto.randomUUID()}`;
}

function linesToEditable(source: EstimationLine[]): EditableLine[] {
    const idToKey = new Map<number, string>();

    for (const line of source) {
        idToKey.set(line.id, `existing-${line.id}`);
    }

    return source.map((line) => ({
        id: line.id,
        client_key: idToKey.get(line.id) ?? newClientKey(),
        parent_id: line.parent_estimation_item_id,
        parent_client_key:
            line.parent_estimation_item_id !== null
                ? (idToKey.get(line.parent_estimation_item_id) ?? null)
                : null,
        title: line.title,
        description: line.description ?? '',
        estimated_minutes:
            line.estimated_minutes !== null ? String(line.estimated_minutes) : '',
        sort_order: line.sort_order,
        tree_depth: line.tree_depth,
    }));
}

const editableLines = ref<EditableLine[]>(linesToEditable(props.estimation_lines));

watch(
    () => props.estimation_lines,
    (next) => {
        if (!isEditable.value) {
            return;
        }

        editableLines.value = linesToEditable(next);
    },
    { deep: true },
);

function addRootRow(): void {
    editableLines.value.push({
        id: null,
        client_key: newClientKey(),
        parent_id: null,
        parent_client_key: null,
        title: '',
        description: '',
        estimated_minutes: '',
        sort_order: editableLines.value.length,
        tree_depth: 0,
    });
}

function addSubRow(parent: EditableLine): void {
    const newLine: EditableLine = {
        id: null,
        client_key: newClientKey(),
        parent_id: parent.id,
        parent_client_key: parent.client_key,
        title: '',
        description: '',
        estimated_minutes: '',
        sort_order: editableLines.value.length,
        tree_depth: parent.tree_depth + 1,
    };

    parent.estimated_minutes = '';

    const insertAt = insertIndexAfterSubtree(editableLines.value, parent.client_key);
    const next = [...editableLines.value];
    next.splice(insertAt, 0, newLine);
    editableLines.value = next;
}

function removeRow(row: EditableLine): void {
    const key = row.client_key;
    editableLines.value = editableLines.value
        .filter((line) => line.client_key !== key)
        .map((line) => {
            if (line.parent_client_key === key) {
                return {
                    ...line,
                    parent_id: null,
                    parent_client_key: null,
                    tree_depth: 0,
                };
            }

            return line;
        });
}

const parentOptionsForRow = (row: EditableLine) =>
    depthFirstEstimationLines(editableLines.value)
        .filter((candidate) => candidate.client_key !== row.client_key)
        .map((candidate) => ({
            value: candidate.client_key,
            label:
                candidate.tree_depth > 0
                    ? `${'— '.repeat(candidate.tree_depth)}${candidate.title || 'Untitled'}`
                    : candidate.title || 'Untitled',
        }));

function onParentChange(row: EditableLine, parentKey: string): void {
    if (parentKey === '') {
        row.parent_id = null;
        row.parent_client_key = null;
        row.tree_depth = 0;

        return;
    }

    const parent = editableLines.value.find((line) => line.client_key === parentKey);
    row.parent_client_key = parentKey;
    row.parent_id = parent?.id ?? null;
    row.tree_depth = parent ? parent.tree_depth + 1 : 0;

    if (parent !== undefined) {
        parent.estimated_minutes = '';
    }
}

const saveProcessing = ref(false);
const saveErrors = ref<Record<string, string>>({});

function saveLines(): void {
    if (estimationRoute.value === null) {
        return;
    }

    saveProcessing.value = true;
    saveErrors.value = {};

    router.put(
        lines.url(estimationRoute.value),
        {
            lines: editableLines.value.map((line, index) => ({
                id: line.id,
                client_key: line.id === null ? line.client_key : undefined,
                parent_id: line.parent_id,
                parent_client_key:
                    line.parent_id === null ? line.parent_client_key : undefined,
                title: line.title,
                description: line.description || null,
                estimated_minutes: lineHasChildren(line, editableLines.value)
                    ? null
                    : line.estimated_minutes === ''
                      ? null
                      : Number(line.estimated_minutes),
                sort_order: index,
            })),
        },
        {
            preserveScroll: true,
            onFinish: () => {
                saveProcessing.value = false;
            },
            onError: (errors) => {
                saveErrors.value = errors as Record<string, string>;
            },
        },
    );
}

function createEstimation(): void {
    router.post(store.url(routeBase.value));
}

const submitDialogOpen = ref(false);

const submitForm = useForm({
    submitted_to_user_id: '',
    submission_notes: '',
});

function openSubmitDialog(): void {
    submitForm.clearErrors();
    submitDialogOpen.value = true;
}

function submitEstimation(): void {
    if (estimationRoute.value === null) {
        return;
    }

    submitForm.patch(submit.url(estimationRoute.value), {
        preserveScroll: true,
        onSuccess: () => {
            submitDialogOpen.value = false;
        },
    });
}

const reviewForm = useForm({
    review_notes: '',
});

function approveEstimation(): void {
    if (estimationRoute.value === null) {
        return;
    }

    reviewForm.patch(approve.url(estimationRoute.value), { preserveScroll: true });
}

function rejectEstimation(): void {
    if (estimationRoute.value === null) {
        return;
    }

    reviewForm.patch(reject.url(estimationRoute.value), { preserveScroll: true });
}

function requestChangesEstimation(): void {
    if (estimationRoute.value === null) {
        return;
    }

    reviewForm.patch(requestChanges.url(estimationRoute.value), { preserveScroll: true });
}

function requestRevisionEstimation(): void {
    if (estimationRoute.value === null) {
        return;
    }

    router.post(requestRevision.url(estimationRoute.value));
}

function transferEstimation(): void {
    if (estimationRoute.value === null) {
        return;
    }

    if (!window.confirm('Transfer all estimation lines to project tasks?')) {
        return;
    }

    router.post(transfer.url(estimationRoute.value));
}

const displayLines = computed(() =>
    isEditable.value
        ? depthFirstEstimationLines(editableLines.value)
        : props.estimation_lines,
);

const statusBadgeClass = computed(() => {
    const status = props.estimation?.status ?? '';

    if (status === 'approved' || status === 'transferred') {
        return 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300';
    }

    if (status === 'pending_approval') {
        return 'bg-amber-500/15 text-amber-800 dark:text-amber-200';
    }

    if (status === 'changes_requested' || status === 'rejected') {
        return 'bg-destructive/15 text-destructive';
    }

    return 'bg-muted text-muted-foreground';
});
</script>

<template>
    <Head :title="`Estimation · ${requirement.title}`" />

    <div class="flex flex-col gap-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <PageHeader
                :title="`Estimation: ${requirement.title}`"
                :description="`Project ${project.name} · Plan work before creating tasks`"
            />
            <div class="flex flex-wrap items-center gap-2">
                <span
                    v-if="estimation"
                    class="rounded-md px-2 py-1 text-xs font-medium"
                    :class="statusBadgeClass"
                >
                    {{ estimation.status_label }} · v{{ estimation.version }}
                </span>
                <Button variant="outline" as-child>
                    <Link
                        :href="
                            requirementsShow.url({
                                project: project.id,
                                requirement: requirement.id,
                            })
                        "
                    >
                        Back to requirement
                    </Link>
                </Button>
            </div>
        </div>

        <RequirementEstimationAnalyticsCards :analytics="analytics" />

        <p v-if="estimation === null && !can_create_estimation" class="text-sm text-muted-foreground">
            No estimation has been started for this requirement yet.
        </p>

        <div v-if="can_create_estimation">
            <Button type="button" @click="createEstimation">Start estimation</Button>
        </div>

        <template v-if="estimation !== null">
            <div
                v-if="estimation.review_notes"
                class="rounded-lg border border-amber-500/30 bg-amber-500/10 p-4 text-sm"
            >
                <p class="font-medium">Review notes</p>
                <p class="mt-1 whitespace-pre-wrap text-muted-foreground">
                    {{ estimation.review_notes }}
                </p>
            </div>

            <GlassCard>
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold">Estimation lines</h2>
                        <p class="text-sm text-muted-foreground">
                            Total: {{ formatTaskMinutes(total_minutes) }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-if="can_submit"
                            type="button"
                            @click="openSubmitDialog"
                        >
                            <Send class="size-4" aria-hidden="true" />
                            Submit for approval
                        </Button>
                        <Button
                            v-if="can_approve"
                            type="button"
                            variant="secondary"
                            :disabled="reviewForm.processing"
                            @click="approveEstimation"
                        >
                            Approve
                        </Button>
                        <Button
                            v-if="can_request_changes"
                            type="button"
                            variant="outline"
                            :disabled="reviewForm.processing"
                            @click="requestChangesEstimation"
                        >
                            Request changes
                        </Button>
                        <Button
                            v-if="can_reject"
                            type="button"
                            variant="outline"
                            class="text-destructive"
                            :disabled="reviewForm.processing"
                            @click="rejectEstimation"
                        >
                            Reject
                        </Button>
                        <Button
                            v-if="can_request_revision"
                            type="button"
                            variant="outline"
                            @click="requestRevisionEstimation"
                        >
                            Request revision
                        </Button>
                        <Button v-if="can_transfer" type="button" @click="transferEstimation">
                            Transfer to tasks
                        </Button>
                    </div>
                </div>

                <div class="md:overflow-x-auto">
                    <table
                        data-responsive-table
                        class="data-table-responsive w-full table-fixed text-left text-sm md:min-w-[720px]"
                        style="--data-table-min-width: 720px"
                    >
                        <thead class="border-b bg-muted/40">
                            <tr>
                                <th class="w-[22%] px-3 py-3 font-medium">Title</th>
                                <th class="w-[28%] px-3 py-3 font-medium">Description</th>
                                <th class="w-[12%] px-3 py-3 font-medium">Minutes</th>
                                <th v-if="isEditable" class="w-[18%] px-3 py-3 font-medium">
                                    Parent
                                </th>
                                <th class="px-3 py-3 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in displayLines"
                                :key="
                                    isEditable
                                        ? (row as EditableLine).client_key
                                        : (row as EstimationLine).id
                                "
                                class="border-b border-border/60 last:border-0"
                            >
                                <td
                                    data-label="Title"
                                    class="px-3 py-2 align-top"
                                    :style="{
                                        paddingLeft: `calc(0.75rem + ${row.tree_depth} * 1rem)`,
                                    }"
                                >
                                    <div class="flex min-w-0 items-start gap-1">
                                        <CornerDownRight
                                            v-if="row.tree_depth > 0"
                                            class="mt-2 size-4 shrink-0 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                        <Input
                                            v-if="isEditable"
                                            v-model="(row as EditableLine).title"
                                            type="text"
                                            placeholder="Task title"
                                            class="min-w-0"
                                        />
                                        <span v-else class="font-medium">{{
                                            (row as EstimationLine).title
                                        }}</span>
                                    </div>
                                </td>
                                <td data-label="Description" class="px-3 py-2 align-top">
                                    <textarea
                                        v-if="isEditable"
                                        v-model="(row as EditableLine).description"
                                        rows="2"
                                        class="w-full rounded-md border border-input bg-transparent px-2 py-1 text-sm shadow-xs"
                                        placeholder="Optional"
                                    />
                                    <span v-else class="text-muted-foreground">{{
                                        (row as EstimationLine).description || '—'
                                    }}</span>
                                </td>
                                <td data-label="Minutes" class="px-3 py-2 align-top">
                                    <span
                                        v-if="
                                            isEditable
                                            && lineHasChildren(
                                                row as EditableLine,
                                                editableLines,
                                            )
                                        "
                                        class="text-muted-foreground tabular-nums"
                                        :title="'Sum of subtasks'"
                                    >
                                        {{
                                            formatTaskMinutes(
                                                effectiveMinutes(
                                                    row as EditableLine,
                                                    editableLines,
                                                ),
                                            )
                                        }}
                                    </span>
                                    <Input
                                        v-else-if="isEditable"
                                        v-model="(row as EditableLine).estimated_minutes"
                                        type="number"
                                        min="1"
                                        step="1"
                                        placeholder="min"
                                    />
                                    <span
                                        v-else
                                        class="tabular-nums"
                                    >{{
                                        formatTaskMinutes(
                                            lineHasChildrenById(
                                                row as EstimationLine,
                                                props.estimation_lines,
                                            )
                                                ? effectiveMinutesById(
                                                      row as EstimationLine,
                                                      props.estimation_lines,
                                                  )
                                                : (row as EstimationLine).estimated_minutes,
                                        )
                                    }}</span>
                                </td>
                                <td
                                    v-if="isEditable"
                                    data-label="Parent"
                                    class="px-3 py-2 align-top"
                                >
                                    <TaskFormSelect
                                        :id="`est-line-parent-${(row as EditableLine).client_key}`"
                                        :name="`parent_${(row as EditableLine).client_key}`"
                                        exclude-from-submit
                                        :model-value="(row as EditableLine).parent_client_key ?? ''"
                                        placeholder="None (root)"
                                        none-label="None (root)"
                                        :options="parentOptionsForRow(row as EditableLine)"
                                        @update:model-value="
                                            onParentChange(row as EditableLine, $event)
                                        "
                                    />
                                </td>
                                <td data-label="Actions" class="px-3 py-2 align-top text-right">
                                    <div v-if="isEditable" class="flex flex-wrap justify-end gap-1">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            @click="addSubRow(row as EditableLine)"
                                        >
                                            Subtask
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            class="text-destructive"
                                            :disabled="editableLines.length <= 1"
                                            @click="removeRow(row as EditableLine)"
                                        >
                                            Remove
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <InputError class="mt-2" :message="saveErrors.lines" />

                <div v-if="isEditable" class="mt-4 flex flex-wrap gap-2">
                    <Button type="button" variant="outline" @click="addRootRow">
                        <Plus class="size-4" aria-hidden="true" />
                        Add row
                    </Button>
                    <Button type="button" :disabled="saveProcessing" @click="saveLines">
                        Save lines
                    </Button>
                </div>
            </GlassCard>

            <div
                v-if="can_approve || can_reject || can_request_changes"
                class="rounded-xl border border-border p-4"
            >
                <p class="mb-3 text-sm font-medium">Review notes (optional)</p>
                <textarea
                    v-model="reviewForm.review_notes"
                    rows="2"
                    class="mb-3 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                    placeholder="Notes for the submitter when approving, rejecting, or requesting changes"
                />
                <InputError :message="reviewForm.errors.review_notes" />
            </div>
        </template>
    </div>

    <Dialog v-model:open="submitDialogOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Submit estimation for approval</DialogTitle>
                <DialogDescription>
                    Choose who should review this estimation. All lines must have estimated
                    minutes before submitting.
                </DialogDescription>
            </DialogHeader>
            <div class="grid gap-4 py-2">
                <div class="grid gap-2">
                    <Label for="submit-approver">Approver</Label>
                    <TaskFormSelect
                        id="submit-approver"
                        name="submitted_to_user_id"
                        exclude-from-submit
                        v-model="submitForm.submitted_to_user_id"
                        required
                        placeholder="Select approver"
                        :options="
                            approver_options.map((o) => ({
                                value: String(o.value),
                                label: o.label,
                            }))
                        "
                    />
                    <InputError :message="submitForm.errors.submitted_to_user_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="submit-notes">Notes (optional)</Label>
                    <textarea
                        id="submit-notes"
                        v-model="submitForm.submission_notes"
                        rows="3"
                        class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                    />
                    <InputError :message="submitForm.errors.submission_notes" />
                </div>
                <InputError :message="submitForm.errors.lines" />
            </div>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button type="button" variant="outline">Cancel</Button>
                </DialogClose>
                <Button
                    type="button"
                    :disabled="submitForm.processing"
                    @click="submitEstimation"
                >
                    Submit
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
