<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import ProjectController from '@/actions/App/Http/Controllers/Admin/ProjectController';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import FormMultiSelect from '@/components/FormMultiSelect.vue';
import FormSelect from '@/components/FormSelect.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
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

type ClientOption = {
    value: number;
    label: string;
};

type LeadCandidate = {
    value: number;
    label: string;
    team_ids: number[];
};

type ProjectPayload = {
    id: number;
    name: string;
    code: string | null;
    description: string | null;
    client_user_id: number;
    lead_user_id: number | null;
    team_ids: number[];
    estimation_required: boolean;
};

const props = withDefaults(
    defineProps<{
        project: ProjectPayload;
        teams: TeamOption[];
        clients: ClientOption[];
        lead_candidates?: LeadCandidate[];
    }>(),
    { lead_candidates: () => [] },
);

const clientUserId = ref(String(props.project.client_user_id));
const selectedTeamIds = ref<string[]>(props.project.team_ids.map(String));
const leadUserId = ref(
    props.project.lead_user_id !== null ? String(props.project.lead_user_id) : '',
);

const estimationRequired = ref(props.project.estimation_required);

const teamOptions = computed(() =>
    props.teams.map((t) => ({ value: String(t.value), label: t.label })),
);

const clientOptions = computed(() =>
    props.clients.map((c) => ({ value: String(c.value), label: c.label })),
);

const viableLeadCandidates = computed(() =>
    props.lead_candidates.filter((c) =>
        c.team_ids.some((tid) => selectedTeamIds.value.includes(String(tid))),
    ),
);

const leadOptions = computed(() =>
    viableLeadCandidates.value.map((c) => ({
        value: String(c.value),
        label: c.label,
    })),
);

watch(selectedTeamIds, () => {
    if (
        leadUserId.value !== '' &&
        !viableLeadCandidates.value.some((c) => String(c.value) === leadUserId.value)
    ) {
        leadUserId.value = '';
    }
});

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
        <PageHeader title="Edit project" :description="`Update ${project.name}`" />

        <Form v-bind="ProjectController.update.form(project.id)" class="flex max-w-xl flex-col gap-8"
            v-slot="{ errors, processing, recentlySuccessful }">
            <input type="hidden" name="lead_user_id" :value="leadUserId" />
            <input type="hidden" name="estimation_required" :value="estimationRequired ? '1' : '0'" />

            <GlassCard class="p-6">
                <div class="mb-6 space-y-1">
                    <h2 class="text-lg font-semibold">Project details</h2>
                    <p class="text-sm text-muted-foreground">
                        Name, optional code, description, client contact, and assigned teams
                    </p>
                </div>
                <div class="grid gap-6">
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input id="name" name="name" type="text" required :default-value="project.name" />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="code">Code</Label>
                        <Input id="code" name="code" type="text" :default-value="project.code ?? ''" />
                        <InputError :message="errors.code" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="description">Description</Label>
                        <textarea id="description" name="description" rows="4"
                            :default-value="project.description ?? ''"
                            class="w-full rounded-xl border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30" />
                        <InputError :message="errors.description" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="client_user_id">Client contact</Label>
                        <FormSelect id="client_user_id" name="client_user_id" v-model="clientUserId" required
                            placeholder="Select a client user" :options="clientOptions" />
                        <InputError :message="errors.client_user_id" />
                    </div>
                    <div v-if="viableLeadCandidates.length > 0" class="grid gap-2">
                        <Label for="lead_user_id">Project lead</Label>
                        <FormSelect id="lead_user_id" v-model="leadUserId" none-label="First team head (default)"
                            placeholder="First team head (default)" :options="leadOptions" />
                        <p class="text-xs text-muted-foreground">
                            Must be a team head or staff on an assigned team. If cleared, the first
                            team head on those teams becomes the project lead again.
                        </p>
                        <InputError :message="errors.lead_user_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="team_ids">Assigned teams</Label>
                        <FormMultiSelect id="team_ids" name="team_ids" v-model="selectedTeamIds" menu-label="Teams"
                            placeholder="Select teams" :options="teamOptions" />
                        <p class="text-xs text-muted-foreground">
                            Open the menu and tick each team that should work on this project.
                        </p>
                        <InputError :message="errors.team_ids" />
                    </div>
                    <div class="flex gap-3 rounded-lg border border-border p-4">
                        <input id="estimation_required" v-model="estimationRequired" type="checkbox"
                            class="mt-1 size-4 shrink-0 rounded border border-input text-primary focus-visible:ring-[3px] focus-visible:ring-ring/50" />
                        <div class="grid gap-1">
                            <Label for="estimation_required" class="cursor-pointer font-medium">
                                Require time estimate for every task
                            </Label>
                            <p class="text-sm text-muted-foreground">
                                When enabled, new and updated tasks must include an estimate
                                (minutes) to complete the work.
                            </p>
                            <InputError :message="errors.estimation_required" />
                        </div>
                    </div>
                </div>
            </GlassCard>

            <div class="flex items-center gap-4">
                <Button type="submit" :disabled="processing">Save</Button>
                <Button variant="outline" as-child>
                    <Link :href="projectsIndex()">Cancel</Link>
                </Button>
                <span v-show="recentlySuccessful" class="text-sm text-muted-foreground">
                    Saved.
                </span>
            </div>
        </Form>
    </div>
</template>
