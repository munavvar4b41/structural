<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import ProjectRequirementController from '@/actions/App/Http/Controllers/Admin/ProjectRequirementController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import RequirementRichTextEditor from '@/components/RequirementRichTextEditor.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { emptyTipTapDocumentJson } from '@/lib/tiptapDocument';
import {
    create as requirementsCreate,
    index as requirementsIndex,
} from '@/routes/admin/projects/requirements/index';
import { edit as projectsEdit, index as projectsIndex } from '@/routes/admin/projects/index';

type ProjectSummary = {
    id: number;
    name: string;
    code: string | null;
};

type AssignableUser = {
    id: number;
    name: string;
    email: string;
};

const props = defineProps<{
    project: ProjectSummary;
    canManageProject: boolean;
    assignable_responsibles: AssignableUser[];
}>();

const descriptionJson = ref(emptyTipTapDocumentJson());

defineOptions({
    layout: (pageProps: {
        project: ProjectSummary;
        canManageProject: boolean;
    }) => ({
        breadcrumbs: [
            { title: 'Projects', href: projectsIndex.url() },
            {
                title: pageProps.project.name,
                href: pageProps.canManageProject
                    ? projectsEdit.url(pageProps.project.id)
                    : requirementsIndex.url(pageProps.project.id),
            },
            { title: 'Requirements', href: requirementsIndex.url(pageProps.project.id) },
            {
                title: 'Add requirement',
                href: requirementsCreate.url(pageProps.project.id),
            },
        ],
    }),
});
</script>

<template>
    <Head :title="`Add requirement · ${project.name}`" />

    <div class="flex flex-col gap-8">
        <Heading
            title="Add requirement"
            :description="`For project ${project.name}`"
        />

        <Form
            v-bind="ProjectRequirementController.store.form({ project: project.id })"
            class="flex max-w-2xl flex-col gap-8"
            v-slot="{ errors, processing, recentlySuccessful }"
        >
            <Card>
                <CardHeader>
                    <CardTitle>Requirement</CardTitle>
                    <CardDescription>Title and optional rich-text details for your team</CardDescription>
                </CardHeader>
                <CardContent class="grid gap-6">
                    <div class="grid gap-2">
                        <Label for="title">Title</Label>
                        <Input id="title" name="title" type="text" required />
                        <InputError :message="errors.title" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="requirement-description">Description</Label>
                        <RequirementRichTextEditor
                            id="requirement-description"
                            v-model="descriptionJson"
                            input-name="description"
                        />
                        <InputError :message="errors.description" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="responsible_user_id">Responsible (optional)</Label>
                        <select
                            id="responsible_user_id"
                            name="responsible_user_id"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                        >
                            <option value="">Use default (project lead / first team head)</option>
                            <option v-for="u in assignable_responsibles" :key="u.id" :value="String(u.id)">
                                {{ u.name }} ({{ u.email }})
                            </option>
                        </select>
                        <p class="text-xs text-muted-foreground">
                            Leave blank to use the project lead or the first team head on this project.
                        </p>
                        <InputError :message="errors.responsible_user_id" />
                    </div>
                </CardContent>
            </Card>

            <div class="flex items-center gap-4">
                <Button type="submit" :disabled="processing">Create</Button>
                <Button variant="outline" as-child>
                    <Link :href="requirementsIndex.url(project.id)">Cancel</Link>
                </Button>
                <span
                    v-show="recentlySuccessful"
                    class="text-sm text-muted-foreground"
                >
                    Saved.
                </span>
            </div>
        </Form>
    </div>
</template>
