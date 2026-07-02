<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import ConfirmDestructiveDialog from '@/components/ConfirmDestructiveDialog.vue';
import DataTable from '@/components/dashboard/DataTable.vue';
import DataTableEmptyRow from '@/components/dashboard/DataTableEmptyRow.vue';
import DataTablePagination from '@/components/dashboard/DataTablePagination.vue';
import DataTableTd from '@/components/dashboard/DataTableTd.vue';
import DataTableTh from '@/components/dashboard/DataTableTh.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import TableRow from '@/components/dashboard/TableRow.vue';
import FormSelect from '@/components/FormSelect.vue';
import ListToolbar from '@/components/ListToolbar.vue';
import TableIconAction from '@/components/TableIconAction.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { routerReloadOnly, stripFilterParams } from '@/composables/useServerFilters';
import {
    create as usersCreate,
    edit as usersEdit,
    index as usersIndex,
} from '@/routes/admin/users/index';

type UserRow = {
    id: number;
    name: string;
    email: string;
    role: string;
    email_verified_at: string | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedUsers = {
    data: UserRow[];
    links: PaginationLink[];
};

type FilterOption = { value: string; label: string };

type Props = {
    users: PaginatedUsers;
    filters: {
        search: string;
        role: string;
        team_id: string;
        verified: string;
    };
    filter_options: {
        roles: FilterOption[];
        teams: { value: number; label: string }[];
        verified: FilterOption[];
    };
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Users',
                href: usersIndex(),
            },
        ],
    },
});

const props = defineProps<Props>();

const deleteDialogOpen = ref(false);
const userPendingDelete = ref<UserRow | null>(null);

const roleFilter = ref(props.filters.role);
const teamFilter = ref(props.filters.team_id);
const verifiedFilter = ref(props.filters.verified);

watch(
    () => props.filters,
    (f) => {
        roleFilter.value = f.role;
        teamFilter.value = f.team_id;
        verifiedFilter.value = f.verified;
    },
);

const teamSelectOptions = computed(() =>
    props.filter_options.teams.map((t) => ({
        value: String(t.value),
        label: t.label,
    })),
);

const roleSelectOptions = computed(() =>
    props.filter_options.roles.map((r) => ({
        value: r.value,
        label: r.label,
    })),
);

function reload(
    overrides: Partial<Record<'search' | 'role' | 'team_id' | 'verified', string>> = {},
): void {
    routerReloadOnly(
        usersIndex.url({
            query: stripFilterParams({
                search: props.filters.search,
                role: props.filters.role,
                team_id: props.filters.team_id,
                verified: props.filters.verified,
                ...overrides,
                page: 1,
            }),
        }),
        ['users', 'filters', 'filter_options'],
    );
}

function onSearch(search: string): void {
    reload({ search });
}

function onRole(v: string): void {
    reload({ role: v });
}

function onTeam(v: string): void {
    reload({ team_id: v });
}

function onVerified(v: string): void {
    reload({ verified: v });
}

function openDeleteDialog(row: UserRow): void {
    userPendingDelete.value = row;
    deleteDialogOpen.value = true;
}

function executeDelete(): void {
    const row = userPendingDelete.value;

    if (row === null) {
        return;
    }

    router.delete(UserController.destroy.url(row.id));
    userPendingDelete.value = null;
}

const deleteUserDescription = computed(() => {
    const row = userPendingDelete.value;

    if (row === null) {
        return '';
    }

    return `Delete "${row.name}"? This cannot be undone.`;
});

function roleLabel(role: string): string {
    return role.replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}
</script>

<template>

    <Head title="Users" />

    <ConfirmDestructiveDialog v-model:open="deleteDialogOpen" title="Delete user?" :description="deleteUserDescription"
        @confirm="executeDelete" />

    <div class="flex flex-col gap-6">
        <PageHeader title="Users" description="Create and manage accounts for your organization">
            <template #actions>
                <Button as-child>
                    <Link :href="usersCreate()">Add user</Link>
                </Button>
            </template>
        </PageHeader>

        <ListToolbar :model-value="filters.search" placeholder="Search name or email…" @update:model-value="onSearch">
            <template #filters>
                <div class="flex flex-wrap items-end gap-3">
                    <div class="grid gap-1">
                        <Label class="text-xs text-muted-foreground" for="filter-role">Role</Label>
                        <FormSelect id="filter-role" name="role" class="w-[10rem]" :model-value="roleFilter"
                            :options="roleSelectOptions" placeholder="All roles" none-label="All roles"
                            exclude-from-submit @update:model-value="onRole" />
                    </div>
                    <div class="grid gap-1">
                        <Label class="text-xs text-muted-foreground" for="filter-team">Team</Label>
                        <FormSelect id="filter-team" name="team_id" class="w-[12rem]" :model-value="teamFilter"
                            :options="teamSelectOptions" placeholder="All teams" none-label="All teams"
                            exclude-from-submit @update:model-value="onTeam" />
                    </div>
                    <div class="grid gap-1">
                        <Label class="text-xs text-muted-foreground" for="filter-verified">Email</Label>
                        <FormSelect id="filter-verified" name="verified" class="w-[11rem]"
                            :model-value="verifiedFilter" :options="filter_options.verified" placeholder="All"
                            none-label="All" exclude-from-submit @update:model-value="onVerified" />
                    </div>
                </div>
            </template>
        </ListToolbar>

        <DataTable>
            <thead>
                <tr class="border-b border-border/60 bg-muted/40 backdrop-blur-sm">
                    <DataTableTh>Name</DataTableTh>
                    <DataTableTh>Email</DataTableTh>
                    <DataTableTh>Role</DataTableTh>
                    <DataTableTh class="text-right">Actions</DataTableTh>
                </tr>
            </thead>
            <tbody>
                <TableRow v-for="row in users.data" :key="row.id">
                    <DataTableTd label="Name" class="font-medium">{{ row.name }}</DataTableTd>
                    <DataTableTd label="Email" class="text-muted-foreground">
                        {{ row.email }}
                    </DataTableTd>
                    <DataTableTd label="Role" class="text-muted-foreground">
                        {{ roleLabel(row.role) }}
                    </DataTableTd>
                    <DataTableTd label="Actions" class="text-left md:text-right">
                        <div class="flex gap-1 justify-start md:justify-end">
                            <TableIconAction
                                icon="pencil"
                                label="Edit"
                                :href="usersEdit.url(row.id)"
                            />
                            <TableIconAction
                                icon="trash"
                                label="Delete"
                                destructive
                                @click="openDeleteDialog(row)"
                            />
                        </div>
                    </DataTableTd>
                </TableRow>
                <DataTableEmptyRow
                    v-if="users.data.length === 0"
                    :colspan="4"
                    message="No users match this filter."
                />
            </tbody>
        </DataTable>

        <DataTablePagination :links="users.links" />
    </div>
</template>
