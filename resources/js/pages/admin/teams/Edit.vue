<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import TeamController from '@/actions/App/Http/Controllers/Admin/TeamController';
import FormField from '@/components/dashboard/FormField.vue';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    edit as teamsEdit,
    index as teamsIndex,
} from '@/routes/admin/teams/index';

type TeamPayload = {
    id: number;
    name: string;
    code: string | null;
    description: string | null;
};

type Props = {
    team: TeamPayload;
};

defineProps<Props>();

defineOptions({
    layout: (pageProps: { team: TeamPayload }) => ({
        breadcrumbs: [
            { title: 'Teams', href: teamsIndex() },
            {
                title: pageProps.team.name,
                href: teamsEdit(pageProps.team.id),
            },
        ],
    }),
});
</script>

<template>
    <Head :title="`Edit ${team.name}`" />

    <div class="flex flex-col gap-8">
        <PageHeader title="Edit team" :description="`Update ${team.name}`" />

        <Form
            v-bind="TeamController.update.form(team.id)"
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
                        <Input
                            id="name"
                            name="name"
                            type="text"
                            required
                            :default-value="team.name"
                        />
                    </FormField>
                    <FormField label="Code" html-for="code" :error="errors.code">
                        <Input
                            id="code"
                            name="code"
                            type="text"
                            :default-value="team.code ?? ''"
                        />
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
                            :default-value="team.description ?? ''"
                            class="w-full rounded-xl border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                        />
                    </FormField>
                </div>
            </GlassCard>

            <div class="flex items-center gap-4">
                <Button type="submit" :disabled="processing">Save</Button>
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
