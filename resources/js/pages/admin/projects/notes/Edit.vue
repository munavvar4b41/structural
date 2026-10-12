<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import ProjectNoteForm from '@/components/project-notes/ProjectNoteForm.vue';
import { Button } from '@/components/ui/button';
import { index as projectsIndex, show as projectsShow } from '@/routes/admin/projects/index';
import {
    edit as notesEdit,
    index as projectNotesIndex,
    show as notesShow,
} from '@/routes/admin/projects/notes/index';

type ProjectSummary = {
    id: number;
    name: string;
    code: string | null;
};

type NoteEdit = {
    id: number;
    title: string;
    body: string;
};

defineProps<{
    project: ProjectSummary;
    note: NoteEdit;
}>();

defineOptions({
    layout: (pageProps: { project: ProjectSummary; note: NoteEdit }) => ({
        breadcrumbs: [
            { title: 'Projects', href: projectsIndex.url() },
            { title: pageProps.project.name, href: projectsShow.url(pageProps.project.id) },
            { title: 'Notes', href: projectNotesIndex.url(pageProps.project.id) },
            {
                title: pageProps.note.title,
                href: notesShow.url({ project: pageProps.project.id, note: pageProps.note.id }),
            },
            {
                title: 'Edit',
                href: notesEdit.url({ project: pageProps.project.id, note: pageProps.note.id }),
            },
        ],
    }),
});
</script>

<template>
    <Head :title="`Edit note · ${note.title}`" />

    <div class="flex flex-col gap-8">
        <PageHeader title="Edit note" :description="`For project ${project.name}`">
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="notesShow.url({ project: project.id, note: note.id })">Back</Link>
                </Button>
            </template>
        </PageHeader>

        <ProjectNoteForm :project-id="project.id" :note-id="note.id"
            :initial="{ title: note.title, body: note.body }" submit-label="Save note" />
    </div>
</template>
