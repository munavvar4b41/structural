<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import ProjectRequirementController from '@/actions/App/Http/Controllers/Admin/ProjectRequirementController';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type RequirementPhaseSettings = {
    max_generated_phase: number;
    min_allowed_max: number;
    highest_used_phase: number;
    phase_options: { value: number; label: string }[];
    requires_phase_selection: boolean;
};

const props = defineProps<{
    projectId: number;
    requirementId: number;
    phaseSettings: RequirementPhaseSettings;
    canUpdate: boolean;
}>();

const maxPhase = ref(String(props.phaseSettings.max_generated_phase));

watch(
    () => props.phaseSettings.max_generated_phase,
    (value) => {
        maxPhase.value = String(value);
    },
);
</script>

<template>
    <GlassCard id="phase-settings" class="lg:col-span-12">
        <div class="mb-6 space-y-1">
            <h2 class="text-lg font-semibold">Phases</h2>
            <p class="text-sm text-muted-foreground">
                Tasks and estimation lines for this requirement are grouped into phases.
                Increase the maximum when you need more than one phase.
            </p>
        </div>

        <div class="flex flex-wrap gap-2 text-sm text-muted-foreground">
            <span>Available: Phase 1–{{ phaseSettings.max_generated_phase }}</span>
            <span>·</span>
            <span>Highest in use: Phase {{ phaseSettings.highest_used_phase }}</span>
        </div>

        <Form
            v-if="canUpdate"
            v-bind="ProjectRequirementController.updatePhaseSettings.form({
                project: projectId,
                requirement: requirementId,
            })"
            class="mt-4 flex max-w-sm flex-col gap-3"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="max-generated-phase">Maximum phases</Label>
                <Input
                    id="max-generated-phase"
                    name="max_generated_phase"
                    type="number"
                    max="100"
                    :min="phaseSettings.min_allowed_max"
                    v-model="maxPhase"
                    required
                />
                <p class="text-xs text-muted-foreground">
                    Minimum {{ phaseSettings.min_allowed_max }} (cannot go below the highest phase already assigned).
                </p>
                <InputError :message="errors.max_generated_phase" />
            </div>
            <Button type="submit" class="w-fit" :disabled="processing">
                Save phase settings
            </Button>
        </Form>
    </GlassCard>
</template>
