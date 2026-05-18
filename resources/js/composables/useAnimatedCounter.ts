import { TransitionPresets, useTransition } from '@vueuse/core';
import { computed,  toValue, watch } from 'vue';
import type {MaybeRefOrGetter} from 'vue';

export function useAnimatedCounter(
    source: MaybeRefOrGetter<number>,
    duration = 800,
) {
    const output = useTransition(computed(() => toValue(source)), {
        duration,
        transition: TransitionPresets.easeOutCubic,
    });

    const display = computed(() => Math.round(output.value));

    return { display, output };
}

export function useAnimatedCounterRef(
    target: MaybeRefOrGetter<number>,
    duration = 800,
) {
    const { display } = useAnimatedCounter(target, duration);

    watch(
        () => toValue(target),
        () => {},
        { immediate: true },
    );

    return display;
}
