<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ClipboardList,
    FolderKanban,
    LayoutGrid,
    Timer,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import StatCard from '@/components/dashboard/StatCard.vue';
import TaskPriorityBadge from '@/components/tasks/TaskPriorityBadge.vue';
import type { TaskPriorityBadgeData } from '@/components/tasks/TaskPriorityBadge.vue';
import { dashboard } from '@/routes';
import { index as adminMyWorkIndex } from '@/routes/admin/my-work/index';
import { index as adminProjectsIndex } from '@/routes/admin/projects/index';
import { index as adminTimeReportIndex } from '@/routes/admin/time-report/index';
import { index as adminUsersIndex } from '@/routes/admin/users/index';
import { edit as editDashboardTasks } from '@/routes/dashboard-tasks';

type DashboardTask = {
    id: number;
    title: string;
    status: string;
    status_label: string;
    priority: TaskPriorityBadgeData | null;
    project: { id: number; name: string; code: string | null };
    task_show_url: string;
};

defineProps<{
    dashboard_tasks: {
        enabled: boolean;
        tasks: DashboardTask[];
        total: number;
        has_more: boolean;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);

const quickLinks = computed(() => {
    const links: {
        title: string;
        description: string;
        href: ReturnType<typeof adminUsersIndex>;
        icon: typeof LayoutGrid;
    }[] = [];

    if (user.value?.can_manage_users) {
        links.push({
            title: 'Users',
            description: 'Manage team members',
            href: adminUsersIndex(),
            icon: Users,
        });
    }

    if (user.value?.can_view_projects) {
        links.push(
            {
                title: 'Projects',
                description: 'View and manage projects',
                href: adminProjectsIndex(),
                icon: FolderKanban,
            },
            {
                title: 'My work',
                description: 'Your assigned tasks',
                href: adminMyWorkIndex(),
                icon: ClipboardList,
            },
            {
                title: 'Time report',
                description: 'Track logged hours',
                href: adminTimeReportIndex(),
                icon: Timer,
            },
        );
    }

    return links;
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-1 flex-col gap-6">
        <PageHeader
            :title="`Welcome back${user?.name ? `, ${user.name.split(' ')[0]}` : ''}`"
            description="Your workspace overview and quick actions."
        />

        <section
            v-if="user?.can_view_projects"
            class="space-y-4"
            data-test="dashboard-tasks"
        >
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-sm font-medium text-muted-foreground">
                    My tasks
                </h2>
                <Link
                    :href="editDashboardTasks()"
                    class="text-sm font-medium text-primary hover:underline"
                >
                    Task settings
                </Link>
            </div>

            <GlassCard v-if="!dashboard_tasks.enabled" class="p-5">
                <p class="text-sm text-muted-foreground">
                    Dashboard tasks are turned off.
                    <Link
                        :href="editDashboardTasks()"
                        class="font-medium text-foreground underline decoration-border underline-offset-4"
                    >
                        Turn them on in settings.
                    </Link>
                </p>
            </GlassCard>

            <GlassCard
                v-else-if="dashboard_tasks.tasks.length === 0"
                class="p-5"
            >
                <p class="text-sm text-muted-foreground">
                    No tasks match your dashboard settings.
                </p>
            </GlassCard>

            <div v-else class="grid gap-3">
                <Link
                    v-for="task in dashboard_tasks.tasks"
                    :key="task.id"
                    :href="task.task_show_url"
                    class="group block"
                >
                    <GlassCard
                        hover
                        class="flex items-start justify-between gap-4 p-4"
                    >
                        <div class="min-w-0">
                            <p
                                class="font-medium text-foreground group-hover:text-primary"
                            >
                                {{ task.title }}
                            </p>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ task.project.name }}
                                <span v-if="task.project.code"
                                    >({{ task.project.code }})</span
                                >
                                · {{ task.status_label }}
                            </p>
                        </div>
                        <TaskPriorityBadge :priority="task.priority" />
                    </GlassCard>
                </Link>

                <p
                    v-if="dashboard_tasks.has_more"
                    class="text-sm text-muted-foreground"
                >
                    Showing {{ dashboard_tasks.tasks.length }} of
                    {{ dashboard_tasks.total }}.
                    <Link
                        :href="adminMyWorkIndex()"
                        class="font-medium text-foreground underline decoration-border underline-offset-4"
                    >
                        Open My work
                    </Link>
                </p>
            </div>
        </section>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <StatCard
                title="Quick access"
                :value="quickLinks.length"
                description="Available sections"
                :icon="LayoutGrid"
                accent="blue"
            />
            <StatCard
                v-if="user?.can_view_projects"
                title="Projects"
                value="—"
                description="Open projects from the sidebar"
                :icon="FolderKanban"
                accent="purple"
                :animate="false"
            />
            <StatCard
                v-if="user?.can_manage_users"
                title="Team"
                value="—"
                description="Users and teams management"
                :icon="Users"
                accent="green"
                :animate="false"
            />
        </div>

        <div v-if="quickLinks.length > 0">
            <h2 class="mb-4 text-sm font-medium text-muted-foreground">
                Quick links
            </h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="item in quickLinks"
                    :key="item.title"
                    :href="item.href"
                    class="group block"
                >
                    <GlassCard hover class="flex items-start gap-4 p-5">
                        <div
                            class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary"
                        >
                            <component :is="item.icon" class="size-5" />
                        </div>
                        <div class="min-w-0">
                            <p
                                class="font-semibold text-foreground group-hover:text-primary"
                            >
                                {{ item.title }}
                            </p>
                            <p class="mt-0.5 text-sm text-muted-foreground">
                                {{ item.description }}
                            </p>
                        </div>
                    </GlassCard>
                </Link>
            </div>
        </div>
    </div>
</template>
