<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Bell, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ActiveTimerBadge from '@/components/ActiveTimerBadge.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import EmptyState from '@/components/dashboard/EmptyState.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { SidebarTrigger } from '@/components/ui/sidebar';
import UserInfo from '@/components/UserInfo.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';
import type { BreadcrumbItem } from '@/types';

const props = withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
        showSearch?: boolean;
    }>(),
    {
        breadcrumbs: () => [],
        showSearch: false,
    },
);

const page = usePage();
const user = computed(() => page.props.auth.user);
const searchQuery = ref('');

const currentPageTitle = computed(() => {
    const items = props.breadcrumbs;

    if (items.length === 0) {
return '';
}

    return items[items.length - 1]?.title ?? '';
});

const breadcrumbListClass =
    'flex-nowrap overflow-x-auto whitespace-nowrap [scrollbar-width:thin]';
</script>

<template>
    <header
        class="glass-nav sticky top-0 z-20 shrink-0 border-b border-white/20 dark:border-white/10"
    >
        <div class="flex h-14 min-w-0 items-center gap-3 px-4 md:px-6 lg:h-16">
            <SidebarTrigger class="-ml-1 shrink-0 rounded-xl" />
            <span
                v-if="currentPageTitle && breadcrumbs.length <= 1"
                class="min-w-0 truncate text-base font-semibold tracking-tight lg:text-lg"
            >
                {{ currentPageTitle }}
            </span>
            <div
                v-if="showSearch"
                class="relative hidden min-w-0 flex-1 sm:block sm:max-w-md"
            >
                <Search
                    class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="searchQuery"
                    type="search"
                    placeholder="Search…"
                    class="h-10 rounded-full border-0 bg-muted/50 pl-10 ring-1 ring-black/5"
                    disabled
                    aria-label="Search"
                />
            </div>
            <div class="ml-auto flex min-w-0 items-center gap-1.5 sm:gap-2">
                <ActiveTimerBadge />
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-10 rounded-full"
                            aria-label="Notifications"
                        >
                            <Bell class="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-80 rounded-2xl p-0">
                        <EmptyState
                            title="No notifications"
                            description="You're all caught up."
                            class="border-0 bg-transparent"
                        />
                    </DropdownMenuContent>
                </DropdownMenu>
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="ghost"
                            class="hidden h-10 gap-2 rounded-full px-2 sm:flex"
                        >
                            <UserInfo :user="user" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="min-w-56 rounded-2xl">
                        <UserMenuContent :user="user" />
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>
        <div
            v-if="breadcrumbs.length > 1"
            class="flex min-h-9 items-center border-t border-white/10 px-4 md:px-6"
        >
            <Breadcrumbs
                :breadcrumbs="breadcrumbs"
                :list-class="breadcrumbListClass"
            />
        </div>
    </header>
</template>
