<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Briefcase,
    Building2,
    CalendarDays,
    Calculator,
    ClipboardCheck,
    ClipboardList,
    FileText,
    FolderKanban,
    LayoutGrid,
    FileCheck,
    BookOpen,
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
import { index as adminCaseStudiesIndex } from '@/routes/admin/case-studies/index';
import { edit as adminCareersSettingsEdit } from '@/routes/admin/careers-settings/index';
import { edit as adminCompanyEdit } from '@/routes/admin/company/index';
import { index as adminJobPostingsIndex } from '@/routes/admin/job-postings/index';
import { index as leaveRequestsIndex, manage as leaveRequestsManage } from '@/routes/admin/leave-requests/index';
import { edit as adminLeaveSettingsEdit } from '@/routes/admin/leave-settings/index';
import { index as adminMyWorkIndex } from '@/routes/admin/my-work/index';
import { index as adminProjectsIndex } from '@/routes/admin/projects/index';
import { index as adminProposalsIndex } from '@/routes/admin/proposals/index';
import { index as adminRequirementsIndex } from '@/routes/admin/requirements/index';
import { index as adminTaskRatingsReportIndex } from '@/routes/admin/task-ratings-report/index';
import { index as adminEstimationReviewsIndex } from '@/routes/admin/estimation-reviews/index';
import { index as adminTaskReviewsIndex } from '@/routes/admin/task-reviews/index';
import { index as adminTasksIndex } from '@/routes/admin/tasks/index';
import { index as adminTeamsIndex } from '@/routes/admin/teams/index';
import { index as adminTimeReportIndex } from '@/routes/admin/time-report/index';
import { index as adminUsersIndex } from '@/routes/admin/users/index';
import type { NavGroup, NavItem } from '@/types';

const page = usePage();

function pushGroup(groups: NavGroup[], label: string, items: NavItem[]): void {
    if (items.length > 0) {
        groups.push({ label, items });
    }
}

const navGroups = computed((): NavGroup[] => {
    const user = page.props.auth.user;
    const groups: NavGroup[] = [];

    pushGroup(groups, 'Overview', [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
    ]);

    const projectItems: NavItem[] = [];

    if (user?.can_view_projects) {
        projectItems.push(
            {
                title: 'Projects',
                href: adminProjectsIndex(),
                icon: FolderKanban,
                activeMatch: 'prefix',
            },
            {
                title: 'Tasks',
                href: adminTasksIndex(),
                icon: ClipboardList,
                activeMatch: 'prefix',
            },
            {
                title: 'Requirements',
                href: adminRequirementsIndex(),
                icon: FileText,
                activeMatch: 'prefix',
            },
            {
                title: 'Proposals',
                href: adminProposalsIndex(),
                icon: FileCheck,
                activeMatch: 'prefix',
            },
        );

        if (user.role !== 'client') {
            projectItems.push({
                title: 'Case studies',
                href: adminCaseStudiesIndex(),
                icon: BookOpen,
                activeMatch: 'prefix',
            });
        }
    }

    pushGroup(groups, 'Projects', projectItems);

    const workItems: NavItem[] = [];

    if (user?.can_view_projects) {
        workItems.push(
            {
                title: 'My work',
                href: adminMyWorkIndex(),
                icon: ClipboardList,
                activeMatch: 'exact',
            },
            {
                title: 'Time report',
                href: adminTimeReportIndex(),
                icon: Timer,
                activeMatch: 'exact',
            },
        );
    }

    pushGroup(groups, 'Work', workItems);

    const reviewItems: NavItem[] = [];

    if (user?.can_review_task_completions) {
        reviewItems.push({
            title: 'Task reviews',
            href: adminTaskReviewsIndex(),
            icon: ClipboardCheck,
        });
    }

    if (user?.role !== 'client') {
        reviewItems.push({
            title: 'Estimation reviews',
            href: adminEstimationReviewsIndex(),
            icon: Calculator,
        });
    }

    if (user?.can_view_task_rating_report) {
        reviewItems.push({
            title: 'Task ratings',
            href: adminTaskRatingsReportIndex(),
            icon: Star,
        });
    }

    pushGroup(groups, 'Reviews', reviewItems);

    const peopleItems: NavItem[] = [];

    if (user?.role !== 'client') {
        peopleItems.push({
            title: 'Leave requests',
            href: leaveRequestsIndex(),
            icon: CalendarDays,
            activeMatch: 'exact',
        });
    }

    if (user?.can_approve_leave_requests) {
        peopleItems.push({
            title: 'Leave approvals',
            href: leaveRequestsManage(),
            icon: CalendarDays,
            activeMatch: 'exact',
        });
    }

    pushGroup(groups, 'People', peopleItems);

    const careersItems: NavItem[] = [];

    if (user?.can_manage_careers) {
        careersItems.push({
            title: 'Careers',
            href: adminJobPostingsIndex(),
            icon: Briefcase,
            activeMatch: 'prefix',
        });
    }

    pushGroup(groups, 'Careers', careersItems);

    const organizationItems: NavItem[] = [];

    if (user?.can_manage_users) {
        organizationItems.push(
            {
                title: 'Users',
                href: adminUsersIndex(),
                icon: Users,
                activeMatch: 'prefix',
            },
            {
                title: 'Teams',
                href: adminTeamsIndex(),
                icon: Users,
                activeMatch: 'prefix',
            },
        );
    }

    pushGroup(groups, 'Organization', organizationItems);

    const settingsItems: NavItem[] = [];

    if (user?.can_manage_company_settings) {
        settingsItems.push(
            {
                title: 'Company settings',
                href: adminCompanyEdit(),
                icon: Building2,
                activeMatch: 'prefix',
            },
            {
                title: 'Leave emails',
                href: adminLeaveSettingsEdit(),
                icon: CalendarDays,
                activeMatch: 'prefix',
            },
            {
                title: 'Careers emails',
                href: adminCareersSettingsEdit(),
                icon: Briefcase,
                activeMatch: 'prefix',
            },
        );
    }

    pushGroup(groups, 'Settings', settingsItems);

    return groups;
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
            <NavMain :groups="navGroups" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
