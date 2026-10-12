<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProjectNoteController from '@/actions/App/Http/Controllers/Admin/ProjectNoteController';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    projectId: number;
    noteId?: number;
    initial?: {
        title: string;
        body: string;
    };
    submitLabel: string;
}>();

const formBinding = computed(() =>
    props.noteId === undefined
        ? ProjectNoteController.store.form({ project: props.projectId })
        : ProjectNoteController.update.form({ project: props.projectId, note: props.noteId }),
);
</script>

<template>
    <Form v-bind="formBinding" class="flex flex-col gap-8" v-slot="{ errors, processing }">
        <GlassCard class="flex flex-col gap-6 p-6">
            <div class="grid gap-2">
                <Label for="note-title">Title</Label>
                <Input id="note-title" name="title" type="text" :default-value="initial?.title ?? ''" required
                    maxlength="255" />
                <InputError :message="errors.title" />
            </div>
            <div class="grid gap-2">
                <Label for="note-body">Note</Label>
                <textarea id="note-body" name="body" rows="12" required maxlength="10000"
                    :value="initial?.body ?? ''"
                    class="w-full rounded-xl border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30" />
                <InputError :message="errors.body" />
            </div>
        </GlassCard>

        <div class="flex gap-2">
            <Button type="submit" :disabled="processing">{{ submitLabel }}</Button>
        </div>
    </Form>
</template>
