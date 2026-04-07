<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import TeamController from '@/actions/App/Http/Controllers/Admin/TeamController';
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
    create as teamsCreate,
    index as teamsIndex,
} from '@/routes/admin/teams/index';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Teams', href: teamsIndex() },
            { title: 'Add team', href: teamsCreate() },
        ],
    },
});
</script>

<template>
    <Head title="Add team" />

    <div class="flex flex-col gap-8">
        <Heading title="Add team" description="Create a team for your company" />

        <Form
            v-bind="TeamController.store.form()"
            class="flex max-w-xl flex-col gap-8"
            v-slot="{ errors, processing, recentlySuccessful }"
        >
            <Card>
                <CardHeader>
                    <CardTitle>Team details</CardTitle>
                    <CardDescription>
                        Name, optional code, and an internal description
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
                </CardContent>
            </Card>

            <div class="flex items-center gap-4">
                <Button type="submit" :disabled="processing">Create team</Button>
                <Button variant="outline" as-child>
                    <Link :href="teamsIndex()">Cancel</Link>
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
