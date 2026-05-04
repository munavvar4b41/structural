<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CornerDownRight } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ProjectTaskController from '@/actions/App/Http/Controllers/Admin/ProjectTaskController';
import ConfirmDestructiveDialog from '@/components/ConfirmDestructiveDialog.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatTaskMinutes } from '@/lib/formatTaskMinutes';
import { edit as projectsEdit, index as projectsIndex } from '@/routes/admin/projects/index';
import {
    index as requirementsIndex,
    show as requirementsShow,
} from '@/routes/admin/projects/requirements/index';
import {
    index as projectTasksIndex,
    show as projectTasksShow,
} from '@/routes/admin/projects/tasks/index';

type UserBrief = {
    id: number;
    name: string;
    email: string;
} | null;

type TaskParentBrief = {
    id: number;
    title: string;
} | null;

type SubtaskRow = {
    id: number;
    title: string;
    status: string;
    status_label: string;
    assignee_user_id: number | null;
    assignee: UserBrief;
    project_requirement_id: number | null;
    requirement_title: string | null | undefined;
    estimated_minutes: number | null;
    children_count: number;
    tree_depth: number;
    can_update: boolean;
    can_delete: boolean;
};

type TaskDetail = {
    id: number;
    title: string;
    description: string | null;
    status: string;
    status_label: string;
    assignee_user_id: number | null;
    assignee: UserBrief;
    project_requirement_id: number | null;
    requirement_title: string | null | undefined;
    parent_project_task_id: number | null;
    parent: TaskParentBrief;
    estimated_minutes: number | null;
    children_count: number;
    subtasks: SubtaskRow[];
    can_update: boolean;
    can_delete: boolean;
};

type ProjectSummary = {
    id: number;
    name: string;
    code: string | null;
    estimation_required: boolean;
};

const props = defineProps<{
    project: ProjectSummary;
    task: TaskDetail;
    can_manage_project: boolean;
}>();

defineOptions({
    layout: (pageProps: {
        project: ProjectSummary;
        task: TaskDetail;
        can_manage_project: boolean;
    }) => ({
        breadcrumbs: [
            { title: 'Projects', href: projectsIndex.url() },
            {
                title: pageProps.project.name,
                href: pageProps.can_manage_project
                    ? projectsEdit.url(pageProps.project.id)
                    : requirementsIndex.url(pageProps.project.id),
            },
            {
                title: 'Tasks',
                href: projectTasksIndex.url(pageProps.project.id),
            },
            {
                title: pageProps.task.title,
                href: projectTasksShow.url({
                    project: pageProps.project.id,
                    task: pageProps.task.id,
                }),
            },
        ],
    }),
});

const deleteDialogOpen = ref(false);

const deleteTaskDescription = computed(
    () => `Delete "${props.task.title}"? This cannot be undone.`,
);

function executeDelete(): void {
    router.delete(
        ProjectTaskController.destroy.url({
            project: props.project.id,
            task: props.task.id,
        }),
    );
}

const subtaskDeleteOpen = ref(false);
const subtaskPendingDelete = ref<SubtaskRow | null>(null);

function openSubtaskDelete(row: SubtaskRow): void {
    subtaskPendingDelete.value = row;
    subtaskDeleteOpen.value = true;
}

function executeSubtaskDelete(): void {
    const row = subtaskPendingDelete.value;

    if (row === null) {
        return;
    }

    router.delete(
        ProjectTaskController.destroy.url({
            project: props.project.id,
            task: row.id,
        }),
    );
    subtaskPendingDelete.value = null;
}

const subtaskDeleteDescription = computed(() => {
    const row = subtaskPendingDelete.value;

    if (row === null) {
        return '';
    }

    return `Delete "${row.title}"? This cannot be undone.`;
});
</script>

<template>
    <Head :title="`${task.title} · Tasks`" />

    <ConfirmDestructiveDialog
        v-model:open="deleteDialogOpen"
        title="Delete task?"
        :description="deleteTaskDescription"
        @confirm="executeDelete"
    />

    <ConfirmDestructiveDialog
        v-model:open="subtaskDeleteOpen"
        title="Delete subtask?"
        :description="subtaskDeleteDescription"
        @confirm="executeSubtaskDelete"
    />

    <div class="flex flex-col gap-8">
        <div class="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <Heading
                :title="task.title"
                :description="`Project ${project.name}`"
                title-line-clamp
            />
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" as-child>
                    <Link :href="projectTasksIndex.url(project.id)">Back to task list</Link>
                </Button>
                <Button v-if="task.can_update" variant="outline" as-child>
                    <Link
                        :href="
                            projectTasksIndex.url(project.id, {
                                query: { edit_task: String(task.id) },
                            })
                        "
                    >
                        Edit on list
                    </Link>
                </Button>
                <Button
                    v-if="task.can_delete"
                    variant="outline"
                    class="text-destructive hover:bg-destructive/10"
                    type="button"
                    @click="deleteDialogOpen = true"
                >
                    Delete
                </Button>
            </div>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Details</CardTitle>
                <CardDescription>Status, ownership, and links for this task.</CardDescription>
            </CardHeader>
            <CardContent class="grid gap-6 text-sm">
                <div class="grid gap-1">
                    <span class="text-xs font-medium text-muted-foreground">Title</span>
                    <p
                        class="max-w-md text-sm font-medium leading-snug text-foreground line-clamp-2 break-words"
                        :title="task.title"
                    >
                        {{ task.title }}
                    </p>
                </div>
                <div class="grid gap-1">
                    <span class="text-xs font-medium text-muted-foreground">Status</span>
                    <span>{{ task.status_label }}</span>
                </div>
                <div class="grid gap-1">
                    <span class="text-xs font-medium text-muted-foreground">Assignee</span>
                    <span>{{ task.assignee?.name ?? '—' }}</span>
                </div>
                <div class="grid gap-1">
                    <span class="text-xs font-medium text-muted-foreground">Estimate</span>
                    <span>{{ formatTaskMinutes(task.estimated_minutes) }}</span>
                </div>
                <div class="grid gap-1">
                    <span class="text-xs font-medium text-muted-foreground">Requirement</span>
                    <template v-if="task.project_requirement_id">
                        <Button variant="link" class="h-auto justify-start p-0" as-child>
                            <Link
                                :href="
                                    requirementsShow.url({
                                        project: project.id,
                                        requirement: task.project_requirement_id,
                                    })
                                "
                            >
                                {{ task.requirement_title ?? 'View requirement' }}
                            </Link>
                        </Button>
                    </template>
                    <template v-else>
                        <span>—</span>
                    </template>
                </div>
                <div class="grid gap-1">
                    <span class="text-xs font-medium text-muted-foreground">Parent task</span>
                    <template v-if="task.parent">
                        <Button variant="link" class="h-auto max-w-full min-w-0 justify-start p-0" as-child>
                            <Link
                                class="block truncate text-left"
                                :title="task.parent.title"
                                :href="
                                    projectTasksShow.url({
                                        project: project.id,
                                        task: task.parent.id,
                                    })
                                "
                            >
                                {{ task.parent.title }}
                            </Link>
                        </Button>
                    </template>
                    <template v-else>
                        <span>—</span>
                    </template>
                </div>
                <div v-if="task.description" class="grid gap-1">
                    <span class="text-xs font-medium text-muted-foreground">Description</span>
                    <p class="whitespace-pre-wrap text-muted-foreground">{{ task.description }}</p>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Subtasks</CardTitle>
                <CardDescription>
                    Direct children of this task. Same layout as the project task list.
                </CardDescription>
            </CardHeader>
            <CardContent class="overflow-x-auto p-0 sm:p-6">
                <table class="w-full min-w-[720px] table-fixed text-left text-sm">
                    <thead class="border-b bg-muted/40">
                        <tr>
                            <th class="w-[38%] px-4 py-3 font-medium">Title</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Assignee</th>
                            <th class="px-4 py-3 font-medium">Requirement</th>
                            <th class="px-4 py-3 font-medium">Estimate</th>
                            <th class="px-4 py-3 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="sub in task.subtasks"
                            :key="sub.id"
                            class="border-b border-border/60 last:border-0"
                        >
                            <td
                                class="max-w-0 px-4 py-3 align-top"
                                :style="{
                                    paddingLeft: `calc(0.75rem + ${sub.tree_depth} * 1.25rem)`,
                                }"
                            >
                                <div class="flex min-w-0 items-start gap-1.5">
                                    <CornerDownRight
                                        v-if="sub.tree_depth > 0"
                                        class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <div class="min-w-0 flex-1">
                                        <Button
                                            variant="link"
                                            class="h-auto w-full min-w-0 justify-start p-0 font-medium text-foreground"
                                            as-child
                                        >
                                            <Link
                                                class="block text-left text-foreground line-clamp-2 break-words hover:underline"
                                                :title="sub.title"
                                                :href="
                                                    projectTasksShow.url({
                                                        project: project.id,
                                                        task: sub.id,
                                                    })
                                                "
                                            >
                                                {{ sub.title }}
                                            </Link>
                                        </Button>
                                        <span
                                            v-if="sub.children_count > 0"
                                            class="mt-0.5 block text-xs text-muted-foreground"
                                        >
                                            ({{ sub.children_count }} subtasks)
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ sub.status_label }}</td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ sub.assignee?.name ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <template v-if="sub.project_requirement_id">
                                    <Button variant="link" class="h-auto p-0" as-child>
                                        <Link
                                            :href="
                                                requirementsShow.url({
                                                    project: project.id,
                                                    requirement: sub.project_requirement_id,
                                                })
                                            "
                                        >
                                            {{ sub.requirement_title ?? 'View' }}
                                        </Link>
                                    </Button>
                                </template>
                                <template v-else>—</template>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ formatTaskMinutes(sub.estimated_minutes) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <Button v-if="sub.can_update" variant="outline" size="sm" as-child>
                                        <Link
                                            :href="
                                                projectTasksIndex.url(project.id, {
                                                    query: { edit_task: String(sub.id) },
                                                })
                                            "
                                        >
                                            Edit
                                        </Link>
                                    </Button>
                                    <Button
                                        v-if="sub.can_delete"
                                        variant="outline"
                                        size="sm"
                                        class="text-destructive hover:bg-destructive/10"
                                        type="button"
                                        @click="openSubtaskDelete(sub)"
                                    >
                                        Delete
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="task.subtasks.length === 0">
                            <td colspan="6" class="px-4 py-8 text-center text-muted-foreground">
                                No subtasks yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>
    </div>
</template>
