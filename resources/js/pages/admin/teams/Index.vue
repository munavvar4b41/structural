<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import TeamController from '@/actions/App/Http/Controllers/Admin/TeamController';
import ConfirmDestructiveDialog from '@/components/ConfirmDestructiveDialog.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import ListToolbar from '@/components/ListToolbar.vue';
import { routerReloadOnly, stripFilterParams } from '@/composables/useServerFilters';
import { Button } from '@/components/ui/button';
import {
    create as teamsCreate,
    edit as teamsEdit,
    index as teamsIndex,
} from '@/routes/admin/teams/index';

type TeamRow = {
    id: number;
    name: string;
    code: string | null;
    description: string | null;
    users_count: number;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedTeams = {
    data: TeamRow[];
    links: PaginationLink[];
};

type Props = {
    teams: PaginatedTeams;
    filters: {
        search: string;
    };
    errors: {
        team?: string;
    };
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Teams', href: teamsIndex() }],
    },
});

const props = defineProps<Props>();

const deleteDialogOpen = ref(false);
const teamPendingDelete = ref<TeamRow | null>(null);

function reloadSearch(search: string): void {
    routerReloadOnly(
        teamsIndex.url({
            query: stripFilterParams({
                search,
                page: 1,
            }),
        }),
        ['teams', 'filters'],
    );
}

function openDeleteDialog(team: TeamRow): void {
    teamPendingDelete.value = team;
    deleteDialogOpen.value = true;
}

function executeDelete(): void {
    const team = teamPendingDelete.value;

    if (team === null) {
        return;
    }

    router.delete(TeamController.destroy.url(team.id));
    teamPendingDelete.value = null;
}

const deleteTeamDescription = computed(() => {
    const team = teamPendingDelete.value;

    if (team === null) {
        return '';
    }

    return `Delete "${team.name}"? This cannot be undone.`;
});
</script>

<template>
    <Head title="Teams" />

    <ConfirmDestructiveDialog
        v-model:open="deleteDialogOpen"
        title="Delete team?"
        :description="deleteTeamDescription"
        @confirm="executeDelete"
    />

    <div class="flex flex-col gap-8">
        <div class="flex flex-col gap-4">
            <Heading
                title="Teams"
                description="Manage teams and cross-team assignments"
            />

            <ListToolbar
                :model-value="filters.search"
                placeholder="Search team name, code, member…"
                @update:model-value="reloadSearch"
            >
                <template #actions>
                    <Button as-child>
                        <Link :href="teamsCreate()">Add team</Link>
                    </Button>
                </template>
            </ListToolbar>
        </div>

        <InputError :message="errors.team" />

        <div
            class="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <table class="w-full text-left text-sm">
                <thead
                    class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border"
                >
                    <tr>
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">Code</th>
                        <th class="px-4 py-3 font-medium">Members</th>
                        <th class="px-4 py-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="team in teams.data" :key="team.id">
                        <td class="px-4 py-3">{{ team.name }}</td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ team.code ?? '-' }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ team.users_count }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-2">
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="teamsEdit(team.id)">Edit</Link>
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="text-destructive hover:bg-destructive/10"
                                    type="button"
                                    @click="openDeleteDialog(team)"
                                >
                                    Delete
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav
            v-if="teams.links.length > 3"
            class="flex flex-wrap items-center justify-center gap-1"
            aria-label="Pagination"
        >
            <template v-for="(link, i) in teams.links" :key="i">
                <Button
                    v-if="link.url"
                    variant="outline"
                    size="sm"
                    :disabled="link.active"
                    as-child
                >
                    <Link :href="link.url" preserve-scroll v-html="link.label" />
                </Button>
                <span
                    v-else
                    class="px-3 py-1.5 text-sm text-muted-foreground"
                    v-html="link.label"
                />
            </template>
        </nav>
    </div>
</template>
