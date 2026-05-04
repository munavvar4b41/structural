<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import ProjectRequirementController from '@/actions/App/Http/Controllers/Admin/ProjectRequirementController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import RequirementRichTextViewer from '@/components/RequirementRichTextViewer.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    edit as requirementsEdit,
    index as requirementsIndex,
    show as requirementsShow,
} from '@/routes/admin/projects/requirements/index';
import { edit as projectsEdit, index as projectsIndex } from '@/routes/admin/projects/index';

type UserBrief = {
    id: number;
    name: string;
    email: string;
} | null;

type ProjectSummary = {
    id: number;
    name: string;
    code: string | null;
};

type RequirementDetail = {
    id: number;
    title: string;
    description: string | null;
    reviewed_at: string | null;
    reviewed_at_for_input: string | null;
    created_at: string | null;
    updated_at: string | null;
    creator: UserBrief;
    responsible_user: UserBrief;
    reviewer: UserBrief;
};

const props = defineProps<{
    project: ProjectSummary;
    requirement: RequirementDetail;
    can_update: boolean;
    can_mark_reviewed: boolean;
    can_manage_project: boolean;
}>();

const reviewDialogOpen = ref(false);

defineOptions({
    layout: (pageProps: {
        project: ProjectSummary;
        requirement: RequirementDetail;
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
            { title: 'Requirements', href: requirementsIndex.url(pageProps.project.id) },
            {
                title: pageProps.requirement.title,
                href: requirementsShow.url({
                    project: pageProps.project.id,
                    requirement: pageProps.requirement.id,
                }),
            },
        ],
    }),
});
</script>

<template>
    <Head :title="`${requirement.title} · ${project.name}`" />

    <div class="flex flex-col gap-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <Heading :title="requirement.title" :description="`Project ${project.name}`" />
            <div class="flex flex-wrap gap-2">
                <Button v-if="can_update" as-child>
                    <Link
                        :href="
                            requirementsEdit.url({
                                project: project.id,
                                requirement: requirement.id,
                            })
                        "
                    >
                        Edit
                    </Link>
                </Button>
                <Button
                    v-if="can_mark_reviewed"
                    type="button"
                    variant="secondary"
                    @click="reviewDialogOpen = true"
                >
                    Set review time
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="requirementsIndex.url(project.id)">Back to list</Link>
                </Button>
            </div>
        </div>

        <div class="grid max-w-3xl gap-6">
            <Card>
                <CardHeader>
                    <CardTitle>People</CardTitle>
                    <CardDescription>Ownership and review</CardDescription>
                </CardHeader>
                <CardContent class="grid gap-3 text-sm">
                    <div>
                        <span class="text-muted-foreground">Created by</span>
                        {{ requirement.creator?.name ?? '—' }}
                    </div>
                    <div>
                        <span class="text-muted-foreground">Responsible</span>
                        {{ requirement.responsible_user?.name ?? '—' }}
                    </div>
                    <div>
                        <span class="text-muted-foreground">Reviewer</span>
                        {{ requirement.reviewer?.name ?? '—' }}
                    </div>
                    <div>
                        <span class="text-muted-foreground">Reviewed</span>
                        {{
                            requirement.reviewed_at
                                ? new Date(requirement.reviewed_at).toLocaleString()
                                : '—'
                        }}
                    </div>
                    <div class="text-muted-foreground">
                        Created {{ requirement.created_at ? new Date(requirement.created_at).toLocaleString() : '—' }}
                        · Updated
                        {{ requirement.updated_at ? new Date(requirement.updated_at).toLocaleString() : '—' }}
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Description</CardTitle>
                </CardHeader>
                <CardContent>
                    <RequirementRichTextViewer v-if="requirement.description" :json="requirement.description" />
                    <p v-else class="text-sm text-muted-foreground">No description.</p>
                </CardContent>
            </Card>
        </div>

        <Dialog v-if="can_mark_reviewed" v-model:open="reviewDialogOpen">
            <DialogContent class="sm:max-w-md" :show-close-button="true">
                <DialogHeader>
                    <DialogTitle>Review status</DialogTitle>
                    <DialogDescription>
                        Set when this requirement was checked. Leave the field empty and save to clear the
                        review time.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    v-bind="
                        ProjectRequirementController.markReviewed.form({
                            project: project.id,
                            requirement: requirement.id,
                        })
                    "
                    class="grid gap-4"
                    @success="reviewDialogOpen = false"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="review-dialog-reviewed-at">Reviewed at</Label>
                        <Input
                            id="review-dialog-reviewed-at"
                            name="reviewed_at"
                            type="datetime-local"
                            :default-value="requirement.reviewed_at_for_input ?? ''"
                        />
                        <InputError :message="errors.reviewed_at" />
                    </div>
                    <DialogFooter class="gap-2 sm:justify-end">
                        <DialogClose as-child>
                            <Button type="button" variant="outline">Cancel</Button>
                        </DialogClose>
                        <Button type="submit" :disabled="processing">Save</Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
