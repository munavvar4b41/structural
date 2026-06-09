<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import JobPostingController from '@/actions/App/Http/Controllers/Admin/JobPostingController';
import FormField from '@/components/dashboard/FormField.vue';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { emptyTipTapDocumentJson } from '@/lib/tiptapDocument';
import {
    edit as jobPostingsEdit,
    index as jobPostingsIndex,
} from '@/routes/admin/job-postings/index';

type Option = {
    value: string;
    label: string;
};

type TeamOption = {
    value: number;
    label: string;
};

type JobPosting = {
    id: number;
    slug: string;
    title: string;
    team_id: number | null;
    location: string;
    employment_type: string;
    description: string | null;
    requirements: string | null;
    status: string;
    published_at: string | null;
    closes_at: string | null;
};

const props = defineProps<{
    job_posting: JobPosting;
    teams: TeamOption[];
    status_options: Option[];
    employment_type_options: Option[];
}>();

defineOptions({
    layout: (pageProps: { job_posting: JobPosting }) => ({
        breadcrumbs: [
            { title: 'Job postings', href: jobPostingsIndex() },
            {
                title: 'Edit posting',
                href: jobPostingsEdit.url(pageProps.job_posting.id),
            },
        ],
    }),
});

const descriptionJson = ref(props.job_posting.description ?? emptyTipTapDocumentJson());
const requirementsJson = ref(props.job_posting.requirements ?? emptyTipTapDocumentJson());
const teamId = ref(
    props.job_posting.team_id !== null ? String(props.job_posting.team_id) : '',
);
const status = ref(props.job_posting.status);
const employmentType = ref(props.job_posting.employment_type);

const teamLabel = computed(() => {
    if (teamId.value === '') {
        return 'No team';
    }

    return props.teams.find((t) => String(t.value) === teamId.value)?.label ?? 'Select team';
});

const statusLabel = computed(
    () => props.status_options.find((o) => o.value === status.value)?.label ?? 'Select status',
);

const employmentTypeLabel = computed(
    () =>
        props.employment_type_options.find((o) => o.value === employmentType.value)?.label ??
        'Select type',
);
</script>

<template>

    <Head :title="`Edit · ${job_posting.title}`" />

    <div class="flex flex-col gap-8">
        <PageHeader :title="`Edit: ${job_posting.title}`" description="Update position details" />

        <Form v-bind="JobPostingController.update.form(job_posting.id)" class="flex max-w-3xl flex-col gap-8"
            #default="{ errors, processing, recentlySuccessful }">
            <input type="hidden" name="description" :value="descriptionJson" />
            <input type="hidden" name="requirements" :value="requirementsJson" />
            <input type="hidden" name="team_id" :value="teamId" />
            <input type="hidden" name="status" :value="status" />
            <input type="hidden" name="employment_type" :value="employmentType" />

            <GlassCard class="p-6">
                <div class="grid gap-6">
                    <FormField label="Title" html-for="title" :error="errors.title" required>
                        <Input id="title" name="title" type="text" :default-value="job_posting.title" required />
                    </FormField>
                    <FormField label="Slug" html-for="slug" :error="errors.slug" required>
                        <Input id="slug" name="slug" type="text" :default-value="job_posting.slug" required />
                    </FormField>
                    <FormField label="Location" html-for="location" :error="errors.location" required>
                        <Input id="location" name="location" type="text" :default-value="job_posting.location"
                            required />
                    </FormField>

                    <div class="grid gap-2">
                        <span class="text-sm font-medium">Team</span>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button variant="outline" type="button" class="justify-between">
                                    {{ teamLabel }}
                                    <ChevronDown class="size-4 opacity-50" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start" class="w-[var(--reka-dropdown-menu-trigger-width)]">
                                <DropdownMenuRadioGroup v-model="teamId">
                                    <DropdownMenuRadioItem value="">No team</DropdownMenuRadioItem>
                                    <DropdownMenuRadioItem v-for="team in teams" :key="team.value"
                                        :value="String(team.value)">
                                        {{ team.label }}
                                    </DropdownMenuRadioItem>
                                </DropdownMenuRadioGroup>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>

                    <div class="grid gap-2">
                        <span class="text-sm font-medium">Employment type</span>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button variant="outline" type="button" class="justify-between">
                                    {{ employmentTypeLabel }}
                                    <ChevronDown class="size-4 opacity-50" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start">
                                <DropdownMenuRadioGroup v-model="employmentType">
                                    <DropdownMenuRadioItem v-for="opt in employment_type_options" :key="opt.value"
                                        :value="opt.value">
                                        {{ opt.label }}
                                    </DropdownMenuRadioItem>
                                </DropdownMenuRadioGroup>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>

                    <div class="grid gap-2">
                        <span class="text-sm font-medium">Status</span>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button variant="outline" type="button" class="justify-between">
                                    {{ statusLabel }}
                                    <ChevronDown class="size-4 opacity-50" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start">
                                <DropdownMenuRadioGroup v-model="status">
                                    <DropdownMenuRadioItem v-for="opt in status_options" :key="opt.value"
                                        :value="opt.value">
                                        {{ opt.label }}
                                    </DropdownMenuRadioItem>
                                </DropdownMenuRadioGroup>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>

                    <FormField label="Published at" html-for="published_at" :error="errors.published_at">
                        <Input id="published_at" name="published_at" type="datetime-local"
                            :default-value="job_posting.published_at ?? ''" />
                    </FormField>
                    <FormField label="Closes at" html-for="closes_at" :error="errors.closes_at">
                        <Input id="closes_at" name="closes_at" type="datetime-local"
                            :default-value="job_posting.closes_at ?? ''" />
                    </FormField>
                </div>
            </GlassCard>

            <GlassCard class="p-6">
                <h2 class="mb-4 text-lg font-semibold">Description</h2>
                <RichTextEditor input-name="description" v-model="descriptionJson" />
            </GlassCard>

            <GlassCard class="p-6">
                <h2 class="mb-4 text-lg font-semibold">Requirements</h2>
                <RichTextEditor input-name="requirements" v-model="requirementsJson" />
            </GlassCard>

            <div class="flex items-center gap-4">
                <Button type="submit" :disabled="processing">Save changes</Button>
                <Button variant="outline" as-child>
                    <Link :href="jobPostingsIndex()">Cancel</Link>
                </Button>
                <span v-show="recentlySuccessful" class="text-sm text-muted-foreground">
                    Saved.
                </span>
            </div>
        </Form>
    </div>
</template>
