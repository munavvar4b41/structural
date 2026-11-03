<script setup lang="ts">
import { Download, X } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { usePwaInstall } from '@/composables/usePwaInstall';

const { canInstall, install, dismiss } = usePwaInstall();

async function onInstall(): Promise<void> {
    await install();
}
</script>

<template>
    <div
        v-if="canInstall"
        role="dialog"
        aria-label="Install Structural app"
        class="fixed right-4 bottom-4 left-4 z-100 mx-auto max-w-md sm:left-auto"
    >
        <div
            class="glass-panel flex items-start gap-3 rounded-2xl border border-sidebar-border/70 p-4 shadow-lg"
        >
            <div
                class="mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
            >
                <Download class="size-5" />
            </div>
            <div class="min-w-0 flex-1 space-y-3">
                <div class="space-y-1">
                    <p class="text-sm font-medium text-foreground">
                        Install Structural
                    </p>
                    <p class="text-xs leading-relaxed text-muted-foreground">
                        Add this app to your device for quicker access.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Button size="sm" @click="onInstall">Install</Button>
                    <Button size="sm" variant="ghost" @click="dismiss">
                        Not now
                    </Button>
                </div>
            </div>
            <Button
                size="icon"
                variant="ghost"
                class="size-8 shrink-0"
                aria-label="Dismiss install prompt"
                @click="dismiss"
            >
                <X class="size-4" />
            </Button>
        </div>
    </div>
</template>
