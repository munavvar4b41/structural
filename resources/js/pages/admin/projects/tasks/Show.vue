<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import TaskShowPanel from '@/components/tasks/TaskShowPanel.vue';
import { edit as projectsEdit, index as projectsIndex } from '@/routes/admin/projects/index';
import { index as requirementsIndex } from '@/routes/admin/projects/requirements/index';
import {
    index as projectTasksIndex,
    show as projectTasksShow,
} from '@/routes/admin/projects/tasks/index';
import type { Checklist, ProjectSummary, TaskDetail, TimeTracking } from '@/types/projectTaskShow';

defineProps<{
    project: ProjectSummary;
    task: TaskDetail;
    can_manage_project: boolean;
    checklist: Checklist;
    time_tracking: TimeTracking;
}>();

defineOptions({
    layout: (pageProps: {
        project: ProjectSummary;
        task: TaskDetail;
        can_manage_project: boolean;
        checklist: Checklist;
        time_tracking: TimeTracking;
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
</script>

<template>
    <Head :title="`${task.title} · Tasks`" />

    <TaskShowPanel
        :project="project"
        :task="task"
        :can_manage_project="can_manage_project"
        :checklist="checklist"
        :time_tracking="time_tracking"
    />
</template>
