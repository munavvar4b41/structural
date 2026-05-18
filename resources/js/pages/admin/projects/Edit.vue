<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import ProjectController from '@/actions/App/Http/Controllers/Admin/ProjectController';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
const selectedTeamIds = ref<number[]>([...props.project.team_ids]);
const leadUserId = ref(
    props.project.lead_user_id !== null ? String(props.project.lead_user_id) : '',
);

const estimationRequired = ref(props.project.estimation_required);

const viableLeadCandidates = computed(() =>
    props.lead_candidates.filter((c) =>
        c.team_ids.some((tid) => selectedTeamIds.value.includes(tid)),
    ),
);

watch(selectedTeamIds, () => {
    if (
        leadUserId.value !== '' &&
        !viableLeadCandidates.value.some((c) => String(c.value) === leadUserId.value)
    ) {
        leadUserId.value = '';
    }
});

const teamButtonLabel = computed(() => {
    if (props.teams.length === 0) {
        return 'No teams available';
    }

    if (selectedTeamIds.value.length === 0) {
        return 'Select teams';
    }

    if (selectedTeamIds.value.length <= 2) {
        return props.teams
            .filter((t) => selectedTeamIds.value.includes(t.value))
            .map((t) => t.label)
            .join(', ');
    }

    return `${selectedTeamIds.value.length} teams selected`;
});

const clientContactLabel = computed(() => {
    const opt = props.clients.find((c) => String(c.value) === clientUserId.value);

    return opt?.label ?? 'Select a client user';
});

const leadUserLabel = computed(() => {
    if (leadUserId.value === '') {
        return 'Use first team head (default)';
    }

    const opt = viableLeadCandidates.value.find(
        (c) => String(c.value) === leadUserId.value,
    );

    return opt?.label ?? 'Use first team head (default)';
});

function setTeamChecked(teamId: number, checked: boolean): void {
    if (checked) {
        if (!selectedTeamIds.value.includes(teamId)) {
            selectedTeamIds.value = [...selectedTeamIds.value, teamId];
        }
    } else {
        selectedTeamIds.value = selectedTeamIds.value.filter((id) => id !== teamId);
    }
}

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

        <Form
            v-bind="ProjectController.update.form(project.id)"
            class="flex max-w-xl flex-col gap-8"
            v-slot="{ errors, processing, recentlySuccessful }"
        >
            <input type="hidden" name="client_user_id" :value="clientUserId" />
            <input
                v-if="leadUserId !== ''"
                type="hidden"
                name="lead_user_id"
                :value="leadUserId"
            />
            <input v-else type="hidden" name="lead_user_id" value="" />
            <input
                v-for="id in selectedTeamIds"
                :key="id"
                type="hidden"
                name="team_ids[]"
                :value="id"
            />
            <input
                type="hidden"
                name="estimation_required"
                :value="estimationRequired ? '1' : '0'"
            />

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
                            class="w-full rounded-xl border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                        />
                        <InputError :message="errors.description" />
                    </div>
                    <div class="grid gap-2">
                        <Label id="client_user_id-label">Client contact</Label>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button
                                    id="client_user_id"
                                    type="button"
                                    variant="outline"
                                    class="h-auto min-h-9 w-full justify-between px-3 py-2 font-normal"
                                    aria-labelledby="client_user_id-label"
                                >
                                    <span class="truncate text-left">{{
                                        clientContactLabel
                                    }}</span>
                                    <ChevronDown class="size-4 shrink-0 opacity-50" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width)">
                                <DropdownMenuLabel>Client user</DropdownMenuLabel>
                                <DropdownMenuRadioGroup v-model="clientUserId">
                                    <DropdownMenuRadioItem
                                        v-for="opt in clients"
                                        :key="opt.value"
                                        :value="String(opt.value)"
                                    >
                                        {{ opt.label }}
                                    </DropdownMenuRadioItem>
                                </DropdownMenuRadioGroup>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <InputError :message="errors.client_user_id" />
                    </div>
                    <div v-if="viableLeadCandidates.length > 0" class="grid gap-2">
                        <Label id="lead_user_id-label">Project lead</Label>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button
                                    id="lead_user_id"
                                    type="button"
                                    variant="outline"
                                    class="h-auto min-h-9 w-full justify-between px-3 py-2 font-normal"
                                    aria-labelledby="lead_user_id-label"
                                >
                                    <span class="truncate text-left">{{ leadUserLabel }}</span>
                                    <ChevronDown class="size-4 shrink-0 opacity-50" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width)">
                                <DropdownMenuLabel>Project lead</DropdownMenuLabel>
                                <DropdownMenuRadioGroup v-model="leadUserId">
                                    <DropdownMenuRadioItem value="">
                                        First team head (default)
                                    </DropdownMenuRadioItem>
                                    <DropdownMenuRadioItem
                                        v-for="opt in viableLeadCandidates"
                                        :key="opt.value"
                                        :value="String(opt.value)"
                                    >
                                        {{ opt.label }}
                                    </DropdownMenuRadioItem>
                                </DropdownMenuRadioGroup>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <p class="text-xs text-muted-foreground">
                            Must be a team head or staff on an assigned team. If cleared, the first
                            team head on those teams becomes the project lead again.
                        </p>
                        <InputError :message="errors.lead_user_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label id="team_ids-label">Assigned teams</Label>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button
                                    id="team_ids"
                                    type="button"
                                    variant="outline"
                                    class="h-auto min-h-9 w-full justify-between px-3 py-2 font-normal"
                                    aria-labelledby="team_ids-label"
                                >
                                    <span class="truncate text-left">{{ teamButtonLabel }}</span>
                                    <ChevronDown class="size-4 shrink-0 opacity-50" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width)">
                                <DropdownMenuLabel>Teams</DropdownMenuLabel>
                                <DropdownMenuCheckboxItem
                                    v-for="opt in teams"
                                    :key="opt.value"
                                    :model-value="selectedTeamIds.includes(opt.value)"
                                    @update:model-value="
                                        (v: boolean | string) =>
                                            setTeamChecked(opt.value, v === true)
                                    "
                                >
                                    {{ opt.label }}
                                </DropdownMenuCheckboxItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <p class="text-xs text-muted-foreground">
                            Open the menu and tick each team that should work on this project.
                        </p>
                        <InputError :message="errors.team_ids" />
                    </div>
                    <div class="flex gap-3 rounded-lg border border-border p-4">
                        <input
                            id="estimation_required"
                            v-model="estimationRequired"
                            type="checkbox"
                            class="mt-1 size-4 shrink-0 rounded border border-input text-primary focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        />
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
