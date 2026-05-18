<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

defineProps<{
    links: PaginationLink[];
}>();
</script>

<template>
    <nav
        v-if="links.length > 3"
        class="flex flex-wrap items-center justify-center gap-1.5"
        aria-label="Pagination"
    >
        <template v-for="(link, i) in links" :key="i">
            <Button
                v-if="link.url"
                variant="outline"
                size="sm"
                :class="
                    cn(
                        'rounded-full px-4',
                        link.active &&
                            'border-primary/30 bg-primary/10 text-primary shadow-sm',
                    )
                "
                :disabled="link.active"
                as-child
            >
                <Link :href="link.url" preserve-scroll>
                    <span v-html="link.label" />
                </Link>
            </Button>
            <span
                v-else
                class="px-3 py-1.5 text-sm text-muted-foreground"
                v-html="link.label"
            />
        </template>
    </nav>
</template>
