<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import ProjectRequirementController from '@/actions/App/Http/Controllers/Admin/ProjectRequirementController';
import ConfirmDestructiveDialog from '@/components/ConfirmDestructiveDialog.vue';
import Heading from '@/components/Heading.vue';
import ListToolbar from '@/components/ListToolbar.vue';
import TaskFormSelect from '@/components/TaskFormSelect.vue';
import { routerReloadOnly, stripFilterParams } from '@/composables/useServerFilters';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { edit as projectsEdit, index as projectsIndex } from '@/routes/admin/projects/index';
import {
    create as requirementsCreate,
    edit as requirementsEdit,
    index as requirementsIndex,
    show as requirementsShow,
} from '@/routes/admin/projects/requirements/index';

type UserBrief = {
    id: number;
    name: string;
    email: string;
} | null;

type RequirementRow = {
    id: number;
    title: string;
    description_preview: string | null;
    reviewed_at: string | null;
    understanding_confirmed_at: string | null;
    created_at: string | null;
    creator: UserBrief;
    responsible_user: UserBrief;
    reviewer: UserBrief;
    can_update: boolean;
    can_delete: boolean;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedRequirements = {
    data: RequirementRow[];
    links: PaginationLink[];
};

type ProjectSummary = {
    id: number;
    name: string;
    code: string | null;
    estimation_required: boolean;
};

type FilterOption = { value: string; label: string };

const props = defineProps<{
    project: ProjectSummary;
    requirements: PaginatedRequirements;
    canCreateRequirements: boolean;
    canManageProject: boolean;
    filters: {
        search: string;
        review_status: string;
        responsible_user_id: string;
    };
    filter_options: {
        review_status: FilterOption[];
        responsibles: { value: number; label: string }[];
    };
}>();

const reviewStatusFilter = ref(props.filters.review_status);
const responsibleFilter = ref(props.filters.responsible_user_id);

watch(
    () => props.filters,
    (f) => {
        reviewStatusFilter.value = f.review_status;
        responsibleFilter.value = f.responsible_user_id;
    },
);

const reviewStatusSelectOptions = computed(() =>
    props.filter_options.review_status.map((o) => ({
        value: o.value,
        label: o.label,
    })),
);

const responsibleSelectOptions = computed(() =>
    props.filter_options.responsibles.map((o) => ({
        value: String(o.value),
        label: o.label,
    })),
);

function reloadRequirements(
    overrides: Partial<Record<'search' | 'review_status' | 'responsible_user_id', string>> = {},
): void {
    routerReloadOnly(
        requirementsIndex.url(props.project.id, {
            query: stripFilterParams({
                search: props.filters.search,
                review_status: props.filters.review_status,
                responsible_user_id: props.filters.responsible_user_id,
                ...overrides,
                page: 1,
            }),
        }),
        ['requirements', 'filters', 'filter_options'],
    );
}

function onSearch(search: string): void {
    reloadRequirements({ search });
}

function onReviewStatus(v: string): void {
    reloadRequirements({ review_status: v });
}

function onResponsible(v: string): void {
    reloadRequirements({ responsible_user_id: v });
}

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
            {
                title: 'Requirements',
                href: requirementsIndex.url(pageProps.project.id),
            },
        ],
    }),
});

const deleteDialogOpen = ref(false);
const requirementPendingDelete = ref<RequirementRow | null>(null);

function openDeleteDialog(row: RequirementRow): void {
    requirementPendingDelete.value = row;
    deleteDialogOpen.value = true;
}

function executeDelete(): void {
    const row = requirementPendingDelete.value;

    if (row === null) {
        return;
    }

    router.delete(
        ProjectRequirementController.destroy.url({
            project: props.project.id,
            requirement: row.id,
        }),
    );
    requirementPendingDelete.value = null;
}

const deleteRequirementDescription = computed(() => {
    const row = requirementPendingDelete.value;

    if (row === null) {
        return '';
    }

    return `Delete "${row.title}"? This cannot be undone.`;
});
</script>

<template>
    <Head :title="`Requirements · ${project.name}`" />

    <ConfirmDestructiveDialog
        v-model:open="deleteDialogOpen"
        title="Delete requirement?"
        :description="deleteRequirementDescription"
        @confirm="executeDelete"
    />

    <div class="flex flex-col gap-8">
        <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <Heading
                    :title="`Requirements`"
                    :description="`Project ${project.name}${project.code ? ` (${project.code})` : ''}`"
                />
                <div class="flex flex-wrap gap-2">
                    <Button v-if="canCreateRequirements" as-child>
                        <Link :href="requirementsCreate.url(project.id)">Add requirement</Link>
                    </Button>
                    <Button v-if="canManageProject" variant="outline" as-child>
                        <Link :href="projectsEdit.url(project.id)">Edit project</Link>
                    </Button>
                </div>
            </div>

            <ListToolbar
                :model-value="filters.search"
                placeholder="Search title or description…"
                @update:model-value="onSearch"
            >
                <template #filters>
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="grid gap-1">
                            <Label class="text-xs text-muted-foreground" for="filter-review-status"
                                >Stage</Label
                            >
                            <TaskFormSelect
                                id="filter-review-status"
                                name="review_status"
                                class="w-[14rem]"
                                :model-value="reviewStatusFilter"
                                :options="reviewStatusSelectOptions"
                                placeholder="All stages"
                                none-label="All stages"
                                exclude-from-submit
                                @update:model-value="onReviewStatus"
                            />
                        </div>
                        <div class="grid gap-1">
                            <Label class="text-xs text-muted-foreground" for="filter-responsible"
                                >Responsible</Label
                            >
                            <TaskFormSelect
                                id="filter-responsible"
                                name="responsible_user_id"
                                class="min-w-[14rem]"
                                :model-value="responsibleFilter"
                                :options="responsibleSelectOptions"
                                placeholder="Anyone"
                                none-label="Anyone"
                                exclude-from-submit
                                @update:model-value="onResponsible"
                            />
                        </div>
                    </div>
                </template>
            </ListToolbar>
        </div>

        <div
            class="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <table class="w-full text-left text-sm">
                <thead
                    class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border"
                >
                    <tr>
                        <th class="px-4 py-3 font-medium">Title</th>
                        <th class="px-4 py-3 font-medium">Responsible</th>
                        <th class="px-4 py-3 font-medium">Reviewer</th>
                        <th class="px-4 py-3 font-medium">Review</th>
                        <th class="px-4 py-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in requirements.data" :key="row.id" class="border-b border-sidebar-border/70">
                        <td class="px-4 py-3 align-top">
                            <div class="font-medium">{{ row.title }}</div>
                            <p
                                v-if="row.description_preview"
                                class="mt-1 line-clamp-2 text-xs text-muted-foreground"
                            >
                                {{ row.description_preview }}
                            </p>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ row.responsible_user?.name ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ row.reviewer?.name ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            <div class="flex flex-col gap-1">
                                <span v-if="row.understanding_confirmed_at" class="inline-flex w-fit items-center rounded-md border border-transparent bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground">
                                    Confirmed
                                </span>
                                <span
                                    v-else-if="row.reviewed_at"
                                    class="inline-flex w-fit items-center rounded-md border border-input bg-background px-2 py-0.5 text-xs font-medium"
                                >
                                    Awaiting confirmation
                                </span>
                                <span v-else class="text-xs">—</span>
                                <span v-if="row.reviewed_at" class="text-xs">
                                    {{ new Date(row.reviewed_at).toLocaleString() }}
                                </span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-2">
                                <Button variant="ghost" size="sm" as-child>
                                    <Link
                                        :href="
                                            requirementsShow.url({
                                                project: project.id,
                                                requirement: row.id,
                                            })
                                        "
                                    >
                                        View
                                    </Link>
                                </Button>
                                <Button v-if="row.can_update" variant="ghost" size="sm" as-child>
                                    <Link
                                        :href="
                                            requirementsEdit.url({
                                                project: project.id,
                                                requirement: row.id,
                                            })
                                        "
                                    >
                                        Edit
                                    </Link>
                                </Button>
                                <Button
                                    v-if="row.can_delete"
                                    variant="ghost"
                                    size="sm"
                                    class="text-destructive"
                                    type="button"
                                    @click="openDeleteDialog(row)"
                                >
                                    Delete
                                </Button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="requirements.data.length === 0">
                        <td colspan="5" class="px-4 py-8 text-center text-muted-foreground">
                            No requirements yet.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav
            v-if="requirements.links.length > 3"
            class="flex flex-wrap justify-center gap-1 text-sm"
            aria-label="Pagination"
        >
            <template v-for="(link, i) in requirements.links" :key="i">
                <Button
                    v-if="link.url"
                    variant="outline"
                    size="sm"
                    as-child
                    :class="{ 'border-primary': link.active }"
                >
                    <Link :href="link.url" preserve-scroll v-html="link.label" />
                </Button>
                <Button
                    v-else
                    variant="outline"
                    size="sm"
                    disabled
                    :class="{ 'border-primary': link.active }"
                    v-html="link.label"
                />
            </template>
        </nav>
    </div>
</template>
