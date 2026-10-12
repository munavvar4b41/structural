<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import TaskTagController from '@/actions/App/Http/Controllers/Admin/TaskTagController';
import FormField from '@/components/dashboard/FormField.vue';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import FormSelect from '@/components/FormSelect.vue';
import TaskTagBadges from '@/components/tasks/TaskTagBadges.vue';
import type { TaskTagColor, TaskTagShade } from '@/components/tasks/TaskTagBadges.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    edit as taskTagsEdit,
    index as taskTagsIndex,
} from '@/routes/admin/task-tags/index';

type TagPayload = {
    id: number;
    name: string;
    color: TaskTagColor;
    shade: TaskTagShade;
    sort_order: number;
};

type ColorOption = {
    value: TaskTagColor;
    label: string;
};

const props = defineProps<{
    tag: TagPayload;
    color_options: ColorOption[];
    shade_options: { value: TaskTagShade; label: string }[];
}>();

const name = ref(props.tag.name);
const color = ref<TaskTagColor>(props.tag.color);
const shade = ref<TaskTagShade>(props.tag.shade);
const sortOrder = ref(String(props.tag.sort_order));

defineOptions({
    layout: (pageProps: { tag: TagPayload }) => ({
        breadcrumbs: [
            { title: 'Task tags', href: taskTagsIndex() },
            {
                title: pageProps.tag.name,
                href: taskTagsEdit(pageProps.tag.id),
            },
        ],
    }),
});
</script>

<template>
    <Head :title="`Edit ${tag.name}`" />

    <div class="flex flex-col gap-8">
        <PageHeader title="Edit tag" :description="`Update ${tag.name}`" />

        <Form
            v-bind="TaskTagController.update.form(tag.id)"
            class="flex max-w-xl flex-col gap-8"
            v-slot="{ errors, processing }"
        >
            <GlassCard class="p-6">
                <div class="mb-6 space-y-1">
                    <h2 class="text-lg font-semibold">Tag details</h2>
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
                        <TaskTagBadges :tags="[{ id: tag.id, name: name.trim(), color, shade }]" />
                    </div>
                </div>
            </GlassCard>

            <div class="flex items-center gap-4">
                <Button type="submit" :disabled="processing">Save</Button>
                <Button variant="outline" as-child>
                    <Link :href="taskTagsIndex()">Cancel</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
