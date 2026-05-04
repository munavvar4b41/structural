<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { onUnmounted, ref, watch } from 'vue';

const page = usePage();

const visible = ref(false);
const message = ref('');

let hideTimer: ReturnType<typeof setTimeout> | undefined;

function clearHideTimer(): void {
    if (hideTimer !== undefined) {
        clearTimeout(hideTimer);
        hideTimer = undefined;
    }
}

function scheduleHide(): void {
    clearHideTimer();
    hideTimer = window.setTimeout(() => {
        visible.value = false;
        hideTimer = undefined;
    }, 4200);
}

watch(
    () => page.props.flash?.toast,
    (toast) => {
        if (typeof toast === 'string' && toast.length > 0) {
            message.value = toast;
            visible.value = true;
            scheduleHide();
        }
    },
    { immediate: true },
);

onUnmounted(() => {
    clearHideTimer();
});
</script>

<template>
    <div
        role="status"
        aria-live="polite"
        class="pointer-events-none fixed bottom-6 left-1/2 z-50 max-w-md -translate-x-1/2 px-4 transition-all duration-300"
        :class="
            visible
                ? 'translate-y-0 opacity-100'
                : 'pointer-events-none translate-y-2 opacity-0'
        "
    >
        <div
            class="pointer-events-auto rounded-lg border border-sidebar-border/80 bg-foreground px-4 py-3 text-center text-sm text-background shadow-lg"
        >
            {{ message }}
        </div>
    </div>
</template>
