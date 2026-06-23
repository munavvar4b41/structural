<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import FormField from '@/components/dashboard/FormField.vue';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import FormMultiSelect from '@/components/FormMultiSelect.vue';
import FormSelect from '@/components/FormSelect.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    edit as usersEdit,
    index as usersIndex,
} from '@/routes/admin/users/index';

type AssignableRole = {
    value: string;
    label: string;
};

type TeamOption = {
    value: number;
    label: string;
};

type UserPayload = {
    id: number;
    name: string;
    email: string;
    role: string;
    primary_team_id: number | null;
    team_ids: number[];
};

type Props = {
    user: UserPayload;
    assignableRoles: AssignableRole[];
    teams: TeamOption[];
};

const props = defineProps<Props>();

const roleId = ref(props.user.role);
const primaryTeamId = ref(
    props.user.primary_team_id !== null
        ? String(props.user.primary_team_id)
        : props.teams[0] !== undefined
            ? String(props.teams[0].value)
            : '',
);
const selectedTeamIds = ref<string[]>(props.user.team_ids.map(String));

const teamOptions = computed(() =>
    props.teams.map((t) => ({ value: String(t.value), label: t.label })),
);

watch(primaryTeamId, (idStr) => {
    if (idStr === '') {
        return;
    }

    if (!selectedTeamIds.value.includes(idStr)) {
        selectedTeamIds.value = [...selectedTeamIds.value, idStr];
    }
});

defineOptions({
    layout: (pageProps: { user: UserPayload }) => ({
        breadcrumbs: [
            { title: 'Users', href: usersIndex() },
            {
                title: pageProps.user.name,
                href: usersEdit(pageProps.user.id),
            },
        ],
    }),
});
</script>

<template>

    <Head :title="`Edit ${user.name}`" />

    <div class="flex flex-col gap-8">
        <PageHeader title="Edit user" :description="`Update ${user.name}`" />

        <Form v-bind="UserController.update.form(user.id)" class="flex max-w-xl flex-col gap-8"
            v-slot="{ errors, processing, recentlySuccessful }">
            <GlassCard class="p-6">
                <div class="mb-6 space-y-1">
                    <h2 class="text-lg font-semibold">Account</h2>
                    <p class="text-sm text-muted-foreground">
                        Name, email, role, and optional new password
                    </p>
                </div>
                <div class="grid gap-6">
                    <FormField label="Name" html-for="name" :error="errors.name" required>
                        <Input id="name" name="name" type="text" required autocomplete="name"
                            :default-value="user.name" />
                    </FormField>
                    <FormField label="Email" html-for="email" :error="errors.email" required>
                        <Input id="email" name="email" type="email" required autocomplete="username"
                            :default-value="user.email" />
                    </FormField>
                    <div class="grid gap-2">
                        <Label for="role">Role</Label>
                        <FormSelect id="role" name="role" v-model="roleId" required placeholder="Select role"
                            :options="assignableRoles" />
                        <InputError :message="errors.role" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="primary_team_id">Primary team</Label>
                        <FormSelect id="primary_team_id" name="primary_team_id" v-model="primaryTeamId" required
                            placeholder="Select primary team" :options="teamOptions" />
                        <InputError :message="errors.primary_team_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="team_ids">Additional team assignments</Label>
                        <FormMultiSelect id="team_ids" name="team_ids" v-model="selectedTeamIds" menu-label="Teams"
                            placeholder="No additional teams" :options="teamOptions"
                            :disabled-options="primaryTeamId !== '' ? [primaryTeamId] : []">
                            <template #option-suffix="{ option }">
                                <span v-if="option.value === primaryTeamId" class="text-xs text-muted-foreground">
                                    (primary)
                                </span>
                            </template>
                        </FormMultiSelect>
                        <p class="text-xs text-muted-foreground">
                            Open the menu to assign teams. The primary team is always included.
                        </p>
                        <InputError :message="errors.team_ids" />
                    </div>
                    <FormField label="New password" html-for="password" :error="errors.password">
                        <Input id="password" name="password" type="password" autocomplete="new-password"
                            placeholder="Leave blank to keep current password" />
                    </FormField>
                    <FormField label="Confirm new password" html-for="password_confirmation"
                        :error="errors.password_confirmation">
                        <Input id="password_confirmation" name="password_confirmation" type="password"
                            autocomplete="new-password" />
                    </FormField>
                </div>
            </GlassCard>

            <div class="flex items-center gap-4">
                <Button type="submit" :disabled="processing">Save</Button>
                <Button variant="outline" as-child>
                    <Link :href="usersIndex()">Cancel</Link>
                </Button>
                <span v-show="recentlySuccessful" class="text-sm text-muted-foreground">
                    Saved.
                </span>
            </div>
        </Form>
    </div>
</template>
