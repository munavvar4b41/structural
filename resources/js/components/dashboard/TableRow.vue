<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <tr :class="cn('border-b border-border/40 transition-colors bg-muted/15 pt-3 md:even:bg-muted/15 md:pt-0 hover:bg-muted/30', props.class)">
        <slot />
    </tr>
</template>