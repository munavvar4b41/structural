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

type ExistingAttachment = {
    id: number;
    title: string | null;
    original_name: string;
    mime: string;
    type: string;
};

type DocumentRow = {
    title: string;
    file: File | null;
};

type CaseStudyFormData = {
    project_task_id: string;
    title: string;
    overview: string;
    client_issue: string;
    our_solution: string;
    implementation: string;
    other_details: string;
    result_and_impact: string;
    conclusion: string;
    documents: DocumentRow[];
    remove_attachment_ids: number[];
};

const props = defineProps<{
    project: ProjectSummary;
    taskOptions: TaskOption[];
    preselectedTaskId?: number | null;
    caseStudyId?: number;
    initial?: Partial<{
        project_task_id: number | null;
        title: string;
        overview: string | null;
        client_issue: string | null;
        our_solution: string | null;
        implementation: string | null;
        other_details: string | null;
        result_and_impact: string | null;
        conclusion: string | null;
        attachments: ExistingAttachment[];
    }>;
    submitLabel: string;
}>();

const existingAttachments = ref<ExistingAttachment[]>(props.initial?.attachments ?? []);

const form = useForm<CaseStudyFormData>({
    project_task_id: props.initial?.project_task_id
        ? String(props.initial.project_task_id)
        : props.preselectedTaskId
            ? String(props.preselectedTaskId)
            : '',
    title: props.initial?.title ?? '',
    overview: props.initial?.overview ?? emptyTipTapDocumentJson(),
    client_issue: props.initial?.client_issue ?? emptyTipTapDocumentJson(),
    our_solution: props.initial?.our_solution ?? emptyTipTapDocumentJson(),
    implementation: props.initial?.implementation ?? emptyTipTapDocumentJson(),
    other_details: props.initial?.other_details ?? emptyTipTapDocumentJson(),
    result_and_impact: props.initial?.result_and_impact ?? emptyTipTapDocumentJson(),
    conclusion: props.initial?.conclusion ?? emptyTipTapDocumentJson(),
    documents: [],
    remove_attachment_ids: [],
});

const taskSelectOptions = computed(() =>
    props.taskOptions.map((option) => ({
        value: String(option.value),
        label: option.label,
    })),
);

function addDocumentRow(): void {
    form.documents.push({ title: '', file: null });
}

function removeDocumentRow(index: number): void {
    form.documents.splice(index, 1);
}

function onDocumentFileChange(index: number, event: Event): void {
    const target = event.target as HTMLInputElement;
    form.documents[index].file = target.files?.[0] ?? null;
}

function attachmentLabel(attachment: ExistingAttachment): string {
    return attachment.title ?? attachment.original_name;
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
            form.documents = [];
        },
    };

    form.transform((data) => ({
        ...data,
        documents: data.documents.filter((document) => document.file !== null),
    }));

    if (props.caseStudyId !== undefined) {
        form.patch(
            caseStudiesUpdate.url({
                project: props.project.id,
                case_study: props.caseStudyId,
            }),
            options,
        );

        return;
    }

    form.post(caseStudiesStore.url(props.project.id), options);
}
</script>

<template>
    <form class="flex flex-col gap-8" @submit.prevent="submit">
        <GlassCard class="flex flex-col gap-6 p-6">
            <h2 class="text-lg font-semibold">Details</h2>
            <div class="grid gap-2">
                <Label for="title">Title</Label>
                <Input id="title" v-model="form.title" required />
                <p class="text-xs text-muted-foreground">Short name used in lists and navigation.</p>
                <InputError :message="form.errors.title" />
            </div>
            <div class="grid gap-2">
                <Label for="project_task_id">Related task (optional)</Label>
                <FormSelect id="project_task_id" v-model="form.project_task_id" :options="taskSelectOptions"
                    placeholder="No linked task" none-label="No linked task" />
                <InputError :message="form.errors.project_task_id" />
            </div>
        </GlassCard>

        <GlassCard class="flex flex-col gap-6 p-6">
            <div>
                <h2 class="text-lg font-semibold">Overview</h2>
                <p class="text-sm text-muted-foreground">High-level summary of the case study.</p>
            </div>
            <RichTextEditor v-model="form.overview" input-name="overview" />
            <InputError :message="form.errors.overview" />
        </GlassCard>

        <GlassCard class="flex flex-col gap-6 p-6">
            <div>
                <h2 class="text-lg font-semibold">Client issue or challenge</h2>
                <p class="text-sm text-muted-foreground">The problem the client faced and why it mattered.</p>
            </div>
            <RichTextEditor v-model="form.client_issue" input-name="client_issue" />
            <InputError :message="form.errors.client_issue" />
        </GlassCard>

        <GlassCard class="flex flex-col gap-6 p-6">
            <div>
                <h2 class="text-lg font-semibold">Our solution</h2>
                <p class="text-sm text-muted-foreground">The approach and solution proposed.</p>
            </div>
            <RichTextEditor v-model="form.our_solution" input-name="our_solution" />
            <InputError :message="form.errors.our_solution" />
        </GlassCard>

        <GlassCard class="flex flex-col gap-6 p-6">
            <div>
                <h2 class="text-lg font-semibold">Implementation</h2>
                <p class="text-sm text-muted-foreground">How the solution was built and delivered.</p>
            </div>
            <RichTextEditor v-model="form.implementation" input-name="implementation" />
            <InputError :message="form.errors.implementation" />
        </GlassCard>

        <GlassCard class="flex flex-col gap-6 p-6">
            <div>
                <h2 class="text-lg font-semibold">Other details</h2>
                <p class="text-sm text-muted-foreground">Supporting context, constraints, or notes.</p>
            </div>
            <RichTextEditor v-model="form.other_details" input-name="other_details" />
            <InputError :message="form.errors.other_details" />
        </GlassCard>

        <GlassCard class="flex flex-col gap-6 p-6">
            <div>
                <h2 class="text-lg font-semibold">Result and impact</h2>
                <p class="text-sm text-muted-foreground">Outcomes, metrics, and business impact.</p>
            </div>
            <RichTextEditor v-model="form.result_and_impact" input-name="result_and_impact" />
            <InputError :message="form.errors.result_and_impact" />
        </GlassCard>

        <GlassCard class="flex flex-col gap-6 p-6">
            <div>
                <h2 class="text-lg font-semibold">Conclusion</h2>
                <p class="text-sm text-muted-foreground">Final takeaways and closing summary.</p>
            </div>
            <RichTextEditor v-model="form.conclusion" input-name="conclusion" />
            <InputError :message="form.errors.conclusion" />
        </GlassCard>

        <GlassCard class="flex flex-col gap-6 p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold">Documents</h2>
                    <p class="text-sm text-muted-foreground">Add labeled files; each row needs a title and upload.</p>
                </div>
                <Button type="button" variant="outline" @click="addDocumentRow">Add document</Button>
            </div>

            <div v-if="existingAttachments.length > 0" class="grid gap-2">
                <p class="text-sm text-muted-foreground">
                    Existing documents:
                    (<span class="text-xs text-muted-foreground">Select documents to remove</span>)
                </p>
                <label v-for="attachment in existingAttachments" :key="attachment.id"
                    class="flex items-center gap-2 text-sm">
                    <input type="checkbox" :checked="form.remove_attachment_ids.includes(attachment.id)"
                        @change="toggleRemoveAttachment(attachment.id, ($event.target as HTMLInputElement).checked)" />
                    <span>{{ attachmentLabel(attachment) }}</span>
                </label>
            </div>

            <div v-for="(document, index) in form.documents" :key="index"
                class="grid gap-4 rounded-lg border border-border/60 p-4">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-sm font-medium">Document {{ index + 1 }}</p>
                    <Button type="button" variant="ghost" size="sm" @click="removeDocumentRow(index)">
                        Remove
                    </Button>
                </div>
                <div class="grid md:grid-cols-2 gap-2">
                    <div class="grid gap-2 col-span-1">
                        <Label :for="`document-title-${index}`">Document title</Label>
                        <Input :id="`document-title-${index}`" v-model="document.title" />
                        <InputError :message="form.errors[`documents.${index}.title`]" />
                    </div>
                    <div class="grid gap-2 col-span-1">
                        <Label :for="`document-file-${index}`">File</Label>
                        <Input :id="`document-file-${index}`" type="file"
                            accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx"
                            @change="onDocumentFileChange(index, $event)" />
                        <InputError :message="form.errors[`documents.${index}.file`]" />
                    </div>
                </div>
            </div>

            <p class="text-xs text-muted-foreground">Up to 10 MB per file. JPG, PNG, GIF, WEBP, PDF, DOC, DOCX, XLS,
                XLSX.</p>
            <InputError :message="form.errors.documents" />
        </GlassCard>

        <div class="flex gap-2">
            <Button type="submit" :disabled="form.processing">{{ submitLabel }}</Button>
        </div>
    </form>
</template>
