<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import ProjectRequirementController from '@/actions/App/Http/Controllers/Admin/ProjectRequirementController';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import InputError from '@/components/InputError.vue';
import RequirementRichTextEditor from '@/components/RequirementRichTextEditor.vue';
import RequirementRichTextViewer from '@/components/RequirementRichTextViewer.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { emptyTipTapDocumentJson } from '@/lib/tiptapDocument';
import { edit as projectsEdit, index as projectsIndex } from '@/routes/admin/projects/index';
import {
    edit as requirementsEdit,
    index as requirementsIndex,
    show as requirementsShow,
} from '@/routes/admin/projects/requirements/index';

type UserBrief = {
    id: number;
    name: string;
    email: string;
} | null;

type ProjectSummary = {
    id: number;
    name: string;
    code: string | null;
    estimation_required: boolean;
};

type AssignableUser = {
    id: number;
    name: string;
    email: string;
};

type RequirementForm = {
    id: number;
    title: string;
    description: string | null;
    reviewer_user_id: number | null;
    responsible_user_id: number | null;
    creator: UserBrief;
};

const props = defineProps<{
    project: ProjectSummary;
    requirement: RequirementForm;
    assignable_staff: AssignableUser[];
    assignable_responsibles: AssignableUser[];
    can_update_content: boolean;
    can_update_assignments: boolean;
    can_manage_project: boolean;
}>();

const descriptionJson = ref(
    props.requirement.description ?? emptyTipTapDocumentJson(),
);

const responsibleUserId = ref(
    props.requirement.responsible_user_id !== null
        ? String(props.requirement.responsible_user_id)
        : '',
);
const reviewerUserId = ref(
    props.requirement.reviewer_user_id !== null
        ? String(props.requirement.reviewer_user_id)
        : '',
);

const responsibleLabel = computed(() => {
    if (responsibleUserId.value === '') {
        return 'Unassigned';
    }

    const u = props.assignable_responsibles.find(
        (x) => String(x.id) === responsibleUserId.value,
    );

    return u ? `${u.name} (${u.email})` : 'Unassigned';
});

const reviewerLabel = computed(() => {
    if (reviewerUserId.value === '') {
        return 'None';
    }

    const u = props.assignable_staff.find((x) => String(x.id) === reviewerUserId.value);

    return u ? `${u.name} (${u.email})` : 'None';
});

watch(
    () => props.requirement.description,
    (v) => {
        descriptionJson.value = v ?? emptyTipTapDocumentJson();
    },
);

defineOptions({
    layout: (pageProps: {
        project: ProjectSummary;
        requirement: RequirementForm;
        can_manage_project: boolean;
    }) => ({
        breadcrumbs: [
            { title: 'Projects', href: projectsIndex.url() },
            {
                title: pageProps.project.name,
                href: pageProps.can_manage_project
                    ? projectsEdit.url(pageProps.project.id)
                    : requirementsIndex.url(pageProps.project.id),
            },
            {
                title: 'Requirements',
                href: requirementsIndex.url(pageProps.project.id),
            },
            {
                title: pageProps.requirement.title,
                href: requirementsShow.url({
                    project: pageProps.project.id,
                    requirement: pageProps.requirement.id,
                }),
            },
            {
                title: 'Edit',
                href: requirementsEdit.url({
                    project: pageProps.project.id,
                    requirement: pageProps.requirement.id,
                }),
            },
        ],
    }),
});
</script>

<template>
    <Head :title="`Edit requirement · ${project.name}`" />

    <div class="flex flex-col gap-8">
        <PageHeader title="Edit requirement" :description="`Project ${project.name}`" />

        <Form
            v-bind="
                ProjectRequirementController.update.form({
                    project: project.id,
                    requirement: requirement.id,
                })
            "
            class="flex max-w-2xl flex-col gap-8"
            v-slot="{ errors, processing, recentlySuccessful }"
        >
            <template v-if="!can_update_assignments">
                <input
                    type="hidden"
                    name="reviewer_user_id"
                    :value="requirement.reviewer_user_id ?? ''"
                />
                <input
                    type="hidden"
                    name="responsible_user_id"
                    :value="requirement.responsible_user_id ?? ''"
                />
            </template>
            <template v-else>
                <input type="hidden" name="reviewer_user_id" :value="reviewerUserId" />
                <input type="hidden" name="responsible_user_id" :value="responsibleUserId" />
            </template>

            <GlassCard class="p-6">
                <div class="mb-6 space-y-1">
                    <h2 class="text-lg font-semibold">Details</h2>
                    <p class="text-sm text-muted-foreground">
                        Created by {{ requirement.creator?.name ?? 'Unknown' }}
                    </p>
                </div>
                <div class="grid gap-6">
                    <div class="grid gap-2">
                        <Label for="title">Title</Label>
                        <Input
                            id="title"
                            name="title"
                            type="text"
                            required
                            :default-value="requirement.title"
                            :readonly="!can_update_content"
                            :class="{ 'opacity-80': !can_update_content }"
                        />
                        <InputError :message="errors.title" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="requirement-description">Description</Label>
                        <RequirementRichTextEditor
                            v-if="can_update_content"
                            id="requirement-description"
                            v-model="descriptionJson"
                            input-name="description"
                        />
                        <input
                            v-else
                            type="hidden"
                            name="description"
                            :value="requirement.description ?? emptyTipTapDocumentJson()"
                        />
                        <RequirementRichTextViewer
                            v-if="!can_update_content"
                            :json="requirement.description"
                        />
                        <InputError :message="errors.description" />
                    </div>
                </div>
            </GlassCard>

            <GlassCard v-if="can_update_assignments" class="p-6">
                <div class="mb-6 space-y-1">
                    <h2 class="text-lg font-semibold">Responsible</h2>
                    <p class="text-sm text-muted-foreground">
                        Who owns triage for this requirement
                    </p>
                </div>
                <div class="grid gap-2">
                    <Label id="responsible_user_id-label">Responsible user</Label>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button
                                id="responsible_user_id"
                                type="button"
                                variant="outline"
                                class="h-auto min-h-9 w-full justify-between px-3 py-2 font-normal"
                                aria-labelledby="responsible_user_id-label"
                            >
                                <span class="truncate text-left">{{ responsibleLabel }}</span>
                                <ChevronDown class="size-4 shrink-0 opacity-50" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width)">
                            <DropdownMenuLabel>Responsible</DropdownMenuLabel>
                            <DropdownMenuRadioGroup v-model="responsibleUserId">
                                <DropdownMenuRadioItem value="">Unassigned</DropdownMenuRadioItem>
                                <DropdownMenuRadioItem
                                    v-for="u in assignable_responsibles"
                                    :key="u.id"
                                    :value="String(u.id)"
                                >
                                    {{ u.name }} ({{ u.email }})
                                </DropdownMenuRadioItem>
                            </DropdownMenuRadioGroup>
                        </DropdownMenuContent>
                    </DropdownMenu>
                    <InputError :message="errors.responsible_user_id" />
                </div>
            </GlassCard>

            <GlassCard v-if="can_update_assignments" class="p-6">
                <div class="mb-6 space-y-1">
                    <h2 class="text-lg font-semibold">Reviewer</h2>
                    <p class="text-sm text-muted-foreground">
                        Assign a staff member on this project to check the requirement
                    </p>
                </div>
                <div class="grid gap-2">
                    <Label id="reviewer_user_id-label">Reviewer (staff)</Label>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button
                                id="reviewer_user_id"
                                type="button"
                                variant="outline"
                                class="h-auto min-h-9 w-full justify-between px-3 py-2 font-normal"
                                aria-labelledby="reviewer_user_id-label"
                            >
                                <span class="truncate text-left">{{ reviewerLabel }}</span>
                                <ChevronDown class="size-4 shrink-0 opacity-50" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent class="w-(--reka-dropdown-menu-trigger-width)">
                            <DropdownMenuLabel>Reviewer</DropdownMenuLabel>
                            <DropdownMenuRadioGroup v-model="reviewerUserId">
                                <DropdownMenuRadioItem value="">None</DropdownMenuRadioItem>
                                <DropdownMenuRadioItem
                                    v-for="u in assignable_staff"
                                    :key="u.id"
                                    :value="String(u.id)"
                                >
                                    {{ u.name }} ({{ u.email }})
                                </DropdownMenuRadioItem>
                            </DropdownMenuRadioGroup>
                        </DropdownMenuContent>
                    </DropdownMenu>
                    <p v-if="assignable_staff.length === 0" class="text-xs text-muted-foreground">
                        No staff on this project's teams yet.
                    </p>
                    <InputError :message="errors.reviewer_user_id" />
                </div>
            </GlassCard>

            <div class="flex items-center gap-4">
                <Button type="submit" :disabled="processing">Save</Button>
                <Button variant="outline" as-child>
                    <Link
                        :href="
                            requirementsShow.url({
                                project: project.id,
                                requirement: requirement.id,
                            })
                        "
                    >
                        View
                    </Link>
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="requirementsIndex.url(project.id)">Cancel</Link>
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
