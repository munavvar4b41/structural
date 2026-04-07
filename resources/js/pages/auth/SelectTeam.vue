<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import TeamSelectionController from '@/actions/App/Http/Controllers/TeamSelectionController';

type TeamOption = {
    value: number;
    label: string;
};

type Props = {
    teams: TeamOption[];
};

defineOptions({
    layout: {
        title: 'Select your team',
        description: 'Choose your primary team to continue',
    },
});

defineProps<Props>();
</script>

<template>
    <Head title="Select team" />

    <Form
        v-bind="TeamSelectionController.store.form()"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="primary_team_id">Primary team</Label>
                <select
                    id="primary_team_id"
                    name="primary_team_id"
                    required
                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                >
                    <option
                        v-for="opt in teams"
                        :key="opt.value"
                        :value="opt.value"
                    >
                        {{ opt.label }}
                    </option>
                </select>
                <InputError :message="errors.primary_team_id" />
            </div>

            <Button type="submit" class="w-full" :disabled="processing">
                Continue
            </Button>
        </div>
    </Form>
</template>
