<script setup lang="ts">
import AppTopBar from '@/components/dashboard/AppTopBar.vue';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <AppTopBar :breadcrumbs="breadcrumbs" />
</template>
