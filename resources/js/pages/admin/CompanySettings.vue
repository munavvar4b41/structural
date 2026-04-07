<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import CompanySettingsController from '@/actions/App/Http/Controllers/Admin/CompanySettingsController';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit as adminCompanyEdit } from '@/routes/admin/company/index';

type CompanySettingsPayload = {
    name: string;
    legal_name: string | null;
    phone: string | null;
    website: string | null;
    address_line1: string | null;
    address_line2: string | null;
    city: string | null;
    region: string | null;
    postal_code: string | null;
    country: string | null;
    email_domain: string;
};

type Props = {
    company: CompanySettingsPayload;
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Company settings',
                href: adminCompanyEdit(),
            },
        ],
    },
});

const props = defineProps<Props>();

function dv(value: string | null | undefined): string {
    return value ?? '';
}
</script>

<template>
    <Head title="Company settings" />

    <div class="flex flex-col gap-8">
        <Heading
            title="Company settings"
            description="Details for your organization and who may self-register"
        />

        <Form
            v-bind="CompanySettingsController.update.form()"
            class="flex flex-col gap-8"
            v-slot="{ errors, processing, recentlySuccessful }"
        >
            <Card>
                <CardHeader>
                    <CardTitle>Company details</CardTitle>
                    <CardDescription>
                        Basic information shown internally and in customer-facing
                        contexts where appropriate.
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid max-w-xl gap-6">
                    <div class="grid gap-2">
                        <Label for="name">Company name</Label>
                        <Input
                            id="name"
                            name="name"
                            required
                            :default-value="dv(props.company.name)"
                            autocomplete="organization"
                        />
                        <InputError class="mt-1" :message="errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="legal_name">Legal name</Label>
                        <Input
                            id="legal_name"
                            name="legal_name"
                            :default-value="dv(props.company.legal_name)"
                            autocomplete="off"
                        />
                        <InputError class="mt-1" :message="errors.legal_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="phone">Phone</Label>
                        <Input
                            id="phone"
                            name="phone"
                            type="tel"
                            :default-value="dv(props.company.phone)"
                            autocomplete="tel"
                        />
                        <InputError class="mt-1" :message="errors.phone" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="website">Website</Label>
                        <Input
                            id="website"
                            name="website"
                            type="url"
                            :default-value="dv(props.company.website)"
                            autocomplete="url"
                            placeholder="https://"
                        />
                        <InputError class="mt-1" :message="errors.website" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="address_line1">Address line 1</Label>
                        <Input
                            id="address_line1"
                            name="address_line1"
                            :default-value="dv(props.company.address_line1)"
                            autocomplete="address-line1"
                        />
                        <InputError
                            class="mt-1"
                            :message="errors.address_line1"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="address_line2">Address line 2</Label>
                        <Input
                            id="address_line2"
                            name="address_line2"
                            :default-value="dv(props.company.address_line2)"
                            autocomplete="address-line2"
                        />
                        <InputError
                            class="mt-1"
                            :message="errors.address_line2"
                        />
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2 sm:gap-4">
                        <div class="grid gap-2">
                            <Label for="city">City</Label>
                            <Input
                                id="city"
                                name="city"
                                :default-value="dv(props.company.city)"
                                autocomplete="address-level2"
                            />
                            <InputError
                                class="mt-1"
                                :message="errors.city"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="region">Region / state</Label>
                            <Input
                                id="region"
                                name="region"
                                :default-value="dv(props.company.region)"
                                autocomplete="address-level1"
                            />
                            <InputError
                                class="mt-1"
                                :message="errors.region"
                            />
                        </div>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2 sm:gap-4">
                        <div class="grid gap-2">
                            <Label for="postal_code">Postal code</Label>
                            <Input
                                id="postal_code"
                                name="postal_code"
                                :default-value="dv(props.company.postal_code)"
                                autocomplete="postal-code"
                            />
                            <InputError
                                class="mt-1"
                                :message="errors.postal_code"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="country">Country</Label>
                            <Input
                                id="country"
                                name="country"
                                :default-value="dv(props.company.country)"
                                autocomplete="country-name"
                            />
                            <InputError
                                class="mt-1"
                                :message="errors.country"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Self-service registration</CardTitle>
                    <CardDescription>
                        Only email addresses at this domain may create an
                        account via the public registration form. Existing users
                        are not affected when you change this.
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid max-w-xl gap-6">
                    <div class="grid gap-2">
                        <Label for="email_domain">Allowed email domain</Label>
                        <Input
                            id="email_domain"
                            name="email_domain"
                            required
                            :default-value="dv(props.company.email_domain)"
                            autocomplete="off"
                            placeholder="example.com"
                        />
                        <InputError
                            class="mt-1"
                            :message="errors.email_domain"
                        />
                    </div>
                </CardContent>
            </Card>

            <div class="flex items-center gap-4">
                <Button type="submit" :disabled="processing">
                    Save changes
                </Button>
                <p
                    v-if="recentlySuccessful"
                    class="text-sm text-muted-foreground"
                >
                    Saved.
                </p>
            </div>
        </Form>
    </div>
</template>
