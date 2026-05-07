<script setup lang="ts">
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
    }>(),
    {
        required: false,
        placeholder: 'Choose…',
        noneLabel: 'None',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const selectModelValue = computed(() => {
    if (props.required) {
        return props.modelValue;
    }

    return props.modelValue === '' ? NONE_SENTINEL : props.modelValue;
});

function onSelectUpdate(v: string | undefined): void {
    const raw = v ?? '';

    if (!props.required && raw === NONE_SENTINEL) {
        emit('update:modelValue', '');

        return;
    }

    emit('update:modelValue', raw);
}
</script>

<template>
    <div class="grid gap-1">
        <Select
            :model-value="selectModelValue"
            @update:model-value="onSelectUpdate"
        >
            <SelectTrigger :id="id" :class="cn('w-full')">
                <SelectValue :placeholder="placeholder" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem v-if="!required" :value="NONE_SENTINEL">
                    {{ noneLabel }}
                </SelectItem>
                <SelectItem v-for="opt in options" :key="opt.value" :value="opt.value">
                    {{ opt.label }}
                </SelectItem>
            </SelectContent>
        </Select>
        <input v-if="required" type="hidden" :name="name" :value="modelValue" />
        <input v-else-if="modelValue !== ''" type="hidden" :name="name" :value="modelValue" />
    </div>
</template>
