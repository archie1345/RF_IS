<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { AlertTriangle, PencilLine, Plus, UserRoundCog } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import ResourceTablePanel from '@/components/shared/ResourceTablePanel.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
    destroy as adminAccountDestroy,
    forceDelete as adminAccountForceDelete,
    restore as adminAccountRestore,
    store as adminAccountStore,
    update as adminAccountUpdate,
} from '@/routes/admin/accounts';
import { resend as adminAccountInvitationResend } from '@/routes/admin/accounts/invitation';
import type { AdminAccountRole, AdminAccountRow } from '@/types/admin';
import type { TableColumn, TableRow } from '@/types/resource-table';

const props = defineProps<{
    initialUsers: AdminAccountRow[];
}>();

const isFormOpen = ref(false);
const editingId = ref<number | null>(null);
const pendingConfirmation = ref<{
    id: number;
    kind: 'soft-delete' | 'hard-delete';
    title: string;
    message: string;
    confirmLabel: string;
} | null>(null);

const users = computed(() => props.initialUsers);

const form = useForm({
    name: '',
    email: '',
    roles: ['athlete'] as AdminAccountRole[],
    branch: '',
    status: 'active' as AdminAccountRow['status'],
    password: '',
    password_confirmation: '',
});

const rolesError = computed(() => (form.errors as Record<string, string>).roles);

const roleLabel: Record<AdminAccountRole, string> = {
    admin: 'Admin',
    coach: 'Coach',
    parent: 'Parent',
    athlete: 'Athlete',
};

const roleTone: Record<AdminAccountRole, 'danger' | 'info' | 'warning' | 'success'> = {
    admin: 'danger',
    coach: 'info',
    parent: 'warning',
    athlete: 'success',
};

const roleOptions: Array<{ value: AdminAccountRole; label: string }> = [
    { value: 'admin', label: 'Admin' },
    { value: 'coach', label: 'Coach' },
    { value: 'parent', label: 'Parent' },
    { value: 'athlete', label: 'Athlete' },
];

const statusOptions = [
    { value: 'active', label: 'Active' },
    { value: 'suspended', label: 'Not active' },
    { value: 'invited', label: 'Invited' },
];

const statusTone: Record<AdminAccountRow['status'], 'success' | 'warning' | 'danger'> = {
    active: 'success',
    invited: 'warning',
    suspended: 'danger',
};

const statusLabel: Record<AdminAccountRow['status'], string> = {
    active: 'Active',
    invited: 'Invited',
    suspended: 'Not active',
};

const columns: TableColumn[] = [
    { key: 'name', label: 'Name' },
    { key: 'email', label: 'Contact' },
    { key: 'roleLabel', label: 'Roles' },
    { key: 'branch', label: 'Branch' },
    { key: 'statusLabel', label: 'Status' },
    { key: 'createdAt', label: 'Created' },
    { key: 'deletedAt', label: 'Deleted At' },
];

const rows = computed<TableRow[]>(() =>
    users.value.map((user) => ({
        id: String(user.id),
        name: user.name,
        email: user.email,
        roleLabel: {
            kind: 'badge',
            text: (user.roles && user.roles.length > 0 ? user.roles : [user.role])
                .map((role) => roleLabel[role])
                .join(', '),
            tone: roleTone[user.role],
        },
        branch: user.branch,
        statusLabel: {
            kind: 'badge',
            text: statusLabel[user.status],
            tone: statusTone[user.status],
        },
        createdAt: user.createdAt,
        deletedAt: user.deletedAt ?? '-',
        statusValue: user.status,
    })),
);

function accountForRow(row: TableRow): AdminAccountRow | undefined {
    return users.value.find((entry) => String(entry.id) === row.id);
}

function openEditByRow(row: TableRow): void {
    const user = accountForRow(row);
    if (user) openEdit(user);
}

function resetForm(): void {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    form.name = '';
    form.email = '';
    form.roles = ['athlete'];
    form.branch = '';
    form.status = 'invited';
    form.password = '';
    form.password_confirmation = '';
}

function openCreate(): void {
    resetForm();
    isFormOpen.value = true;
}

function openEdit(user: AdminAccountRow): void {
    editingId.value = user.id;
    form.name = user.name;
    form.email = user.email;
    form.roles = user.roles && user.roles.length > 0 ? [...user.roles] : [user.role];
    form.branch = user.branch;
    form.status = user.status;
    form.password = '';
    form.password_confirmation = '';
    isFormOpen.value = true;
}

function generatePassword(): void {
    const lowerChars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    const upperChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    const secureRandomString = (length: number, charset: string): string => {
        const result: string[] = [];
        const maxValid = Math.floor(0x100000000 / charset.length) * charset.length;

        while (result.length < length) {
            const randomValues = new Uint32Array(1);
            window.crypto.getRandomValues(randomValues);
            const value = randomValues[0];

            if (value < maxValid) result.push(charset[value % charset.length]);
        }

        return result.join('');
    };

    const generated = `${secureRandomString(8, lowerChars)}${secureRandomString(4, upperChars)}!`;
    form.password = generated;
    form.password_confirmation = generated;
}

function submit(): void {
    const payload = {
        name: form.name,
        email: form.email,
        roles: form.roles,
        status: form.status,
        password: form.password,
        password_confirmation: form.password_confirmation,
    };
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            isFormOpen.value = false;
            resetForm();
        },
    };

    if (editingId.value !== null) {
        form.transform(() => payload).put(adminAccountUpdate.url(editingId.value), options);
        return;
    }

    form.transform(() => payload).post(adminAccountStore.url(), options);
}

function isInvitedRow(row: TableRow): boolean {
    return row.statusValue === 'invited';
}

function resendInvitation(row: TableRow): void {
    const id = Number(row.id);
    if (!id) return;
    router.post(adminAccountInvitationResend.url(id), {}, { preserveScroll: true });
}

function deleteAccount(row: TableRow): void {
    const id = Number(row.id);
    if (!id) return;
    pendingConfirmation.value = {
        id,
        kind: 'soft-delete',
        title: 'Delete this account?',
        message: 'The account will be soft deleted and can still be restored later.',
        confirmLabel: 'Delete account',
    };
}

function restoreAccount(row: TableRow): void {
    const id = Number(row.id);
    if (!id) return;
    router.put(adminAccountRestore.url(id), {}, { preserveScroll: true });
}

function hardDeleteAccount(row: TableRow): void {
    const id = Number(row.id);
    if (!id) return;
    pendingConfirmation.value = {
        id,
        kind: 'hard-delete',
        title: 'Permanently delete this account?',
        message: 'This removes the account for good. This action cannot be undone.',
        confirmLabel: 'Permanently delete',
    };
}

function cancelPendingConfirmation(): void {
    pendingConfirmation.value = null;
}

function handleConfirmationOpenChange(open: boolean): void {
    if (!open) cancelPendingConfirmation();
}

function confirmPendingAction(): void {
    const confirmation = pendingConfirmation.value;
    if (!confirmation) return;

    pendingConfirmation.value = null;

    if (confirmation.kind === 'hard-delete') {
        router.delete(adminAccountForceDelete.url(confirmation.id), { preserveScroll: true });
        return;
    }

    router.delete(adminAccountDestroy.url(confirmation.id), { preserveScroll: true });
}
</script>

<template>
    <div class="space-y-6">
        <Dialog :open="Boolean(pendingConfirmation)" @update:open="handleConfirmationOpenChange">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ pendingConfirmation?.title }}</DialogTitle>
                    <DialogDescription>Review this action before continuing.</DialogDescription>
                </DialogHeader>

                <Alert
                    v-if="pendingConfirmation"
                    :variant="pendingConfirmation.kind === 'hard-delete' ? 'destructive' : 'default'"
                >
                    <AlertTriangle class="size-4" />
                    <AlertTitle>
                        {{ pendingConfirmation.kind === 'hard-delete' ? 'Permanent action' : 'Restore is available' }}
                    </AlertTitle>
                    <AlertDescription>{{ pendingConfirmation.message }}</AlertDescription>
                </Alert>

                <DialogFooter class="gap-2 sm:justify-end">
                    <Button type="button" variant="outline" @click="cancelPendingConfirmation">Cancel</Button>
                    <Button
                        v-if="pendingConfirmation"
                        type="button"
                        :variant="pendingConfirmation.kind === 'hard-delete' ? 'destructive' : 'default'"
                        @click="confirmPendingAction"
                    >
                        {{ pendingConfirmation.confirmLabel }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <ResourceTablePanel
            eyebrow="Admin panel"
            title="User Management"
            description="Create accounts here and set every non-invited account to Active or Not active. Profile pages remain responsible for role-specific details."
            create-label="Create user"
            table-title="Account roster"
            table-description="A role-based management table for admins, coaches, parents, and athletes."
            :columns="columns"
            :rows="rows"
            empty-text="No accounts available yet."
            action-label="Action"
            searchable
            search-placeholder="Search users by name, email, role, branch, or status"
            @create="openCreate"
        >
            <template #actions>
                <Button type="button" class="gap-2" @click="openCreate">
                    <Plus class="size-4" />
                    Add new user
                </Button>
            </template>

            <template #row-actions="{ row }">
                <div class="flex flex-wrap justify-end gap-2">
                    <Button v-if="row.deletedAt === '-'" variant="outline" class="gap-2" @click="openEditByRow(row)">
                        <PencilLine class="size-4" />
                    </Button>
                    <Button
                        v-if="row.deletedAt === '-' && isInvitedRow(row)"
                        variant="outline"
                        @click="resendInvitation(row)"
                    >
                        Resend invite
                    </Button>
                    <Button v-if="row.deletedAt === '-'" variant="destructive" @click="deleteAccount(row)"
                        >Delete</Button
                    >
                    <Button v-else variant="outline" @click="restoreAccount(row)">Restore</Button>
                    <Button v-if="row.deletedAt !== '-'" variant="destructive" @click="hardDeleteAccount(row)"
                        >Delete</Button
                    >
                </div>
            </template>
        </ResourceTablePanel>
    </div>

    <Dialog v-model:open="isFormOpen">
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <UserRoundCog class="size-5" />
                    {{ editingId !== null ? 'Edit account' : 'Create user account' }}
                </DialogTitle>
                <DialogDescription>
                    Admin-only account creation. Use multiple roles when the same person is both a coach, parent,
                    athlete, or admin.
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4 py-2 md:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="admin-name">Full name</Label>
                    <Input id="admin-name" v-model="form.name" placeholder="Enter full name" />
                    <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
                </div>
                <div class="grid gap-2">
                    <Label for="admin-email">Email</Label>
                    <Input id="admin-email" v-model="form.email" placeholder="name@example.com" />
                    <p v-if="form.errors.email" class="text-sm text-destructive">{{ form.errors.email }}</p>
                </div>
                <FormSelectField
                    id="admin-roles"
                    v-model="form.roles"
                    class="md:col-span-2"
                    label="Roles"
                    :options="roleOptions"
                    placeholder="Select one or more roles"
                    help="Select multiple roles when one account needs more than one permission/profile."
                    multiple
                    :error="rolesError"
                />
                <FormSelectField
                    id="admin-status"
                    v-model="form.status"
                    label="Account state"
                    :options="statusOptions"
                    :error="form.errors.status"
                />
                <div class="grid gap-2">
                    <div class="flex items-center justify-between gap-3">
                        <Label for="admin-password">Password</Label>
                        <Button type="button" variant="outline" size="sm" @click="generatePassword">Generate</Button>
                    </div>
                    <Input
                        id="admin-password"
                        v-model="form.password"
                        type="text"
                        :placeholder="
                            form.status === 'invited'
                                ? 'Invitation users set this themselves'
                                : editingId !== null
                                  ? 'Leave blank to keep current password'
                                  : 'Set initial password'
                        "
                    />
                    <p v-if="form.errors.password" class="text-sm text-destructive">{{ form.errors.password }}</p>
                </div>
                <div class="grid gap-2">
                    <Label for="admin-password-confirmation">Confirm password</Label>
                    <Input
                        id="admin-password-confirmation"
                        v-model="form.password_confirmation"
                        type="text"
                        placeholder="Repeat password"
                    />
                </div>
            </div>

            <DialogFooter class="gap-2">
                <Button variant="outline" @click="isFormOpen = false">Cancel</Button>
                <Button :disabled="form.processing" @click="submit">
                    {{ editingId !== null ? 'Save changes' : 'Create account' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
