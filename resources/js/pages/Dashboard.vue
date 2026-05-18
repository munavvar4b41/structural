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
import { dashboard } from '@/routes';
import { index as adminMyWorkIndex } from '@/routes/admin/my-work/index';
import { index as adminProjectsIndex } from '@/routes/admin/projects/index';
import { index as adminTimeReportIndex } from '@/routes/admin/time-report/index';
import { index as adminUsersIndex } from '@/routes/admin/users/index';

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
                            <p class="font-semibold text-foreground group-hover:text-primary">
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
