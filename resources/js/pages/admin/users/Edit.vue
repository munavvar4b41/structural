<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
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
import {
    index as usersIndex,
    edit as usersEdit,
} from '@/routes/admin/users/index';

type AssignableRole = {
    value: string;
    label: string;
};

type UserPayload = {
    id: number;
    name: string;
    email: string;
    role: string;
};

type Props = {
    user: UserPayload;
    assignableRoles: AssignableRole[];
};

defineProps<Props>();

defineOptions({
    layout: (pageProps: { user: UserPayload }) => ({
        breadcrumbs: [
            { title: 'Users', href: usersIndex() },
            {
                title: pageProps.user.name,
                href: usersEdit(pageProps.user.id),
            },
        ],
    }),
});
</script>

<template>
    <Head :title="`Edit ${user.name}`" />

    <div class="flex flex-col gap-8">
        <Heading
            title="Edit user"
            :description="`Update ${user.name}`"
        />

        <Form
            v-bind="UserController.update.form(user.id)"
            class="flex max-w-xl flex-col gap-8"
            v-slot="{ errors, processing, recentlySuccessful }"
        >
            <Card>
                <CardHeader>
                    <CardTitle>Account</CardTitle>
                    <CardDescription>
                        Name, email, role, and optional new password
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-6">
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            type="text"
                            required
                            autocomplete="name"
                            :default-value="user.name"
                        />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="email">Email</Label>
                        <Input
                            id="email"
                            name="email"
                            type="email"
                            required
                            autocomplete="username"
                            :default-value="user.email"
                        />
                        <InputError :message="errors.email" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="role">Role</Label>
                        <select
                            id="role"
                            name="role"
                            required
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                        >
                            <option
                                v-for="opt in assignableRoles"
                                :key="opt.value"
                                :value="opt.value"
                                :selected="opt.value === user.role"
                            >
                                {{ opt.label }}
                            </option>
                        </select>
                        <InputError :message="errors.role" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="password">New password</Label>
                        <Input
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="new-password"
                            placeholder="Leave blank to keep current password"
                        />
                        <InputError :message="errors.password" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="password_confirmation">
                            Confirm new password
                        </Label>
                        <Input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            autocomplete="new-password"
                        />
                        <InputError :message="errors.password_confirmation" />
                    </div>
                </CardContent>
            </Card>

            <div class="flex items-center gap-4">
                <Button type="submit" :disabled="processing">Save</Button>
                <Button variant="outline" as-child>
                    <Link :href="usersIndex()">Cancel</Link>
                </Button>
                <span
                    v-show="recentlySuccessful"
                    class="text-sm text-muted-foreground"
                >
                    Saved.
                </span>
            </div>
        </Form>
    </div>
</template>
