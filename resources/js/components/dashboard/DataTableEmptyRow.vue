<script setup lang="ts">
import DataTableTd from '@/components/dashboard/DataTableTd.vue';

defineProps<{
    colspan: number;
    message: string;
}>();
</script>

<template>
    <tr>
        <DataTableTd label="" :colspan="colspan" class="py-8 text-center text-muted-foreground">
            {{ message }}
        </DataTableTd>
    </tr>
</template>
