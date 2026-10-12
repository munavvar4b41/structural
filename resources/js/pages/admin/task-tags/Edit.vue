<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import TaskPriorityController from '@/actions/App/Http/Controllers/Admin/TaskPriorityController';
import FormField from '@/components/dashboard/FormField.vue';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import FormSelect from '@/components/FormSelect.vue';
import TaskPriorityBadge from '@/components/tasks/TaskPriorityBadge.vue';
import type { TaskPriorityColor, TaskPriorityShade } from '@/components/tasks/TaskPriorityBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    edit as taskPrioritiesEdit,
    index as taskPrioritiesIndex,
} from '@/routes/admin/task-priorities/index';

type PriorityPayload = {
    id: number;
    name: string;
    color: TaskPriorityColor;
    shade: TaskPriorityShade;
    sort_order: number;
};

type ColorOption = {
    value: TaskPriorityColor;
    label: string;
};

const props = defineProps<{
    priority: PriorityPayload;
    color_options: ColorOption[];
    shade_options: { value: TaskPriorityShade; label: string }[];
}>();

const name = ref(props.priority.name);
const color = ref<TaskPriorityColor>(props.priority.color);
const shade = ref<TaskPriorityShade>(props.priority.shade);
const sortOrder = ref(String(props.priority.sort_order));

defineOptions({
    layout: (pageProps: { priority: PriorityPayload }) => ({
        breadcrumbs: [
            { title: 'Task priorities', href: taskPrioritiesIndex() },
            {
                title: pageProps.priority.name,
                href: taskPrioritiesEdit(pageProps.priority.id),
            },
        ],
    }),
});
</script>

<template>
    <Head :title="`Edit ${priority.name}`" />

    <div class="flex flex-col gap-8">
        <PageHeader title="Edit priority" :description="`Update ${priority.name}`" />

        <Form
            v-bind="TaskPriorityController.update.form(priority.id)"
            class="flex max-w-xl flex-col gap-8"
            v-slot="{ errors, processing }"
        >
            <GlassCard class="p-6">
                <div class="mb-6 space-y-1">
                    <h2 class="text-lg font-semibold">Priority details</h2>
                    <p class="text-sm text-muted-foreground">
                        Name, an allowed color, and the order it appears in filters
                    </p>
                </div>
                <div class="grid gap-6">
                    <FormField label="Name" html-for="name" :error="errors.name" required>
                        <Input id="name" name="name" type="text" required v-model="name" />
                    </FormField>
                    <FormField label="Color" html-for="color" :error="errors.color" required>
                        <FormSelect
                            id="color"
                            name="color"
                            v-model="color"
                            :options="color_options"
                            required
                            placeholder="Select color"
                        />
                    </FormField>
                    <FormField label="Style" html-for="shade" :error="errors.shade" required>
                        <FormSelect
                            id="shade"
                            name="shade"
                            v-model="shade"
                            :options="shade_options"
                            required
                            placeholder="Select style"
                        />
                    </FormField>
                    <FormField label="Sort order" html-for="sort_order" :error="errors.sort_order" required>
                        <Input
                            id="sort_order"
                            name="sort_order"
                            type="number"
                            min="0"
                            required
                            v-model="sortOrder"
                        />
                    </FormField>
                    <div v-if="name.trim() !== ''" class="flex items-center gap-3">
                        <span class="text-sm text-muted-foreground">Preview</span>
                        <TaskPriorityBadge :priority="{ id: priority.id, name: name.trim(), color, shade }" />
                    </div>
                </div>
            </GlassCard>

            <div class="flex items-center gap-4">
                <Button type="submit" :disabled="processing">Save</Button>
                <Button variant="outline" as-child>
                    <Link :href="taskPrioritiesIndex()">Cancel</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
