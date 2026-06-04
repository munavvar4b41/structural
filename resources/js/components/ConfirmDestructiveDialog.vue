<script setup lang="ts">
import type { ButtonVariants } from '@/components/ui/button';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type Props = {
    title: string;
    description: string;
    confirmLabel?: string;
    confirmVariant?: ButtonVariants['variant'];
};

withDefaults(defineProps<Props>(), {
    confirmLabel: 'Delete',
    confirmVariant: 'destructive',
});

const open = defineModel<boolean>('open', { default: false });

const emit = defineEmits<{
    confirm: [];
}>();

function confirm(): void {
    emit('confirm');
    open.value = false;
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md" :show-close-button="true">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2 sm:justify-end">
                <DialogClose as-child>
                    <Button type="button" variant="secondary">Cancel</Button>
                </DialogClose>
                <Button type="button" :variant="confirmVariant" @click="confirm">
                    {{ confirmLabel }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
