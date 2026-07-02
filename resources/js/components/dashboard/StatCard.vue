<script setup lang="ts">
import type { Component } from 'vue';
import { useAnimatedCounter } from '@/composables/useAnimatedCounter';
import { cn } from '@/lib/utils';
import GlassCard from './GlassCard.vue';

const props = withDefaults(
    defineProps<{
        title: string;
        value?: number | string;
        description?: string;
        icon?: Component;
        accent?: 'blue' | 'green' | 'purple' | 'amber' | 'rose';
        animate?: boolean;
    }>(),
    {
        accent: 'blue',
        animate: true,
    },
);

const numericValue = typeof props.value === 'number' ? props.value : 0;
const { display } = useAnimatedCounter(() => numericValue);

const accentClasses: Record<string, string> = {
    blue: 'from-primary/20 to-primary/5 text-primary',
    green: 'from-success/20 to-success/5 text-success',
    purple: 'from-chart-3/20 to-chart-3/5 text-chart-3',
    amber: 'from-warning/20 to-warning/5 text-warning',
    rose: 'from-destructive/20 to-destructive/5 text-destructive',
};
</script>

<template>
    <GlassCard hover class="relative overflow-hidden">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-1 bg-gradient-to-r opacity-80"
            :class="accentClasses[accent]"
        />
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0 flex-1 space-y-1">
                <p class="text-sm font-medium text-muted-foreground">
                    {{ title }}
                </p>
                <p class="text-3xl font-semibold tracking-tight text-foreground">
                    <template v-if="typeof value === 'number' && animate">
                        {{ display }}
                    </template>
                    <template v-else>
                        {{ value }}
                    </template>
                </p>
                <p v-if="description" class="text-sm text-muted-foreground">
                    {{ description }}
                </p>
            </div>
            <div
                v-if="icon"
                :class="
                    cn(
                        'flex size-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br',
                        accentClasses[accent],
                    )
                "
            >
                <component :is="icon" class="size-5" />
            </div>
        </div>
    </GlassCard>
</template>
