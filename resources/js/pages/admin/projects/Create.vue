<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ProjectController from '@/actions/App/Http/Controllers/Admin/ProjectController';
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
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    create as projectsCreate,
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

const props = defineProps<{
    teams: TeamOption[];
    clients: ClientOption[];
}>();

const clientUserId = ref<string | undefined>(undefined);
const selectedTeamIds = ref<number[]>([]);

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

function setTeamChecked(teamId: number, checked: boolean | 'indeterminate'): void {
    if (checked === 'indeterminate') {
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
            { title: 'Projects', href: projectsIndex() },
            { title: 'Add project', href: projectsCreate() },
        ],
    },
});
</script>

<template>
    <Head title="Add project" />

    <div class="flex flex-col gap-8">
        <Heading
            title="Add project"
            description="Create a project, assign a client contact, and assign teams"
        />

        <Form
            v-bind="ProjectController.store.form()"
            class="flex max-w-xl flex-col gap-8"
            v-slot="{ errors, processing, recentlySuccessful }"
        >
            <input type="hidden" name="client_user_id" :value="clientUserId ?? ''" />
            <input
                v-for="id in selectedTeamIds"
                :key="id"
                type="hidden"
                name="team_ids[]"
                :value="id"
            />

            <Card>
                <CardHeader>
                    <CardTitle>Project details</CardTitle>
                    <CardDescription>
                        Name, optional code, description, client contact, and assigned teams
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-6">
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input id="name" name="name" type="text" required />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="code">Code</Label>
                        <Input id="code" name="code" type="text" />
                        <InputError :message="errors.code" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="description">Description</Label>
                        <textarea
                            id="description"
                            name="description"
                            rows="4"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                        />
                        <InputError :message="errors.description" />
                    </div>
                    <div class="grid gap-2">
                        <Label id="client_user_id-label">Client contact</Label>
                        <Select v-model="clientUserId">
                            <SelectTrigger
                                id="client_user_id"
                                class="w-full min-w-0"
                                aria-labelledby="client_user_id-label"
                            >
                                <SelectValue placeholder="Select a client user" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="opt in clients"
                                    :key="opt.value"
                                    :value="String(opt.value)"
                                >
                                    {{ opt.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.client_user_id" />
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
                                    :checked="selectedTeamIds.includes(opt.value)"
                                    @update:checked="
                                        (v: boolean | 'indeterminate') =>
                                            setTeamChecked(opt.value, v)
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
                </CardContent>
            </Card>

            <div class="flex items-center gap-4">
                <Button type="submit" :disabled="processing">
                    Create project
                </Button>
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
