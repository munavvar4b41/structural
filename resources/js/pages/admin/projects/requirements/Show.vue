<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import ProjectRequirementController from '@/actions/App/Http/Controllers/Admin/ProjectRequirementController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import RequirementRichTextEditor from '@/components/RequirementRichTextEditor.vue';
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
import { Label } from '@/components/ui/label';
import { emptyTipTapDocumentJson } from '@/lib/tiptapDocument';
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
    review_understanding: string | null;
    reviewed_at: string | null;
    understanding_confirmed_at: string | null;
    understanding_confirmed_by: UserBrief;
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
    can_confirm_understanding: boolean;
    can_manage_project: boolean;
}>();

const reviewDialogOpen = ref(false);

const reviewUnderstandingJson = ref(
    props.requirement.review_understanding ?? emptyTipTapDocumentJson(),
);

watch(
    () => props.requirement.review_understanding,
    (v) => {
        reviewUnderstandingJson.value = v ?? emptyTipTapDocumentJson();
    },
);

watch(reviewDialogOpen, (open) => {
    if (open) {
        reviewUnderstandingJson.value =
            props.requirement.review_understanding ?? emptyTipTapDocumentJson();
    }
});

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
                    {{ requirement.review_understanding ? 'Update review understanding' : 'Submit review understanding' }}
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
                        <span class="text-muted-foreground">Reviewed at</span>
                        {{
                            requirement.reviewed_at
                                ? new Date(requirement.reviewed_at).toLocaleString()
                                : '—'
                        }}
                    </div>
                    <div>
                        <span class="text-muted-foreground">Understanding confirmed</span>
                        <template v-if="requirement.understanding_confirmed_at">
                            {{ new Date(requirement.understanding_confirmed_at).toLocaleString() }}
                            <span class="text-muted-foreground">
                                ({{ requirement.understanding_confirmed_by?.name ?? '—' }})
                            </span>
                        </template>
                        <template v-else>—</template>
                    </div>
                    <div class="text-muted-foreground">
                        Created {{ requirement.created_at ? new Date(requirement.created_at).toLocaleString() : '—' }}
                        · Updated
                        {{ requirement.updated_at ? new Date(requirement.updated_at).toLocaleString() : '—' }}
                    </div>
                </CardContent>
            </Card>

            <Card v-if="requirement.review_understanding">
                <CardHeader>
                    <CardTitle>Review understanding</CardTitle>
                    <CardDescription>What the reviewing party recorded about this requirement</CardDescription>
                </CardHeader>
                <CardContent>
                    <RequirementRichTextViewer :json="requirement.review_understanding" />
                </CardContent>
            </Card>

            <Card v-if="can_confirm_understanding">
                <CardHeader>
                    <CardTitle>Confirm understanding</CardTitle>
                    <CardDescription>
                        Confirm that this matches your intent as creator or responsible person.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Form
                        v-bind="
                            ProjectRequirementController.confirmUnderstanding.form({
                                project: project.id,
                                requirement: requirement.id,
                            })
                        "
                        class="flex flex-col gap-3"
                        v-slot="{ processing }"
                    >
                        <Button type="submit" :disabled="processing">Confirm understanding</Button>
                    </Form>
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
            <DialogContent class="sm:max-w-2xl" :show-close-button="true">
                <DialogHeader>
                    <DialogTitle>Review understanding</DialogTitle>
                    <DialogDescription>
                        Describe how you interpret this requirement. Saving records the time of review and clears any
                        prior confirmation until the owner confirms again.
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
                        <Label for="review-understanding-editor">Your understanding</Label>
                        <RequirementRichTextEditor
                            id="review-understanding-editor"
                            v-model="reviewUnderstandingJson"
                            input-name="review_understanding"
                        />
                        <InputError :message="errors.review_understanding" />
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
