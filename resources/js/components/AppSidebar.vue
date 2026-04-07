<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Building2, FolderKanban, LayoutGrid, Users } from 'lucide-vue-next';
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
import { index as adminProjectsIndex } from '@/routes/admin/projects/index';
import { index as adminTeamsIndex } from '@/routes/admin/teams/index';
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

    if (page.props.auth.user?.can_manage_company_settings) {
        items.push({
            title: 'Company settings',
            href: adminCompanyEdit(),
            icon: Building2,
        });
    }

    if (page.props.auth.user?.can_manage_users) {
        items.push({
            title: 'Users',
            href: adminUsersIndex(),
            icon: Users,
        });
        items.push({
            title: 'Teams',
            href: adminTeamsIndex(),
            icon: Users,
        });
    }

    if (page.props.auth.user?.can_manage_projects) {
        items.push({
            title: 'Projects',
            href: adminProjectsIndex(),
            icon: FolderKanban,
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
