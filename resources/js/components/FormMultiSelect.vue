<script setup lang="ts">
import { ChevronDown } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
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
        id?: string;
        name?: string;
        modelValue: string[];
        options: { value: string; label: string }[];
        placeholder?: string;
        menuLabel?: string;
        disabledOptions?: string[];
        class?: string;
        align?: 'start' | 'center' | 'end';
        excludeFromSubmit?: boolean;
    }>(),
    {
        placeholder: 'Select…',
        menuLabel: 'Options',
        align: 'start',
        disabledOptions: () => [],
        excludeFromSubmit: false,
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

function isDisabled(value: string): boolean {
    return props.disabledOptions.includes(value);
}

function onToggle(value: string, checked: boolean): void {
    if (isDisabled(value) && !checked) {
        return;
    }

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
    const next = selected.value.filter((value) => isDisabled(value));

    selected.value = next;
    emit('update:modelValue', next);
}
</script>

<template>
    <div class="grid gap-1">
        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <button :id="id" type="button" :class="cn(
                    'border-input data-[placeholder]:text-muted-foreground [&_svg:not([class*=\'text-\'])]:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:bg-input/30 dark:hover:bg-input/50 flex h-9 w-full max-w-full min-w-0 items-center justify-between gap-2 overflow-hidden rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*=\'size-\'])]:size-4',
                    props.class,
                )">
                    <span class="min-w-0 flex-1 truncate text-left">{{ triggerLabel }}</span>
                    <ChevronDown class="size-4 opacity-50" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent :align="align" :collision-padding="8"
                class="max-h-(--reka-dropdown-menu-content-available-height) max-w-(--reka-dropdown-menu-content-available-width) min-w-[var(--reka-dropdown-menu-trigger-width)]">
                <DropdownMenuLabel class="text-xs font-medium text-muted-foreground">
                    {{ menuLabel }}
                </DropdownMenuLabel>
                <DropdownMenuCheckboxItem v-for="opt in options" :key="opt.value" :disabled="isDisabled(opt.value)"
                    :model-value="isChecked(opt.value)" @update:model-value="
                        (value: boolean | 'indeterminate') =>
                            onToggle(opt.value, value === true)
                    ">
                    {{ opt.label }}
                    <slot name="option-suffix" :option="opt" />
                </DropdownMenuCheckboxItem>
                <template v-if="selected.length > 0">
                    <DropdownMenuSeparator />
                    <DropdownMenuItem class="text-muted-foreground" @select="clearAll">
                        Clear all
                    </DropdownMenuItem>
                </template>
            </DropdownMenuContent>
        </DropdownMenu>
        <template v-if="name !== undefined">
            <input v-for="value in modelValue" :key="value" type="hidden" :name="`${name}[]`" :value="value"
                :disabled="excludeFromSubmit" />
        </template>
    </div>
</template>
