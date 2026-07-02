<script setup lang="ts">
type Props = {
    title: string;
    description?: string;
};

defineProps<Props>();
</script>

<template>
    <header class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0 space-y-1">
            <h1 class="text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
                {{ title }}
            </h1>
            <p v-if="description" class="text-sm leading-relaxed text-muted-foreground">
                {{ description }}
            </p>
        </div>
        <div class="flex shrink-0 flex-wrap items-center gap-0.5 empty:hidden">
            <slot name="actions" />
        </div>
    </header>
</template>
