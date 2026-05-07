<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
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
    create as usersCreate,
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

type Props = {
    assignableRoles: AssignableRole[];
    teams: TeamOption[];
};

const props = defineProps<Props>();

const roleId = ref(props.assignableRoles[0]?.value ?? '');
const primaryTeamId = ref(
    props.teams[0] !== undefined ? String(props.teams[0].value) : '',
);
const selectedTeamIds = ref<number[]>(
    props.teams[0] !== undefined ? [props.teams[0].value] : [],
);

watch(primaryTeamId, (idStr) => {
    const id = Number(idStr);

    if (!Number.isFinite(id)) {
        return;
    }

    if (!selectedTeamIds.value.includes(id)) {
        selectedTeamIds.value = [...selectedTeamIds.value, id];
    }
});

const roleLabel = computed(
    () =>
        props.assignableRoles.find((r) => r.value === roleId.value)?.label ??
        'Select role',
);

const primaryTeamLabel = computed(
    () =>
        props.teams.find((t) => String(t.value) === primaryTeamId.value)?.label ??
        'Select primary team',
);

const additionalTeamsLabel = computed(() => {
    if (props.teams.length === 0) {
        return 'No teams available';
    }

    const extraIds = selectedTeamIds.value.filter(
        (id) => id !== Number(primaryTeamId.value),
    );

    if (extraIds.length === 0) {
        return 'No additional teams';
    }

    if (extraIds.length <= 2) {
        return props.teams
            .filter((t) => extraIds.includes(t.value))
            .map((t) => t.label)
            .join(', ');
    }

    return `${extraIds.length} additional teams`;
});

function setTeamChecked(teamId: number, checked: boolean): void {
    if (teamId === Number(primaryTeamId.value) && !checked) {
        return;
    }

    if (checked) {
        if (!selectedTeamIds.value.includes(teamId)) {
            selectedTeamIds.value = [...selectedTeamIds.value, teamId];
        }
    } else {
        selectedTeamIds.value = selectedTeamIds.value.filter((id) => id !== teamId);
    }
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Users', href: usersIndex() },
            { title: 'Add user', href: usersCreate() },
        ],
    },
});
</script>

<template>
    <Head title="Add user" />

    <div class="flex flex-col gap-8">
        <Heading title="Add user" description="Create a new account" />

        <Form
            v-bind="UserController.store.form()"
            class="flex max-w-xl flex-col gap-8"
            v-slot="{ errors, processing, recentlySuccessful }"
        >
            <input type="hidden" name="role" :value="roleId" />
            <input type="hidden" name="primary_team_id" :value="primaryTeamId" />
            <input
                v-for="id in selectedTeamIds"
                :key="id"
                type="hidden"
                name="team_ids[]"
                :value="id"
            />

            <Card>
                <CardHeader>
                    <CardTitle>Account</CardTitle>
                    <CardDescription>
                        Name, email, role, and initial password
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-6">
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            type="text"
                            required
                            autocomplete="name"
                            placeholder="Full name"
                        />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="email">Email</Label>
                        <Input
                            id="email"
                            name="email"
                            type="email"
                            required
                            autocomplete="username"
                            placeholder="email@example.com"
                        />
                        <InputError :message="errors.email" />
                    </div>
                    <div class="grid gap-2">
                        <Label id="role-label">Role</Label>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button
                                    id="role"
                                    type="button"
                                    variant="outline"
                                    class="h-auto min-h-9 w-full justify-between px-3 py-2 font-normal"
                                    aria-labelledby="role-label"
                                >
                                    <span class="truncate text-left">{{ roleLabel }}</span>
                                    <ChevronDown class="size-4 shrink-0 opacity-50" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width)">
                                <DropdownMenuLabel>Role</DropdownMenuLabel>
                                <DropdownMenuRadioGroup v-model="roleId">
                                    <DropdownMenuRadioItem
                                        v-for="opt in assignableRoles"
                                        :key="opt.value"
                                        :value="opt.value"
                                    >
                                        {{ opt.label }}
                                    </DropdownMenuRadioItem>
                                </DropdownMenuRadioGroup>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <InputError :message="errors.role" />
                    </div>
                    <div class="grid gap-2">
                        <Label id="primary_team_id-label">Primary team</Label>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button
                                    id="primary_team_id"
                                    type="button"
                                    variant="outline"
                                    class="h-auto min-h-9 w-full justify-between px-3 py-2 font-normal"
                                    aria-labelledby="primary_team_id-label"
                                >
                                    <span class="truncate text-left">{{
                                        primaryTeamLabel
                                    }}</span>
                                    <ChevronDown class="size-4 shrink-0 opacity-50" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width)">
                                <DropdownMenuLabel>Primary team</DropdownMenuLabel>
                                <DropdownMenuRadioGroup v-model="primaryTeamId">
                                    <DropdownMenuRadioItem
                                        v-for="opt in teams"
                                        :key="opt.value"
                                        :value="String(opt.value)"
                                    >
                                        {{ opt.label }}
                                    </DropdownMenuRadioItem>
                                </DropdownMenuRadioGroup>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <InputError :message="errors.primary_team_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label id="team_ids-label">Additional team assignments</Label>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button
                                    id="team_ids"
                                    type="button"
                                    variant="outline"
                                    class="h-auto min-h-9 w-full justify-between px-3 py-2 font-normal"
                                    aria-labelledby="team_ids-label"
                                >
                                    <span class="truncate text-left">{{
                                        additionalTeamsLabel
                                    }}</span>
                                    <ChevronDown class="size-4 shrink-0 opacity-50" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width)">
                                <DropdownMenuLabel>Teams</DropdownMenuLabel>
                                <DropdownMenuCheckboxItem
                                    v-for="opt in teams"
                                    :key="opt.value"
                                    :disabled="opt.value === Number(primaryTeamId)"
                                    :model-value="selectedTeamIds.includes(opt.value)"
                                    @update:model-value="
                                        (v: boolean | string) =>
                                            setTeamChecked(opt.value, v === true)
                                    "
                                >
                                    {{ opt.label }}
                                    <span
                                        v-if="opt.value === Number(primaryTeamId)"
                                        class="text-xs text-muted-foreground"
                                    >
                                        (primary)
                                    </span>
                                </DropdownMenuCheckboxItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <p class="text-xs text-muted-foreground">
                            Open the menu to assign teams. The primary team is always included.
                        </p>
                        <InputError :message="errors.team_ids" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="password">Password</Label>
                        <Input
                            id="password"
                            name="password"
                            type="password"
                            required
                            autocomplete="new-password"
                        />
                        <InputError :message="errors.password" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="password_confirmation">
                            Confirm password
                        </Label>
                        <Input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            required
                            autocomplete="new-password"
                        />
                        <InputError :message="errors.password_confirmation" />
                    </div>
                </CardContent>
            </Card>

            <div class="flex items-center gap-4">
                <Button type="submit" :disabled="processing">
                    Create user
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="usersIndex()">Cancel</Link>
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
