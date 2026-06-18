<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import { Button } from '@/components/ui/button';
import { home, login } from '@/routes';
import { index as careersIndex, show as careersShow } from '@/routes/careers/index';

type JobPostingCard = {
    slug: string;
    title: string;
    location: string;
    employment_type_label: string;
    team_name: string | null;
    description_preview: string | null;
};

defineProps<{
    job_postings: JobPostingCard[];
}>();
</script>

<template>

    <Head title="Careers" />

    <div class="page-gradient flex min-h-svh flex-col p-6 md:p-10">
        <header class="mb-10 flex items-center justify-between">
            <Link :href="home()" class="text-lg font-semibold tracking-tight text-foreground">
                Structural
            </Link>
        </header>

        <div class="mx-auto w-full max-w-4xl">
            <h1 class="text-3xl font-semibold tracking-tight text-foreground">Careers</h1>
            <p class="mt-2 text-sm text-muted-foreground">
                Explore open positions and apply with your resume.
            </p>

            <div v-if="job_postings.length === 0" class="mt-10">
                <GlassCard class="p-8 text-center text-muted-foreground">
                    No open positions at the moment. Check back soon.
                </GlassCard>
            </div>

            <div v-else class="mt-8 grid gap-4">
                <GlassCard v-for="posting in job_postings" :key="posting.slug" class="p-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="text-xl font-semibold">{{ posting.title }}</h2>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ posting.location }} · {{ posting.employment_type_label }}
                                <span v-if="posting.team_name"> · {{ posting.team_name }}</span>
                            </p>
                            <p v-if="posting.description_preview"
                                class="mt-3 text-sm leading-relaxed text-muted-foreground">
                                {{ posting.description_preview }}
                            </p>
                        </div>
                        <Button as-child class="shrink-0">
                            <Link :href="careersShow(posting.slug)">View & apply</Link>
                        </Button>
                    </div>
                </GlassCard>
            </div>

            <div class="mt-8 text-center">
                <Button variant="link" as-child>
                    <Link :href="careersIndex()">Refresh listings</Link>
                </Button>
            </div>
        </div>
    </div>
</template>
