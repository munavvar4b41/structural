<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import ConfirmDestructiveDialog from '@/components/ConfirmDestructiveDialog.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
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

type Props = {
    users: PaginatedUsers;
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

defineProps<Props>();

const deleteDialogOpen = ref(false);
const userPendingDelete = ref<UserRow | null>(null);

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

    <ConfirmDestructiveDialog
        v-model:open="deleteDialogOpen"
        title="Delete user?"
        :description="deleteUserDescription"
        @confirm="executeDelete"
    />

    <div class="flex flex-col gap-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <Heading
                title="Users"
                description="Create and manage accounts for your organization"
            />
            <Button as-child>
                <Link :href="usersCreate()">Add user</Link>
            </Button>
        </div>

        <div
            class="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <table class="w-full text-left text-sm">
                <thead
                    class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border"
                >
                    <tr>
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">Email</th>
                        <th class="px-4 py-3 font-medium">Role</th>
                        <th class="px-4 py-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in users.data" :key="row.id">
                        <td class="px-4 py-3">{{ row.name }}</td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ row.email }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ roleLabel(row.role) }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-2">
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="usersEdit(row.id)">Edit</Link>
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="text-destructive hover:bg-destructive/10"
                                    type="button"
                                    @click="openDeleteDialog(row)"
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
            v-if="users.links.length > 3"
            class="flex flex-wrap items-center justify-center gap-1"
            aria-label="Pagination"
        >
            <template v-for="(link, i) in users.links" :key="i">
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
