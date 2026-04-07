<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import ProjectController from '@/actions/App/Http/Controllers/Admin/ProjectController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    create as projectsCreate,
    edit as projectsEdit,
    index as projectsIndex,
} from '@/routes/admin/projects/index';

type ProjectRow = {
    id: number;
    name: string;
    code: string | null;
    description: string | null;
    teams_count: number;
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
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: projectsIndex() }],
    },
});

defineProps<Props>();

function confirmDelete(project: ProjectRow): void {
    if (!confirm(`Delete project "${project.name}"? This cannot be undone.`)) {
        return;
    }

    router.delete(ProjectController.destroy.url(project.id));
}
</script>

<template>
    <Head title="Projects" />

    <div class="flex flex-col gap-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <Heading
                title="Projects"
                description="Projects visible to your assigned teams"
            />
            <Button v-if="canManageProjects" as-child>
                <Link :href="projectsCreate()">Add project</Link>
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
                        <th class="px-4 py-3 font-medium">Code</th>
                        <th class="px-4 py-3 font-medium">Teams</th>
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
                            {{ project.teams_count }}
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
                                    @click="confirmDelete(project)"
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
