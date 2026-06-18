<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import JobApplicationPublicController from '@/actions/App/Http/Controllers/JobApplicationPublicController';
import FormField from '@/components/dashboard/FormField.vue';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import InputError from '@/components/InputError.vue';
import RichTextViewer from '@/components/RichTextViewer.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { home } from '@/routes';
import { index as careersIndex } from '@/routes/careers/index';

type JobPosting = {
    slug: string;
    title: string;
    location: string;
    employment_type_label: string;
    team_name: string | null;
    description: string | null;
    requirements: string | null;
};

const props = defineProps<{
    job_posting: JobPosting;
}>();

const resumeInput = ref<HTMLInputElement | null>(null);

const form = useForm({
    candidate_name: '',
    candidate_email: '',
    candidate_phone: '',
    linkedin_url: '',
    portfolio_url: '',
    cover_letter: '',
    skills: '',
    years_of_experience: '',
    salary_expectation: '',
    preferred_location: '',
    resume: null as File | null,
});

function onResumeChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;
    form.resume = file;
}

function submitApplication(): void {
    form.post(JobApplicationPublicController.store.url({ jobPosting: props.job_posting.slug }), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            if (resumeInput.value) {
                resumeInput.value.value = '';
            }
        },
    });
}
</script>

<template>

    <Head :title="`${job_posting.title} · Careers`" />

    <div class="page-gradient flex min-h-svh flex-col p-6 md:p-10">
        <header class="mb-10 flex items-center justify-between">
            <Link :href="home()" class="text-lg font-semibold tracking-tight text-foreground">
                Structural
            </Link>
            <Button variant="outline" as-child>
                <Link :href="careersIndex()">All positions</Link>
            </Button>
        </header>

        <div class="mx-auto flex w-full max-w-3xl flex-col gap-8">
            <div>
                <h1 class="text-3xl font-semibold tracking-tight">{{ job_posting.title }}</h1>
                <p class="mt-2 text-sm text-muted-foreground">
                    {{ job_posting.location }} · {{ job_posting.employment_type_label }}
                    <span v-if="job_posting.team_name"> · {{ job_posting.team_name }}</span>
                </p>
            </div>

            <GlassCard v-if="job_posting.description" class="p-6">
                <h2 class="mb-4 text-lg font-semibold">About the role</h2>
                <RichTextViewer :json="job_posting.description" />
            </GlassCard>

            <GlassCard v-if="job_posting.requirements" class="p-6">
                <h2 class="mb-4 text-lg font-semibold">Requirements</h2>
                <RichTextViewer :json="job_posting.requirements" />
            </GlassCard>

            <GlassCard class="p-6">
                <h2 class="mb-2 text-lg font-semibold">Apply for this position</h2>
                <p class="mb-6 text-sm text-muted-foreground">
                    Fill in your details and upload your resume (PDF, DOC, or DOCX, max 5 MB).
                </p>

                <form class="grid gap-6" @submit.prevent="submitApplication">
                    <div class="grid gap-6 sm:grid-cols-2">
                        <FormField label="Full name" html-for="candidate_name" :error="form.errors.candidate_name"
                            required>
                            <Input id="candidate_name" v-model="form.candidate_name" type="text" required />
                        </FormField>
                        <FormField label="Email" html-for="candidate_email" :error="form.errors.candidate_email"
                            required>
                            <Input id="candidate_email" v-model="form.candidate_email" type="email" required />
                        </FormField>
                        <FormField label="Phone" html-for="candidate_phone" :error="form.errors.candidate_phone"
                            required>
                            <Input id="candidate_phone" v-model="form.candidate_phone" type="tel" required />
                        </FormField>
                        <FormField label="Years of experience" html-for="years_of_experience"
                            :error="form.errors.years_of_experience" required>
                            <Input id="years_of_experience" v-model="form.years_of_experience" type="number" min="0"
                                max="50" required />
                        </FormField>
                        <FormField label="Salary expectation" html-for="salary_expectation"
                            :error="form.errors.salary_expectation" required>
                            <Input id="salary_expectation" v-model="form.salary_expectation" type="text"
                                placeholder="e.g. 80000 USD" required />
                        </FormField>
                        <FormField label="Preferred location" html-for="preferred_location"
                            :error="form.errors.preferred_location" required>
                            <Input id="preferred_location" v-model="form.preferred_location" type="text" required />
                        </FormField>
                    </div>

                    <FormField label="Skills" html-for="skills" :error="form.errors.skills" required>
                        <textarea id="skills" v-model="form.skills" rows="3" required :class="cn(
                            'border-input w-full rounded-xl border bg-transparent px-3 py-2 text-sm shadow-xs',
                            'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                        )" />
                    </FormField>

                    <div class="grid gap-6 sm:grid-cols-2">
                        <FormField label="LinkedIn URL" html-for="linkedin_url" :error="form.errors.linkedin_url">
                            <Input id="linkedin_url" v-model="form.linkedin_url" type="url" placeholder="https://" />
                        </FormField>
                        <FormField label="Portfolio URL" html-for="portfolio_url" :error="form.errors.portfolio_url">
                            <Input id="portfolio_url" v-model="form.portfolio_url" type="url" placeholder="https://" />
                        </FormField>
                    </div>

                    <FormField label="Cover letter" html-for="cover_letter" :error="form.errors.cover_letter">
                        <textarea id="cover_letter" v-model="form.cover_letter" rows="4" :class="cn(
                            'border-input w-full rounded-xl border bg-transparent px-3 py-2 text-sm shadow-xs',
                            'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                        )" />
                    </FormField>

                    <div class="grid gap-2">
                        <label for="resume" class="text-sm font-medium">
                            Resume <span class="text-destructive">*</span>
                        </label>
                        <input id="resume" ref="resumeInput" type="file"
                            accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                            required
                            class="text-sm file:mr-4 file:rounded-lg file:border-0 file:bg-primary file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary-foreground"
                            @change="onResumeChange" />
                        <InputError :message="form.errors.resume" />
                    </div>

                    <Button type="submit" :disabled="form.processing">
                        Submit application
                    </Button>
                </form>
            </GlassCard>
        </div>
    </div>
</template>
