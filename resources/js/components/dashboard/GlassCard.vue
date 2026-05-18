<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        class?: HTMLAttributes['class'];
        hover?: boolean;
        padding?: boolean;
    }>(),
    {
        hover: false,
        padding: true,
    },
);
</script>

<template>
    <div
        :class="
            cn(
                'glass-panel rounded-3xl',
                padding && 'p-6',
                hover && 'hover-lift cursor-default',
                props.class,
            )
        "
    >
        <slot />
    </div>
</template>
