<script setup lang="ts">
type Props = {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
    /** When true, long titles get a single-line ellipsis in constrained layouts. */
    titleTruncate?: boolean;
    /** When true, title shows at most two lines with ellipsis (overrides single-line truncate). */
    titleLineClamp?: boolean;
};

withDefaults(defineProps<Props>(), {
    variant: 'default',
    titleTruncate: false,
    titleLineClamp: false,
});
</script>

<template>
    <header
        :class="[
            variant === 'small' ? '' : 'mb-8 space-y-0.5',
            titleTruncate || titleLineClamp ? 'min-w-0 max-w-full' : '',
        ]"
    >
        <h2
            :class="[
                variant === 'small'
                    ? 'mb-0.5 text-base font-medium'
                    : 'text-2xl font-semibold tracking-tight sm:text-3xl',
                titleLineClamp ? 'line-clamp-2 min-w-0 break-words' : '',
                titleTruncate && !titleLineClamp ? 'min-w-0 truncate' : '',
            ]"
        >
            {{ title }}
        </h2>
        <p v-if="description" class="text-sm text-muted-foreground">
            {{ description }}
        </p>
    </header>
</template>
