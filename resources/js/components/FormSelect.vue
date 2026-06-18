<script setup lang="ts">
import type { AcceptableValue } from 'reka-ui';
import { computed } from 'vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

/** Reka Select reserves empty string for clearing; SelectItem values must be non-empty. */
const NONE_SENTINEL = '__task_form_unset__';

const props = withDefaults(
    defineProps<{
        id: string;
        name: string;
        modelValue: string;
        options: { value: string; label: string }[];
        required?: boolean;
        placeholder?: string;
        noneLabel?: string;
        /** When true, the trigger is non-interactive (matches native disabled select). */
        disabled?: boolean;
        /**
         * When true, hidden inputs are disabled so the field is omitted from form submission
         * (same as a disabled native control).
         */
        excludeFromSubmit?: boolean;
    }>(),
    {
        required: false,
        placeholder: 'Choose…',
        noneLabel: 'None',
        disabled: false,
        excludeFromSubmit: false,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

/** Reka UI rejects SelectItem with value=""; "clear" uses noneLabel + NONE_SENTINEL instead. */
const itemOptions = computed(() =>
    props.options.filter((opt) => opt.value !== ''),
);

const selectModelValue = computed(() => {
    if (props.required) {
        return props.modelValue;
    }

    return props.modelValue === '' ? NONE_SENTINEL : props.modelValue;
});

function onSelectUpdate(v: AcceptableValue): void {
    const raw = v === null || v === undefined ? '' : String(v);

    if (!props.required && raw === NONE_SENTINEL) {
        emit('update:modelValue', '');

        return;
    }

    emit('update:modelValue', raw);
}
</script>

<template>
    <div class="grid gap-1">
        <Select :model-value="selectModelValue" :disabled="disabled" @update:model-value="onSelectUpdate">
            <SelectTrigger :id="id" :class="cn('w-full')">
                <SelectValue :placeholder="placeholder" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem v-if="!required" :value="NONE_SENTINEL">
                    {{ noneLabel }}
                </SelectItem>
                <SelectItem
                    v-for="opt in itemOptions"
                    :key="opt.value"
                    :value="opt.value"
                >
                    {{ opt.label }}
                </SelectItem>
            </SelectContent>
        </Select>
        <input v-if="required" type="hidden" :name="name" :value="modelValue" :disabled="excludeFromSubmit" />
        <input v-else-if="modelValue !== ''" type="hidden" :name="name" :value="modelValue"
            :disabled="excludeFromSubmit" />
    </div>
</template>
