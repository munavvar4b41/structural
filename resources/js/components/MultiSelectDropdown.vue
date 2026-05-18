<script setup lang="ts">
import { ChevronDown } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        modelValue: string[];
        options: { value: string; label: string }[];
        placeholder?: string;
        menuLabel?: string;
        id?: string;
        class?: string;
        align?: 'start' | 'center' | 'end';
    }>(),
    {
        placeholder: 'Select…',
        menuLabel: 'Options',
        align: 'start',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string[]];
}>();

/** Local copy updates immediately; parent may sync after server round-trip. */
const selected = ref<string[]>([...props.modelValue]);

watch(
    () => props.modelValue,
    (value) => {
        selected.value = [...value];
    },
);

const triggerLabel = computed(() => {
    if (selected.value.length === 0) {
        return props.placeholder;
    }

    if (selected.value.length === 1) {
        const match = props.options.find((o) => o.value === selected.value[0]);

        return match?.label ?? selected.value[0];
    }

    return `${selected.value.length} selected`;
});

function isChecked(value: string): boolean {
    return selected.value.includes(value);
}

function onToggle(value: string, checked: boolean): void {
    const next = [...selected.value];
    const index = next.indexOf(value);

    if (checked && index === -1) {
        next.push(value);
    }

    if (!checked && index !== -1) {
        next.splice(index, 1);
    }

    selected.value = next;
    emit('update:modelValue', next);
}

function clearAll(): void {
    selected.value = [];
    emit('update:modelValue', []);
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button :id="id" type="button" variant="outline" :class="cn(
                'h-11 min-w-[12rem] justify-between gap-2 rounded-xl font-normal',
                props.class,
            )
                ">
                <span class="truncate">{{ triggerLabel }}</span>
                <ChevronDown class="size-4 shrink-0 opacity-50" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent :align="align" class="min-w-[var(--reka-dropdown-menu-trigger-width)]">
            <DropdownMenuLabel class="text-xs font-medium text-muted-foreground">
                {{ menuLabel }}
            </DropdownMenuLabel>
            <DropdownMenuCheckboxItem v-for="opt in options" :key="opt.value" :model-value="isChecked(opt.value)"
                @update:model-value="
                    (value: boolean | 'indeterminate') =>
                        onToggle(opt.value, value === true)
                ">
                {{ opt.label }}
            </DropdownMenuCheckboxItem>
            <template v-if="selected.length > 0">
                <DropdownMenuSeparator />
                <DropdownMenuItem class="text-muted-foreground" @select="clearAll">
                    Clear all
                </DropdownMenuItem>
            </template>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
