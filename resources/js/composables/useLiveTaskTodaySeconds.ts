import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';

export type ActiveTimeEntrySnapshot = {
    task_today_seconds: number;
    is_paused: boolean;
};

/**
 * Live "time today on task" for the active timer badge.
 * Anchors to server `task_today_seconds` and ticks only while running.
 */
export function useLiveTaskTodaySeconds(
    active: ComputedRef<ActiveTimeEntrySnapshot | null>,
    now: Ref<number>,
): ComputedRef<number> {
    const anchorMs = ref(Date.now());

    watch(
        active,
        () => {
            anchorMs.value = Date.now();
        },
        { deep: true },
    );

    return computed(() => {
        if (active.value === null) {
            return 0;
        }

        const base = Math.max(0, active.value.task_today_seconds);

        if (active.value.is_paused) {
            return base;
        }

        const secondsSinceAnchor = Math.max(
            0,
            Math.floor((now.value - anchorMs.value) / 1000),
        );

        return base + secondsSinceAnchor;
    });
}

/**
 * Shared 1s clock for timer UI components.
 */
export function useTimerClock(): Ref<number> {
    const now = ref(Date.now());
    let intervalId: number | undefined;

    onMounted(() => {
        intervalId = window.setInterval(() => {
            now.value = Date.now();
        }, 1000);
    });

    onBeforeUnmount(() => {
        if (intervalId !== undefined) {
            window.clearInterval(intervalId);
        }
    });

    return now;
}
