<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import FormSelect from '@/components/FormSelect.vue';
import InputError from '@/components/InputError.vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { emptyTipTapDocumentJson } from '@/lib/tiptapDocument';
import { store as caseStudiesStore, update as caseStudiesUpdate } from '@/routes/admin/projects/case-studies/index';

type ProjectSummary = {
    id: number;
    name: string;
};

type TaskOption = {
    value: number;
    label: string;
};

type WorkloadPeriodOption = {
    value: string;
    label: string;
};

type ExistingAttachment = {
    id: number;
    original_name: string;
    mime: string;
    type: string;
};

type CaseStudyFormData = {
    project_task_id: string;
    title: string;
    summary: string;
    client_issue: string;
    business_impact: string;
    solution_discovery: string;
    proposed_solution: string;
    implementation: string;
    resolution: string;
    workload_reduction_details: string;
    workload_hours_saved: string;
    workload_percentage_reduction: string;
    workload_period: string;
    attachments: File[];
    remove_attachment_ids: number[];
};

const props = defineProps<{
    project: ProjectSummary;
    taskOptions: TaskOption[];
    workloadPeriodOptions: WorkloadPeriodOption[];
    preselectedTaskId?: number | null;
    caseStudyId?: number;
    initial?: Partial<{
        project_task_id: number | null;
        title: string;
        summary: string | null;
        client_issue: string | null;
        business_impact: string | null;
        solution_discovery: string | null;
        proposed_solution: string | null;
        implementation: string | null;
        resolution: string | null;
        workload_reduction_details: string | null;
        workload_hours_saved: string | number | null;
        workload_percentage_reduction: string | number | null;
        workload_period: string | null;
        attachments: ExistingAttachment[];
    }>;
    submitLabel: string;
}>();

const fileInput = ref<HTMLInputElement | null>(null);
const existingAttachments = ref<ExistingAttachment[]>(props.initial?.attachments ?? []);

const form = useForm<CaseStudyFormData>({
    project_task_id: props.initial?.project_task_id
        ? String(props.initial.project_task_id)
        : props.preselectedTaskId
          ? String(props.preselectedTaskId)
          : '',
    title: props.initial?.title ?? '',
    summary: props.initial?.summary ?? '',
    client_issue: props.initial?.client_issue ?? emptyTipTapDocumentJson(),
    business_impact: props.initial?.business_impact ?? emptyTipTapDocumentJson(),
    solution_discovery: props.initial?.solution_discovery ?? emptyTipTapDocumentJson(),
    proposed_solution: props.initial?.proposed_solution ?? emptyTipTapDocumentJson(),
    implementation: props.initial?.implementation ?? emptyTipTapDocumentJson(),
    resolution: props.initial?.resolution ?? emptyTipTapDocumentJson(),
    workload_reduction_details: props.initial?.workload_reduction_details ?? emptyTipTapDocumentJson(),
    workload_hours_saved:
        props.initial?.workload_hours_saved !== null && props.initial?.workload_hours_saved !== undefined
            ? String(props.initial.workload_hours_saved)
            : '',
    workload_percentage_reduction:
        props.initial?.workload_percentage_reduction !== null &&
        props.initial?.workload_percentage_reduction !== undefined
            ? String(props.initial.workload_percentage_reduction)
            : '',
    workload_period: props.initial?.workload_period ?? '',
    attachments: [],
    remove_attachment_ids: [],
});

const taskSelectOptions = computed(() =>
    props.taskOptions.map((option) => ({
        value: String(option.value),
        label: option.label,
    })),
);

const workloadPeriodSelectOptions = computed(() =>
    props.workloadPeriodOptions.map((option) => ({
        value: option.value,
        label: option.label,
    })),
);

function onFilesChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    form.attachments = target.files ? Array.from(target.files) : [];
}

function toggleRemoveAttachment(id: number, checked: boolean): void {
    if (checked) {
        if (!form.remove_attachment_ids.includes(id)) {
            form.remove_attachment_ids.push(id);
        }

        return;
    }

    form.remove_attachment_ids = form.remove_attachment_ids.filter((attachmentId) => attachmentId !== id);
}

function submit(): void {
    const options = {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.attachments = [];
            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
    };

    if (props.caseStudyId !== undefined) {
        form.post(
            caseStudiesUpdate.url({
                project: props.project.id,
                case_study: props.caseStudyId,
            }),
            {
                ...options,
                _method: 'patch',
            },
        );

        return;
    }

    form.post(caseStudiesStore.url(props.project.id), options);
}
</script>

<template>
    <form class="flex flex-col gap-8" @submit.prevent="submit">
        <GlassCard class="flex flex-col gap-6 p-6">
            <h2 class="text-lg font-semibold">Overview</h2>
            <div class="grid gap-2">
                <Label for="title">Title</Label>
                <Input id="title" v-model="form.title" required />
                <InputError :message="form.errors.title" />
            </div>
            <div class="grid gap-2">
                <Label for="summary">Summary</Label>
                <Input id="summary" v-model="form.summary" placeholder="Short preview for lists" />
                <InputError :message="form.errors.summary" />
            </div>
            <div class="grid gap-2">
                <Label for="project_task_id">Related task (optional)</Label>
                <FormSelect
                    id="project_task_id"
                    v-model="form.project_task_id"
                    :options="taskSelectOptions"
                    placeholder="No linked task"
                    none-label="No linked task"
                />
                <InputError :message="form.errors.project_task_id" />
            </div>
        </GlassCard>

        <GlassCard class="flex flex-col gap-6 p-6">
            <h2 class="text-lg font-semibold">The client problem</h2>
            <div class="grid gap-2">
                <Label>Client issue</Label>
                <RichTextEditor v-model="form.client_issue" input-name="client_issue" />
                <InputError :message="form.errors.client_issue" />
            </div>
            <div class="grid gap-2">
                <Label>Business impact</Label>
                <RichTextEditor v-model="form.business_impact" input-name="business_impact" />
                <InputError :message="form.errors.business_impact" />
            </div>
        </GlassCard>

        <GlassCard class="flex flex-col gap-6 p-6">
            <h2 class="text-lg font-semibold">Finding the solution</h2>
            <div class="grid gap-2">
                <Label>How we found the solution</Label>
                <RichTextEditor v-model="form.solution_discovery" input-name="solution_discovery" />
                <InputError :message="form.errors.solution_discovery" />
            </div>
            <div class="grid gap-2">
                <Label>Proposed solution</Label>
                <RichTextEditor v-model="form.proposed_solution" input-name="proposed_solution" />
                <InputError :message="form.errors.proposed_solution" />
            </div>
        </GlassCard>

        <GlassCard class="flex flex-col gap-6 p-6">
            <h2 class="text-lg font-semibold">Delivery</h2>
            <div class="grid gap-2">
                <Label>Implementation</Label>
                <RichTextEditor v-model="form.implementation" input-name="implementation" />
                <InputError :message="form.errors.implementation" />
            </div>
            <div class="grid gap-2">
                <Label>Resolution</Label>
                <RichTextEditor v-model="form.resolution" input-name="resolution" />
                <InputError :message="form.errors.resolution" />
            </div>
        </GlassCard>

        <GlassCard class="flex flex-col gap-6 p-6">
            <h2 class="text-lg font-semibold">Workload impact</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="grid gap-2">
                    <Label for="workload_hours_saved">Hours saved</Label>
                    <Input id="workload_hours_saved" v-model="form.workload_hours_saved" type="number" min="0" step="0.01" />
                    <InputError :message="form.errors.workload_hours_saved" />
                </div>
                <div class="grid gap-2">
                    <Label for="workload_percentage_reduction">Reduction (%)</Label>
                    <Input
                        id="workload_percentage_reduction"
                        v-model="form.workload_percentage_reduction"
                        type="number"
                        min="0"
                        max="100"
                        step="0.01"
                    />
                    <InputError :message="form.errors.workload_percentage_reduction" />
                </div>
                <div class="grid gap-2">
                    <Label for="workload_period">Period</Label>
                    <FormSelect
                        id="workload_period"
                        v-model="form.workload_period"
                        :options="workloadPeriodSelectOptions"
                        placeholder="Select period"
                        none-label="Not specified"
                    />
                    <InputError :message="form.errors.workload_period" />
                </div>
            </div>
            <div class="grid gap-2">
                <Label>Workload reduction details</Label>
                <RichTextEditor
                    v-model="form.workload_reduction_details"
                    input-name="workload_reduction_details"
                />
                <InputError :message="form.errors.workload_reduction_details" />
            </div>
        </GlassCard>

        <GlassCard class="flex flex-col gap-6 p-6">
            <h2 class="text-lg font-semibold">Attachments</h2>
            <div v-if="existingAttachments.length > 0" class="grid gap-2">
                <p class="text-sm text-muted-foreground">Existing files</p>
                <label
                    v-for="attachment in existingAttachments"
                    :key="attachment.id"
                    class="flex items-center gap-2 text-sm"
                >
                    <input
                        type="checkbox"
                        :checked="form.remove_attachment_ids.includes(attachment.id)"
                        @change="toggleRemoveAttachment(attachment.id, ($event.target as HTMLInputElement).checked)"
                    />
                    <span>{{ attachment.original_name }}</span>
                </label>
            </div>
            <div class="grid gap-2">
                <Label for="attachments">Add images or documents</Label>
                <Input
                    id="attachments"
                    ref="fileInput"
                    type="file"
                    multiple
                    accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx"
                    @change="onFilesChange"
                />
                <p class="text-xs text-muted-foreground">Up to 10 MB per file. JPG, PNG, GIF, WEBP, PDF, DOC, DOCX, XLS, XLSX.</p>
                <InputError :message="form.errors.attachments" />
                <InputError :message="form.errors['attachments.0']" />
            </div>
        </GlassCard>

        <div class="flex gap-2">
            <Button type="submit" :disabled="form.processing">{{ submitLabel }}</Button>
        </div>
    </form>
</template>
