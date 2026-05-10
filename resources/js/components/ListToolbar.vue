<script setup lang="ts">
import { Search, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        /** Current search value from server / parent state */
        modelValue: string;
        debounceMs?: number;
        placeholder?: string;
        inputClass?: string;
    }>(),
    {
        debounceMs: 300,
        placeholder: 'Search…',
        inputClass: '',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const localSearch = ref(props.modelValue);

watch(
    () => props.modelValue,
    (v) => {
        localSearch.value = v;
    },
);

let debounceTimer: ReturnType<typeof setTimeout> | undefined;

watch(localSearch, (v) => {
    window.clearTimeout(debounceTimer);
    debounceTimer = window.setTimeout(() => {
        emit('update:modelValue', v);
    }, props.debounceMs);
});

function clearSearch(): void {
    localSearch.value = '';
}
</script>

<template>
    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
        <div class="flex min-w-0 flex-1 flex-col gap-3 sm:flex-row sm:items-center">
            <div :class="cn('relative min-w-[12rem] flex-1 sm:max-w-sm', props.inputClass)">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <Input
                    v-model="localSearch"
                    type="search"
                    :placeholder="placeholder"
                    class="h-9 pl-9 pr-9"
                    autocomplete="off"
                    aria-label="Search"
                />
                <Button
                    v-if="localSearch !== ''"
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="absolute top-1/2 right-1 size-7 -translate-y-1/2 text-muted-foreground"
                    aria-label="Clear search"
                    @click="clearSearch"
                >
                    <X class="size-4" />
                </Button>
            </div>
            <div v-if="$slots.filters" class="flex flex-wrap items-center gap-2">
                <slot name="filters" />
            </div>
        </div>
        <div v-if="$slots.actions" class="flex shrink-0 flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
