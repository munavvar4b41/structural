<script setup lang="ts">
import { Clock, FolderTree, Layers, ListChecks } from 'lucide-vue-next';
import GlassCard from '@/components/dashboard/GlassCard.vue';

export type EstimationAnalytics = {
    total_lines: number;
    root_modules_count: number;
    subtask_count: number;
    lines_with_estimate: number;
    total_minutes: number;
    total_hours: number;
    total_days: number;
    formatted_minutes: string;
    formatted_hours: string;
    formatted_days: string;
};

defineProps<{
    analytics: EstimationAnalytics;
}>();
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <GlassCard class="flex flex-col gap-2 p-4">
            <div class="flex items-center justify-between gap-2">
                <span class="text-sm text-muted-foreground">Total lines</span>
                <ListChecks class="size-4 text-muted-foreground" aria-hidden="true" />
            </div>
            <p class="text-2xl font-semibold tabular-nums">{{ analytics.total_lines }}</p>
            <p class="text-xs text-muted-foreground">
                {{ analytics.lines_with_estimate }} with estimates
            </p>
        </GlassCard>

        <GlassCard class="flex flex-col gap-2 p-4">
            <div class="flex items-center justify-between gap-2">
                <span class="text-sm text-muted-foreground">Main modules</span>
                <FolderTree class="size-4 text-muted-foreground" aria-hidden="true" />
            </div>
            <p class="text-2xl font-semibold tabular-nums">{{ analytics.root_modules_count }}</p>
            <p class="text-xs text-muted-foreground">
                {{ analytics.subtask_count }} subtasks
            </p>
        </GlassCard>

        <GlassCard class="flex flex-col gap-2 p-4">
            <div class="flex items-center justify-between gap-2">
                <span class="text-sm text-muted-foreground">Estimated time</span>
                <Clock class="size-4 text-muted-foreground" aria-hidden="true" />
            </div>
            <p class="text-2xl font-semibold">{{ analytics.formatted_minutes }}</p>
            <p class="text-xs text-muted-foreground">
                {{ analytics.formatted_hours }} · {{ analytics.total_minutes }} min
            </p>
        </GlassCard>

        <GlassCard class="flex flex-col gap-2 p-4">
            <div class="flex items-center justify-between gap-2">
                <span class="text-sm text-muted-foreground">Work days</span>
                <Layers class="size-4 text-muted-foreground" aria-hidden="true" />
            </div>
            <p class="text-2xl font-semibold">{{ analytics.formatted_days }}</p>
            <p class="text-xs text-muted-foreground">Based on 8-hour days</p>
        </GlassCard>
    </div>
</template>
