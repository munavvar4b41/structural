<script setup lang="ts">
import type { Component } from 'vue';
import { cn } from '@/lib/utils';

defineProps<{
    title: string;
    description?: string;
    icon?: Component;
    class?: string;
}>();
</script>

<template>
    <div
        :class="
            cn(
                'flex flex-col items-center justify-center rounded-3xl border border-dashed border-border/80 bg-muted/20 px-6 py-14 text-center',
                $props.class,
            )
        "
    >
        <component
            :is="icon"
            v-if="icon"
            class="mb-4 size-10 text-muted-foreground/60"
        />
        <h3 class="text-base font-medium text-foreground">{{ title }}</h3>
        <p v-if="description" class="mt-1 max-w-sm text-sm text-muted-foreground">
            {{ description }}
        </p>
        <div class="mt-4 empty:hidden">
            <slot />
        </div>
    </div>
</template>
