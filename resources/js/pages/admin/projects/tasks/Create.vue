<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import ProjectTaskController from '@/actions/App/Http/Controllers/Admin/ProjectTaskController';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import FormSelect from '@/components/FormSelect.vue';
import InputError from '@/components/InputError.vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
import TypeaheadInput from '@/components/TypeaheadInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { buildPhaseSelectOptions, requiresPhaseSelection } from '@/lib/requirementPhaseOptions';
import { emptyTipTapDocumentJson } from '@/lib/tiptapDocument';
import { index as projectsIndex, show as projectsShow } from '@/routes/admin/projects/index';
import {
    create as projectTasksCreate,
    index as projectTasksIndex,
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

type Defaults = {
    project_requirement_id: string;
    parent_project_task_id: string;
};

const props = defineProps<{
    project: ProjectSummary;
    status_options: Option[];
    assignable_users: AssignableUser[];
    requirements: RequirementOption[];
    parent_tasks: ParentTaskOption[];
    defaults: Defaults;
    cancel_href: string;
}>();

const descriptionJson = ref(emptyTipTapDocumentJson());
const createTitle = ref('');
const createStatus = ref(props.status_options[0]?.value ?? 'to_do');
const createAssignee = ref('');
const createRequirement = ref(props.defaults.project_requirement_id);
const createPhase = ref('1');
const createParent = ref(props.defaults.parent_project_task_id);
const createDisplayAfterAt = ref('');
const createNotifyAt = ref('');

const statusSelectOptions = computed(() =>
    props.status_options.map((o) => ({ value: o.value, label: o.label })),
);

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

const createPhaseSelectOptions = computed(() =>
    buildPhaseSelectOptions(requirementMaxPhase(createRequirement.value)),
);

const showCreatePhaseField = computed(
    () =>
        createRequirement.value !== '' &&
        requiresPhaseSelection(requirementMaxPhase(createRequirement.value)),
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

watch(createRequirement, () => {
    createPhase.value = '1';
});

defineOptions({
    layout: (pageProps: { project: ProjectSummary }) => ({
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
                title: 'Add task',
                href: projectTasksCreate.url(pageProps.project.id),
            },
        ],
    }),
});
</script>

<template>

    <Head :title="`Add task · ${project.name}`" />

    <div class="flex flex-col gap-8">
        <PageHeader title="Add task" :description="project.estimation_required
                ? `For project ${project.name}. Time estimate (minutes) is required.`
                : `For project ${project.name}.`
            " />

        <GlassCard class="p-6">
            <Form v-bind="ProjectTaskController.store.form({ project: project.id })" class="grid grid-cols-1 md:grid-cols-2 gap-8"
            v-slot="{ errors, processing, recentlySuccessful }">
            <GlassCard class="p-6">
                <div class="mb-6 space-y-1">
                    <h2 class="text-lg font-semibold">Task</h2>
                    <p class="text-sm text-muted-foreground">
                        Title and optional rich-text description
                    </p>
                </div>
                <div class="grid gap-6">
                    <div class="grid gap-2">
                        <Label for="create-title">Title</Label>
                        <TypeaheadInput id="create-title" v-model="createTitle" name="title" type="task_title"
                            required />
                        <InputError :message="errors.title" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="create-description">Description</Label>
                        <RichTextEditor id="create-description" v-model="descriptionJson" input-name="description" />
                        <InputError :message="errors.description" />
                    </div>
                </div>
            </GlassCard>

            <GlassCard class="p-6">
                <div class="mb-6 space-y-1">
                    <h2 class="text-lg font-semibold">Assignment & scheduling</h2>
                </div>
                <div class="grid gap-6">
                    <div class="grid gap-2">
                        <Label for="create-status">Status</Label>
                        <FormSelect id="create-status" name="status" v-model="createStatus" required
                            placeholder="Status" :options="statusSelectOptions" />
                        <InputError :message="errors.status" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="create-assignee">Assignee</Label>
                        <FormSelect id="create-assignee" name="assignee_user_id" v-model="createAssignee"
                            none-label="Unassigned" placeholder="Unassigned" :options="assigneeSelectOptions" />
                        <InputError :message="errors.assignee_user_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="create-requirement">Requirement</Label>
                        <FormSelect id="create-requirement" name="project_requirement_id" v-model="createRequirement"
                            none-label="None" placeholder="None" :options="requirementSelectOptions" />
                        <InputError :message="errors.project_requirement_id" />
                    </div>
                    <div v-if="showCreatePhaseField" class="grid gap-2">
                        <Label for="create-phase">Phase</Label>
                        <FormSelect id="create-phase" name="phase" v-model="createPhase" required placeholder="Phase"
                            :options="createPhaseSelectOptions" />
                        <InputError :message="errors.phase" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="create-parent">Parent task (subtask)</Label>
                        <FormSelect id="create-parent" name="parent_project_task_id" v-model="createParent"
                            none-label="None" placeholder="None" :options="parentSelectOptions" />
                        <InputError :message="errors.parent_project_task_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="create-estimate">Estimate (minutes)</Label>
                        <Input id="create-estimate" name="estimated_minutes" type="number" min="1" step="1"
                            :required="project.estimation_required" />
                        <InputError :message="errors.estimated_minutes" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="create-display-after-at">Display after</Label>
                        <Input id="create-display-after-at" name="display_after_at" type="datetime-local"
                            v-model="createDisplayAfterAt" />
                        <InputError :message="errors.display_after_at" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="create-notify-at">Notify task at</Label>
                        <Input id="create-notify-at" name="notify_at" type="datetime-local" v-model="createNotifyAt" />
                        <InputError :message="errors.notify_at" />
                    </div>
                </div>
            </GlassCard>

            <div class="flex items-center gap-4">
                <Button type="submit" :disabled="processing">Create task</Button>
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
