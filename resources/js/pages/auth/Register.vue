<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import FormField from '@/components/dashboard/FormField.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineOptions({
    layout: {
        title: 'Create an account',
        description: 'Enter your details below to create your account',
    },
});

const page = usePage();
const registrationDomain = computed(
    () => page.props.companyRegistration.registration_email_domain,
);
const emailPlaceholder = computed(
    () => `you@${registrationDomain.value || 'company.com'}`,
);
</script>

<template>
    <Head title="Register" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <p
                v-if="registrationDomain"
                class="text-sm text-muted-foreground"
            >
                Use your
                <strong class="text-foreground"
                    >@{{ registrationDomain }}</strong
                >
                email address to create an account.
            </p>

            <FormField
                label="Name"
                html-for="name"
                :error="errors.name"
                required
            >
                <Input
                    id="name"
                    type="text"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="name"
                    name="name"
                    placeholder="Full name"
                />
            </FormField>

            <FormField
                label="Email address"
                html-for="email"
                :error="errors.email"
                required
            >
                <Input
                    id="email"
                    type="email"
                    required
                    :tabindex="2"
                    autocomplete="email"
                    name="email"
                    :placeholder="emailPlaceholder"
                />
            </FormField>

            <FormField
                label="Password"
                html-for="password"
                :error="errors.password"
                required
            >
                <PasswordInput
                    id="password"
                    required
                    :tabindex="3"
                    autocomplete="new-password"
                    name="password"
                    placeholder="Password"
                />
            </FormField>

            <FormField
                label="Confirm password"
                html-for="password_confirmation"
                :error="errors.password_confirmation"
                required
            >
                <PasswordInput
                    id="password_confirmation"
                    required
                    :tabindex="4"
                    autocomplete="new-password"
                    name="password_confirmation"
                    placeholder="Confirm password"
                />
            </FormField>

            <Button
                type="submit"
                class="mt-2 w-full"
                tabindex="5"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                Create account
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            Already have an account?
            <TextLink
                :href="login()"
                class="underline underline-offset-4"
                :tabindex="6"
                >Log in</TextLink
            >
        </div>
    </Form>
</template>
