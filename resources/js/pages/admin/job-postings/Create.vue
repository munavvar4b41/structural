<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import JobPostingController from '@/actions/App/Http/Controllers/Admin/JobPostingController';
import FormField from '@/components/dashboard/FormField.vue';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import FormSelect from '@/components/FormSelect.vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { emptyTipTapDocumentJson } from '@/lib/tiptapDocument';
import {
    create as jobPostingsCreate,
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

const props = defineProps<{
    teams: TeamOption[];
    status_options: Option[];
    employment_type_options: Option[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Job postings', href: jobPostingsIndex() },
            { title: 'Add posting', href: jobPostingsCreate() },
        ],
    },
});

const descriptionJson = ref(emptyTipTapDocumentJson());
const requirementsJson = ref(emptyTipTapDocumentJson());
const teamId = ref('');
const status = ref(props.status_options[0]?.value ?? 'draft');
const employmentType = ref(props.employment_type_options[0]?.value ?? 'full_time');

const teamOptions = computed(() =>
    props.teams.map((t) => ({ value: String(t.value), label: t.label })),
);
</script>

<template>

    <Head title="Add job posting" />

    <div class="flex flex-col gap-8">
        <PageHeader title="Add job posting" description="Create a new position for the careers page" />

        <Form v-bind="JobPostingController.store.form()" class="flex max-w-3xl flex-col gap-8"
            #default="{ errors, processing, recentlySuccessful }">
            <input type="hidden" name="description" :value="descriptionJson" />
            <input type="hidden" name="requirements" :value="requirementsJson" />

            <GlassCard class="p-6">
                <div class="mb-6 space-y-1">
                    <h2 class="text-lg font-semibold">Position details</h2>
                </div>
                <div class="grid gap-6">
                    <FormField label="Title" html-for="title" :error="errors.title" required>
                        <Input id="title" name="title" type="text" required />
                    </FormField>
                    <FormField label="Location" html-for="location" :error="errors.location" required>
                        <Input id="location" name="location" type="text" required />
                    </FormField>

                    <FormField label="Team" html-for="team_id">
                        <FormSelect id="team_id" name="team_id" v-model="teamId" none-label="No team"
                            placeholder="Select team" :options="teamOptions" />
                    </FormField>

                    <FormField label="Employment type" html-for="employment_type" required>
                        <FormSelect id="employment_type" name="employment_type" v-model="employmentType" required
                            placeholder="Select type" :options="employment_type_options" />
                    </FormField>

                    <FormField label="Status" html-for="status" required>
                        <FormSelect id="status" name="status" v-model="status" required placeholder="Select status"
                            :options="status_options" />
                    </FormField>

                    <FormField label="Published at" html-for="published_at" :error="errors.published_at">
                        <Input id="published_at" name="published_at" type="datetime-local" />
                    </FormField>
                    <FormField label="Closes at" html-for="closes_at" :error="errors.closes_at">
                        <Input id="closes_at" name="closes_at" type="datetime-local" />
                    </FormField>
                </div>
            </GlassCard>

            <GlassCard class="p-6">
                <div class="mb-4 space-y-1">
                    <h2 class="text-lg font-semibold">Description</h2>
                </div>
                <RichTextEditor input-name="description" v-model="descriptionJson" />
            </GlassCard>

            <GlassCard class="p-6">
                <div class="mb-4 space-y-1">
                    <h2 class="text-lg font-semibold">Requirements</h2>
                </div>
                <RichTextEditor input-name="requirements" v-model="requirementsJson" />
            </GlassCard>

            <div class="flex items-center gap-4">
                <Button type="submit" :disabled="processing">Create posting</Button>
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
