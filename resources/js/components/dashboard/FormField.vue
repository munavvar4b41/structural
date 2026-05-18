<script setup lang="ts">
import { Label } from '@/components/ui/label';

defineProps<{
    label?: string;
    htmlFor?: string;
    error?: string;
    hint?: string;
    required?: boolean;
}>();
</script>

<template>
    <div class="grid gap-2">
        <Label
            v-if="label"
            :for="htmlFor"
            class="text-sm font-medium text-foreground"
        >
            {{ label }}
            <span v-if="required" class="text-destructive">*</span>
        </Label>
        <slot />
        <p v-if="hint && !error" class="text-xs text-muted-foreground">
            {{ hint }}
        </p>
        <p
            v-if="error"
            class="text-sm text-destructive"
            role="alert"
        >
            {{ error }}
        </p>
    </div>
</template>
