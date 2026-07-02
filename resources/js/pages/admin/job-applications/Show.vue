<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import JobApplicationController from '@/actions/App/Http/Controllers/Admin/JobApplicationController';
import JobApplicationResumeController from '@/actions/App/Http/Controllers/Admin/JobApplicationResumeController';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import InputError from '@/components/InputError.vue';
import TableIconAction from '@/components/TableIconAction.vue';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { show as jobApplicationsShow } from '@/routes/admin/job-applications/index';
import {
    applications as jobPostingsApplications,
    index as jobPostingsIndex,
} from '@/routes/admin/job-postings/index';

type Application = {
    id: number;
    candidate_name: string;
    candidate_email: string;
    candidate_phone: string;
    linkedin_url: string | null;
    portfolio_url: string | null;
    cover_letter: string | null;
    skills: string;
    years_of_experience: number;
    salary_expectation: string;
    preferred_location: string;
    status: string;
    status_label: string;
    rejection_reason: string | null;
    applied_at: string;
    reviewed_at: string | null;
    resume_original_name: string;
    job_posting: { id: number; title: string; slug: string };
    reviewed_by: { id: number; name: string } | null;
    can_advance: boolean;
    next_stage_label: string | null;
};

const props = defineProps<{
    application: Application;
}>();

defineOptions({
    layout: (pageProps: { application: Application }) => ({
        breadcrumbs: [
            { title: 'Job postings', href: jobPostingsIndex() },
            {
                title: 'Applications',
                href: jobPostingsApplications.url(pageProps.application.job_posting.id),
            },
            {
                title: pageProps.application.candidate_name,
                href: jobApplicationsShow.url(pageProps.application.id),
            },
        ],
    }),
});

const rejectForm = useForm({
    rejection_reason: '',
});

function advanceApplication(): void {
    router.patch(JobApplicationController.advance.url(props.application.id));
}

function submitReject(): void {
    rejectForm.patch(JobApplicationController.reject.url(props.application.id), {
        preserveScroll: true,
        onSuccess: () => rejectForm.reset(),
    });
}

function formatDate(iso: string): string {
    return new Date(iso).toLocaleString();
}
</script>

<template>

    <Head :title="`${application.candidate_name} · Application`" />

    <div class="flex flex-col gap-6">
        <PageHeader :title="application.candidate_name"
            :description="`Application for ${application.job_posting.title}`">
            <template #actions>
                <div class="flex flex-wrap gap-1">
                    <TableIconAction
                        v-if="application.can_advance && application.next_stage_label"
                        icon="arrow-right"
                        :label="`Advance to ${application.next_stage_label}`"
                        @click="advanceApplication"
                    />
                    <TableIconAction
                        icon="download"
                        label="Download resume"
                        external
                        :href="JobApplicationResumeController.show.url(application.id)"
                    />
                    <TableIconAction
                        icon="arrow-left"
                        label="Back to list"
                        :href="jobPostingsApplications(application.job_posting.id)"
                    />
                </div>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-2">
            <GlassCard class="p-6">
                <h2 class="mb-4 text-lg font-semibold">Status</h2>
                <dl class="grid gap-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Current stage</dt>
                        <dd class="font-medium">{{ application.status_label }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Applied</dt>
                        <dd>{{ formatDate(application.applied_at) }}</dd>
                    </div>
                    <div v-if="application.reviewed_at" class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Last reviewed</dt>
                        <dd>{{ formatDate(application.reviewed_at) }}</dd>
                    </div>
                    <div v-if="application.reviewed_by" class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Reviewed by</dt>
                        <dd>{{ application.reviewed_by.name }}</dd>
                    </div>
                    <div v-if="application.rejection_reason" class="flex flex-col gap-1">
                        <dt class="text-muted-foreground">Rejection reason</dt>
                        <dd>{{ application.rejection_reason }}</dd>
                    </div>
                </dl>
            </GlassCard>

            <GlassCard class="p-6">
                <h2 class="mb-4 text-lg font-semibold">Contact</h2>
                <dl class="grid gap-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Email</dt>
                        <dd>{{ application.candidate_email }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Phone</dt>
                        <dd>{{ application.candidate_phone }}</dd>
                    </div>
                    <div v-if="application.linkedin_url" class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">LinkedIn</dt>
                        <dd>
                            <a :href="application.linkedin_url" class="text-primary underline" target="_blank"
                                rel="noopener noreferrer">
                                Profile
                            </a>
                        </dd>
                    </div>
                    <div v-if="application.portfolio_url" class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Portfolio</dt>
                        <dd>
                            <a :href="application.portfolio_url" class="text-primary underline" target="_blank"
                                rel="noopener noreferrer">
                                Website
                            </a>
                        </dd>
                    </div>
                </dl>
            </GlassCard>

            <GlassCard class="p-6 lg:col-span-2">
                <h2 class="mb-4 text-lg font-semibold">Profile</h2>
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-muted-foreground">Skills</dt>
                        <dd class="mt-1">{{ application.skills }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Years of experience</dt>
                        <dd class="mt-1">{{ application.years_of_experience }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Salary expectation</dt>
                        <dd class="mt-1">{{ application.salary_expectation }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Preferred location</dt>
                        <dd class="mt-1">{{ application.preferred_location }}</dd>
                    </div>
                    <div v-if="application.cover_letter" class="sm:col-span-2">
                        <dt class="text-muted-foreground">Cover letter</dt>
                        <dd class="mt-1 whitespace-pre-wrap">{{ application.cover_letter }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Resume</dt>
                        <dd class="mt-1">{{ application.resume_original_name }}</dd>
                    </div>
                </dl>
            </GlassCard>

            <GlassCard v-if="application.can_advance" class="p-6 lg:col-span-2">
                <h2 class="mb-4 text-lg font-semibold">Reject application</h2>
                <div class="grid max-w-xl gap-4">
                    <div class="grid gap-2">
                        <Label for="rejection_reason">Reason (optional)</Label>
                        <textarea id="rejection_reason" v-model="rejectForm.rejection_reason" rows="3" :class="cn(
                            'border-input w-full rounded-xl border bg-transparent px-3 py-2 text-sm shadow-xs',
                            'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                        )" />
                        <InputError :message="rejectForm.errors.rejection_reason" />
                    </div>
                    <TableIconAction
                        icon="x"
                        label="Reject application"
                        destructive
                        :disabled="rejectForm.processing"
                        @click="submitReject"
                    />
                </div>
            </GlassCard>
        </div>
    </div>
</template>
