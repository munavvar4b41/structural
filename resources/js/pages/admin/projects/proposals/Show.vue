<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import ProjectProposalController from '@/actions/App/Http/Controllers/Admin/ProjectProposalController';
import ProjectProposalMessageController from '@/actions/App/Http/Controllers/Admin/ProjectProposalMessageController';
import ConfirmDestructiveDialog from '@/components/ConfirmDestructiveDialog.vue';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import InputError from '@/components/InputError.vue';
import RichTextViewer from '@/components/RichTextViewer.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn, isCurrentUser } from '@/lib/utils';
import { index as projectsIndex, show as projectsShow } from '@/routes/admin/projects/index';
import {
    edit as proposalsEdit,
    index as proposalsIndex,
    show as proposalsShow,
} from '@/routes/admin/projects/proposals/index';
import { show as requirementsShow } from '@/routes/admin/projects/requirements/index';

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

type ProposalDetail = {
    id: number;
    title: string;
    description: string | null;
    status: string;
    status_label: string;
    created_at: string | null;
    submitted_at: string | null;
    reviewed_at: string | null;
    review_notes: string | null;
    rejection_reason: string | null;
    reopened_at: string | null;
    creator: UserBrief;
    reviewed_by: UserBrief;
    reopened_by: UserBrief;
    linked_requirement: { id: number; title: string } | null;
    transferred_requirement: { id: number; title: string } | null;
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
};

const props = defineProps<{
    project: ProjectSummary;
    proposal: ProposalDetail;
    proposal_chat_messages: PaginatedChatMessages;
    can_post_proposal_chat: boolean;
    can_update: boolean;
    can_submit: boolean;
    can_confirm: boolean;
    can_reject: boolean;
    can_reopen: boolean;
    can_delete: boolean;
}>();

const rejectDialogOpen = ref(false);
const rejectReason = ref('');
const confirmNotes = ref('');
const deleteDialogOpen = ref(false);

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
    router.reload({ only: ['proposal_chat_messages'] });
}

function onChatMessagePosted(): void {
    chatBody.value = '';
    reloadChatMessages();
}

onMounted(() => {
    scrollChatToBottom();
    chatPollTimer = window.setInterval(() => {
        reloadChatMessages();
    }, 30_000);
});

onUnmounted(() => {
    if (chatPollTimer !== null) {
        clearInterval(chatPollTimer);
    }
});

const olderMessagesHref = computed(() => {
    const { current_page: currentPage } = props.proposal_chat_messages;

    if (currentPage <= 1) {
        return null;
    }

    return proposalsShow.url(
        { project: props.project.id, proposal: props.proposal.id },
        { query: { chat_page: String(currentPage - 1) } },
    );
});

function statusBadgeVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'confirmed') {
        return 'default';
    }

    if (status === 'rejected') {
        return 'destructive';
    }

    if (status === 'pending') {
        return 'secondary';
    }

    return 'outline';
}

function submitProposal(): void {
    router.patch(
        ProjectProposalController.submit.url({
            project: props.project.id,
            proposal: props.proposal.id,
        }),
    );
}

function confirmProposal(): void {
    router.patch(
        ProjectProposalController.confirm.url({
            project: props.project.id,
            proposal: props.proposal.id,
        }),
        { review_notes: confirmNotes.value || null },
        { preserveScroll: true },
    );
}

function rejectProposal(): void {
    router.patch(
        ProjectProposalController.reject.url({
            project: props.project.id,
            proposal: props.proposal.id,
        }),
        { rejection_reason: rejectReason.value || null },
        {
            preserveScroll: true,
            onSuccess: () => {
                rejectDialogOpen.value = false;
                rejectReason.value = '';
            },
        },
    );
}

function reopenProposal(): void {
    router.patch(
        ProjectProposalController.reopen.url({
            project: props.project.id,
            proposal: props.proposal.id,
        }),
    );
}

function executeDelete(): void {
    router.delete(
        ProjectProposalController.destroy.url({
            project: props.project.id,
            proposal: props.proposal.id,
        }),
    );
}

defineOptions({
    layout: (pageProps: { project: ProjectSummary; proposal: ProposalDetail }) => ({
        breadcrumbs: [
            { title: 'Projects', href: projectsIndex.url() },
            {
                title: pageProps.project.name,
                href: projectsShow.url(pageProps.project.id),
            },
            { title: 'Proposals', href: proposalsIndex.url(pageProps.project.id) },
            {
                title: pageProps.proposal.title,
                href: proposalsShow.url({
                    project: pageProps.project.id,
                    proposal: pageProps.proposal.id,
                }),
            },
        ],
    }),
});
</script>

<template>

    <Head :title="`${proposal.title} · Proposals`" />

    <ConfirmDestructiveDialog v-model:open="deleteDialogOpen" title="Delete proposal?"
        :description="`Delete &quot;${proposal.title}&quot;? This cannot be undone.`" @confirm="executeDelete" />

    <Dialog v-model:open="rejectDialogOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Reject proposal</DialogTitle>
                <DialogDescription>Optionally provide a reason for the creator.</DialogDescription>
            </DialogHeader>
            <div class="grid gap-2">
                <Label for="rejection-reason">Rejection reason</Label>
                <textarea id="rejection-reason" v-model="rejectReason" rows="4" :class="cn(
                    'placeholder:text-muted-foreground border-input w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none',
                    'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                )
                    " />
            </div>
            <DialogFooter>
                <Button variant="outline" @click="rejectDialogOpen = false">Cancel</Button>
                <Button variant="destructive" @click="rejectProposal">Reject</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <div class="flex flex-col gap-6">
        <PageHeader :title="proposal.title" :description="`Proposal for ${project.name}`">
            <template #actions>
                <Badge :variant="statusBadgeVariant(proposal.status)">{{ proposal.status_label }}</Badge>
                <Button v-if="can_update" as-child>
                    <Link :href="proposalsEdit.url({
                        project: project.id,
                        proposal: proposal.id,
                    })
                        ">
                        Edit
                    </Link>
                </Button>
                <Button v-if="can_delete" variant="destructive" @click="deleteDialogOpen = true">Delete</Button>
                <Button variant="outline" as-child>
                    <Link :href="proposalsIndex.url(project.id)">All proposals</Link>
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-12">
            <GlassCard class="lg:col-span-7">
                <div class="mb-6 space-y-1">
                    <h2 class="text-lg font-semibold">Details</h2>
                    <p class="text-sm text-muted-foreground">
                        Created by {{ proposal.creator?.name ?? '—' }}
                        <template v-if="proposal.created_at">
                            · {{ new Date(proposal.created_at).toLocaleString() }}
                        </template>
                    </p>
                </div>

                <RichTextViewer v-if="proposal.description" :json="proposal.description" class="text-sm" />
                <p v-else class="text-sm text-muted-foreground">No description.</p>

                <div class="mt-6 space-y-2 text-sm text-muted-foreground">
                    <div v-if="proposal.linked_requirement">
                        Linked requirement:
                        <Link class="font-medium text-foreground underline-offset-4 hover:underline" :href="requirementsShow.url({
                            project: project.id,
                            requirement: proposal.linked_requirement.id,
                        })
                            ">
                            {{ proposal.linked_requirement.title }}
                        </Link>
                    </div>
                    <div v-if="proposal.transferred_requirement">
                        Created requirement:
                        <Link class="font-medium text-foreground underline-offset-4 hover:underline" :href="requirementsShow.url({
                            project: project.id,
                            requirement: proposal.transferred_requirement.id,
                        })
                            ">
                            {{ proposal.transferred_requirement.title }}
                        </Link>
                    </div>
                    <div v-if="proposal.submitted_at">
                        Submitted {{ new Date(proposal.submitted_at).toLocaleString() }}
                    </div>
                    <div v-if="proposal.reviewed_at">
                        Reviewed {{ new Date(proposal.reviewed_at).toLocaleString() }}
                        <span v-if="proposal.reviewed_by"> by {{ proposal.reviewed_by.name }}</span>
                    </div>
                    <div v-if="proposal.review_notes" class="text-foreground">
                        Review notes: {{ proposal.review_notes }}
                    </div>
                    <div v-if="proposal.rejection_reason" class="text-destructive">
                        Rejection reason: {{ proposal.rejection_reason }}
                    </div>
                    <div v-if="proposal.reopened_at">
                        Reopened {{ new Date(proposal.reopened_at).toLocaleString() }}
                        <span v-if="proposal.reopened_by"> by {{ proposal.reopened_by.name }}</span>
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap gap-2">
                    <Button v-if="can_submit" @click="submitProposal">Submit for review</Button>
                    <template v-if="can_confirm">
                        <div class="flex w-full flex-col gap-2 sm:w-auto">
                            <Input v-model="confirmNotes" placeholder="Optional review notes" />
                            <Button @click="confirmProposal">Confirm</Button>
                        </div>
                    </template>
                    <Button v-if="can_reject" variant="destructive" @click="rejectDialogOpen = true">
                        Reject
                    </Button>
                    <Button v-if="can_reopen" variant="outline" @click="reopenProposal">Reopen</Button>
                </div>
            </GlassCard>

            <GlassCard class="flex h-full min-h-0 flex-col lg:col-span-5">
                <div class="mb-6 space-y-1">
                    <h2 class="text-lg font-semibold">Discussion</h2>
                    <p class="text-sm text-muted-foreground">
                        Ask questions and align on this proposal before confirming.
                    </p>
                </div>
                <div class="flex min-h-0 flex-1 flex-col gap-4">
                    <div v-if="olderMessagesHref" class="flex shrink-0 justify-center border-b border-border pb-3">
                        <Button variant="outline" size="sm" as-child>
                            <Link :href="olderMessagesHref">Load older messages</Link>
                        </Button>
                    </div>
                    <div ref="chatScrollEl"
                        class="min-h-32 min-w-0 flex-1 space-y-3 overflow-y-auto rounded-xl border border-input bg-muted/20 p-3 text-sm lg:max-h-[min(32rem,calc(100vh-14rem))]">
                        <p v-if="proposal_chat_messages.data.length === 0" class="text-muted-foreground">
                            No messages yet. Start the thread below.
                        </p>
                        <div v-for="row in proposal_chat_messages.data" :key="row.id"
                            class="rounded-md bg-background p-2 shadow-xs"
                            :class="isCurrentUser(row.user?.id ?? 0) ? 'ms-5' : 'me-5'">
                            <div
                                class="flex flex-wrap items-baseline justify-between gap-2 text-xs text-muted-foreground">
                                <span class="font-medium text-foreground">{{ row.user?.name ?? 'Unknown' }}</span>
                                <span>{{ row.created_at ? new Date(row.created_at).toLocaleString() : '—' }}</span>
                            </div>
                            <p class="mt-1 whitespace-pre-wrap break-words">{{ row.body }}</p>
                        </div>
                    </div>
                    <div v-if="can_post_proposal_chat" class="shrink-0">
                        <Form v-bind="ProjectProposalMessageController.store.form({
                            project: project.id,
                            proposal: proposal.id,
                        })
                            " class="grid gap-2" @success="onChatMessagePosted" v-slot="{ errors, processing }">
                            <Label for="proposal-chat-body">Message</Label>
                            <textarea id="proposal-chat-body" v-model="chatBody" name="body" rows="3" required :class="cn(
                                'placeholder:text-muted-foreground border-input w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none',
                                'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                            )
                                " placeholder="Share context or ask a question…" />
                            <InputError :message="errors.body" />
                            <Button type="submit" :disabled="processing" class="w-fit">Send</Button>
                        </Form>
                    </div>
                    <p v-else class="shrink-0 text-xs text-muted-foreground">
                        You can view this thread but cannot post.
                    </p>
                </div>
            </GlassCard>
        </div>
    </div>
</template>
