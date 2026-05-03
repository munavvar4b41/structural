<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import ProjectRequirementController from '@/actions/App/Http/Controllers/Admin/ProjectRequirementController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    create as requirementsCreate,
    edit as requirementsEdit,
    index as requirementsIndex,
} from '@/routes/admin/projects/requirements/index';
import { edit as projectsEdit, index as projectsIndex } from '@/routes/admin/projects/index';

type UserBrief = {
    id: number;
    name: string;
    email: string;
} | null;

type RequirementRow = {
    id: number;
    title: string;
    description: string | null;
    reviewed_at: string | null;
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
};

const props = defineProps<{
    project: ProjectSummary;
    requirements: PaginatedRequirements;
    canCreateRequirements: boolean;
    canManageProject: boolean;
}>();

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

function confirmDelete(row: RequirementRow): void {
    if (!confirm(`Delete requirement "${row.title}"? This cannot be undone.`)) {
        return;
    }

    router.delete(
        ProjectRequirementController.destroy.url({
            project: props.project.id,
            requirement: row.id,
        }),
    );
}
</script>

<template>
    <Head :title="`Requirements · ${project.name}`" />

    <div class="flex flex-col gap-8">
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
                        <th class="px-4 py-3 font-medium">Reviewed</th>
                        <th class="px-4 py-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in requirements.data" :key="row.id" class="border-b border-sidebar-border/70">
                        <td class="px-4 py-3 align-top">
                            <div class="font-medium">{{ row.title }}</div>
                            <p
                                v-if="row.description"
                                class="mt-1 line-clamp-2 text-xs text-muted-foreground"
                            >
                                {{ row.description }}
                            </p>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ row.responsible_user?.name ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ row.reviewer?.name ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ row.reviewed_at ? new Date(row.reviewed_at).toLocaleString() : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-2">
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
                                    @click="confirmDelete(row)"
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
