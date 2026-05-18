<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { cn } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    { title: 'Profile', href: editProfile() },
    { title: 'Security', href: editSecurity() },
    { title: 'Appearance', href: editAppearance() },
];

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="space-y-6">
        <PageHeader
            title="Settings"
            description="Manage your profile and account settings"
        />

        <div class="flex flex-col gap-6 lg:flex-row lg:gap-8">
            <aside class="w-full lg:w-52">
                <GlassCard :padding="true" class="p-3">
                    <nav class="flex flex-col gap-1" aria-label="Settings">
                        <Button
                            v-for="item in sidebarNavItems"
                            :key="toUrl(item.href)"
                            variant="ghost"
                            :class="
                                cn(
                                    'w-full justify-start rounded-full',
                                    isCurrentOrParentUrl(item.href) &&
                                        'nav-pill-active',
                                )
                            "
                            as-child
                        >
                            <Link :href="item.href">{{ item.title }}</Link>
                        </Button>
                    </nav>
                </GlassCard>
            </aside>

            <Separator class="lg:hidden" />

            <GlassCard class="min-w-0 flex-1">
                <section class="max-w-xl space-y-10">
                    <slot />
                </section>
            </GlassCard>
        </div>
    </div>
</template>
