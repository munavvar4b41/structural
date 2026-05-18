<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ProjectRequirementController from '@/actions/App/Http/Controllers/Admin/ProjectRequirementController';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import InputError from '@/components/InputError.vue';
import RequirementRichTextEditor from '@/components/RequirementRichTextEditor.vue';
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
    create as requirementsCreate,
    index as requirementsIndex,
} from '@/routes/admin/projects/requirements/index';

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

const props = defineProps<{
    project: ProjectSummary;
    canManageProject: boolean;
    assignable_responsibles: AssignableUser[];
}>();

const descriptionJson = ref(emptyTipTapDocumentJson());

const responsibleUserId = ref('');

const responsibleLabel = computed(() => {
    if (responsibleUserId.value === '') {
        return 'Use default (project lead / first team head)';
    }

    const u = props.assignable_responsibles.find(
        (x) => String(x.id) === responsibleUserId.value,
    );

    return u ? `${u.name} (${u.email})` : 'Use default (project lead / first team head)';
});

defineOptions({
    layout: (pageProps: {
        project: ProjectSummary;
        canManageProject: boolean;
    }) => ({
        breadcrumbs: [
            { title: 'Projects', href: projectsIndex.url() },
            {
                title: pageProps.project.name,
                href: pageProps.canManageProject
                    ? projectsEdit.url(pageProps.project.id)
                    : requirementsIndex.url(pageProps.project.id),
            },
            { title: 'Requirements', href: requirementsIndex.url(pageProps.project.id) },
            {
                title: 'Add requirement',
                href: requirementsCreate.url(pageProps.project.id),
            },
        ],
    }),
});
</script>

<template>
    <Head :title="`Add requirement · ${project.name}`" />

    <div class="flex flex-col gap-8">
        <PageHeader title="Add requirement"
            :description="`For project ${project.name}`" />

        <Form
            v-bind="ProjectRequirementController.store.form({ project: project.id })"
            class="flex max-w-2xl flex-col gap-8"
            v-slot="{ errors, processing, recentlySuccessful }"
        >
            <input type="hidden" name="responsible_user_id" :value="responsibleUserId" />

            <GlassCard class="p-6">
                <div class="mb-6 space-y-1">
                    <h2 class="text-lg font-semibold">Requirement</h2>
                    <p class="text-sm text-muted-foreground">
                        Title and optional rich-text details for your team
                    </p>
                </div>
                <div class="grid gap-6">
                    <div class="grid gap-2">
                        <Label for="title">Title</Label>
                        <Input id="title" name="title" type="text" required />
                        <InputError :message="errors.title" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="requirement-description">Description</Label>
                        <RequirementRichTextEditor
                            id="requirement-description"
                            v-model="descriptionJson"
                            input-name="description"
                        />
                        <InputError :message="errors.description" />
                    </div>
                    <div class="grid gap-2">
                        <Label id="responsible_user_id-label">Responsible (optional)</Label>
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
                                    <DropdownMenuRadioItem value="">
                                        Use default (project lead / first team head)
                                    </DropdownMenuRadioItem>
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
                        <p class="text-xs text-muted-foreground">
                            Leave blank to use the project lead or the first team head on this project.
                        </p>
                        <InputError :message="errors.responsible_user_id" />
                    </div>
                </div>
            </GlassCard>

            <div class="flex items-center gap-4">
                <Button type="submit" :disabled="processing">Create</Button>
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
