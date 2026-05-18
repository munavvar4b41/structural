<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import TeamController from '@/actions/App/Http/Controllers/Admin/TeamController';
import FormField from '@/components/dashboard/FormField.vue';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
        <PageHeader
            title="Add team"
            description="Create a team for your company"
        />

        <Form
            v-bind="TeamController.store.form()"
            class="flex max-w-xl flex-col gap-8"
            v-slot="{ errors, processing, recentlySuccessful }"
        >
            <GlassCard class="p-6">
                <div class="mb-6 space-y-1">
                    <h2 class="text-lg font-semibold">Team details</h2>
                    <p class="text-sm text-muted-foreground">
                        Name, optional code, and an internal description
                    </p>
                </div>
                <div class="grid gap-6">
                    <FormField
                        label="Name"
                        html-for="name"
                        :error="errors.name"
                        required
                    >
                        <Input id="name" name="name" type="text" required />
                    </FormField>
                    <FormField label="Code" html-for="code" :error="errors.code">
                        <Input id="code" name="code" type="text" />
                    </FormField>
                    <FormField
                        label="Description"
                        html-for="description"
                        :error="errors.description"
                    >
                        <textarea
                            id="description"
                            name="description"
                            rows="4"
                            class="w-full rounded-xl border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                        />
                    </FormField>
                </div>
            </GlassCard>

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
