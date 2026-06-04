<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

export type SuggestionType =
    | 'tag'
    | 'metadata_key'
    | 'metadata_value'
    | 'requirement_title'
    | 'task_title'
    | 'time_entry_notes';

type Props = {
    modelValue?: string;
    type: SuggestionType;
    metadataKey?: string;
    debounceMs?: number;
    id?: string;
    name?: string;
    placeholder?: string;
    required?: boolean;
    disabled?: boolean;
    class?: string;
};

const props = withDefaults(defineProps<Props>(), {
    modelValue: '',
    debounceMs: 300,
    metadataKey: undefined,
    id: undefined,
    name: undefined,
    placeholder: undefined,
    required: false,
    disabled: false,
    class: undefined,
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
    blur: [];
}>();

const http = useHttp();
const containerRef = ref<HTMLElement | null>(null);
const portalTarget = ref<HTMLElement | null>(null);
const suggestions = ref<string[]>([]);
const isOpen = ref(false);
const activeIndex = ref(-1);
const dropdownStyle = ref({ top: '0px', left: '0px', width: '0px' });
const listboxId = computed(() => (props.id !== undefined ? `${props.id}-suggestions` : undefined));

let debounceTimer: ReturnType<typeof setTimeout> | null = null;
let closeTimer: ReturnType<typeof setTimeout> | null = null;
let fetchGeneration = 0;

function resolvePortalTarget(): void {
    portalTarget.value =
        containerRef.value?.closest<HTMLElement>('[data-slot=dialog-content]') ?? null;
}

function closeSuggestions(): void {
    isOpen.value = false;
    activeIndex.value = -1;
}

function updateDropdownPosition(): void {
    const element = containerRef.value;

    if (element === null) {
        return;
    }

    const inputRect = element.getBoundingClientRect();
    const portal = portalTarget.value;

    if (portal !== null) {
        const portalRect = portal.getBoundingClientRect();

        dropdownStyle.value = {
            top: `${inputRect.bottom - portalRect.top + 4}px`,
            left: `${inputRect.left - portalRect.left}px`,
            width: `${inputRect.width}px`,
        };

        return;
    }

    dropdownStyle.value = {
        top: `${inputRect.bottom + 4}px`,
        left: `${inputRect.left}px`,
        width: `${inputRect.width}px`,
    };
}

function buildSuggestionsUrl(query: string): string {
    const params = new URLSearchParams({
        type: props.type,
        q: query,
    });

    const metadataKey = props.metadataKey?.trim();

    if (metadataKey !== undefined && metadataKey !== '') {
        params.set('key', metadataKey);
    }

    return `/admin/suggestions?${params.toString()}`;
}

function selectSuggestion(value: string): void {
    if (closeTimer !== null) {
        clearTimeout(closeTimer);
        closeTimer = null;
    }

    emit('update:modelValue', value);
    closeSuggestions();
}

function onDropdownMouseDown(event: MouseEvent): void {
    event.preventDefault();
}

async function fetchSuggestions(query: string): Promise<void> {
    const trimmed = query.trim();

    if (trimmed === '') {
        suggestions.value = [];
        closeSuggestions();

        return;
    }

    const generation = ++fetchGeneration;

    try {
        const response = (await http.get(buildSuggestionsUrl(trimmed))) as {
            suggestions?: string[];
        };

        if (generation !== fetchGeneration) {
            return;
        }

        resolvePortalTarget();
        suggestions.value = response.suggestions ?? [];
        updateDropdownPosition();
        isOpen.value = suggestions.value.length > 0;
        activeIndex.value = suggestions.value.length > 0 ? 0 : -1;
    } catch {
        if (generation !== fetchGeneration) {
            return;
        }

        suggestions.value = [];
        closeSuggestions();
    }
}

function scheduleFetch(query: string): void {
    if (debounceTimer !== null) {
        clearTimeout(debounceTimer);
    }

    debounceTimer = setTimeout(() => {
        void fetchSuggestions(query);
    }, props.debounceMs);
}

function onFocus(): void {
    resolvePortalTarget();

    if (props.modelValue.trim() !== '' && suggestions.value.length > 0) {
        updateDropdownPosition();
        isOpen.value = true;
    } else if (props.modelValue.trim() !== '') {
        scheduleFetch(props.modelValue);
    }
}

function onBlur(): void {
    if (closeTimer !== null) {
        clearTimeout(closeTimer);
    }

    closeTimer = window.setTimeout(() => {
        closeSuggestions();
        closeTimer = null;
    }, 150);

    emit('blur');
}

function onKeydown(event: KeyboardEvent): void {
    if (!isOpen.value || suggestions.value.length === 0) {
        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        activeIndex.value = (activeIndex.value + 1) % suggestions.value.length;
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        activeIndex.value =
            activeIndex.value <= 0 ? suggestions.value.length - 1 : activeIndex.value - 1;
    } else if (event.key === 'Enter' && activeIndex.value >= 0) {
        event.preventDefault();
        const selected = suggestions.value[activeIndex.value];

        if (selected !== undefined) {
            selectSuggestion(selected);
        }
    } else if (event.key === 'Escape') {
        closeSuggestions();
    }
}

watch(
    () => props.metadataKey,
    () => {
        if (props.modelValue.trim() !== '') {
            scheduleFetch(props.modelValue);
        }
    },
);

watch(isOpen, (open) => {
    if (open) {
        resolvePortalTarget();
        updateDropdownPosition();
    }
});

onBeforeUnmount(() => {
    if (debounceTimer !== null) {
        clearTimeout(debounceTimer);
    }

    if (closeTimer !== null) {
        clearTimeout(closeTimer);
    }
});
</script>

<template>
    <div ref="containerRef" class="relative overflow-visible">
        <Input
            :id="id"
            :name="name"
            :model-value="modelValue"
            :placeholder="placeholder"
            :required="required"
            :disabled="disabled"
            autocomplete="off"
            role="combobox"
            :aria-expanded="isOpen"
            :aria-controls="listboxId"
            :class="cn(props.class)"
            @update:model-value="
                (value) => {
                    emit('update:modelValue', value);
                    scheduleFetch(String(value));
                }
            "
            @focus="onFocus"
            @blur="onBlur"
            @keydown="onKeydown"
        />

        <Teleport v-if="portalTarget !== null" :to="portalTarget">
            <ul
                v-if="isOpen && suggestions.length > 0"
                :id="listboxId"
                role="listbox"
                class="absolute z-[200] max-h-48 overflow-auto rounded-xl border border-border bg-popover py-1 text-popover-foreground shadow-md"
                :style="dropdownStyle"
                @mousedown="onDropdownMouseDown"
            >
                <li
                    v-for="(suggestion, index) in suggestions"
                    :key="`${suggestion}-${index}`"
                    role="option"
                    :aria-selected="index === activeIndex"
                    class="cursor-pointer px-3 py-2 text-sm"
                    :class="index === activeIndex ? 'bg-accent text-accent-foreground' : ''"
                    @click="selectSuggestion(suggestion)"
                >
                    {{ suggestion }}
                </li>
            </ul>
        </Teleport>

        <ul
            v-else-if="isOpen && suggestions.length > 0"
            :id="listboxId"
            role="listbox"
            class="absolute top-full z-[200] mt-1 max-h-48 w-full overflow-auto rounded-xl border border-border bg-popover py-1 text-popover-foreground shadow-md"
            @mousedown="onDropdownMouseDown"
        >
            <li
                v-for="(suggestion, index) in suggestions"
                :key="`${suggestion}-${index}`"
                role="option"
                :aria-selected="index === activeIndex"
                class="cursor-pointer px-3 py-2 text-sm"
                :class="index === activeIndex ? 'bg-accent text-accent-foreground' : ''"
                @click="selectSuggestion(suggestion)"
            >
                {{ suggestion }}
            </li>
        </ul>
    </div>
</template>
