<script setup lang="ts">
import type { ApexOptions } from 'apexcharts';
import { computed, onMounted, ref, watch } from 'vue';
import VueApexCharts from 'vue3-apexcharts';
import { buildApexTheme, isDarkMode, mergeApexOptions } from '@/lib/charts';
import GlassCard from './GlassCard.vue';

const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
        type?: 'line' | 'area' | 'bar' | 'donut' | 'pie';
        series: ApexOptions['series'];
        options?: ApexOptions;
        height?: number;
    }>(),
    {
        type: 'line',
        height: 280,
    },
);

const mounted = ref(false);
const chartKey = ref(0);

const chartOptions = computed(() => {
    const base = buildApexTheme(isDarkMode());

    return mergeApexOptions(base, {
        chart: { type: props.type },
        ...props.options,
    });
});

onMounted(() => {
    mounted.value = true;

    const observer = new MutationObserver(() => {
        chartKey.value += 1;
    });
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class'],
    });
});

watch(
    () => document.documentElement.classList.contains('dark'),
    () => {
        chartKey.value += 1;
    },
);
</script>

<template>
    <GlassCard class="space-y-4">
        <div class="space-y-1">
            <h3 class="text-base font-semibold tracking-tight text-foreground">
                {{ title }}
            </h3>
            <p v-if="description" class="text-sm text-muted-foreground">
                {{ description }}
            </p>
        </div>
        <div class="flex flex-wrap gap-3 empty:hidden">
            <slot name="legend" />
        </div>
        <VueApexCharts
            v-if="mounted"
            :key="chartKey"
            :type="type"
            :height="height"
            :options="chartOptions"
            :series="series ?? []"
        />
        <div
            v-else
            class="flex items-center justify-center rounded-2xl bg-muted/30"
            :style="{ height: `${height}px` }"
        >
            <span class="text-sm text-muted-foreground">Loading chart…</span>
        </div>
    </GlassCard>
</template>
