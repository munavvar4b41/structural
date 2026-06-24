<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ConfirmDestructiveDialog from '@/components/ConfirmDestructiveDialog.vue';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import TableIconAction from '@/components/TableIconAction.vue';
import RichTextViewer from '@/components/RichTextViewer.vue';
import { index as projectsIndex, show as projectsShow } from '@/routes/admin/projects/index';
import { show as projectTasksShow } from '@/routes/admin/projects/tasks/index';
import { index as projectCaseStudiesIndex } from '@/routes/admin/projects/case-studies/index';
import {
    destroy as caseStudiesDestroy,
    edit as caseStudiesEdit,
} from '@/routes/admin/projects/case-studies/index';
import { index as globalCaseStudiesIndex } from '@/routes/admin/case-studies/index';
import { show as attachmentShow } from '@/routes/admin/case-studies/attachments/index';

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

type Attachment = {
    id: number;
    original_name: string;
    mime: string;
    type: string;
    sort_order: number;
};

type CaseStudyDetail = {
    id: number;
    title: string;
    summary: string | null;
    client_issue: string | null;
    proposed_solution: string | null;
    resolution: string | null;
    workload_reduction_details: string | null;
    workload_hours_saved: string | number | null;
    workload_percentage_reduction: string | number | null;
    workload_period: string | null;
    workload_period_label: string | null;
    created_at: string | null;
    creator: UserBrief;
    task: { id: number; title: string } | null;
    attachments: Attachment[];
};

const props = defineProps<{
    project: ProjectSummary;
    case_study: CaseStudyDetail;
    can_update: boolean;
    can_delete: boolean;
}>();

defineOptions({
    layout: (pageProps: { project: ProjectSummary; case_study: CaseStudyDetail }) => ({
        breadcrumbs: [
            { title: 'Projects', href: projectsIndex.url() },
            { title: pageProps.project.name, href: projectsShow.url(pageProps.project.id) },
            { title: 'Case studies', href: projectCaseStudiesIndex.url(pageProps.project.id) },
            { title: pageProps.case_study.title, href: '#' },
        ],
    }),
});

const deleteDialogOpen = ref(false);

const workloadSummary = computed((): string | null => {
    const parts: string[] = [];
    const hours = props.case_study.workload_hours_saved;
    const percent = props.case_study.workload_percentage_reduction;
    const period = props.case_study.workload_period_label;

    if (hours !== null && hours !== '') {
        parts.push(`${hours} hours saved`);
    }

    if (percent !== null && percent !== '') {
        parts.push(`${percent}% reduction`);
    }

    if (period !== null && period !== '') {
        parts.push(period.toLowerCase());
    }

    return parts.length > 0 ? parts.join(' · ') : null;
});

const imageAttachments = computed(() =>
    props.case_study.attachments.filter((attachment) => attachment.type === 'image'),
);

const documentAttachments = computed(() =>
    props.case_study.attachments.filter((attachment) => attachment.type === 'document'),
);

function attachmentUrl(attachmentId: number): string {
    return attachmentShow.url({
        caseStudy: props.case_study.id,
        attachment: attachmentId,
    });
}

function executeDelete(): void {
    router.delete(
        caseStudiesDestroy.url({
            project: props.project.id,
            case_study: props.case_study.id,
        }),
    );
}
</script>

<template>

    <Head :title="`${case_study.title} · Case study`" />

    <ConfirmDestructiveDialog v-model:open="deleteDialogOpen" title="Delete case study?"
        :description="`Delete &quot;${case_study.title}&quot;? This cannot be undone.`" @confirm="executeDelete" />

    <div class="flex flex-col gap-8">
        <PageHeader :title="case_study.title" :description="case_study.summary ?? undefined">
            <template #actions>
                <div class="flex flex-wrap gap-1">
                    <TableIconAction
                        v-if="can_update"
                        icon="pencil"
                        label="Edit case study"
                        :href="caseStudiesEdit.url({
                            project: project.id,
                            case_study: case_study.id,
                        })"
                    />
                    <TableIconAction
                        v-if="can_delete"
                        icon="trash"
                        label="Delete case study"
                        destructive
                        @click="deleteDialogOpen = true"
                    />
                    <TableIconAction
                        icon="list"
                        label="All case studies"
                        :href="globalCaseStudiesIndex.url()"
                    />
                </div>
            </template>
        </PageHeader>

        <GlassCard class="p-6">
            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-sm text-muted-foreground">Project</dt>
                    <dd class="font-medium">
                        <Link :href="projectsShow.url(project.id)" class="hover:underline">
                            {{ project.name }}
                        </Link>
                    </dd>
                </div>
                <div v-if="case_study.task">
                    <dt class="text-sm text-muted-foreground">Related task</dt>
                    <dd class="font-medium">
                        <Link :href="projectTasksShow.url({
                            project: project.id,
                            task: case_study.task.id,
                        })
                            " class="hover:underline">
                            {{ case_study.task.title }}
                        </Link>
                    </dd>
                </div>
                <div>
                    <dt class="text-sm text-muted-foreground">Created by</dt>
                    <dd>{{ case_study.creator?.name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-muted-foreground">Created</dt>
                    <dd>
                        {{ case_study.created_at ? new Date(case_study.created_at).toLocaleString() : '—' }}
                    </dd>
                </div>
            </dl>
        </GlassCard>

        <GlassCard class="flex flex-col gap-6 p-6">
            <h2 class="text-lg font-semibold">The client problem</h2>
            <RichTextViewer v-if="case_study.client_issue" :json="case_study.client_issue" />
            <p v-else class="text-sm text-muted-foreground">Not documented.</p>
        </GlassCard>

        <GlassCard class="flex flex-col gap-6 p-6">
            <h2 class="text-lg font-semibold">Our solution</h2>
            <RichTextViewer v-if="case_study.proposed_solution" :json="case_study.proposed_solution" />
            <p v-else class="text-sm text-muted-foreground">Not documented.</p>
        </GlassCard>

        <GlassCard class="flex flex-col gap-6 p-6">
            <h2 class="text-lg font-semibold">Implementation and results</h2>
            <RichTextViewer v-if="case_study.resolution" :json="case_study.resolution" />
            <p v-else class="text-sm text-muted-foreground">Not documented.</p>
        </GlassCard>

        <GlassCard class="flex flex-col gap-6 p-6">
            <h2 class="text-lg font-semibold">Workload impact</h2>
            <section v-if="workloadSummary" class="grid gap-2">
                <h3 class="text-sm font-medium text-muted-foreground">Workload reduction</h3>
                <p class="text-sm font-medium">{{ workloadSummary }}</p>
            </section>
            <section class="grid gap-2">
                <h3 class="text-sm font-medium text-muted-foreground">Workload reduction details</h3>
                <RichTextViewer v-if="case_study.workload_reduction_details"
                    :json="case_study.workload_reduction_details" />
                <p v-else class="text-sm text-muted-foreground">Not documented.</p>
            </section>
        </GlassCard>

        <GlassCard v-if="case_study.attachments.length > 0" class="flex flex-col gap-4 p-6">
            <h2 class="text-lg font-semibold">Attachments</h2>
            <div v-if="imageAttachments.length > 0" class="grid gap-4 sm:grid-cols-2">
                <a v-for="attachment in imageAttachments" :key="attachment.id" :href="attachmentUrl(attachment.id)"
                    target="_blank" rel="noopener noreferrer"
                    class="overflow-hidden rounded-lg border border-border/60">
                    <img :src="attachmentUrl(attachment.id)" :alt="attachment.original_name"
                        class="max-h-64 w-full object-cover" />
                </a>
            </div>
            <ul v-if="documentAttachments.length > 0" class="grid gap-2">
                <li v-for="attachment in documentAttachments" :key="attachment.id">
                    <a :href="attachmentUrl(attachment.id)" target="_blank" rel="noopener noreferrer"
                        class="text-sm font-medium hover:underline">
                        {{ attachment.original_name }}
                    </a>
                </li>
            </ul>
        </GlassCard>
    </div>
</template>
