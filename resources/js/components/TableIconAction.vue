<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    BookOpen,
    Calculator,
    Check,
    CheckCircle,
    ChevronsUp,
    ClipboardList,
    Download,
    Eye,
    ExternalLink,
    FileCheck,
    FileText,
    List,
    Pencil,
    Plus,
    RotateCcw,
    Send,
    Timer,
    Trash2,
    Users,
    X,
} from 'lucide-vue-next';
import type { Component } from 'vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

type IconName =
    | 'eye'
    | 'pencil'
    | 'trash'
    | 'arrow-right'
    | 'arrow-left'
    | 'external-link'
    | 'users'
    | 'check-circle'
    | 'check'
    | 'x'
    | 'list'
    | 'plus'
    | 'download'
    | 'rotate-ccw'
    | 'file-text'
    | 'timer'
    | 'send'
    | 'calculator'
    | 'chevrons-up'
    | 'clipboard-list'
    | 'file-check'
    | 'book-open';

type ActionTone =
    | 'view'
    | 'edit'
    | 'delete'
    | 'create'
    | 'navigate'
    | 'advance'
    | 'confirm'
    | 'reject'
    | 'submit'
    | 'time'
    | 'users'
    | 'download'
    | 'estimate'
    | 'reopen';

const props = withDefaults(
    defineProps<{
        label: string;
        icon?: IconName;
        tone?: ActionTone;
        href?: string;
        external?: boolean;
        destructive?: boolean;
        type?: 'button' | 'submit';
        disabled?: boolean;
    }>(),
    {
        icon: 'eye',
        external: false,
        destructive: false,
        type: 'button',
        disabled: false,
    },
);

const emit = defineEmits<{
    click: [event: MouseEvent];
}>();

const iconMap: Record<IconName, Component> = {
    eye: Eye,
    pencil: Pencil,
    trash: Trash2,
    'arrow-right': ArrowRight,
    'arrow-left': ArrowLeft,
    'external-link': ExternalLink,
    users: Users,
    'check-circle': CheckCircle,
    check: Check,
    x: X,
    list: List,
    plus: Plus,
    download: Download,
    'rotate-ccw': RotateCcw,
    'file-text': FileText,
    timer: Timer,
    send: Send,
    calculator: Calculator,
    'chevrons-up': ChevronsUp,
    'clipboard-list': ClipboardList,
    'file-check': FileCheck,
    'book-open': BookOpen,
};

const toneByIcon: Record<IconName, ActionTone> = {
    eye: 'view',
    pencil: 'edit',
    trash: 'delete',
    'arrow-right': 'advance',
    'arrow-left': 'navigate',
    'external-link': 'view',
    users: 'users',
    'check-circle': 'confirm',
    check: 'confirm',
    x: 'reject',
    list: 'navigate',
    plus: 'create',
    download: 'download',
    'rotate-ccw': 'reopen',
    'file-text': 'submit',
    timer: 'time',
    send: 'submit',
    calculator: 'estimate',
    'chevrons-up': 'navigate',
    'clipboard-list': 'navigate',
    'file-check': 'advance',
    'book-open': 'estimate',
};

const toneClasses: Record<ActionTone, string> = {
    view: 'border-blue-200 bg-blue-50 text-blue-600 hover:bg-blue-100 dark:border-blue-500/30 dark:bg-blue-950/40 dark:text-blue-400 dark:hover:bg-blue-900/50',
    edit: 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100 dark:border-amber-500/30 dark:bg-amber-950/40 dark:text-amber-400 dark:hover:bg-amber-900/50',
    delete: 'border-red-200 bg-red-50 text-red-600 hover:bg-red-100 dark:border-red-500/30 dark:bg-red-950/40 dark:text-red-400 dark:hover:bg-red-900/50',
    create: 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:border-emerald-500/30 dark:bg-emerald-950/40 dark:text-emerald-400 dark:hover:bg-emerald-900/50',
    navigate: 'border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100 dark:border-slate-500/30 dark:bg-slate-800/40 dark:text-slate-300 dark:hover:bg-slate-700/50',
    advance: 'border-indigo-200 bg-indigo-50 text-indigo-600 hover:bg-indigo-100 dark:border-indigo-500/30 dark:bg-indigo-950/40 dark:text-indigo-400 dark:hover:bg-indigo-900/50',
    confirm: 'border-green-200 bg-green-50 text-green-700 hover:bg-green-100 dark:border-green-500/30 dark:bg-green-950/40 dark:text-green-400 dark:hover:bg-green-900/50',
    reject: 'border-red-200 bg-red-50 text-red-600 hover:bg-red-100 dark:border-red-500/30 dark:bg-red-950/40 dark:text-red-400 dark:hover:bg-red-900/50',
    submit: 'border-violet-200 bg-violet-50 text-violet-700 hover:bg-violet-100 dark:border-violet-500/30 dark:bg-violet-950/40 dark:text-violet-400 dark:hover:bg-violet-900/50',
    time: 'border-orange-200 bg-orange-50 text-orange-700 hover:bg-orange-100 dark:border-orange-500/30 dark:bg-orange-950/40 dark:text-orange-400 dark:hover:bg-orange-900/50',
    users: 'border-sky-200 bg-sky-50 text-sky-700 hover:bg-sky-100 dark:border-sky-500/30 dark:bg-sky-950/40 dark:text-sky-400 dark:hover:bg-sky-900/50',
    download: 'border-cyan-200 bg-cyan-50 text-cyan-700 hover:bg-cyan-100 dark:border-cyan-500/30 dark:bg-cyan-950/40 dark:text-cyan-400 dark:hover:bg-cyan-900/50',
    estimate: 'border-purple-200 bg-purple-50 text-purple-700 hover:bg-purple-100 dark:border-purple-500/30 dark:bg-purple-950/40 dark:text-purple-400 dark:hover:bg-purple-900/50',
    reopen: 'border-yellow-200 bg-yellow-50 text-yellow-700 hover:bg-yellow-100 dark:border-yellow-500/30 dark:bg-yellow-950/40 dark:text-yellow-400 dark:hover:bg-yellow-900/50',
};

const IconComponent = computed(() => iconMap[props.icon]);

const resolvedTone = computed((): ActionTone => {
    if (props.tone) {
        return props.tone;
    }

    if (props.destructive) {
        return 'delete';
    }

    return toneByIcon[props.icon];
});

const buttonClass = computed(() =>
    cn('size-9 shrink-0 rounded-lg border shadow-none', toneClasses[resolvedTone.value]),
);
</script>

<template>
    <span class="inline-flex shrink-0 p-0.5">
        <Tooltip>
            <TooltipTrigger as-child>
                <Button v-if="href && external" variant="outline" size="icon-sm" :class="buttonClass" as-child>
                    <a :href="href" :aria-label="label" target="_blank" rel="noopener noreferrer">
                        <component :is="IconComponent" class="size-4" />
                        <span class="sr-only">{{ label }}</span>
                    </a>
                </Button>
                <Button v-else-if="href" variant="outline" size="icon-sm" :class="buttonClass" as-child>
                    <Link :href="href" :aria-label="label">
                        <component :is="IconComponent" class="size-4" />
                        <span class="sr-only">{{ label }}</span>
                    </Link>
                </Button>
                <Button v-else variant="outline" size="icon-sm" :class="buttonClass" :type="type" :disabled="disabled"
                    :aria-label="label" @click="emit('click', $event)">
                    <component :is="IconComponent" class="size-4" />
                    <span class="sr-only">{{ label }}</span>
                </Button>
            </TooltipTrigger>
            <TooltipContent>{{ label }}</TooltipContent>
        </Tooltip>
    </span>
</template>
