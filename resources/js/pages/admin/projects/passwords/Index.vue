<script setup lang="ts">
import { Form, Head, useHttp } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import ProjectPassphraseController from '@/actions/App/Http/Controllers/Admin/ProjectPassphraseController';
import ProjectPasswordController from '@/actions/App/Http/Controllers/Admin/ProjectPasswordController';
import DataTable from '@/components/dashboard/DataTable.vue';
import DataTableEmptyRow from '@/components/dashboard/DataTableEmptyRow.vue';
import DataTableTd from '@/components/dashboard/DataTableTd.vue';
import DataTableTh from '@/components/dashboard/DataTableTh.vue';
import FormField from '@/components/dashboard/FormField.vue';
import GlassCard from '@/components/dashboard/GlassCard.vue';
import PageHeader from '@/components/dashboard/PageHeader.vue';
import TableRow from '@/components/dashboard/TableRow.vue';
import TableIconAction from '@/components/TableIconAction.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    index as projectsIndex,
    show as projectsShow,
} from '@/routes/admin/projects/index';
import { index as projectPasswordsIndex } from '@/routes/admin/projects/passwords/index';

type Creator = {
    id: number;
    name: string;
} | null;

type PasswordRow = {
    id: number;
    label: string;
    username: string | null;
    url: string | null;
    has_notes: boolean;
    created_by: Creator;
    created_at: string | null;
};

type RevealedPassword = {
    secret: string;
    notes: string | null;
};

const props = defineProps<{
    project: {
        id: number;
        name: string;
        has_passphrase: boolean;
    };
    passwords: PasswordRow[];
}>();

defineOptions({
    layout: (pageProps: { project: { id: number; name: string } }) => ({
        breadcrumbs: [
            { title: 'Projects', href: projectsIndex() },
            {
                title: pageProps.project.name,
                href: projectsShow(pageProps.project.id),
            },
            {
                title: 'Passwords',
                href: projectPasswordsIndex(pageProps.project.id),
            },
        ],
    }),
});

const addOpen = ref(false);
const addFormKey = ref(0);
const editOpen = ref(false);
const editing = ref<PasswordRow | null>(null);
const editFormKey = ref(0);
const deleteOpen = ref(false);
const deleting = ref<PasswordRow | null>(null);
const revealOpen = ref(false);
const revealing = ref<PasswordRow | null>(null);
const revealed = ref<RevealedPassword | null>(null);

const revealHttp = useHttp({
    passphrase: '',
}).dontRemember('passphrase');

watch(revealOpen, (open) => {
    if (!open) {
        revealed.value = null;
        revealing.value = null;
        revealHttp.passphrase = '';
        revealHttp.clearErrors();
    }
});

function openEdit(row: PasswordRow): void {
    editing.value = row;
    editFormKey.value += 1;
    editOpen.value = true;
}

function openDelete(row: PasswordRow): void {
    deleting.value = row;
    deleteOpen.value = true;
}

function openReveal(row: PasswordRow): void {
    revealing.value = row;
    revealed.value = null;
    revealHttp.passphrase = '';
    revealHttp.clearErrors();
    revealOpen.value = true;
}

function submitReveal(): void {
    if (revealing.value === null) {
        return;
    }

    revealHttp.submit(
        ProjectPasswordController.reveal({
            project: props.project.id,
            password: revealing.value.id,
        }),
        {
            onSuccess: (response: RevealedPassword) => {
                revealed.value = response;
                revealHttp.passphrase = '';
            },
        },
    );
}

function formatCreatedAt(value: string | null): string {
    if (value === null) {
        return '—';
    }

    return new Date(value).toLocaleString();
}
</script>

<template>
    <Head :title="`${project.name} passwords`" />

    <div class="flex flex-col gap-8">
        <PageHeader
            title="Passwords"
            :description="`Shared credentials for ${project.name}. The project passphrase encrypts every password and is not stored.`"
        >
            <template v-if="project.has_passphrase" #actions>
                <Button type="button" @click="addOpen = true"
                    >Add password</Button
                >
            </template>
        </PageHeader>

        <GlassCard v-if="!project.has_passphrase" class="p-6">
            <div class="mb-6 space-y-1">
                <h2 class="text-lg font-semibold">Set project passphrase</h2>
                <p class="text-sm text-muted-foreground">
                    This project does not have a passphrase yet. Set it once.
                    Everyone who should read these passwords needs the same
                    passphrase. It cannot be recovered later.
                </p>
            </div>
            <Form
                v-bind="ProjectPassphraseController.store.form(project.id)"
                class="grid max-w-xl gap-6"
                v-slot="{ errors, processing }"
            >
                <FormField
                    label="Passphrase"
                    html-for="passphrase"
                    :error="errors.passphrase"
                    required
                    hint="At least 12 characters. Share it only with people who should open these passwords."
                >
                    <Input
                        id="passphrase"
                        name="passphrase"
                        type="password"
                        autocomplete="new-password"
                        required
                        minlength="12"
                    />
                </FormField>
                <FormField
                    label="Confirm passphrase"
                    html-for="passphrase_confirmation"
                    :error="errors.passphrase_confirmation"
                    required
                >
                    <Input
                        id="passphrase_confirmation"
                        name="passphrase_confirmation"
                        type="password"
                        autocomplete="new-password"
                        required
                        minlength="12"
                    />
                </FormField>
                <Button type="submit" :disabled="processing"
                    >Save passphrase</Button
                >
            </Form>
        </GlassCard>

        <DataTable v-else>
            <thead>
                <tr
                    class="border-b border-border/60 bg-muted/40 backdrop-blur-sm"
                >
                    <DataTableTh>Label</DataTableTh>
                    <DataTableTh>Username</DataTableTh>
                    <DataTableTh>URL</DataTableTh>
                    <DataTableTh>Added by</DataTableTh>
                    <DataTableTh class="text-right">Actions</DataTableTh>
                </tr>
            </thead>
            <tbody>
                <TableRow v-for="row in passwords" :key="row.id">
                    <DataTableTd label="Label">
                        <div class="font-medium">{{ row.label }}</div>
                        <p
                            v-if="row.has_notes"
                            class="text-xs text-muted-foreground"
                        >
                            Has notes
                        </p>
                    </DataTableTd>
                    <DataTableTd label="Username">{{
                        row.username ?? '—'
                    }}</DataTableTd>
                    <DataTableTd label="URL">
                        <a
                            v-if="row.url"
                            :href="row.url"
                            class="text-primary underline-offset-4 hover:underline"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            {{ row.url }}
                        </a>
                        <span v-else>—</span>
                    </DataTableTd>
                    <DataTableTd label="Added by">
                        <div>{{ row.created_by?.name ?? '—' }}</div>
                        <p class="text-xs text-muted-foreground">
                            {{ formatCreatedAt(row.created_at) }}
                        </p>
                    </DataTableTd>
                    <DataTableTd
                        label="Actions"
                        class="text-left md:text-right"
                    >
                        <div class="flex justify-end gap-1">
                            <TableIconAction
                                icon="eye"
                                label="Reveal password"
                                @click="openReveal(row)"
                            />
                            <TableIconAction
                                icon="pencil"
                                label="Edit password"
                                @click="openEdit(row)"
                            />
                            <TableIconAction
                                icon="trash"
                                label="Delete password"
                                @click="openDelete(row)"
                            />
                        </div>
                    </DataTableTd>
                </TableRow>
                <DataTableEmptyRow
                    v-if="passwords.length === 0"
                    :colspan="5"
                    message="No passwords yet."
                />
            </tbody>
        </DataTable>

        <Dialog v-model:open="addOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Add password</DialogTitle>
                    <DialogDescription>
                        Enter the project passphrase. It is checked before this
                        password is encrypted.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    :key="addFormKey"
                    v-bind="ProjectPasswordController.store.form(project.id)"
                    class="grid gap-4"
                    @success="
                        addOpen = false;
                        addFormKey += 1;
                    "
                    v-slot="{ errors, processing }"
                >
                    <FormField
                        label="Label"
                        html-for="add-label"
                        :error="errors.label"
                        required
                    >
                        <Input
                            id="add-label"
                            name="label"
                            type="text"
                            required
                        />
                    </FormField>
                    <FormField
                        label="Username"
                        html-for="add-username"
                        :error="errors.username"
                    >
                        <Input
                            id="add-username"
                            name="username"
                            type="text"
                            autocomplete="off"
                        />
                    </FormField>
                    <FormField
                        label="URL"
                        html-for="add-url"
                        :error="errors.url"
                    >
                        <Input id="add-url" name="url" type="url" />
                    </FormField>
                    <FormField
                        label="Password"
                        html-for="add-secret"
                        :error="errors.secret"
                        required
                    >
                        <Input
                            id="add-secret"
                            name="secret"
                            type="password"
                            autocomplete="new-password"
                            required
                        />
                    </FormField>
                    <FormField
                        label="Notes"
                        html-for="add-notes"
                        :error="errors.notes"
                    >
                        <textarea
                            id="add-notes"
                            name="notes"
                            rows="3"
                            class="w-full rounded-xl border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                        />
                    </FormField>
                    <FormField
                        label="Project passphrase"
                        html-for="add-passphrase"
                        :error="errors.passphrase"
                        required
                    >
                        <Input
                            id="add-passphrase"
                            name="passphrase"
                            type="password"
                            autocomplete="off"
                            required
                        />
                    </FormField>
                    <DialogFooter class="gap-2 sm:justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            @click="addOpen = false"
                            >Cancel</Button
                        >
                        <Button type="submit" :disabled="processing"
                            >Save password</Button
                        >
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="editOpen">
            <DialogContent v-if="editing" class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Edit {{ editing.label }}</DialogTitle>
                    <DialogDescription>
                        Leave the password and notes blank to keep the saved
                        values.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    :key="editFormKey"
                    v-bind="
                        ProjectPasswordController.update.form({
                            project: project.id,
                            password: editing.id,
                        })
                    "
                    class="grid gap-4"
                    @success="editOpen = false"
                    v-slot="{ errors, processing }"
                >
                    <FormField
                        label="Label"
                        html-for="edit-label"
                        :error="errors.label"
                        required
                    >
                        <Input
                            id="edit-label"
                            name="label"
                            type="text"
                            required
                            :default-value="editing.label"
                        />
                    </FormField>
                    <FormField
                        label="Username"
                        html-for="edit-username"
                        :error="errors.username"
                    >
                        <Input
                            id="edit-username"
                            name="username"
                            type="text"
                            autocomplete="off"
                            :default-value="editing.username ?? ''"
                        />
                    </FormField>
                    <FormField
                        label="URL"
                        html-for="edit-url"
                        :error="errors.url"
                    >
                        <Input
                            id="edit-url"
                            name="url"
                            type="url"
                            :default-value="editing.url ?? ''"
                        />
                    </FormField>
                    <FormField
                        label="Password"
                        html-for="edit-secret"
                        :error="errors.secret"
                        hint="Leave blank to keep the current password."
                    >
                        <Input
                            id="edit-secret"
                            name="secret"
                            type="password"
                            autocomplete="new-password"
                        />
                    </FormField>
                    <FormField
                        label="Notes"
                        html-for="edit-notes"
                        :error="errors.notes"
                        hint="Leave blank to keep the current notes."
                    >
                        <textarea
                            id="edit-notes"
                            name="notes"
                            rows="3"
                            class="w-full rounded-xl border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                        />
                    </FormField>
                    <div class="flex gap-3">
                        <input type="hidden" name="clear_notes" value="0" />
                        <input
                            id="edit-clear-notes"
                            name="clear_notes"
                            type="checkbox"
                            value="1"
                            class="mt-1 size-4 shrink-0 rounded border border-input"
                        />
                        <Label
                            for="edit-clear-notes"
                            class="cursor-pointer font-normal"
                            >Remove saved notes</Label
                        >
                    </div>
                    <FormField
                        label="Project passphrase"
                        html-for="edit-passphrase"
                        :error="errors.passphrase"
                        required
                    >
                        <Input
                            id="edit-passphrase"
                            name="passphrase"
                            type="password"
                            autocomplete="off"
                            required
                        />
                    </FormField>
                    <DialogFooter class="gap-2 sm:justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            @click="editOpen = false"
                            >Cancel</Button
                        >
                        <Button type="submit" :disabled="processing"
                            >Save changes</Button
                        >
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="deleteOpen">
            <DialogContent v-if="deleting" class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Delete {{ deleting.label }}?</DialogTitle>
                    <DialogDescription>
                        This removes the encrypted password. Enter the project
                        passphrase to confirm.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    v-bind="
                        ProjectPasswordController.destroy.form({
                            project: project.id,
                            password: deleting.id,
                        })
                    "
                    class="grid gap-4"
                    @success="deleteOpen = false"
                    v-slot="{ errors, processing }"
                >
                    <FormField
                        label="Project passphrase"
                        html-for="delete-passphrase"
                        :error="errors.passphrase"
                        required
                    >
                        <Input
                            id="delete-passphrase"
                            name="passphrase"
                            type="password"
                            autocomplete="off"
                            required
                        />
                    </FormField>
                    <DialogFooter class="gap-2 sm:justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            @click="deleteOpen = false"
                            >Cancel</Button
                        >
                        <Button
                            type="submit"
                            variant="destructive"
                            :disabled="processing"
                            >Delete password</Button
                        >
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="revealOpen">
            <DialogContent v-if="revealing" class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Reveal {{ revealing.label }}</DialogTitle>
                    <DialogDescription>
                        The passphrase is checked on the server and the password
                        is shown only in this window.
                    </DialogDescription>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="submitReveal">
                    <FormField
                        label="Project passphrase"
                        html-for="reveal-passphrase"
                        :error="revealHttp.errors.passphrase"
                        required
                    >
                        <Input
                            id="reveal-passphrase"
                            v-model="revealHttp.passphrase"
                            type="password"
                            autocomplete="off"
                            required
                        />
                    </FormField>
                    <FormField
                        v-if="revealed"
                        label="Password"
                        html-for="revealed-secret"
                    >
                        <Input
                            id="revealed-secret"
                            v-model="revealed.secret"
                            type="text"
                            readonly
                        />
                    </FormField>
                    <FormField
                        v-if="revealed?.notes"
                        label="Notes"
                        html-for="revealed-notes"
                    >
                        <textarea
                            id="revealed-notes"
                            :value="revealed.notes"
                            rows="3"
                            readonly
                            class="w-full rounded-xl border border-input bg-transparent px-3 py-2 text-sm shadow-xs dark:bg-input/30"
                        />
                    </FormField>
                    <DialogFooter class="gap-2 sm:justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            @click="revealOpen = false"
                            >Close</Button
                        >
                        <Button type="submit" :disabled="revealHttp.processing"
                            >Reveal</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
