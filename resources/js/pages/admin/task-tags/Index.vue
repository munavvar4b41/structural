<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import TaskTagController from '@/actions/App/Http/Controllers/Admin/TaskTagController';
import ConfirmDestructiveDialog from '@/components/ConfirmDestructiveDialog.vue';
import DataTable from '@/components/dashboard/DataTable.vue';
import DataTableEmptyRow from '@/components/dashboard/DataTableEmptyRow.vue';
import DataTablePagination from '@/components/dashboard/DataTablePagination.vue';
import DataTableTd from '@/components/dashboard/DataTableTd.vue';
import DataTableTh from '@/components/dashboard/DataTableTh.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import TableRow from '@/components/dashboard/TableRow.vue';
import InputError from '@/components/InputError.vue';
import ListToolbar from '@/components/ListToolbar.vue';
import TableIconAction from '@/components/TableIconAction.vue';
import TaskTagBadges from '@/components/tasks/TaskTagBadges.vue';
import type { TaskTagColor, TaskTagShade } from '@/components/tasks/TaskTagBadges.vue';
import { Button } from '@/components/ui/button';
import { routerReloadOnly, stripFilterParams } from '@/composables/useServerFilters';
import {
    create as taskTagsCreate,
    edit as taskTagsEdit,
    index as taskTagsIndex,
} from '@/routes/admin/task-tags/index';

type TagRow = {
    id: number;
    name: string;
    color: TaskTagColor;
    shade: TaskTagShade;
    sort_order: number;
    tasks_count: number;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedTags = {
    data: TagRow[];
    links: PaginationLink[];
};

type Props = {
    tags: PaginatedTags;
    filters: {
        search: string;
    };
    errors: {
        tag?: string;
    };
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Task tags', href: taskTagsIndex() }],
    },
});

defineProps<Props>();

const deleteDialogOpen = ref(false);
const tagPendingDelete = ref<TagRow | null>(null);

function reloadSearch(search: string): void {
    routerReloadOnly(
        taskTagsIndex.url({
            query: stripFilterParams({
                search,
                page: 1,
            }),
        }),
        ['tags', 'filters'],
    );
}

function openDeleteDialog(tag: TagRow): void {
    tagPendingDelete.value = tag;
    deleteDialogOpen.value = true;
}

function executeDelete(): void {
    const tag = tagPendingDelete.value;

    if (tag === null) {
        return;
    }

    router.delete(TaskTagController.destroy.url(tag.id));
    tagPendingDelete.value = null;
}

const deleteTagDescription = computed(() => {
    const tag = tagPendingDelete.value;

    if (tag === null) {
        return '';
    }

    return `Delete "${tag.name}"? This cannot be undone.`;
});
</script>

<template>
    <Head title="Task tags" />

    <ConfirmDestructiveDialog
        v-model:open="deleteDialogOpen"
        title="Delete tag?"
        :description="deleteTagDescription"
        @confirm="executeDelete"
    />

    <div class="flex flex-col gap-6">
        <PageHeader title="Task tags" description="Badges shown on every task listing">
            <template #actions>
                <Button as-child>
                    <Link :href="taskTagsCreate()">Add tag</Link>
                </Button>
            </template>
        </PageHeader>

        <ListToolbar
            :model-value="filters.search"
            placeholder="Search tag name…"
            @update:model-value="reloadSearch"
        />

        <InputError :message="errors.tag" />

        <DataTable>
            <thead>
                <tr class="border-b border-border/60 bg-muted/40 backdrop-blur-sm">
                    <DataTableTh>Tag</DataTableTh>
                    <DataTableTh>Sort order</DataTableTh>
                    <DataTableTh>Tasks</DataTableTh>
                    <DataTableTh class="text-right">Actions</DataTableTh>
                </tr>
            </thead>
            <tbody>
                <TableRow v-for="tag in tags.data" :key="tag.id">
                    <DataTableTd label="Tag">
                        <TaskTagBadges :tags="[tag]" />
                    </DataTableTd>
                    <DataTableTd label="Sort order" class="text-muted-foreground">
                        {{ tag.sort_order }}
                    </DataTableTd>
                    <DataTableTd label="Tasks" class="text-muted-foreground">
                        {{ tag.tasks_count }}
                    </DataTableTd>
                    <DataTableTd label="Actions" class="text-left md:text-right">
                        <div class="flex gap-1 justify-start md:justify-end">
                            <TableIconAction
                                icon="pencil"
                                label="Edit"
                                :href="taskTagsEdit.url(tag.id)"
                            />
                            <TableIconAction
                                icon="trash"
                                label="Delete"
                                destructive
                                @click="openDeleteDialog(tag)"
                            />
                        </div>
                    </DataTableTd>
                </TableRow>
                <DataTableEmptyRow
                    v-if="tags.data.length === 0"
                    :colspan="4"
                    message="No tags match this filter."
                />
            </tbody>
        </DataTable>

        <DataTablePagination :links="tags.links" />
    </div>
</template>
