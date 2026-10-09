<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import DashboardTaskSettingsController from '@/actions/App/Http/Controllers/Settings/DashboardTaskSettingsController';
import FormField from '@/components/dashboard/FormField.vue';
import FormMultiSelect from '@/components/FormMultiSelect.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { edit } from '@/routes/dashboard-tasks';

type Option = { value: string; label: string };

const props = defineProps<{
    preferences: {
        enabled: boolean;
        statuses: string[];
        priorities: string[];
        project_ids: string[];
    };
    status_options: Option[];
    priority_options: Option[];
    project_options: Option[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard settings',
                href: edit(),
            },
        ],
    },
});

const enabled = ref(props.preferences.enabled);
const statuses = ref<string[]>([...props.preferences.statuses]);
const priorities = ref<string[]>([...props.preferences.priorities]);
const projectIds = ref<string[]>([...props.preferences.project_ids]);

function fieldError(
    errors: Record<string, string>,
    key: string,
): string | undefined {
    return errors[key] ?? errors[`${key}.0`];
}
</script>

<template>
    <Head title="Dashboard settings" />

    <h1 class="sr-only">Dashboard settings</h1>

    <div class="flex flex-col gap-6">
        <div class="space-y-1">
            <h2 class="text-lg font-semibold">Dashboard tasks</h2>
            <p class="text-sm text-muted-foreground">
                Choose which of your assigned tasks appear on the dashboard.
                Leave a field empty to include every value.
            </p>
        </div>

        <Form
            v-bind="DashboardTaskSettingsController.update.form()"
            class="flex flex-col gap-6"
            v-slot="{ errors, processing, recentlySuccessful }"
        >
            <div class="flex items-center justify-between gap-4">
                <div class="space-y-1">
                    <Label for="dashboard-tasks-enabled"
                        >Show tasks on the dashboard</Label
                    >
                    <p class="text-sm text-muted-foreground">
                        Turn this off to hide tasks. Your filters stay saved.
                    </p>
                </div>
                <Switch id="dashboard-tasks-enabled" v-model="enabled" />
            </div>
            <input type="hidden" name="enabled" :value="enabled ? '1' : '0'" />

            <FormField
                label="Status"
                html-for="dashboard-task-statuses"
                :error="fieldError(errors, 'statuses')"
                hint="Leave empty to include every status."
            >
                <FormMultiSelect
                    id="dashboard-task-statuses"
                    name="statuses"
                    v-model="statuses"
                    :options="status_options"
                    placeholder="All statuses"
                    menu-label="Statuses"
                />
            </FormField>

            <FormField
                label="Priority"
                html-for="dashboard-task-priorities"
                :error="fieldError(errors, 'priorities')"
                hint="Leave empty to include every priority."
            >
                <FormMultiSelect
                    id="dashboard-task-priorities"
                    name="priorities"
                    v-model="priorities"
                    :options="priority_options"
                    placeholder="All priorities"
                    menu-label="Priorities"
                />
            </FormField>

            <FormField
                label="Project"
                html-for="dashboard-task-projects"
                :error="fieldError(errors, 'project_ids')"
                hint="Leave empty to include every project you can see."
            >
                <FormMultiSelect
                    id="dashboard-task-projects"
                    name="project_ids"
                    v-model="projectIds"
                    :options="project_options"
                    placeholder="All projects"
                    menu-label="Projects"
                />
            </FormField>

            <div class="flex items-center gap-4">
                <Button
                    :disabled="processing"
                    data-test="update-dashboard-tasks-button"
                >
                    Save
                </Button>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-show="recentlySuccessful"
                        class="text-sm text-muted-foreground"
                    >
                        Saved.
                    </p>
                </Transition>
            </div>
        </Form>
    </div>
</template>
