<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import ProjectRequirementController from '@/actions/App/Http/Controllers/Admin/ProjectRequirementController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
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
import {
    edit as requirementsEdit,
    index as requirementsIndex,
} from '@/routes/admin/projects/requirements/index';
import { edit as projectsEdit, index as projectsIndex } from '@/routes/admin/projects/index';

type UserBrief = {
    id: number;
    name: string;
    email: string;
} | null;

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

type RequirementForm = {
    id: number;
    title: string;
    description: string | null;
    reviewer_user_id: number | null;
    responsible_user_id: number | null;
    reviewed_at: string | null;
    creator: UserBrief;
};

const props = defineProps<{
    project: ProjectSummary;
    requirement: RequirementForm;
    assignable_staff: AssignableUser[];
    assignable_responsibles: AssignableUser[];
    can_update_content: boolean;
    can_update_assignments: boolean;
    can_mark_reviewed: boolean;
    can_manage_project: boolean;
}>();

defineOptions({
    layout: (pageProps: {
        project: ProjectSummary;
        requirement: RequirementForm;
        can_manage_project: boolean;
    }) => ({
        breadcrumbs: [
            { title: 'Projects', href: projectsIndex.url() },
            {
                title: pageProps.project.name,
                href: pageProps.can_manage_project
                    ? projectsEdit.url(pageProps.project.id)
                    : requirementsIndex.url(pageProps.project.id),
            },
            {
                title: 'Requirements',
                href: requirementsIndex.url(pageProps.project.id),
            },
            {
                title: pageProps.requirement.title,
                href: requirementsEdit.url({
                    project: pageProps.project.id,
                    requirement: pageProps.requirement.id,
                }),
            },
        ],
    }),
});
</script>

<template>
    <Head :title="`Edit requirement · ${project.name}`" />

    <div class="flex flex-col gap-8">
        <Heading title="Edit requirement" :description="`Project ${project.name}`" />

        <Form
            v-bind="
                ProjectRequirementController.update.form({
                    project: project.id,
                    requirement: requirement.id,
                })
            "
            class="flex max-w-xl flex-col gap-8"
            v-slot="{ errors, processing, recentlySuccessful }"
        >
            <template v-if="!can_update_assignments">
                <input
                    type="hidden"
                    name="reviewer_user_id"
                    :value="requirement.reviewer_user_id ?? ''"
                />
                <input
                    type="hidden"
                    name="responsible_user_id"
                    :value="requirement.responsible_user_id ?? ''"
                />
            </template>
            <input
                v-if="!can_mark_reviewed"
                type="hidden"
                name="reviewed_at"
                :value="requirement.reviewed_at ?? ''"
            />

            <Card>
                <CardHeader>
                    <CardTitle>Details</CardTitle>
                    <CardDescription>
                        Created by {{ requirement.creator?.name ?? 'Unknown' }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-6">
                    <div class="grid gap-2">
                        <Label for="title">Title</Label>
                        <Input
                            id="title"
                            name="title"
                            type="text"
                            required
                            :default-value="requirement.title"
                            :readonly="!can_update_content"
                            :class="{ 'opacity-80': !can_update_content }"
                        />
                        <InputError :message="errors.title" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="description">Description</Label>
                        <textarea
                            id="description"
                            name="description"
                            rows="5"
                            :default-value="requirement.description ?? ''"
                            :readonly="!can_update_content"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                            :class="{ 'opacity-80': !can_update_content }"
                        />
                        <InputError :message="errors.description" />
                    </div>
                </CardContent>
            </Card>

            <Card v-if="can_update_assignments">
                <CardHeader>
                    <CardTitle>Responsible</CardTitle>
                    <CardDescription>Who owns triage for this requirement</CardDescription>
                </CardHeader>
                <CardContent class="grid gap-2">
                    <Label for="responsible_user_id">Responsible user</Label>
                    <select
                        id="responsible_user_id"
                        name="responsible_user_id"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                    >
                        <option value="">Unassigned</option>
                        <option
                            v-for="u in assignable_responsibles"
                            :key="u.id"
                            :value="String(u.id)"
                            :selected="u.id === requirement.responsible_user_id"
                        >
                            {{ u.name }} ({{ u.email }})
                        </option>
                    </select>
                    <InputError :message="errors.responsible_user_id" />
                </CardContent>
            </Card>

            <Card v-if="can_update_assignments">
                <CardHeader>
                    <CardTitle>Reviewer</CardTitle>
                    <CardDescription>Assign a staff member on this project to check the requirement</CardDescription>
                </CardHeader>
                <CardContent class="grid gap-2">
                    <Label for="reviewer_user_id">Reviewer (staff)</Label>
                    <select
                        id="reviewer_user_id"
                        name="reviewer_user_id"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                    >
                        <option value="">None</option>
                        <option
                            v-for="u in assignable_staff"
                            :key="u.id"
                            :value="String(u.id)"
                            :selected="u.id === requirement.reviewer_user_id"
                        >
                            {{ u.name }} ({{ u.email }})
                        </option>
                    </select>
                    <p v-if="assignable_staff.length === 0" class="text-xs text-muted-foreground">
                        No staff on this project's teams yet.
                    </p>
                    <InputError :message="errors.reviewer_user_id" />
                </CardContent>
            </Card>

            <Card v-if="can_mark_reviewed">
                <CardHeader>
                    <CardTitle>Review</CardTitle>
                    <CardDescription>Mark when the requirement has been checked</CardDescription>
                </CardHeader>
                <CardContent class="grid gap-2">
                    <Label for="reviewed_at">Reviewed at</Label>
                    <Input
                        id="reviewed_at"
                        name="reviewed_at"
                        type="datetime-local"
                        :default-value="requirement.reviewed_at ?? ''"
                    />
                    <InputError :message="errors.reviewed_at" />
                </CardContent>
            </Card>

            <div class="flex items-center gap-4">
                <Button type="submit" :disabled="processing">Save</Button>
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
