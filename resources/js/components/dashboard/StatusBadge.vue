<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

const props = defineProps<{
    status: string;
    label?: string;
}>();

const tone = computed(() => {
    const s = props.status.toLowerCase();

    if (['active', 'approved', 'verified', 'completed', 'done', 'paid'].some((k) => s.includes(k))) {
        return 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border-emerald-500/20';
    }

    if (['pending', 'draft', 'open', 'in_progress', 'processing'].some((k) => s.includes(k))) {
        return 'bg-amber-500/15 text-amber-700 dark:text-amber-400 border-amber-500/20';
    }

    if (['rejected', 'cancelled', 'failed', 'inactive', 'suspended'].some((k) => s.includes(k))) {
        return 'bg-rose-500/15 text-rose-700 dark:text-rose-400 border-rose-500/20';
    }

    return 'bg-zinc-500/10 text-zinc-600 dark:text-zinc-400 border-zinc-500/20';
});
</script>

<template>
    <Badge
        variant="outline"
        :class="cn('rounded-full border px-2.5 py-0.5 font-medium', tone)"
    >
        {{ label ?? status }}
    </Badge>
</template>
