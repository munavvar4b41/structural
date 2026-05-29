<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Bell, BellRing, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import NotificationController from '@/actions/App/Http/Controllers/Admin/NotificationController';
import ActiveTimerBadge from '@/components/ActiveTimerBadge.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import EmptyState from '@/components/dashboard/EmptyState.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
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
type NotificationFilter = 'unread' | 'read';

type NotificationItem = {
    id: string;
    title: string | null;
    project_name: string | null;
    task_show_url: string | null;
    read_at: string | null;
    created_at: string | null;
};

const notifications = computed(() => {
    return (
        page.props.notifications ?? {
            unread_count: 0,
            read_count: 0,
            unread_items: [],
            read_items: [],
        }
    ) as {
        unread_count: number;
        read_count: number;
        unread_items: NotificationItem[];
        read_items: NotificationItem[];
    };
});
const unreadNotificationCount = computed(() => notifications.value.unread_count ?? 0);
const readNotificationCount = computed(() => notifications.value.read_count ?? 0);
const notificationFilter = ref<NotificationFilter>('unread');

const displayedNotifications = computed(() => {
    if (notificationFilter.value === 'read') {
        return notifications.value.read_items;
    }

    return notifications.value.unread_items;
});

const hasAnyNotifications = computed(
    () => unreadNotificationCount.value > 0 || readNotificationCount.value > 0,
);

function setNotificationFilter(filter: NotificationFilter): void {
    notificationFilter.value = filter;
}
const notificationBadgeCount = computed(() => {
    if (unreadNotificationCount.value > 99) {
        return '99+';
    }

    return String(unreadNotificationCount.value);
});

const currentPageTitle = computed(() => {
    const items = props.breadcrumbs;

    if (items.length === 0) {
        return '';
    }

    return items[items.length - 1]?.title ?? '';
});

const breadcrumbListClass =
    'flex-nowrap overflow-x-auto whitespace-nowrap [scrollbar-width:thin]';

function openNotification(item: NotificationItem): void {
    const navigate = (): void => {
        if (item.task_show_url !== null) {
            router.visit(item.task_show_url);
        }
    };

    if (item.read_at !== null) {
        navigate();

        return;
    }

    router.patch(
        NotificationController.markAsRead.url({ notification: item.id }),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            only: ['notifications'],
            onSuccess: () => {
                if (unreadNotificationCount.value === 0) {
                    notificationFilter.value = 'read';
                }
            },
            onFinish: navigate,
        },
    );
}
</script>

<template>
    <header class="glass-nav sticky top-0 z-20 shrink-0 border-b border-white/20 dark:border-white/10">
        <div class="flex h-14 min-w-0 items-center gap-3 px-4 md:px-6 lg:h-16">
            <SidebarTrigger class="-ml-1 shrink-0 rounded-xl" />
            <span v-if="currentPageTitle && breadcrumbs.length <= 1"
                class="min-w-0 truncate text-base font-semibold tracking-tight lg:text-lg">
                {{ currentPageTitle }}
            </span>
            <div v-if="showSearch" class="relative hidden min-w-0 flex-1 sm:block sm:max-w-md">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input v-model="searchQuery" type="search" placeholder="Search…"
                    class="h-10 rounded-full border-0 bg-muted/50 pl-10 ring-1 ring-black/5" disabled
                    aria-label="Search" />
            </div>
            <div class="ml-auto flex min-w-0 items-center gap-1.5 sm:gap-2">
                <ActiveTimerBadge />
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button variant="ghost" size="icon" class="relative size-10 rounded-full"
                            aria-label="Notifications">
                            <BellRing v-if="unreadNotificationCount > 0" class="size-4" />
                            <Bell v-else class="size-4" />
                            <span v-if="unreadNotificationCount > 0"
                                class="absolute -top-1 -right-1 min-w-5 rounded-full bg-primary px-1 text-center text-[10px] font-medium text-primary-foreground">
                                {{ notificationBadgeCount }}
                            </span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-80 rounded-2xl p-0">
                        <div class="border-b border-border/60 px-4 py-2 flex items-center justify-between">
                            <p class="text-sm font-medium">Notifications</p>
                            <div class="mt-2 ms-auto inline-flex  rounded-lg border border-border/80 bg-muted/30 p-0.5"
                                role="tablist" aria-label="Notification filter">
                                <Button type="button" size="sm"
                                    :variant="notificationFilter === 'unread' ? 'default' : 'ghost'"
                                    class="h-7 text-xs" role="tab"
                                    :aria-selected="notificationFilter === 'unread'"
                                    @click="setNotificationFilter('unread')">
                                    Unread ({{ unreadNotificationCount }})
                                </Button>
                                <Button type="button" size="sm"
                                    :variant="notificationFilter === 'read' ? 'default' : 'ghost'"
                                    class="h-7 text-xs" role="tab" :aria-selected="notificationFilter === 'read'"
                                    @click="setNotificationFilter('read')">
                                    Read ({{ readNotificationCount }})
                                </Button>
                            </div>
                        </div>
                        <div v-if="displayedNotifications.length > 0" class="max-h-80 overflow-y-auto py-1">
                            <DropdownMenuItem v-for="item in displayedNotifications" :key="item.id"
                                class="cursor-pointer px-4 py-3"
                                :class="notificationFilter === 'unread' ? 'bg-muted/30' : ''"
                                @click="openNotification(item)">
                                <div class="w-full">
                                    <p class="line-clamp-2 text-sm" :class="notificationFilter === 'unread'
                                            ? 'font-medium text-foreground'
                                            : 'text-muted-foreground'
                                        ">
                                        {{ item.title ?? 'Task reminder' }}
                                    </p>
                                    <p v-if="item.project_name" class="mt-1 text-xs text-muted-foreground">
                                        {{ item.project_name }}
                                    </p>
                                </div>
                            </DropdownMenuItem>
                        </div>
                        <EmptyState v-else-if="hasAnyNotifications" :title="notificationFilter === 'unread'
                                ? 'No unread notifications'
                                : 'No read notifications'
                            " :description="notificationFilter === 'unread'
                                    ? 'Switch to Read to view older notifications.'
                                    : 'Notifications you open will appear here.'
                                " class="border-0 bg-transparent" />
                        <EmptyState v-else title="No notifications" description="You're all caught up."
                            class="border-0 bg-transparent" />
                    </DropdownMenuContent>
                </DropdownMenu>
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button variant="ghost" class="hidden h-10 gap-2 rounded-full px-2 sm:flex">
                            <UserInfo :user="user" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="min-w-56 rounded-2xl">
                        <UserMenuContent :user="user" />
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>
        <div v-if="breadcrumbs.length > 1" class="flex min-h-9 items-center border-t border-white/10 px-4 md:px-6">
            <Breadcrumbs :breadcrumbs="breadcrumbs" :list-class="breadcrumbListClass" />
        </div>
    </header>
</template>
