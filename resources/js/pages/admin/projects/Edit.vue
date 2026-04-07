<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import ProjectController from '@/actions/App/Http/Controllers/Admin/ProjectController';
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
    edit as projectsEdit,
    index as projectsIndex,
} from '@/routes/admin/projects/index';

type TeamOption = {
    value: number;
    label: string;
};

type ProjectPayload = {
    id: number;
    name: string;
    code: string | null;
    description: string | null;
    team_ids: number[];
};

type Props = {
    project: ProjectPayload;
    teams: TeamOption[];
};

defineProps<Props>();

defineOptions({
    layout: (pageProps: { project: ProjectPayload }) => ({
        breadcrumbs: [
            { title: 'Projects', href: projectsIndex() },
            {
                title: pageProps.project.name,
                href: projectsEdit(pageProps.project.id),
            },
        ],
    }),
});
</script>

<template>
    <Head :title="`Edit ${project.name}`" />

    <div class="flex flex-col gap-8">
        <Heading title="Edit project" :description="`Update ${project.name}`" />

        <Form
            v-bind="ProjectController.update.form(project.id)"
            class="flex max-w-xl flex-col gap-8"
            v-slot="{ errors, processing, recentlySuccessful }"
        >
            <Card>
                <CardHeader>
                    <CardTitle>Project details</CardTitle>
                    <CardDescription>
                        Name, optional code, description, and assigned teams
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-6">
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            type="text"
                            required
                            :default-value="project.name"
                        />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="code">Code</Label>
                        <Input
                            id="code"
                            name="code"
                            type="text"
                            :default-value="project.code ?? ''"
                        />
                        <InputError :message="errors.code" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="description">Description</Label>
                        <textarea
                            id="description"
                            name="description"
                            rows="4"
                            :default-value="project.description ?? ''"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                        />
                        <InputError :message="errors.description" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="team_ids">Assigned teams</Label>
                        <select
                            id="team_ids"
                            name="team_ids[]"
                            multiple
                            required
                            class="min-h-28 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                        >
                            <option
                                v-for="opt in teams"
                                :key="opt.value"
                                :value="opt.value"
                                :selected="project.team_ids.includes(opt.value)"
                            >
                                {{ opt.label }}
                            </option>
                        </select>
                        <p class="text-xs text-muted-foreground">
                            Hold Ctrl/Cmd to select multiple teams.
                        </p>
                        <InputError :message="errors.team_ids" />
                    </div>
                </CardContent>
            </Card>

            <div class="flex items-center gap-4">
                <Button type="submit" :disabled="processing">Save</Button>
                <Button variant="outline" as-child>
                    <Link :href="projectsIndex()">Cancel</Link>
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
