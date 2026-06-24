<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Check,
    CheckCircle,
    Eye,
    ExternalLink,
    List,
    Pencil,
    Trash2,
    Users,
    X,
} from 'lucide-vue-next';
import type { Component } from 'vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';

type IconName =
    | 'eye'
    | 'pencil'
    | 'trash'
    | 'arrow-right'
    | 'external-link'
    | 'users'
    | 'check-circle'
    | 'check'
    | 'x'
    | 'list';

const props = withDefaults(
    defineProps<{
        label: string;
        icon?: IconName;
        href?: string;
        variant?: 'default' | 'destructive' | 'outline' | 'secondary' | 'ghost' | 'link';
        destructive?: boolean;
        type?: 'button' | 'submit';
        disabled?: boolean;
    }>(),
    {
        icon: 'eye',
        variant: 'outline',
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
    'external-link': ExternalLink,
    users: Users,
    'check-circle': CheckCircle,
    check: Check,
    x: X,
    list: List,
};

const IconComponent = computed(() => iconMap[props.icon]);

const buttonVariant = computed(() => {
    if (props.destructive) {
        return 'outline';
    }

    return props.variant;
});

const buttonClass = computed(() => {
    const classes = ['h-8', 'w-8', 'shrink-0'];

    if (props.destructive) {
        classes.push('text-destructive hover:bg-destructive/10');
    }

    return classes;
});
</script>

<template>
    <Tooltip>
        <TooltipTrigger as-child>
            <Button
                v-if="href"
                :variant="buttonVariant"
                size="icon-sm"
                :class="buttonClass"
                as-child
            >
                <Link :href="href" :aria-label="label">
                    <component :is="IconComponent" class="size-3.5" />
                    <span class="sr-only">{{ label }}</span>
                </Link>
            </Button>
            <Button
                v-else
                :variant="buttonVariant"
                size="icon-sm"
                :class="buttonClass"
                :type="type"
                :disabled="disabled"
                :aria-label="label"
                @click="emit('click', $event)"
            >
                <component :is="IconComponent" class="size-3.5" />
                <span class="sr-only">{{ label }}</span>
            </Button>
        </TooltipTrigger>
        <TooltipContent>{{ label }}</TooltipContent>
    </Tooltip>
</template>
