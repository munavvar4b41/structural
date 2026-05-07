<script setup lang="ts">
import { computed } from 'vue';
import ActiveTimerBadge from '@/components/ActiveTimerBadge.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

const props = withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const currentPageTitle = computed(() => {
    const items = props.breadcrumbs;

    if (items.length === 0) {
        return '';
    }

    return items[items.length - 1]?.title ?? '';
});

const breadcrumbListSingleLineClass =
    'flex-nowrap overflow-x-auto break-normal whitespace-nowrap [scrollbar-width:thin]';
</script>

<template>
    <header class="shrink-0 border-b border-sidebar-border/70">
        <div
            class="flex h-16 min-w-0 items-center gap-2 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
        >
            <SidebarTrigger class="-ml-1 shrink-0" />
            <span
                v-if="currentPageTitle"
                class="min-w-0 truncate font-semibold text-foreground"
            >
                {{ currentPageTitle }}
            </span>
            <div class="ml-auto min-w-0">
                <ActiveTimerBadge />
            </div>
        </div>
        <div
            v-if="props.breadcrumbs.length > 1"
            class="flex min-h-10 w-full min-w-0 items-center border-t border-sidebar-border/70 px-6 md:px-4"
        >
            <div class="min-w-0 flex-1 overflow-x-auto py-1.5 [scrollbar-width:thin]">
                <Breadcrumbs
                    :breadcrumbs="props.breadcrumbs"
                    :list-class="breadcrumbListSingleLineClass"
                />
            </div>
        </div>
    </header>
</template>
