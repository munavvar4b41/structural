<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import CaseStudyForm from '@/components/case-studies/CaseStudyForm.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { index as globalCaseStudiesIndex } from '@/routes/admin/case-studies/index';
import {
    edit as caseStudiesEdit,
    show as caseStudiesShow,
} from '@/routes/admin/projects/case-studies/index';
import { index as projectsIndex, show as projectsShow } from '@/routes/admin/projects/index';

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

type CaseStudyEdit = {
    id: number;
    project_task_id: number | null;
    title: string;
    overview: string | null;
    client_issue: string | null;
    our_solution: string | null;
    implementation: string | null;
    other_details: string | null;
    result_and_impact: string | null;
    conclusion: string | null;
    attachments: {
        id: number;
        title: string | null;
        original_name: string;
        mime: string;
        type: string;
    }[];
};

const props = defineProps<{
    project: ProjectSummary;
    case_study: CaseStudyEdit;
    task_options: TaskOption[];
}>();

defineOptions({
    layout: (pageProps: { project: ProjectSummary; case_study: CaseStudyEdit }) => ({
        breadcrumbs: [
            { title: 'Projects', href: projectsIndex.url() },
            { title: pageProps.project.name, href: projectsShow.url(pageProps.project.id) },
            { title: 'Case studies', href: globalCaseStudiesIndex.url() },
            {
                title: pageProps.case_study.title,
                href: caseStudiesShow.url({
                    project: pageProps.project.id,
                    case_study: pageProps.case_study.id,
                }),
            },
            {
                title: 'Edit',
                href: caseStudiesEdit.url({
                    project: pageProps.project.id,
                    case_study: pageProps.case_study.id,
                }),
            },
        ],
    }),
});
</script>

<template>

    <Head :title="`Edit ${case_study.title}`" />

    <div class="flex flex-col gap-8">
        <PageHeader title="Edit case study" :description="case_study.title">
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="caseStudiesShow.url({
                        project: project.id,
                        case_study: case_study.id,
                    })
                        ">
                        Back
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <CaseStudyForm :project="project" :task-options="task_options" :case-study-id="case_study.id"
            :initial="case_study" submit-label="Save changes" />
    </div>
</template>
