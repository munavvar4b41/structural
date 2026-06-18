<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        class?: HTMLAttributes['class'];
        minWidth?: string;
        responsive?: boolean;
    }>(),
    {
        responsive: true,
    },
);
</script>

<template>
    <div :class="cn('data-table glass-panel overflow-hidden rounded-3xl', props.class)">
        <div :class="cn(
            props.responsive ? 'md:overflow-x-auto' : 'overflow-x-auto',
        )
            ">
            <table :data-responsive-table="props.responsive ? '' : undefined" :class="cn(
                'w-full text-left text-sm',
                props.responsive && 'data-table-responsive',
            )
                " :style="minWidth
                    ? {
                        '--data-table-min-width': minWidth,
                    }
                    : undefined
                    ">
                <slot />
            </table>
        </div>
    </div>
</template>
