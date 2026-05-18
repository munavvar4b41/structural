<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        class?: HTMLAttributes['class'];
        hover?: boolean;
    }>(),
    {
        hover: false,
    },
);
</script>

<template>
    <div :class="cn(
        'glass-panel rounded-3xl p-6',
        hover && 'hover-lift cursor-default',
        props.class,
    )
        ">
        <slot />
    </div>
</template>
