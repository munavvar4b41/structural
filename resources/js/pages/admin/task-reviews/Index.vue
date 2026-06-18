<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ClipboardCheck } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import TaskCompletionReviewController from '@/actions/App/Http/Controllers/Admin/TaskCompletionReviewController';
import DataTable from '@/components/dashboard/DataTable.vue';
import DataTableTd from '@/components/dashboard/DataTableTd.vue';
import DataTableTh from '@/components/dashboard/DataTableTh.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import InputError from '@/components/InputError.vue';
import ListToolbar from '@/components/ListToolbar.vue';
import FormSelect from '@/components/FormSelect.vue';
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
import { Label } from '@/components/ui/label';
import { index as projectsIndex } from '@/routes/admin/projects/index';
import { index as taskReviewsIndex } from '@/routes/admin/task-reviews/index';
import TableRow from '@/components/dashboard/TableRow.vue';

type UserBrief = {
    id: number;
    name: string;
    email: string;
};

type QueueTask = {
    id: number;
    title: string;
    project_id: number;
    project: { id: number; name: string; code: string | null };
    assignee: UserBrief | null;
    creator: UserBrief | null;
    completion_submitted_at: string | null;
    completion_submitted_by: UserBrief | null;
    review_stage: string;
    task_show_url: string;
};

const props = defineProps<{
    tasks: QueueTask[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Projects', href: projectsIndex.url() },
            { title: 'Task reviews', href: taskReviewsIndex.url() },
        ],
    },
});

const searchText = ref('');
const stageFilter = ref('');

const stageFilterOptions = [{ value: 'awaiting_confirmation', label: 'Awaiting confirmation' }];

function setStageFilter(v: string): void {
    stageFilter.value = v;
}

const filteredTasks = computed(() => {
    return props.tasks.filter((row) => {
        if (stageFilter.value !== '' && row.review_stage !== stageFilter.value) {
            return false;
        }

        const needle = searchText.value.trim().toLowerCase();

        if (needle === '') {
            return true;
        }

        const hay = [
            row.title,
            row.project.name,
            row.project.code,
            row.assignee?.name,
            row.completion_submitted_by?.name,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return hay.includes(needle);
    });
});

const ratingOptions = [1, 2, 3, 4, 5].map((n) => ({
    value: String(n),
    label: String(n),
}));

const confirmOpen = ref(false);
const taskPendingConfirm = ref<QueueTask | null>(null);

const form = useForm({
    review_notes: '',
    task_rating: '5',
    assignee_rating: '5',
    creator_rating: '5',
});

watch([confirmOpen, taskPendingConfirm], () => {
    if (confirmOpen.value && taskPendingConfirm.value) {
        form.reset();
        form.clearErrors();
        form.task_rating = '5';
        form.assignee_rating = '5';
        form.creator_rating = '5';

        if (taskPendingConfirm.value.assignee === null) {
            form.assignee_rating = '';
        }
    }
});

const showAssigneeRating = computed(
    () => taskPendingConfirm.value?.assignee !== null,
);

function openConfirm(row: QueueTask): void {
    taskPendingConfirm.value = row;
    confirmOpen.value = true;
}

function closeConfirm(): void {
    confirmOpen.value = false;
    taskPendingConfirm.value = null;
}

function submitConfirm(): void {
    const row = taskPendingConfirm.value;

    if (row === null) {
        return;
    }

    form.transform((data) => ({
        review_notes: data.review_notes === '' ? null : data.review_notes,
        task_rating: Number.parseInt(data.task_rating, 10),
        assignee_rating:
            row.assignee === null || data.assignee_rating === ''
                ? null
                : Number.parseInt(data.assignee_rating, 10),
        creator_rating: Number.parseInt(data.creator_rating, 10),
    })).post(TaskCompletionReviewController.confirm.url({ project: row.project_id, task: row.id }), {
        preserveScroll: true,
        onSuccess: () => closeConfirm(),
    });
}

function submittedLabel(at: string | null): string {
    if (!at) {
        return '—';
    }

    return new Date(at).toLocaleString();
}
</script>

<template>

    <Head title="Task reviews" />

    <div class="flex flex-col gap-6">
        <PageHeader title="Task reviews"
            description="Tasks submitted by assignees for completion. Confirm to mark done and record ratings." />

        <ListToolbar v-model="searchText" placeholder="Search task, project, assignee…">
            <template #filters>
                <div class="grid gap-1">
                    <Label class="text-xs text-muted-foreground" for="tr-stage">Stage</Label>
                    <FormSelect id="tr-stage" name="task_review_stage" class="w-[14rem]" :model-value="stageFilter"
                        :options="stageFilterOptions" placeholder="All stages" none-label="All stages"
                        exclude-from-submit @update:model-value="setStageFilter" />
                </div>
            </template>
        </ListToolbar>

        <Card>
            <CardHeader>
                <div class="flex items-center gap-2">
                    <ClipboardCheck class="size-5 text-muted-foreground" />
                    <div>
                        <CardTitle>Awaiting confirmation</CardTitle>
                        <CardDescription>
                            {{ filteredTasks.length }} task(s) in review. Open a task or confirm here with notes and
                            ratings.
                        </CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent>
                <DataTable v-if="filteredTasks.length > 0">
                    <thead>
                        <tr class="border-b border-border/60 bg-muted/40 backdrop-blur-sm">
                            <DataTableTh>Task</DataTableTh>
                            <DataTableTh>Project</DataTableTh>
                            <DataTableTh>Assignee</DataTableTh>
                            <DataTableTh>Submitted</DataTableTh>
                            <DataTableTh class="text-right">Actions</DataTableTh>
                        </tr>
                    </thead>
                    <tbody>
                        <TableRow v-for="row in filteredTasks" :key="row.id">
                            <DataTableTd label="Task" class="align-top font-medium">{{ row.title }}</DataTableTd>
                            <DataTableTd label="Project" class="align-top text-muted-foreground">
                                {{ row.project.name }}
                                <span v-if="row.project.code">({{ row.project.code }})</span>
                            </DataTableTd>
                            <DataTableTd label="Assignee" class="align-top text-muted-foreground">
                                {{ row.assignee?.name ?? '—' }}
                            </DataTableTd>
                            <DataTableTd label="Submitted" class="align-top text-muted-foreground">
                                <span class="block">{{ submittedLabel(row.completion_submitted_at) }}</span>
                                <span v-if="row.completion_submitted_by" class="text-xs">
                                    by {{ row.completion_submitted_by.name }}
                                </span>
                            </DataTableTd>
                            <DataTableTd label="Actions" class="text-left md:text-right">
                                <div class="flex flex-wrap justify-start md:justify-end gap-2">
                                    <Button variant="outline" size="sm" as-child>
                                        <Link :href="row.task_show_url">Open task</Link>
                                    </Button>
                                    <Button size="sm" type="button" @click="openConfirm(row)">
                                        Confirm & rate
                                    </Button>
                                </div>
                            </DataTableTd>
                        </TableRow>
                    </tbody>
                </DataTable>
                <div v-else class="text-sm text-muted-foreground">
                    {{ tasks.length === 0 ? 'No tasks awaiting review.' : 'No tasks match your filters.' }}
                </div>
            </CardContent>
        </Card>
    </div>

    <Dialog :open="confirmOpen" @update:open="(v) => !v && closeConfirm()">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Confirm completion</DialogTitle>
                <DialogDescription>
                    Add optional notes and ratings (1–5). The task will be marked done.
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-4" @submit.prevent="submitConfirm">
                <div v-if="taskPendingConfirm" class="rounded-md border border-border/80 bg-muted/30 p-3 text-xs">
                    <p class="font-medium text-foreground">{{ taskPendingConfirm.title }}</p>
                    <p class="mt-1 text-muted-foreground">
                        Owner: {{ taskPendingConfirm.creator?.name ?? '—' }} · Assignee:
                        {{ taskPendingConfirm.assignee?.name ?? '—' }}
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="review-notes">Review notes</Label>
                    <textarea id="review-notes" v-model="form.review_notes" rows="3" maxlength="10000"
                        class="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-transparent"
                        placeholder="Optional feedback for the team" />
                    <InputError :message="form.errors.review_notes" />
                </div>

                <div class="grid gap-2">
                    <Label for="task-rating">Task quality (1–5)</Label>
                    <FormSelect id="task-rating" v-model="form.task_rating" name="task_rating" required
                        :options="ratingOptions" />
                    <InputError :message="form.errors.task_rating" />
                </div>

                <div v-if="showAssigneeRating" class="grid gap-2">
                    <Label for="assignee-rating">Assignee performance (1–5)</Label>
                    <FormSelect id="assignee-rating" v-model="form.assignee_rating" name="assignee_rating" required
                        :options="ratingOptions" />
                    <InputError :message="form.errors.assignee_rating" />
                </div>

                <div class="grid gap-2">
                    <Label for="creator-rating">Task owner / creator (1–5)</Label>
                    <FormSelect id="creator-rating" v-model="form.creator_rating" name="creator_rating" required
                        :options="ratingOptions" />
                    <InputError :message="form.errors.creator_rating" />
                </div>

                <DialogFooter class="gap-3">
                    <Button type="button" variant="outline" @click="closeConfirm">Cancel</Button>
                    <Button type="submit" :disabled="form.processing">Confirm & mark done</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
