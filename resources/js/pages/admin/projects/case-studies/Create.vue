<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import CaseStudyForm from '@/components/case-studies/CaseStudyForm.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { index as projectsIndex, show as projectsShow } from '@/routes/admin/projects/index';
import {
    create as caseStudiesCreate,
    index as projectCaseStudiesIndex,
} from '@/routes/admin/projects/case-studies/index';
import { index as globalCaseStudiesIndex } from '@/routes/admin/case-studies/index';

type ProjectSummary = {
    id: number;
    name: string;
    code: string | null;
    estimation_required: boolean;
};

type TaskOption = {
    value: number;
    label: string;
};

const props = defineProps<{
    project: ProjectSummary;
    task_options: TaskOption[];
    preselected_task_id: number | null;
}>();

defineOptions({
    layout: (pageProps: { project: ProjectSummary }) => ({
        breadcrumbs: [
            { title: 'Projects', href: projectsIndex.url() },
            { title: pageProps.project.name, href: projectsShow.url(pageProps.project.id) },
            { title: 'Case studies', href: globalCaseStudiesIndex.url() },
            { title: 'Add case study', href: caseStudiesCreate.url(pageProps.project.id) },
        ],
    }),
});
</script>

<template>
    <Head :title="`Add case study · ${project.name}`" />

    <div class="flex flex-col gap-8">
        <PageHeader title="Add case study" :description="`For project ${project.name}`">
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="projectCaseStudiesIndex.url(project.id)">Back</Link>
                </Button>
            </template>
        </PageHeader>

        <CaseStudyForm
            :project="project"
            :task-options="task_options"
            :preselected-task-id="preselected_task_id"
            submit-label="Create case study"
        />
    </div>
</template>
