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
    blue: 'from-blue-500/20 to-blue-500/5 text-blue-600 dark:text-blue-400',
    green: 'from-emerald-500/20 to-emerald-500/5 text-emerald-600 dark:text-emerald-400',
    purple: 'from-violet-500/20 to-violet-500/5 text-violet-600 dark:text-violet-400',
    amber: 'from-amber-500/20 to-amber-500/5 text-amber-600 dark:text-amber-400',
    rose: 'from-rose-500/20 to-rose-500/5 text-rose-600 dark:text-rose-400',
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
