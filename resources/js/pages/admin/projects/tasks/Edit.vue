<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import ProjectTaskController from '@/actions/App/Http/Controllers/Admin/ProjectTaskController';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import FormSelect from '@/components/FormSelect.vue';
import InputError from '@/components/InputError.vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
import RichTextViewer from '@/components/RichTextViewer.vue';
import TypeaheadInput from '@/components/TypeaheadInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { buildPhaseSelectOptions, requiresPhaseSelection } from '@/lib/requirementPhaseOptions';
import { emptyTipTapDocumentJson } from '@/lib/tiptapDocument';
import { index as projectsIndex, show as projectsShow } from '@/routes/admin/projects/index';
import {
    edit as projectTasksEdit,
    index as projectTasksIndex,
    show as projectTasksShow,
} from '@/routes/admin/projects/tasks/index';

type ProjectSummary = {
    id: number;
    name: string;
    code: string | null;
    estimation_required: boolean;
};

type Option = {
    value: string;
    label: string;
};

type AssignableUser = {
    value: number;
    label: string;
};

type RequirementOption = {
    value: number;
    label: string;
    max_generated_phase: number;
};

type ParentTaskOption = {
    id: number;
    title: string;
    tree_depth: number;
};

type TaskForm = {
    id: number;
    title: string;
    description: string | null;
    status: string;
    assignee_user_id: number | null;
    project_requirement_id: number | null;
    parent_project_task_id: number | null;
    estimated_minutes: number | null;
    phase: number | null;
    display_after_at: string | null;
    notify_at: string | null;
};

const props = defineProps<{
    project: ProjectSummary;
    task: TaskForm;
    status_options: Option[];
    assignable_users: AssignableUser[];
    requirements: RequirementOption[];
    parent_tasks: ParentTaskOption[];
    is_assignee_only_limited: boolean;
    cancel_href: string;
}>();

function toDatetimeLocalValue(value: string | null): string {
    if (value === null) {
        return '';
    }

    const asDate = new Date(value);

    if (Number.isNaN(asDate.getTime())) {
        return '';
    }

    const local = new Date(asDate.getTime() - asDate.getTimezoneOffset() * 60000);

    return local.toISOString().slice(0, 16);
}

const descriptionJson = ref(props.task.description ?? emptyTipTapDocumentJson());
const editTitle = ref(props.task.title);
const editStatus = ref(props.task.status);
const editAssignee = ref(
    props.task.assignee_user_id !== null ? String(props.task.assignee_user_id) : '',
);
const editRequirement = ref(
    props.task.project_requirement_id !== null ? String(props.task.project_requirement_id) : '',
);
const editPhase = ref(props.task.phase !== null ? String(props.task.phase) : '1');
const editParent = ref(
    props.task.parent_project_task_id !== null ? String(props.task.parent_project_task_id) : '',
);
const editDisplayAfterAt = ref(toDatetimeLocalValue(props.task.display_after_at));
const editNotifyAt = ref(toDatetimeLocalValue(props.task.notify_at));

const statusSelectOptions = computed(() => {
    const base = props.status_options.map((o) => ({ value: o.value, label: o.label }));

    if (props.is_assignee_only_limited) {
        return base.filter((o) => o.value !== 'done');
    }

    return base;
});

const assigneeSelectOptions = computed(() =>
    props.assignable_users.map((u) => ({ value: String(u.value), label: u.label })),
);

const requirementSelectOptions = computed(() =>
    props.requirements.map((r) => ({ value: String(r.value), label: r.label })),
);

function requirementMaxPhase(requirementId: string): number {
    if (requirementId === '') {
        return 1;
    }

    const requirement = props.requirements.find((r) => String(r.value) === requirementId);

    return requirement?.max_generated_phase ?? 1;
}

const editPhaseSelectOptions = computed(() =>
    buildPhaseSelectOptions(requirementMaxPhase(editRequirement.value)),
);

const showEditPhaseField = computed(
    () =>
        editRequirement.value !== '' &&
        requiresPhaseSelection(requirementMaxPhase(editRequirement.value)),
);

const parentSelectOptions = computed(() =>
    props.parent_tasks.map((task) => ({
        value: String(task.id),
        label:
            task.tree_depth > 0
                ? `${'— '.repeat(task.tree_depth)}${task.title}`
                : task.title,
    })),
);

watch(editRequirement, () => {
    editPhase.value = '1';
});

defineOptions({
    layout: (pageProps: { project: ProjectSummary; task: TaskForm }) => ({
        breadcrumbs: [
            { title: 'Projects', href: projectsIndex.url() },
            {
                title: pageProps.project.name,
                href: projectsShow.url(pageProps.project.id),
            },
            {
                title: 'Tasks',
                href: projectTasksIndex.url(pageProps.project.id),
            },
            {
                title: pageProps.task.title,
                href: projectTasksShow.url({
                    project: pageProps.project.id,
                    task: pageProps.task.id,
                }),
            },
            {
                title: 'Edit',
                href: projectTasksEdit.url({
                    project: pageProps.project.id,
                    task: pageProps.task.id,
                }),
            },
        ],
    }),
});
</script>

<template>

    <Head :title="`Edit task · ${task.title}`" />

    <div class="flex flex-col gap-8">
        <PageHeader title="Edit task" :description="project.estimation_required
            ? `Update ${task.title}. Time estimate (minutes) is required.`
            : `Update ${task.title}.`
            " />

        <GlassCard class="p-6">
            <Form v-bind="ProjectTaskController.update.form({
                project: project.id,
                task: task.id,
            })" class="grid grid-cols-1 md:grid-cols-2 gap-8" v-slot="{ errors, processing, recentlySuccessful }">
                <input type="hidden" name="return" :value="cancel_href" />
                <GlassCard v-if="!is_assignee_only_limited" class="p-6">
                    <div class="mb-6 space-y-1">
                        <h2 class="text-lg font-semibold">Task</h2>
                        <p class="text-sm text-muted-foreground">
                            Title and optional rich-text description
                        </p>
                    </div>
                    <div class="grid gap-6">
                        <div class="grid gap-2">
                            <Label for="edit-title">Title</Label>
                            <TypeaheadInput id="edit-title" v-model="editTitle" name="title" type="task_title"
                                required />
                            <InputError :message="errors.title" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit-description">Description</Label>
                            <RichTextEditor id="edit-description" v-model="descriptionJson" input-name="description" />
                            <InputError :message="errors.description" />
                        </div>
                    </div>
                </GlassCard>

                <GlassCard v-else class="p-6">
                    <div class="mb-6 space-y-1">
                        <h2 class="text-lg font-semibold">Task</h2>
                        <p class="text-sm text-muted-foreground">
                            You can update status and estimate only.
                        </p>
                    </div>
                    <div class="grid gap-6">
                        <div class="grid gap-1">
                            <span class="text-xs font-medium text-muted-foreground">Title</span>
                            <p>{{ task.title }}</p>
                        </div>
                        <div v-if="task.description" class="grid gap-1">
                            <span class="text-xs font-medium text-muted-foreground">Description</span>
                            <RichTextViewer :json="task.description" class="text-muted-foreground" />
                        </div>
                    </div>
                </GlassCard>

                <GlassCard class="p-6">
                    <div class="mb-6 space-y-1">
                        <h2 class="text-lg font-semibold">
                            {{ is_assignee_only_limited ? 'Progress' : 'Assignment & scheduling' }}
                        </h2>
                    </div>
                    <div class="grid gap-6">
                        <div class="grid gap-2">
                            <Label for="edit-status">Status</Label>
                            <FormSelect id="edit-status" name="status" v-model="editStatus" required
                                placeholder="Status" :options="statusSelectOptions" />
                            <InputError :message="errors.status" />
                        </div>
                        <template v-if="!is_assignee_only_limited">
                            <div class="grid gap-2">
                                <Label for="edit-assignee">Assignee</Label>
                                <FormSelect id="edit-assignee" name="assignee_user_id" v-model="editAssignee"
                                    none-label="Unassigned" placeholder="Unassigned" :options="assigneeSelectOptions" />
                                <InputError :message="errors.assignee_user_id" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="edit-requirement">Requirement</Label>
                                <FormSelect id="edit-requirement" name="project_requirement_id"
                                    v-model="editRequirement" none-label="None" placeholder="None"
                                    :options="requirementSelectOptions" />
                                <InputError :message="errors.project_requirement_id" />
                            </div>
                            <div v-if="showEditPhaseField" class="grid gap-2">
                                <Label for="edit-phase">Phase</Label>
                                <FormSelect id="edit-phase" name="phase" v-model="editPhase" required
                                    placeholder="Phase" :options="editPhaseSelectOptions" />
                                <InputError :message="errors.phase" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="edit-parent">Parent task (subtask)</Label>
                                <FormSelect id="edit-parent" name="parent_project_task_id" v-model="editParent"
                                    none-label="None" placeholder="None" :options="parentSelectOptions" />
                                <InputError :message="errors.parent_project_task_id" />
                            </div>
                        </template>
                        <div class="grid gap-2">
                            <Label for="edit-estimate">Estimate (minutes)</Label>
                            <Input id="edit-estimate" name="estimated_minutes" type="number" min="1" step="1"
                                :required="project.estimation_required" :default-value="task.estimated_minutes !== null
                                    ? String(task.estimated_minutes)
                                    : ''
                                    " />
                            <InputError :message="errors.estimated_minutes" />
                        </div>
                        <template v-if="!is_assignee_only_limited">
                            <div class="grid gap-2">
                                <Label for="edit-display-after-at">Display after</Label>
                                <Input id="edit-display-after-at" name="display_after_at" type="datetime-local"
                                    v-model="editDisplayAfterAt" />
                                <InputError :message="errors.display_after_at" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="edit-notify-at">Notify task at</Label>
                                <Input id="edit-notify-at" name="notify_at" type="datetime-local"
                                    v-model="editNotifyAt" />
                                <InputError :message="errors.notify_at" />
                            </div>
                        </template>
                    </div>
                </GlassCard>

                <div class="flex items-center gap-4">
                    <Button type="submit" :disabled="processing">Save changes</Button>
                    <Button variant="outline" as-child>
                        <Link :href="cancel_href">Cancel</Link>
                    </Button>
                    <span v-show="recentlySuccessful" class="text-sm text-muted-foreground">
                        Saved.
                    </span>
                </div>
            </Form>
        </GlassCard>
    </div>
</template>
