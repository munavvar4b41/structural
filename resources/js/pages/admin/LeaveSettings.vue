<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import LeaveSettingsController from '@/actions/App/Http/Controllers/Admin/LeaveSettingsController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { edit as adminLeaveSettingsEdit } from '@/routes/admin/leave-settings/index';

type Props = {
    notification_emails_text: string;
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Leave notification settings',
                href: adminLeaveSettingsEdit(),
            },
        ],
    },
});

defineProps<Props>();
</script>

<template>

    <Head title="Leave notification settings" />

    <div class="flex flex-col gap-8">
        <Heading title="Leave notification settings"
            description="Addresses that receive an email when someone submits a leave request. Team heads who share a team with the requester are always notified in addition to this list." />

        <Form v-bind="LeaveSettingsController.update.form()" class="flex flex-col gap-8"
            #default="{ errors, processing, recentlySuccessful }">
            <Card>
                <CardHeader>
                    <CardTitle>Notification recipients</CardTitle>
                    <CardDescription>
                        One email per line, or separate with commas. Duplicates are removed
                        automatically.
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid max-w-xl gap-6">
                    <div class="grid gap-2">
                        <Label for="notification_emails_text">Email addresses</Label>
                        <textarea id="notification_emails_text" name="notification_emails_text" rows="8"
                            :default-value="notification_emails_text" :class="cn(
                                'border-input placeholder:text-muted-foreground min-h-[160px] w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs md:text-sm',
                                'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                            )
                                " />
                        <InputError class="mt-1"
                            :message="errors.notification_emails ?? errors['notification_emails.0']" />
                    </div>

                    <div class="flex items-center gap-4">
                        <Button type="submit" :disabled="processing">Save</Button>
                        <span v-show="recentlySuccessful" class="text-sm text-neutral-600 dark:text-neutral-400">
                            Saved.
                        </span>
                    </div>
                </CardContent>
            </Card>
        </Form>
    </div>
</template>
