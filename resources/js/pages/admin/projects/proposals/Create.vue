<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import ProjectProposalController from '@/actions/App/Http/Controllers/Admin/ProjectProposalController';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import InputError from '@/components/InputError.vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
import FormSelect from '@/components/FormSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { emptyTipTapDocumentJson } from '@/lib/tiptapDocument';
import { index as projectsIndex, show as projectsShow } from '@/routes/admin/projects/index';
import {
    create as proposalsCreate,
    index as proposalsIndex,
} from '@/routes/admin/projects/proposals/index';

type ProjectSummary = {
    id: number;
    name: string;
    code: string | null;
    estimation_required: boolean;
};

type RequirementOption = {
    value: number;
    label: string;
    title: string;
    description: string;
};

const props = defineProps<{
    project: ProjectSummary;
    requirement_options: RequirementOption[];
}>();

const title = ref('');
const descriptionJson = ref(emptyTipTapDocumentJson());
const projectRequirementId = ref('');

watch(projectRequirementId, (value) => {
    if (value === '') {
        return;
    }

    const option = props.requirement_options.find((o) => String(o.value) === value);

    if (option) {
        title.value = option.title;
        descriptionJson.value = option.description || emptyTipTapDocumentJson();
    }
});

defineOptions({
    layout: (pageProps: { project: ProjectSummary }) => ({
        breadcrumbs: [
            { title: 'Projects', href: projectsIndex.url() },
            {
                title: pageProps.project.name,
                href: projectsShow.url(pageProps.project.id),
            },
            { title: 'Proposals', href: proposalsIndex.url(pageProps.project.id) },
            {
                title: 'Add proposal',
                href: proposalsCreate.url(pageProps.project.id),
            },
        ],
    }),
});
</script>

<template>

    <Head :title="`Add proposal · ${project.name}`" />

    <div class="flex flex-col gap-8">
        <PageHeader title="Add proposal" :description="`For project ${project.name}`" />

        <Form v-bind="ProjectProposalController.store.form({ project: project.id })"
            class="flex max-w-2xl flex-col gap-8" v-slot="{ errors, processing }">
            <GlassCard class="p-6">
                <div class="mb-6 space-y-1">
                    <h2 class="text-lg font-semibold">Proposal details</h2>
                    <p class="text-sm text-muted-foreground">
                        Optionally link to an existing requirement to pre-fill the title and description.
                    </p>
                </div>

                <div class="grid gap-4">
                    <div class="grid gap-2">
                        <Label for="project_requirement_id">Linked requirement (optional)</Label>
                        <FormSelect id="project_requirement_id" name="project_requirement_id"
                            v-model="projectRequirementId" :options="requirement_options.map((o) => ({
                                value: String(o.value),
                                label: o.label,
                            }))
                                " placeholder="None" none-label="None" />
                        <InputError :message="errors.project_requirement_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="title">Title</Label>
                        <Input id="title" v-model="title" name="title" required />
                        <InputError :message="errors.title" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="proposal-description">Description</Label>
                        <RichTextEditor id="proposal-description" v-model="descriptionJson" input-name="description" />
                        <InputError :message="errors.description" />
                    </div>
                </div>
            </GlassCard>

            <div class="flex flex-wrap gap-3">
                <Button type="submit" :disabled="processing">Create proposal</Button>
                <Button variant="outline" as-child>
                    <Link :href="proposalsIndex.url(project.id)">Cancel</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
