<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <th :class="cn(
        'px-5 py-3.5 text-xs font-medium uppercase tracking-wide text-muted-foreground',
        props.class,
    )
        ">
        <slot />
    </th>
</template>
