<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    class?: HTMLAttributes['class'];
    minWidth?: string;
}>();
</script>

<template>
    <div
        :class="cn('glass-panel overflow-hidden rounded-3xl', props.class)"
    >
        <div class="overflow-x-auto">
            <table
                :class="cn('w-full text-left text-sm', minWidth && `min-w-[${minWidth}]`)"
                :style="minWidth ? { minWidth } : undefined"
            >
                <slot />
            </table>
        </div>
    </div>
</template>
