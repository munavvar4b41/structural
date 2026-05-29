<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Building2,
    CalendarDays,
    ClipboardCheck,
    ClipboardList,
    FolderKanban,
    LayoutGrid,
    Star,
    Timer,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { edit as adminCompanyEdit } from '@/routes/admin/company/index';
import { index as leaveRequestsIndex, manage as leaveRequestsManage } from '@/routes/admin/leave-requests/index';
import { edit as adminLeaveSettingsEdit } from '@/routes/admin/leave-settings/index';
import { index as adminMyWorkIndex } from '@/routes/admin/my-work/index';
import { index as adminProjectsIndex } from '@/routes/admin/projects/index';
import { index as adminTaskRatingsReportIndex } from '@/routes/admin/task-ratings-report/index';
import { index as adminTaskReviewsIndex } from '@/routes/admin/task-reviews/index';
import { index as adminTasksIndex } from '@/routes/admin/tasks/index';
import { index as adminTeamsIndex } from '@/routes/admin/teams/index';
import { index as adminTimeReportIndex } from '@/routes/admin/time-report/index';
import { index as adminUsersIndex } from '@/routes/admin/users/index';
import type { NavItem } from '@/types';

const page = usePage();

const mainNavItems = computed((): NavItem[] => {
    const items: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
    ];

    if (page.props.auth.user?.can_manage_users) {
        items.push({
            title: 'Users',
            href: adminUsersIndex(),
            icon: Users,
            activeMatch: 'prefix',
        });
        items.push({
            title: 'Teams',
            href: adminTeamsIndex(),
            icon: Users,
            activeMatch: 'prefix',
        });
    }

    if (page.props.auth.user?.can_view_projects) {
        items.push({
            title: 'Projects',
            href: adminProjectsIndex(),
            icon: FolderKanban,
            activeMatch: 'prefix',
        });
        items.push({
            title: 'Tasks',
            href: adminTasksIndex(),
            icon: ClipboardList,
            activeMatch: 'prefix',
        });
        items.push({
            title: 'My work',
            href: adminMyWorkIndex(),
            icon: ClipboardList,
            activeMatch: 'exact',
        });
        items.push({
            title: 'Time report',
            href: adminTimeReportIndex(),
            icon: Timer,
            activeMatch: 'exact',
        });
    }

    if (page.props.auth.user?.can_review_task_completions) {
        items.push({
            title: 'Task reviews',
            href: adminTaskReviewsIndex(),
            icon: ClipboardCheck,
        });
    }

    if (page.props.auth.user?.can_view_task_rating_report) {
        items.push({
            title: 'Task ratings',
            href: adminTaskRatingsReportIndex(),
            icon: Star,
        });
    }

    if (page.props.auth.user?.role !== 'client') {
        items.push({
            title: 'Leave requests',
            href: leaveRequestsIndex(),
            icon: CalendarDays,
            activeMatch: 'exact',
        });
    }

    if (page.props.auth.user?.can_approve_leave_requests) {
        items.push({
            title: 'Leave approvals',
            href: leaveRequestsManage(),
            icon: CalendarDays,
            activeMatch: 'exact',
        });
    }

    if (page.props.auth.user?.can_manage_company_settings) {
        items.push({
            title: 'Company settings',
            href: adminCompanyEdit(),
            icon: Building2,
            activeMatch: 'prefix',
        });
        items.push({
            title: 'Leave emails',
            href: adminLeaveSettingsEdit(),
            icon: CalendarDays,
            activeMatch: 'prefix',
        });
    }

    return items;
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
