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
    create as projectsCreate,
    index as projectsIndex,
} from '@/routes/admin/projects/index';

type TeamOption = {
    value: number;
    label: string;
};

type Props = {
    teams: TeamOption[];
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Projects', href: projectsIndex() },
            { title: 'Add project', href: projectsCreate() },
        ],
    },
});

defineProps<Props>();
</script>

<template>
    <Head title="Add project" />

    <div class="flex flex-col gap-8">
        <Heading title="Add project" description="Create a project and assign teams" />

        <Form
            v-bind="ProjectController.store.form()"
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
                        <Input id="name" name="name" type="text" required />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="code">Code</Label>
                        <Input id="code" name="code" type="text" />
                        <InputError :message="errors.code" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="description">Description</Label>
                        <textarea
                            id="description"
                            name="description"
                            rows="4"
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
                <Button type="submit" :disabled="processing">
                    Create project
                </Button>
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
