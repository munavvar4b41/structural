<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import ProjectRequirementController from '@/actions/App/Http/Controllers/Admin/ProjectRequirementController';
import ProjectRequirementMessageController from '@/actions/App/Http/Controllers/Admin/ProjectRequirementMessageController';
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
import { cn, isCurrentUser } from '@/lib/utils';
import { edit as projectsEdit, index as projectsIndex } from '@/routes/admin/projects/index';
import {
    edit as requirementsEdit,
    index as requirementsIndex,
    show as requirementsShow,
} from '@/routes/admin/projects/requirements/index';

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

type ChatMessageRow = {
    id: number;
    body: string;
    created_at: string | null;
    user: UserBrief;
};

type PaginatedChatMessages = {
    data: ChatMessageRow[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
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
    requirement_chat_messages: PaginatedChatMessages;
    can_post_requirement_chat: boolean;
    can_update: boolean;
    can_mark_reviewed: boolean;
    can_confirm_understanding: boolean;
    can_manage_project: boolean;
}>();

const reviewDialogOpen = ref(false);
const chatScrollEl = ref<HTMLElement | null>(null);
const chatBody = ref('');
let chatPollTimer: ReturnType<typeof setInterval> | null = null;

function scrollChatToBottom(): void {
    nextTick(() => {
        const el = chatScrollEl.value;

        if (el) {
            el.scrollTop = el.scrollHeight;
        }
    });
}

function reloadChatMessages(): void {
    router.reload({ only: ['requirement_chat_messages'] });
}

function onChatMessagePosted(): void {
    chatBody.value = '';
    reloadChatMessages();
}

watch(
    () => props.requirement_chat_messages.data,
    () => {
        scrollChatToBottom();
    },
    { deep: true },
);

onMounted(() => {
    scrollChatToBottom();
    chatPollTimer = window.setInterval(() => {
        reloadChatMessages();
    }, 30_000);
});

onUnmounted(() => {
    if (chatPollTimer !== null) {
        window.clearInterval(chatPollTimer);
        chatPollTimer = null;
    }
});

const olderMessagesHref = computed((): string | null => {
    const { current_page: currentPage } = props.requirement_chat_messages;

    if (currentPage <= 1) {
        return null;
    }

    return requirementsShow.url(
        { project: props.project.id, requirement: props.requirement.id },
        { query: { chat_page: currentPage - 1 } },
    );
});

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
                    <Link :href="requirementsEdit.url({
                        project: project.id,
                        requirement: requirement.id,
                    })
                        ">
                        Edit
                    </Link>
                </Button>
                <Button v-if="can_mark_reviewed" type="button" variant="secondary" @click="reviewDialogOpen = true">
                    {{ requirement.review_understanding ? 'Update review understanding' : 'Submit review understanding'
                    }}
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="requirementsIndex.url(project.id)">Back to list</Link>
                </Button>
            </div>
        </div>

        <div
            class="mx-auto grid w-full gap-8 lg:grid-cols-12 lg:items-start">
            <div class="grid min-w-0 gap-6 lg:col-span-7">
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
                            Created {{ requirement.created_at ? new Date(requirement.created_at).toLocaleString() : '—'
                            }}
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
                        <Form v-bind="ProjectRequirementController.confirmUnderstanding.form({
                            project: project.id,
                            requirement: requirement.id,
                        })
                            " class="flex flex-col gap-3" v-slot="{ processing }">
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

            <aside class="min-w-0 lg:sticky lg:top-6 lg:col-span-5 lg:self-start">
                <Card>
                    <CardHeader>
                        <CardTitle>Clarification discussion</CardTitle>
                        <CardDescription>
                            Ask questions and align with the team before finalizing review understanding.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-4">
                        <div v-if="olderMessagesHref" class="flex justify-center border-b border-border pb-3">
                            <Button variant="outline" size="sm" as-child>
                                <Link :href="olderMessagesHref">Load older messages</Link>
                            </Button>
                        </div>
                        <div ref="chatScrollEl"
                            class="max-h-80 space-y-3 overflow-y-auto rounded-md border border-input bg-muted/20 p-3 text-sm lg:max-h-[min(36rem,calc(100vh-10rem))]">
                            <p v-if="requirement_chat_messages.data.length === 0" class="text-muted-foreground">
                                No messages yet. Start the thread below.
                            </p>
                            <div v-for="row in requirement_chat_messages.data" :key="row.id"
                                class="rounded-md bg-background p-2 shadow-xs"
                                :class="isCurrentUser(row.user?.id ?? 0) ? 'ms-5' : 'me-5'">
                                <div
                                    class="flex flex-wrap items-baseline justify-between gap-2 text-xs text-muted-foreground">
                                    <span class="font-medium text-foreground">{{ row.user?.name ?? 'Unknown' }}</span>
                                    <span>{{
                                        row.created_at ? new Date(row.created_at).toLocaleString() : '—'
                                        }}</span>
                                </div>
                                <p class="mt-1 whitespace-pre-wrap break-words">{{ row.body }}</p>
                            </div>
                        </div>
                        <div v-if="can_post_requirement_chat">
                            <Form v-bind="ProjectRequirementMessageController.store.form({
                                project: project.id,
                                requirement: requirement.id,
                            })
                                " class="grid gap-2" @success="onChatMessagePosted" v-slot="{ errors, processing }">
                                <Label for="requirement-chat-body">Message</Label>
                                <textarea id="requirement-chat-body" v-model="chatBody" name="body" rows="3" required
                                    :class="cn(
                                        'placeholder:text-muted-foreground border-input w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none',
                                        'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                        'aria-invalid:border-destructive',
                                    )
                                        " placeholder="Ask for clarification or share context…" />
                                <InputError :message="errors.body" />
                                <Button type="submit" :disabled="processing" class="w-fit">Send</Button>
                            </Form>
                        </div>
                        <p v-else class="text-xs text-muted-foreground">You can view this thread but cannot post.</p>
                    </CardContent>
                </Card>
            </aside>
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
                <Form v-bind="ProjectRequirementController.markReviewed.form({
                    project: project.id,
                    requirement: requirement.id,
                })
                    " class="grid gap-4" @success="reviewDialogOpen = false" v-slot="{ errors, processing }">
                    <div class="grid gap-2">
                        <Label for="review-understanding-editor">Your understanding</Label>
                        <RequirementRichTextEditor id="review-understanding-editor" v-model="reviewUnderstandingJson"
                            input-name="review_understanding" />
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
