<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import TeamSelectionController from '@/actions/App/Http/Controllers/TeamSelectionController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Label } from '@/components/ui/label';

type TeamOption = {
    value: number;
    label: string;
};

type Props = {
    teams: TeamOption[];
};

const props = defineProps<Props>();

const primaryTeamId = ref(
    props.teams[0] !== undefined ? String(props.teams[0].value) : '',
);

const primaryTeamLabel = computed(
    () =>
        props.teams.find((t) => String(t.value) === primaryTeamId.value)?.label ??
        'Select primary team',
);

defineOptions({
    layout: {
        title: 'Select your team',
        description: 'Choose your primary team to continue',
    },
});
</script>

<template>
    <Head title="Select team" />

    <Form
        v-bind="TeamSelectionController.store.form()"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <input type="hidden" name="primary_team_id" :value="primaryTeamId" />

        <div class="grid gap-6">
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
                            <span class="truncate text-left">{{ primaryTeamLabel }}</span>
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

            <Button type="submit" class="w-full" :disabled="processing">
                Continue
            </Button>
        </div>
    </Form>
</template>
