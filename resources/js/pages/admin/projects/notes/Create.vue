<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import ProjectNoteForm from '@/components/project-notes/ProjectNoteForm.vue';
import { Button } from '@/components/ui/button';
import { index as projectsIndex, show as projectsShow } from '@/routes/admin/projects/index';
import { create as notesCreate, index as projectNotesIndex } from '@/routes/admin/projects/notes/index';

type ProjectSummary = {
    id: number;
    name: string;
    code: string | null;
};

defineProps<{
    project: ProjectSummary;
}>();

defineOptions({
    layout: (pageProps: { project: ProjectSummary }) => ({
        breadcrumbs: [
            { title: 'Projects', href: projectsIndex.url() },
            { title: pageProps.project.name, href: projectsShow.url(pageProps.project.id) },
            { title: 'Notes', href: projectNotesIndex.url(pageProps.project.id) },
            { title: 'Add note', href: notesCreate.url(pageProps.project.id) },
        ],
    }),
});
</script>

<template>
    <Head :title="`Add note · ${project.name}`" />

    <div class="flex flex-col gap-8">
        <PageHeader title="Add note" :description="`For project ${project.name}`">
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="projectNotesIndex.url(project.id)">Back</Link>
                </Button>
            </template>
        </PageHeader>

        <ProjectNoteForm :project-id="project.id" submit-label="Add note" />
    </div>
</template>
