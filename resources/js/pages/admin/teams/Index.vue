<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import TeamController from '@/actions/App/Http/Controllers/Admin/TeamController';
import ConfirmDestructiveDialog from '@/components/ConfirmDestructiveDialog.vue';
import DataTable from '@/components/dashboard/DataTable.vue';
import DataTablePagination from '@/components/dashboard/DataTablePagination.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import InputError from '@/components/InputError.vue';
import ListToolbar from '@/components/ListToolbar.vue';
import { Button } from '@/components/ui/button';
import { routerReloadOnly, stripFilterParams } from '@/composables/useServerFilters';
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

defineProps<Props>();

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

    <div class="flex flex-col gap-6">
        <PageHeader
            title="Teams"
            description="Manage teams and cross-team assignments"
        >
            <template #actions>
                <Button as-child>
                    <Link :href="teamsCreate()">Add team</Link>
                </Button>
            </template>
        </PageHeader>

        <ListToolbar
            :model-value="filters.search"
            placeholder="Search team name, code, member…"
            @update:model-value="reloadSearch"
        />

        <InputError :message="errors.team" />

        <DataTable>
            <thead>
                <tr class="border-b border-border/60 bg-muted/40 backdrop-blur-sm">
                    <th class="px-5 py-3.5 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        Name
                    </th>
                    <th class="px-5 py-3.5 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        Code
                    </th>
                    <th class="px-5 py-3.5 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        Members
                    </th>
                    <th class="px-5 py-3.5 text-right text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="team in teams.data"
                    :key="team.id"
                    class="border-b border-border/40 transition-colors even:bg-muted/15 hover:bg-muted/30"
                >
                    <td class="px-5 py-3.5 font-medium">{{ team.name }}</td>
                    <td class="px-5 py-3.5 text-muted-foreground">
                        {{ team.code ?? '-' }}
                    </td>
                    <td class="px-5 py-3.5 text-muted-foreground">
                        {{ team.users_count }}
                    </td>
                    <td class="px-5 py-3.5 text-right">
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
        </DataTable>

        <DataTablePagination :links="teams.links" />
    </div>
</template>
