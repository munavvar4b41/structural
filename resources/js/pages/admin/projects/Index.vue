<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import ProjectController from '@/actions/App/Http/Controllers/Admin/ProjectController';
import ConfirmDestructiveDialog from '@/components/ConfirmDestructiveDialog.vue';
import Heading from '@/components/Heading.vue';
import ListToolbar from '@/components/ListToolbar.vue';
import TaskFormSelect from '@/components/TaskFormSelect.vue';
import { routerReloadOnly, stripFilterParams } from '@/composables/useServerFilters';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    create as projectsCreate,
    edit as projectsEdit,
    index as projectsIndex,
} from '@/routes/admin/projects/index';
import { index as projectRequirementsIndex } from '@/routes/admin/projects/requirements/index';
import { index as projectTasksIndex } from '@/routes/admin/projects/tasks/index';

type ClientUserSummary = {
    id: number;
    name: string;
    email: string;
};

type ProjectRow = {
    id: number;
    name: string;
    code: string | null;
    description: string | null;
    teams_count: number;
    client_user: ClientUserSummary | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedProjects = {
    data: ProjectRow[];
    links: PaginationLink[];
};

type Props = {
    projects: PaginatedProjects;
    canManageProjects: boolean;
    filters: {
        search: string;
        team_id: string;
        lead_user_id: string;
    };
    filter_options: {
        teams: { value: number; label: string }[];
        leads: { value: number; label: string }[];
    };
    show_lead_filter: boolean;
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: projectsIndex.url() }],
    },
});

const props = defineProps<Props>();

const deleteDialogOpen = ref(false);
const projectPendingDelete = ref<ProjectRow | null>(null);

const teamFilter = ref(props.filters.team_id);
const leadFilter = ref(props.filters.lead_user_id);

watch(
    () => props.filters,
    (f) => {
        teamFilter.value = f.team_id;
        leadFilter.value = f.lead_user_id;
    },
);

const teamSelectOptions = computed(() =>
    props.filter_options.teams.map((t) => ({
        value: String(t.value),
        label: t.label,
    })),
);

const leadSelectOptions = computed(() =>
    props.filter_options.leads.map((l) => ({
        value: String(l.value),
        label: l.label,
    })),
);

function reload(
    overrides: Partial<Record<'search' | 'team_id' | 'lead_user_id', string>> = {},
): void {
    routerReloadOnly(
        projectsIndex.url({
            query: stripFilterParams({
                search: props.filters.search,
                team_id: props.filters.team_id,
                lead_user_id: props.filters.lead_user_id,
                ...overrides,
                page: 1,
            }),
        }),
        ['projects', 'filters', 'filter_options', 'show_lead_filter'],
    );
}

function onSearch(search: string): void {
    reload({ search });
}

function onTeam(v: string): void {
    reload({ team_id: v });
}

function onLead(v: string): void {
    reload({ lead_user_id: v });
}

function openDeleteDialog(project: ProjectRow): void {
    projectPendingDelete.value = project;
    deleteDialogOpen.value = true;
}

function executeDelete(): void {
    const project = projectPendingDelete.value;

    if (project === null) {
        return;
    }

    router.delete(ProjectController.destroy.url(project.id));
    projectPendingDelete.value = null;
}

const deleteProjectDescription = computed(() => {
    const project = projectPendingDelete.value;

    if (project === null) {
        return '';
    }

    return `Delete "${project.name}"? This cannot be undone.`;
});
</script>

<template>
    <Head title="Projects" />

    <ConfirmDestructiveDialog
        v-model:open="deleteDialogOpen"
        title="Delete project?"
        :description="deleteProjectDescription"
        @confirm="executeDelete"
    />

    <div class="flex flex-col gap-8">
        <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <Heading
                    title="Projects"
                    description="Projects you can access based on your role and assignments"
                />
                <Button v-if="canManageProjects" as-child>
                    <Link :href="projectsCreate()">Add project</Link>
                </Button>
            </div>

            <ListToolbar
                :model-value="filters.search"
                placeholder="Search name, code, description…"
                @update:model-value="onSearch"
            >
                <template #filters>
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="grid gap-1">
                            <Label class="text-xs text-muted-foreground" for="filter-team"
                                >Team</Label
                            >
                            <TaskFormSelect
                                id="filter-team"
                                name="team_id"
                                class="w-[12rem]"
                                :model-value="teamFilter"
                                :options="teamSelectOptions"
                                placeholder="All teams"
                                none-label="All teams"
                                exclude-from-submit
                                @update:model-value="onTeam"
                            />
                        </div>
                        <div v-if="show_lead_filter" class="grid gap-1">
                            <Label class="text-xs text-muted-foreground" for="filter-lead"
                                >Lead</Label
                            >
                            <TaskFormSelect
                                id="filter-lead"
                                name="lead_user_id"
                                class="min-w-[14rem]"
                                :model-value="leadFilter"
                                :options="leadSelectOptions"
                                placeholder="All leads"
                                none-label="All leads"
                                exclude-from-submit
                                @update:model-value="onLead"
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
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">Code</th>
                        <th class="px-4 py-3 font-medium">Client</th>
                        <th class="px-4 py-3 font-medium">Teams</th>
                        <th class="px-4 py-3 font-medium">Requirements</th>
                        <th class="px-4 py-3 font-medium">Tasks</th>
                        <th
                            v-if="canManageProjects"
                            class="px-4 py-3 font-medium text-right"
                        >
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="project in projects.data" :key="project.id">
                        <td class="px-4 py-3">{{ project.name }}</td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ project.code ?? '-' }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            <template v-if="project.client_user">
                                {{ project.client_user.name }}
                                <span class="text-xs">({{ project.client_user.email }})</span>
                            </template>
                            <template v-else>—</template>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ project.teams_count }}
                        </td>
                        <td class="px-4 py-3">
                            <Button variant="link" class="h-auto p-0" as-child>
                                <Link :href="projectRequirementsIndex.url(project.id)">View</Link>
                            </Button>
                        </td>
                        <td class="px-4 py-3">
                            <Button variant="link" class="h-auto p-0" as-child>
                                <Link :href="projectTasksIndex.url(project.id)">View</Link>
                            </Button>
                        </td>
                        <td v-if="canManageProjects" class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-2">
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="projectsEdit(project.id)">Edit</Link>
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="text-destructive hover:bg-destructive/10"
                                    type="button"
                                    @click="openDeleteDialog(project)"
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
            v-if="projects.links.length > 3"
            class="flex flex-wrap items-center justify-center gap-1"
            aria-label="Pagination"
        >
            <template v-for="(link, i) in projects.links" :key="i">
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
