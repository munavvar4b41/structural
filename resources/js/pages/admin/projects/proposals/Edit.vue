<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import ProjectProposalController from '@/actions/App/Http/Controllers/Admin/ProjectProposalController';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import InputError from '@/components/InputError.vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { emptyTipTapDocumentJson } from '@/lib/tiptapDocument';
import { index as projectsIndex, show as projectsShow } from '@/routes/admin/projects/index';
import {
    edit as proposalsEdit,
    index as proposalsIndex,
    show as proposalsShow,
} from '@/routes/admin/projects/proposals/index';

type ProjectSummary = {
    id: number;
    name: string;
    code: string | null;
    estimation_required: boolean;
};

type ProposalForm = {
    id: number;
    title: string;
    description: string | null;
    status: string;
    status_label: string;
};

const props = defineProps<{
    project: ProjectSummary;
    proposal: ProposalForm;
}>();

const title = ref(props.proposal.title);
const descriptionJson = ref(props.proposal.description ?? emptyTipTapDocumentJson());

watch(
    () => props.proposal,
    (proposal) => {
        title.value = proposal.title;
        descriptionJson.value = proposal.description ?? emptyTipTapDocumentJson();
    },
);

defineOptions({
    layout: (pageProps: { project: ProjectSummary; proposal: ProposalForm }) => ({
        breadcrumbs: [
            { title: 'Projects', href: projectsIndex.url() },
            {
                title: pageProps.project.name,
                href: projectsShow.url(pageProps.project.id),
            },
            { title: 'Proposals', href: proposalsIndex.url(pageProps.project.id) },
            {
                title: pageProps.proposal.title,
                href: proposalsShow.url({
                    project: pageProps.project.id,
                    proposal: pageProps.proposal.id,
                }),
            },
            {
                title: 'Edit',
                href: proposalsEdit.url({
                    project: pageProps.project.id,
                    proposal: pageProps.proposal.id,
                }),
            },
        ],
    }),
});
</script>

<template>

    <Head :title="`Edit · ${proposal.title}`" />

    <div class="flex flex-col gap-8">
        <PageHeader :title="`Edit ${proposal.title}`" :description="`Proposal for ${project.name}`">
            <template #actions>
                <Badge variant="outline">{{ proposal.status_label }}</Badge>
                <Button variant="outline" as-child>
                    <Link :href="proposalsShow.url({
                        project: project.id,
                        proposal: proposal.id,
                    })
                        ">
                        Back to proposal
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <Form v-bind="ProjectProposalController.update.form({
            project: project.id,
            proposal: proposal.id,
        })
            " class="flex max-w-2xl flex-col gap-8" v-slot="{ errors, processing }">
            <GlassCard class="p-6">
                <div class="grid gap-4">
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
                <Button type="submit" :disabled="processing">Save changes</Button>
                <Button variant="outline" as-child>
                    <Link :href="proposalsShow.url({
                        project: project.id,
                        proposal: proposal.id,
                    })
                        ">
                        Cancel
                    </Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
