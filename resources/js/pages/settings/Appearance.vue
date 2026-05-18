<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Appearance settings',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Appearance settings" />

    <h1 class="sr-only">Appearance settings</h1>

    <div class="flex flex-col gap-6">
        <div class="space-y-1">
            <h2 class="text-lg font-semibold">Appearance settings</h2>
            <p class="text-sm text-muted-foreground">
                Update your account's appearance settings
            </p>
        </div>
        <AppearanceTabs />
    </div>
</template>
