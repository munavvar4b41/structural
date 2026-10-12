<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import ConfirmDestructiveDialog from '@/components/ConfirmDestructiveDialog.vue';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import TableIconAction from '@/components/TableIconAction.vue';
import { index as projectsIndex, show as projectsShow } from '@/routes/admin/projects/index';
import {
    destroy as notesDestroy,
    edit as notesEdit,
    index as projectNotesIndex,
    show as notesShow,
} from '@/routes/admin/projects/notes/index';

type UserBrief = {
    id: number;
    name: string;
    email: string;
} | null;

type ProjectSummary = {
    id: number;
    name: string;
    code: string | null;
};

type NoteDetail = {
    id: number;
    title: string;
    body: string;
    created_at: string | null;
    updated_at: string | null;
    creator: UserBrief;
};

const props = defineProps<{
    project: ProjectSummary;
    note: NoteDetail;
    can_update: boolean;
    can_delete: boolean;
}>();

defineOptions({
    layout: (pageProps: { project: ProjectSummary; note: NoteDetail }) => ({
        breadcrumbs: [
            { title: 'Projects', href: projectsIndex.url() },
            { title: pageProps.project.name, href: projectsShow.url(pageProps.project.id) },
            { title: 'Notes', href: projectNotesIndex.url(pageProps.project.id) },
            {
                title: pageProps.note.title,
                href: notesShow.url({ project: pageProps.project.id, note: pageProps.note.id }),
            },
        ],
    }),
});

const deleteDialogOpen = ref(false);

function executeDelete(): void {
    router.delete(
        notesDestroy.url({
            project: props.project.id,
            note: props.note.id,
        }),
    );
}
</script>

<template>
    <Head :title="`${note.title} · Note`" />

    <ConfirmDestructiveDialog v-model:open="deleteDialogOpen" title="Delete note?"
        :description="`Delete &quot;${note.title}&quot;? This cannot be undone.`" @confirm="executeDelete" />

    <div class="flex flex-col gap-8">
        <PageHeader :title="note.title">
            <template #actions>
                <div class="flex flex-wrap gap-1">
                    <TableIconAction v-if="can_update" icon="pencil" label="Edit note"
                        :href="notesEdit.url({ project: project.id, note: note.id })" />
                    <TableIconAction v-if="can_delete" icon="trash" label="Delete note" destructive
                        @click="deleteDialogOpen = true" />
                    <TableIconAction icon="list" label="All notes" :href="projectNotesIndex.url(project.id)" />
                </div>
            </template>
        </PageHeader>

        <GlassCard class="p-6">
            <dl class="grid gap-4 sm:grid-cols-3">
                <div>
                    <dt class="text-sm text-muted-foreground">Project</dt>
                    <dd class="font-medium">
                        <Link :href="projectsShow.url(project.id)" class="hover:underline">
                            {{ project.name }}
                        </Link>
                    </dd>
                </div>
                <div>
                    <dt class="text-sm text-muted-foreground">Created by</dt>
                    <dd>{{ note.creator?.name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-muted-foreground">Created</dt>
                    <dd>{{ note.created_at ? new Date(note.created_at).toLocaleString() : '—' }}</dd>
                </div>
            </dl>
        </GlassCard>

        <GlassCard class="p-6">
            <p class="whitespace-pre-wrap text-sm">{{ note.body }}</p>
        </GlassCard>
    </div>
</template>
