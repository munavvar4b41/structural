<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import { Button } from '@/components/ui/button';
import { index as careersIndex } from '@/routes/careers/index';
import { dashboard, login, register } from '@/routes';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);
</script>

<template>

    <Head title="Welcome" />

    <div class="page-gradient flex min-h-svh flex-col items-center justify-center p-6 md:p-10">
        <header class="absolute right-6 top-6 flex items-center gap-2 md:right-10 md:top-10">
            <Button variant="ghost" as-child>
                <Link :href="careersIndex()">Careers</Link>
            </Button>
            <Button v-if="$page.props.auth.user" variant="outline" as-child>
                <Link :href="dashboard()">Dashboard</Link>
            </Button>
            <template v-else>
                <Button variant="ghost" as-child>
                    <Link :href="login()">Log in</Link>
                </Button>
                <Button v-if="canRegister" as-child>
                    <Link :href="register()">Register</Link>
                </Button>
            </template>
        </header>

        <GlassCard class="w-full max-w-lg p-8 text-center">
            <h1 class="text-3xl font-semibold tracking-tight text-foreground">
                Structural
            </h1>
            <p class="mt-3 text-sm leading-relaxed text-muted-foreground">
                Project and task management for your team. Sign in to open your
                workspace or create an account to get started.
            </p>
            <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
                <Button v-if="$page.props.auth.user" class="w-full sm:w-auto" as-child>
                    <Link :href="dashboard()">Go to dashboard</Link>
                </Button>
                <template v-else>
                    <Button class="w-full sm:w-auto" as-child>
                        <Link :href="login()">Log in</Link>
                    </Button>
                    <Button v-if="canRegister" variant="outline" class="w-full sm:w-auto" as-child>
                        <Link :href="register()">Create account</Link>
                    </Button>
                </template>
            </div>
        </GlassCard>
    </div>
</template>
