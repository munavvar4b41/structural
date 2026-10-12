<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import TaskPriorityController from '@/actions/App/Http/Controllers/Admin/TaskPriorityController';
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
import TaskPriorityBadge from '@/components/tasks/TaskPriorityBadge.vue';
import type { TaskPriorityColor, TaskPriorityShade } from '@/components/tasks/TaskPriorityBadge.vue';
import { Button } from '@/components/ui/button';
import { routerReloadOnly, stripFilterParams } from '@/composables/useServerFilters';
import {
    create as taskPrioritiesCreate,
    edit as taskPrioritiesEdit,
    index as taskPrioritiesIndex,
} from '@/routes/admin/task-priorities/index';

type PriorityRow = {
    id: number;
    name: string;
    color: TaskPriorityColor;
    shade: TaskPriorityShade;
    sort_order: number;
    tasks_count: number;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedPriorities = {
    data: PriorityRow[];
    links: PaginationLink[];
};

type Props = {
    priorities: PaginatedPriorities;
    filters: {
        search: string;
    };
    errors: {
        priority?: string;
    };
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Task priorities', href: taskPrioritiesIndex() }],
    },
});

defineProps<Props>();

const deleteDialogOpen = ref(false);
const priorityPendingDelete = ref<PriorityRow | null>(null);

function reloadSearch(search: string): void {
    routerReloadOnly(
        taskPrioritiesIndex.url({
            query: stripFilterParams({
                search,
                page: 1,
            }),
        }),
        ['priorities', 'filters'],
    );
}

function openDeleteDialog(priority: PriorityRow): void {
    priorityPendingDelete.value = priority;
    deleteDialogOpen.value = true;
}

function executeDelete(): void {
    const priority = priorityPendingDelete.value;

    if (priority === null) {
        return;
    }

    router.delete(TaskPriorityController.destroy.url(priority.id));
    priorityPendingDelete.value = null;
}

const deletePriorityDescription = computed(() => {
    const priority = priorityPendingDelete.value;

    if (priority === null) {
        return '';
    }

    return `Delete "${priority.name}"? This cannot be undone.`;
});
</script>

<template>
    <Head title="Task priorities" />

    <ConfirmDestructiveDialog
        v-model:open="deleteDialogOpen"
        title="Delete priority?"
        :description="deletePriorityDescription"
        @confirm="executeDelete"
    />

    <div class="flex flex-col gap-6">
        <PageHeader title="Task priorities" description="Badges shown on every task listing">
            <template #actions>
                <Button as-child>
                    <Link :href="taskPrioritiesCreate()">Add priority</Link>
                </Button>
            </template>
        </PageHeader>

        <ListToolbar
            :model-value="filters.search"
            placeholder="Search priority name…"
            @update:model-value="reloadSearch"
        />

        <InputError :message="errors.priority" />

        <DataTable>
            <thead>
                <tr class="border-b border-border/60 bg-muted/40 backdrop-blur-sm">
                    <DataTableTh>Priority</DataTableTh>
                    <DataTableTh>Sort order</DataTableTh>
                    <DataTableTh>Tasks</DataTableTh>
                    <DataTableTh class="text-right">Actions</DataTableTh>
                </tr>
            </thead>
            <tbody>
                <TableRow v-for="priority in priorities.data" :key="priority.id">
                    <DataTableTd label="Priority">
                        <TaskPriorityBadge :priority="priority" />
                    </DataTableTd>
                    <DataTableTd label="Sort order" class="text-muted-foreground">
                        {{ priority.sort_order }}
                    </DataTableTd>
                    <DataTableTd label="Tasks" class="text-muted-foreground">
                        {{ priority.tasks_count }}
                    </DataTableTd>
                    <DataTableTd label="Actions" class="text-left md:text-right">
                        <div class="flex gap-1 justify-start md:justify-end">
                            <TableIconAction
                                icon="pencil"
                                label="Edit"
                                :href="taskPrioritiesEdit.url(priority.id)"
                            />
                            <TableIconAction
                                icon="trash"
                                label="Delete"
                                destructive
                                @click="openDeleteDialog(priority)"
                            />
                        </div>
                    </DataTableTd>
                </TableRow>
                <DataTableEmptyRow
                    v-if="priorities.data.length === 0"
                    :colspan="4"
                    message="No priorities match this filter."
                />
            </tbody>
        </DataTable>

        <DataTablePagination :links="priorities.links" />
    </div>
</template>
