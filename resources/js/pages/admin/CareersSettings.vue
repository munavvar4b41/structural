<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import CareersSettingsController from '@/actions/App/Http/Controllers/Admin/CareersSettingsController';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { edit as adminCareersSettingsEdit } from '@/routes/admin/careers-settings/index';

type Props = {
    notification_emails_text: string;
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Careers notification settings',
                href: adminCareersSettingsEdit(),
            },
        ],
    },
});

defineProps<Props>();
</script>

<template>

    <Head title="Careers notification settings" />

    <div class="flex flex-col gap-8">
        <PageHeader title="Careers notification settings"
            description="Addresses that receive an email when someone submits a job application." />

        <Form v-bind="CareersSettingsController.update.form()" class="flex flex-col gap-8"
            #default="{ errors, processing, recentlySuccessful }">
            <GlassCard class="p-6">
                <div class="mb-6 space-y-1">
                    <h2 class="text-lg font-semibold">Notification recipients</h2>
                    <p class="text-sm text-muted-foreground">
                        One email per line, or separate with commas.
                    </p>
                </div>
                <div class="grid max-w-xl gap-6">
                    <div class="grid gap-2">
                        <Label for="notification_emails_text">Email addresses</Label>
                        <textarea id="notification_emails_text" name="notification_emails_text" rows="8"
                            :value="notification_emails_text" :class="cn(
                                'border-input placeholder:text-muted-foreground min-h-[160px] w-full rounded-xl border bg-transparent px-3 py-2 text-base shadow-xs md:text-sm',
                                'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                            )" />
                        <InputError :message="errors.notification_emails ?? errors['notification_emails.0']" />
                    </div>

                    <div class="flex items-center gap-4">
                        <Button type="submit" :disabled="processing">Save</Button>
                        <span v-show="recentlySuccessful" class="text-sm text-neutral-600 dark:text-neutral-400">
                            Saved.
                        </span>
                    </div>
                </div>
            </GlassCard>
        </Form>
    </div>
</template>
