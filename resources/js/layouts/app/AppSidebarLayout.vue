<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import FlashToast from '@/components/FlashToast.vue';
import type { BreadcrumbItem } from '@/types';

const page = usePage();

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <FlashToast />
        <AppSidebar />
        <AppContent variant="sidebar" class="page-gradient overflow-x-hidden">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <div
                :key="page.url"
                class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-6 lg:p-8"
            >
                <slot />
            </div>
        </AppContent>
    </AppShell>
</template>
