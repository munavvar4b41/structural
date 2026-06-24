<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
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
import { cn, toUrl } from '@/lib/utils';

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
        href?: NonNullable<InertiaLinkProps['href']>;
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
    view: 'border-info/30 bg-info/10 text-info hover:bg-info/20 dark:border-info/30 dark:bg-info/10 dark:text-info dark:hover:bg-info/20',
    edit: 'border-warning/30 bg-warning/10 text-warning hover:bg-warning/20 dark:border-warning/30 dark:bg-warning/10 dark:text-warning dark:hover:bg-warning/20',
    delete: 'border-destructive/30 bg-destructive/10 text-destructive hover:bg-destructive/20 dark:border-destructive/30 dark:bg-destructive/10 dark:text-destructive dark:hover:bg-destructive/20',
    create: 'border-success/30 bg-success/10 text-success hover:bg-success/20 dark:border-success/30 dark:bg-success/10 dark:text-success dark:hover:bg-success/20',
    navigate: 'border-border bg-muted/50 text-muted-foreground hover:bg-muted dark:border-border dark:bg-muted/30 dark:text-muted-foreground dark:hover:bg-muted/50',
    advance: 'border-primary/30 bg-primary/10 text-primary hover:bg-primary/20 dark:border-primary/30 dark:bg-primary/10 dark:text-primary dark:hover:bg-primary/20',
    confirm: 'border-success/30 bg-success/10 text-success hover:bg-success/20 dark:border-success/30 dark:bg-success/10 dark:text-success dark:hover:bg-success/20',
    reject: 'border-destructive/30 bg-destructive/10 text-destructive hover:bg-destructive/20 dark:border-destructive/30 dark:bg-destructive/10 dark:text-destructive dark:hover:bg-destructive/20',
    submit: 'border-primary/30 bg-primary/10 text-primary hover:bg-primary/20 dark:border-primary/30 dark:bg-primary/10 dark:text-primary dark:hover:bg-primary/20',
    time: 'border-warning/30 bg-warning/10 text-warning hover:bg-warning/20 dark:border-warning/30 dark:bg-warning/10 dark:text-warning dark:hover:bg-warning/20',
    users: 'border-info/30 bg-info/10 text-info hover:bg-info/20 dark:border-info/30 dark:bg-info/10 dark:text-info dark:hover:bg-info/20',
    download: 'border-info/30 bg-info/10 text-info hover:bg-info/20 dark:border-info/30 dark:bg-info/10 dark:text-info dark:hover:bg-info/20',
    estimate: 'border-primary/30 bg-primary/10 text-primary hover:bg-primary/20 dark:border-primary/30 dark:bg-primary/10 dark:text-primary dark:hover:bg-primary/20',
    reopen: 'border-warning/30 bg-warning/10 text-warning hover:bg-warning/20 dark:border-warning/30 dark:bg-warning/10 dark:text-warning dark:hover:bg-warning/20',
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

const resolvedHref = computed(() => (props.href === undefined ? undefined : toUrl(props.href)));

const buttonClass = computed(() =>
    cn('size-9 shrink-0 rounded-lg border shadow-none', toneClasses[resolvedTone.value]),
);
</script>

<template>
    <span class="inline-flex shrink-0 p-0.5">
        <Tooltip>
            <TooltipTrigger as-child>
                <Button v-if="resolvedHref && external" variant="outline" size="icon-sm" :class="buttonClass" as-child>
                    <a :href="resolvedHref" :aria-label="label" target="_blank" rel="noopener noreferrer">
                        <component :is="IconComponent" class="size-4" />
                        <span class="sr-only">{{ label }}</span>
                    </a>
                </Button>
                <Button v-else-if="resolvedHref" variant="outline" size="icon-sm" :class="buttonClass" as-child>
                    <Link :href="resolvedHref" :aria-label="label">
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
