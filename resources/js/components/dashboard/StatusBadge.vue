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
        return 'bg-success/15 text-success border-success/20';
    }

    if (['pending', 'draft', 'open', 'in_progress', 'processing'].some((k) => s.includes(k))) {
        return 'bg-warning/15 text-warning border-warning/20';
    }

    if (['rejected', 'cancelled', 'failed', 'inactive', 'suspended'].some((k) => s.includes(k))) {
        return 'bg-destructive/15 text-destructive border-destructive/20';
    }

    return 'bg-muted text-muted-foreground border-border';
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
