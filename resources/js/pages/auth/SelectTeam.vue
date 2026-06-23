<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import TeamSelectionController from '@/actions/App/Http/Controllers/TeamSelectionController';
import FormField from '@/components/dashboard/FormField.vue';
import FormSelect from '@/components/FormSelect.vue';
import { Button } from '@/components/ui/button';

type TeamOption = {
    value: number;
    label: string;
};

type Props = {
    teams: TeamOption[];
};

const props = defineProps<Props>();

const primaryTeamId = ref(
    props.teams[0] !== undefined ? String(props.teams[0].value) : '',
);

const teamOptions = computed(() =>
    props.teams.map((t) => ({ value: String(t.value), label: t.label })),
);

defineOptions({
    layout: {
        title: 'Select your team',
        description: 'Choose your primary team to continue',
    },
});
</script>

<template>

    <Head title="Select team" />

    <Form v-bind="TeamSelectionController.store.form()" v-slot="{ errors, processing }" class="flex flex-col gap-6">
        <div class="grid gap-6">
            <FormField label="Primary team" html-for="primary_team_id" :error="errors.primary_team_id" required>
                <FormSelect id="primary_team_id" name="primary_team_id" v-model="primaryTeamId" required
                    placeholder="Select primary team" :options="teamOptions" />
            </FormField>

            <Button type="submit" class="w-full" :disabled="processing">
                Continue
            </Button>
        </div>
    </Form>
</template>
