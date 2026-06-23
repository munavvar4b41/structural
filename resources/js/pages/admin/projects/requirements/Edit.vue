<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import ProjectRequirementController from '@/actions/App/Http/Controllers/Admin/ProjectRequirementController';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import FormSelect from '@/components/FormSelect.vue';
import InputError from '@/components/InputError.vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
import RichTextViewer from '@/components/RichTextViewer.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { emptyTipTapDocumentJson } from '@/lib/tiptapDocument';
import { index as projectsIndex, show as projectsShow } from '@/routes/admin/projects/index';
import {
    edit as requirementsEdit,
    index as requirementsIndex,
    show as requirementsShow,
} from '@/routes/admin/projects/requirements/index';

type UserBrief = {
    id: number;
    name: string;
    email: string;
} | null;

type ProjectSummary = {
    id: number;
    name: string;
    code: string | null;
    estimation_required: boolean;
};

type AssignableUser = {
    id: number;
    name: string;
    email: string;
};

type RequirementForm = {
    id: number;
    title: string;
    description: string | null;
    reviewer_user_id: number | null;
    responsible_user_id: number | null;
    creator: UserBrief;
};

const props = defineProps<{
    project: ProjectSummary;
    requirement: RequirementForm;
    assignable_staff: AssignableUser[];
    assignable_responsibles: AssignableUser[];
    can_update_content: boolean;
    can_update_assignments: boolean;
    can_manage_project: boolean;
}>();

const descriptionJson = ref(
    props.requirement.description ?? emptyTipTapDocumentJson(),
);

const responsibleUserId = ref(
    props.requirement.responsible_user_id !== null
        ? String(props.requirement.responsible_user_id)
        : '',
);
const reviewerUserId = ref(
    props.requirement.reviewer_user_id !== null
        ? String(props.requirement.reviewer_user_id)
        : '',
);

const responsibleOptions = computed(() =>
    props.assignable_responsibles.map((u) => ({
        value: String(u.id),
        label: `${u.name} (${u.email})`,
    })),
);

const reviewerOptions = computed(() =>
    props.assignable_staff.map((u) => ({
        value: String(u.id),
        label: `${u.name} (${u.email})`,
    })),
);

watch(
    () => props.requirement.description,
    (v) => {
        descriptionJson.value = v ?? emptyTipTapDocumentJson();
    },
);

defineOptions({
    layout: (pageProps: {
        project: ProjectSummary;
        requirement: RequirementForm;
        can_manage_project: boolean;
    }) => ({
        breadcrumbs: [
            { title: 'Projects', href: projectsIndex.url() },
            {
                title: pageProps.project.name,
                href: projectsShow.url(pageProps.project.id),
            },
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
                title: 'Edit',
                href: requirementsEdit.url({
                    project: pageProps.project.id,
                    requirement: pageProps.requirement.id,
                }),
            },
        ],
    }),
});
</script>

<template>

    <Head :title="`Edit requirement · ${project.name}`" />

    <div class="flex flex-col gap-8">
        <PageHeader title="Edit requirement" :description="`Project ${project.name}`" />

        <Form v-bind="ProjectRequirementController.update.form({
            project: project.id,
            requirement: requirement.id,
        })
            " class="flex max-w-2xl flex-col gap-8" v-slot="{ errors, processing, recentlySuccessful }">
            <template v-if="!can_update_assignments">
                <input type="hidden" name="reviewer_user_id" :value="requirement.reviewer_user_id ?? ''" />
                <input type="hidden" name="responsible_user_id" :value="requirement.responsible_user_id ?? ''" />
            </template>

            <GlassCard class="p-6">
                <div class="mb-6 space-y-1">
                    <h2 class="text-lg font-semibold">Details</h2>
                    <p class="text-sm text-muted-foreground">
                        Created by {{ requirement.creator?.name ?? 'Unknown' }}
                    </p>
                </div>
                <div class="grid gap-6">
                    <div class="grid gap-2">
                        <Label for="title">Title</Label>
                        <Input id="title" name="title" type="text" required :default-value="requirement.title"
                            :readonly="!can_update_content" :class="{ 'opacity-80': !can_update_content }" />
                        <InputError :message="errors.title" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="requirement-description">Description</Label>
                        <RichTextEditor v-if="can_update_content" id="requirement-description" v-model="descriptionJson"
                            input-name="description" />
                        <input v-else type="hidden" name="description"
                            :value="requirement.description ?? emptyTipTapDocumentJson()" />
                        <RichTextViewer v-if="!can_update_content" :json="requirement.description" />
                        <InputError :message="errors.description" />
                    </div>
                </div>
            </GlassCard>

            <GlassCard v-if="can_update_assignments" class="p-6">
                <div class="mb-6 space-y-1">
                    <h2 class="text-lg font-semibold">Responsible</h2>
                    <p class="text-sm text-muted-foreground">
                        Who owns triage for this requirement
                    </p>
                </div>
                <div class="grid gap-2">
                    <Label for="responsible_user_id">Responsible user</Label>
                    <FormSelect id="responsible_user_id" name="responsible_user_id" v-model="responsibleUserId"
                        none-label="Unassigned" placeholder="Unassigned" :options="responsibleOptions" />
                    <InputError :message="errors.responsible_user_id" />
                </div>
            </GlassCard>

            <GlassCard v-if="can_update_assignments" class="p-6">
                <div class="mb-6 space-y-1">
                    <h2 class="text-lg font-semibold">Reviewer</h2>
                    <p class="text-sm text-muted-foreground">
                        Assign a staff member on this project to check the requirement
                    </p>
                </div>
                <div class="grid gap-2">
                    <Label for="reviewer_user_id">Reviewer (staff)</Label>
                    <FormSelect id="reviewer_user_id" name="reviewer_user_id" v-model="reviewerUserId" none-label="None"
                        placeholder="None" :options="reviewerOptions" />
                    <p v-if="assignable_staff.length === 0" class="text-xs text-muted-foreground">
                        No staff on this project's teams yet.
                    </p>
                    <InputError :message="errors.reviewer_user_id" />
                </div>
            </GlassCard>

            <div class="flex items-center gap-4">
                <Button type="submit" :disabled="processing">Save</Button>
                <Button variant="outline" as-child>
                    <Link :href="requirementsShow.url({
                        project: project.id,
                        requirement: requirement.id,
                    })
                        ">
                        View
                    </Link>
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="requirementsIndex.url(project.id)">Cancel</Link>
                </Button>
                <span v-show="recentlySuccessful" class="text-sm text-muted-foreground">
                    Saved.
                </span>
            </div>
        </Form>
    </div>
</template>
