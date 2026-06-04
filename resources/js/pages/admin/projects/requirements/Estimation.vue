<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Plus, Send } from 'lucide-vue-next';
import { computed, nextTick, ref, toRef, watch } from 'vue';
import RequirementEstimationAnalyticsCards, {
    type EstimationAnalytics,
} from '@/components/requirements/RequirementEstimationAnalyticsCards.vue';
import EstimationLinesTable from '@/components/requirements/EstimationLinesTable.vue';
import ConfirmDestructiveDialog from '@/components/ConfirmDestructiveDialog.vue';
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
import { Label } from '@/components/ui/label';
import { useEstimationDisplayLines } from '@/composables/useEstimationDisplayLines';
import { useEstimationLineCollapse } from '@/composables/useEstimationLineCollapse';
import {
    useEstimationLinesIndex,
    type EstimationLineEditable,
    type EstimationLineReadonly,
} from '@/composables/useEstimationLinesIndex';
import {
    depthFirstEstimationLines,
    insertIndexAfterSubtree,
} from '@/lib/estimationLinesOrder';
import { formatTaskMinutes } from '@/lib/formatTaskMinutes';
import { generateUuid } from '@/lib/generateUuid';
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

const props = defineProps<{
    project: { id: number; name: string; code: string | null };
    requirement: { id: number; title: string };
    understanding_confirmed: boolean;
    estimation: EstimationDetail | null;
    estimation_lines: EstimationLineReadonly[];
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
    return `new-${generateUuid()}`;
}

function linesToEditable(source: EstimationLineReadonly[]): EstimationLineEditable[] {
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

const editableLines = ref<EstimationLineEditable[]>(linesToEditable(props.estimation_lines));

watch(
    () => props.estimation_lines,
    (next) => {
        if (!isEditable.value) {
            return;
        }

        editableLines.value = linesToEditable(next);
    },
);

const {
    displayLines,
    hasChildrenByKey,
    effectiveMinutesByKey,
    hasChildrenById,
    effectiveMinutesById,
    totalEffectiveMinutes,
} = useEstimationLinesIndex(
    editableLines,
    toRef(props, 'estimation_lines'),
    isEditable,
);

const displayedTotalMinutes = computed(() =>
    isEditable.value ? totalEffectiveMinutes.value : props.total_minutes,
);

const { treeLines, parentKeysWithChildren } = useEstimationDisplayLines(
    displayLines,
    isEditable,
);

const {
    visibleLines,
    directChildCountByKey,
    isCollapsed,
    toggleCollapsed,
    expandLine,
    expandAll,
    collapseAllParents,
    anyCollapsed,
} = useEstimationLineCollapse(treeLines, parentKeysWithChildren);

const linesTableRef = ref<{ scrollToEnd: () => Promise<void> } | null>(null);

function addRootRow(): void {
    collapseAllParents();

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

    void nextTick(() => linesTableRef.value?.scrollToEnd());
}

function addSubRow(parent: EstimationLineEditable): void {
    const newLine: EstimationLineEditable = {
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

    expandLine(parent.client_key);

    const insertAt = insertIndexAfterSubtree(editableLines.value, parent.client_key);
    const next = [...editableLines.value];
    next.splice(insertAt, 0, newLine);
    editableLines.value = next;
}

function removeRow(row: EstimationLineEditable): void {
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

const saveProcessing = ref(false);
const saveErrors = ref<Record<string, string>>({});

function saveLines(): void {
    if (estimationRoute.value === null) {
        return;
    }

    saveProcessing.value = true;
    saveErrors.value = {};

    const linesPayload = depthFirstEstimationLines([...editableLines.value]).map(
        (line, index) => ({
            id: line.id,
            client_key: line.id === null ? line.client_key : undefined,
            parent_id: line.parent_id,
            parent_client_key:
                line.parent_id === null ? line.parent_client_key : undefined,
            title: line.title,
            description: line.description || null,
            estimated_minutes: hasChildrenByKey.value.has(line.client_key)
                ? null
                : line.estimated_minutes === ''
                    ? null
                    : Number(line.estimated_minutes),
            sort_order: index,
        }),
    );

    router.put(
        lines.url(estimationRoute.value),
        { lines: linesPayload },
        {
            preserveScroll: true,
            onSuccess: () => {
                router.reload({
                    only: ['estimation_lines', 'analytics', 'total_minutes'],
                });
            },
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

const transferDialogOpen = ref(false);

function confirmTransferEstimation(): void {
    if (estimationRoute.value === null) {
        return;
    }

    router.post(transfer.url(estimationRoute.value));
}

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
            <PageHeader :title="`Estimation: ${requirement.title}`"
                :description="`Project ${project.name} · Plan work before creating tasks`" />
            <div class="flex flex-wrap items-center gap-2">
                <span v-if="estimation" class="rounded-md px-2 py-1 text-xs font-medium" :class="statusBadgeClass">
                    {{ estimation.status_label }} · v{{ estimation.version }}
                </span>
                <Button variant="outline" as-child>
                    <Link :href="requirementsShow.url({
                        project: project.id,
                        requirement: requirement.id,
                    })
                        ">
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
            <div v-if="estimation.review_notes"
                class="rounded-lg border border-amber-500/30 bg-amber-500/10 p-4 text-sm">
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
                            Total: {{ formatTaskMinutes(displayedTotalMinutes) }}
                            <span v-if="displayLines.length > 0" class="text-muted-foreground">
                                · {{ displayLines.length }} lines total
                            </span>
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button v-if="can_submit" type="button" @click="openSubmitDialog">
                            <Send class="size-4" aria-hidden="true" />
                            Submit for approval
                        </Button>
                        <Button v-if="can_approve" type="button" variant="secondary" :disabled="reviewForm.processing"
                            @click="approveEstimation">
                            Approve
                        </Button>
                        <Button v-if="can_request_changes" type="button" variant="outline"
                            :disabled="reviewForm.processing" @click="requestChangesEstimation">
                            Request changes
                        </Button>
                        <Button v-if="can_reject" type="button" variant="outline" class="text-destructive"
                            :disabled="reviewForm.processing" @click="rejectEstimation">
                            Reject
                        </Button>
                        <Button v-if="can_request_revision" type="button" variant="outline"
                            @click="requestRevisionEstimation">
                            Request revision
                        </Button>
                        <Button v-if="can_transfer" type="button" @click="transferDialogOpen = true">
                            Transfer to tasks
                        </Button>
                    </div>
                </div>

                <EstimationLinesTable
                    ref="linesTableRef"
                    :is-editable="isEditable"
                    :visible-lines="visibleLines"
                    :total-line-count="displayLines.length"
                    :has-children-by-key="hasChildrenByKey"
                    :effective-minutes-by-key="effectiveMinutesByKey"
                    :has-children-by-id="hasChildrenById"
                    :effective-minutes-by-id="effectiveMinutesById"
                    :direct-child-count-by-key="directChildCountByKey"
                    :is-collapsed="isCollapsed"
                    :any-collapsed="anyCollapsed"
                    :can-remove-line="editableLines.length > 1"
                    @add-subtask="addSubRow"
                    @remove="removeRow"
                    @toggle-collapse="toggleCollapsed"
                    @expand-all="expandAll"
                    @collapse-all="collapseAllParents"
                />

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

            <div v-if="can_approve || can_reject || can_request_changes" class="rounded-xl border border-border p-4">
                <p class="mb-3 text-sm font-medium">Review notes (optional)</p>
                <textarea v-model="reviewForm.review_notes" rows="2"
                    class="mb-3 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                    placeholder="Notes for the submitter when approving, rejecting, or requesting changes" />
                <InputError :message="reviewForm.errors.review_notes" />
            </div>
        </template>
    </div>

    <ConfirmDestructiveDialog v-model:open="transferDialogOpen" title="Transfer to project tasks?"
        description="This will create project tasks from every estimation line. You can continue working in the task list after transfer."
        confirm-label="Transfer" confirm-variant="default" @confirm="confirmTransferEstimation" />

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
                    <TaskFormSelect id="submit-approver" name="submitted_to_user_id" exclude-from-submit
                        v-model="submitForm.submitted_to_user_id" required placeholder="Select approver" :options="approver_options.map((o) => ({
                            value: String(o.value),
                            label: o.label,
                        }))
                            " />
                    <InputError :message="submitForm.errors.submitted_to_user_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="submit-notes">Notes (optional)</Label>
                    <textarea id="submit-notes" v-model="submitForm.submission_notes" rows="3"
                        class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs" />
                    <InputError :message="submitForm.errors.submission_notes" />
                </div>
                <InputError :message="submitForm.errors.lines" />
            </div>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button type="button" variant="outline">Cancel</Button>
                </DialogClose>
                <Button type="button" :disabled="submitForm.processing" @click="submitEstimation">
                    Submit
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
