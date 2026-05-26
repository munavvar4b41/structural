<script setup lang="ts">
import { ChevronDown, ChevronRight } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

withDefaults(defineProps<{
    label: string;
    shown: number;
    total: number;
    collapsible?: boolean;
    collapsed: boolean;
    sectionId: string;
}>(), {
    collapsible: false,
});

const emit = defineEmits<{
    toggle: [];
}>();
</script>

<template>
    <div class="flex items-center justify-between gap-2">
        <div class="flex min-w-0 flex-1 items-center gap-1">
            <Button type="button" variant="ghost" size="sm" class="size-7 shrink-0 p-0" :aria-expanded="!collapsed"
                :aria-controls="sectionId" @click="emit('toggle')" v-if="collapsible">
                <ChevronDown v-if="!collapsed" class="size-4" />
                <ChevronRight v-else class="size-4" />
                <span class="sr-only">
                    {{ collapsed ? 'Expand' : 'Collapse' }} {{ label }}
                </span>
            </Button>
            <h2 class="truncate text-sm font-semibold">{{ label }}</h2>
        </div>
        <span class="shrink-0 text-xs text-muted-foreground tabular-nums">
            {{ shown }} / {{ total }}
        </span>
    </div>
</template>
