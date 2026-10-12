<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ConfirmDestructiveDialog from '@/components/ConfirmDestructiveDialog.vue';
import DataTable from '@/components/dashboard/DataTable.vue';
import DataTableEmptyRow from '@/components/dashboard/DataTableEmptyRow.vue';
import DataTablePagination from '@/components/dashboard/DataTablePagination.vue';
import DataTableTd from '@/components/dashboard/DataTableTd.vue';
import DataTableTh from '@/components/dashboard/DataTableTh.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import TableRow from '@/components/dashboard/TableRow.vue';
import ListToolbar from '@/components/ListToolbar.vue';
import TableIconAction from '@/components/TableIconAction.vue';
import { Button } from '@/components/ui/button';
import { routerReloadOnly } from '@/composables/useServerFilters';
import { index as projectsIndex, show as projectsShow } from '@/routes/admin/projects/index';
import {
    create as notesCreate,
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

type NoteRow = {
    id: number;
    title: string;
    body_preview: string;
    created_at: string | null;
    creator: UserBrief;
    can_update: boolean;
    can_delete: boolean;
};

type ProjectSummary = {
    id: number;
    name: string;
    code: string | null;
};

const props = defineProps<{
    project: ProjectSummary;
    notes: {
        data: NoteRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    can_create: boolean;
    filters: {
        search: string;
    };
}>();

defineOptions({
    layout: (pageProps: { project: ProjectSummary }) => ({
        breadcrumbs: [
            { title: 'Projects', href: projectsIndex.url() },
            { title: pageProps.project.name, href: projectsShow.url(pageProps.project.id) },
            { title: 'Notes', href: projectNotesIndex.url(pageProps.project.id) },
        ],
    }),
});

function onSearch(search: string): void {
    routerReloadOnly(
        projectNotesIndex.url(props.project.id, { query: { search, page: 1 } }),
        ['project', 'notes', 'can_create', 'filters'],
    );
}

const deleteDialogOpen = ref(false);
const notePendingDelete = ref<NoteRow | null>(null);

function openDeleteDialog(row: NoteRow): void {
    notePendingDelete.value = row;
    deleteDialogOpen.value = true;
}

function executeDelete(): void {
    const row = notePendingDelete.value;

    if (row === null) {
        return;
    }

    router.delete(
        notesDestroy.url({
            project: props.project.id,
            note: row.id,
        }),
        { preserveScroll: true },
    );
    notePendingDelete.value = null;
}

const deleteDescription = computed(() => {
    const row = notePendingDelete.value;

    if (row === null) {
        return '';
    }

    return `Delete "${row.title}"? This cannot be undone.`;
});
</script>

<template>
    <Head :title="`Notes · ${project.name}`" />

    <ConfirmDestructiveDialog v-model:open="deleteDialogOpen" title="Delete note?" :description="deleteDescription"
        @confirm="executeDelete" />

    <div class="flex flex-col gap-6">
        <PageHeader title="Notes" :description="`Ideas and details to remember about ${project.name}`">
            <template #actions>
                <Button v-if="can_create" as-child>
                    <Link :href="notesCreate.url(project.id)">Add note</Link>
                </Button>
            </template>
        </PageHeader>

        <ListToolbar :model-value="filters.search" placeholder="Search title or note…"
            @update:model-value="onSearch" />

        <DataTable>
            <thead>
                <tr class="border-b border-border/60 bg-muted/40 backdrop-blur-sm">
                    <DataTableTh>Title</DataTableTh>
                    <DataTableTh>Creator</DataTableTh>
                    <DataTableTh>Created</DataTableTh>
                    <DataTableTh class="text-right">Actions</DataTableTh>
                </tr>
            </thead>
            <tbody>
                <TableRow v-for="row in notes.data" :key="row.id">
                    <DataTableTd label="Title" class="align-middle">
                        <div class="font-medium">{{ row.title }}</div>
                        <p class="mt-1 line-clamp-2 hidden text-xs text-muted-foreground md:block">
                            {{ row.body_preview }}
                        </p>
                    </DataTableTd>
                    <DataTableTd label="Creator" class="text-muted-foreground">
                        {{ row.creator?.name ?? '—' }}
                    </DataTableTd>
                    <DataTableTd label="Created" class="text-muted-foreground">
                        {{ row.created_at ? new Date(row.created_at).toLocaleString() : '—' }}
                    </DataTableTd>
                    <DataTableTd label="Actions" class="text-left md:text-right">
                        <div class="flex justify-start gap-1 md:justify-end">
                            <TableIconAction icon="eye" label="View"
                                :href="notesShow.url({ project: project.id, note: row.id })" />
                            <TableIconAction v-if="row.can_update" icon="pencil" label="Edit"
                                :href="notesEdit.url({ project: project.id, note: row.id })" />
                            <TableIconAction v-if="row.can_delete" icon="trash" label="Delete" destructive
                                @click="openDeleteDialog(row)" />
                        </div>
                    </DataTableTd>
                </TableRow>
                <DataTableEmptyRow v-if="notes.data.length === 0" :colspan="4"
                    message="No notes for this project yet." />
            </tbody>
        </DataTable>

        <DataTablePagination v-if="notes.links.length > 3" :links="notes.links" />
    </div>
</template>
