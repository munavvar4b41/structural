<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <div class="overflow-hidden rounded-md">
        <img src="/logo.jpg" alt="Structural" class="w-full h-full object-contain" />
    </div>
</template>
