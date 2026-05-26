<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    label: string;
    class?: HTMLAttributes['class'];
    colspan?: number;
}>();
</script>

<template>
    <td :colspan="colspan" :data-label="label" :class="cn('px-5 py-3.5', props.class)">
        <slot />
    </td>
</template>
