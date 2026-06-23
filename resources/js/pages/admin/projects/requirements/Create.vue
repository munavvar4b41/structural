<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ProjectRequirementController from '@/actions/App/Http/Controllers/Admin/ProjectRequirementController';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import FormSelect from '@/components/FormSelect.vue';
import InputError from '@/components/InputError.vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { emptyTipTapDocumentJson } from '@/lib/tiptapDocument';
import { index as projectsIndex, show as projectsShow } from '@/routes/admin/projects/index';
import {
    create as requirementsCreate,
    index as requirementsIndex,
} from '@/routes/admin/projects/requirements/index';

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

const props = defineProps<{
    project: ProjectSummary;
    canManageProject: boolean;
    assignable_responsibles: AssignableUser[];
}>();

const descriptionJson = ref(emptyTipTapDocumentJson());

const responsibleUserId = ref('');
const maxGeneratedPhase = ref('1');

const responsibleOptions = computed(() =>
    props.assignable_responsibles.map((u) => ({
        value: String(u.id),
        label: `${u.name} (${u.email})`,
    })),
);

defineOptions({
    layout: (pageProps: {
        project: ProjectSummary;
        canManageProject: boolean;
    }) => ({
        breadcrumbs: [
            { title: 'Projects', href: projectsIndex.url() },
            {
                title: pageProps.project.name,
                href: projectsShow.url(pageProps.project.id),
            },
            { title: 'Requirements', href: requirementsIndex.url(pageProps.project.id) },
            {
                title: 'Add requirement',
                href: requirementsCreate.url(pageProps.project.id),
            },
        ],
    }),
});
</script>

<template>

    <Head :title="`Add requirement · ${project.name}`" />

    <div class="flex flex-col gap-8">
        <PageHeader title="Add requirement" :description="`For project ${project.name}`" />

        <Form v-bind="ProjectRequirementController.store.form({ project: project.id })"
            class="flex max-w-2xl flex-col gap-8" v-slot="{ errors, processing, recentlySuccessful }">

            <GlassCard class="p-6">
                <div class="mb-6 space-y-1">
                    <h2 class="text-lg font-semibold">Requirement</h2>
                    <p class="text-sm text-muted-foreground">
                        Title and optional rich-text details for your team
                    </p>
                </div>
                <div class="grid gap-6">
                    <div class="grid gap-2">
                        <Label for="title">Title</Label>
                        <Input id="title" name="title" type="text" required />
                        <InputError :message="errors.title" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="requirement-description">Description</Label>
                        <RichTextEditor id="requirement-description" v-model="descriptionJson"
                            input-name="description" />
                        <InputError :message="errors.description" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="max-generated-phase">Number of phases</Label>
                        <Input id="max-generated-phase" name="max_generated_phase" type="number" min="1" max="100"
                            v-model="maxGeneratedPhase" required />
                        <p class="text-xs text-muted-foreground">
                            How many phases this requirement spans. Use 1 when no phase split is needed.
                        </p>
                        <InputError :message="errors.max_generated_phase" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="responsible_user_id">Responsible (optional)</Label>
                        <FormSelect id="responsible_user_id" name="responsible_user_id" v-model="responsibleUserId"
                            none-label="Use default (project lead / first team head)"
                            placeholder="Use default (project lead / first team head)" :options="responsibleOptions" />
                        <p class="text-xs text-muted-foreground">
                            Leave blank to use the project lead or the first team head on this project.
                        </p>
                        <InputError :message="errors.responsible_user_id" />
                    </div>
                </div>
            </GlassCard>

            <div class="flex items-center gap-4">
                <Button type="submit" :disabled="processing">Create</Button>
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
