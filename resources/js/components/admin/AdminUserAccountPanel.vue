<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { AlertTriangle, PencilLine, UserRoundCog } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import ResourceTablePanel from '@/components/shared/ResourceTablePanel.vue';
import StatCard from '@/components/shared/StatCard.vue';
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
import type { AdminAccountRole, AdminAccountRow } from '@/types/admin';
import type { Metric, TableColumn, TableRow } from '@/types/resource-table';

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

// const profileForm = useForm({
//     bio: '',
//     profile_picture: null as File | null,
// });

const stats = computed<Metric[]>(() => [
    {
        label: 'Total accounts',
        value: String(users.value.length),
        detail: 'Managed from one admin workspace',
        tone: 'info',
    },
    {
        label: 'Admins and coaches',
        value: String(
            users.value.filter((user) => {
                const roles = user.roles && user.roles.length > 0 ? user.roles : [user.role];
                return roles.includes('admin') || roles.includes('coach');
            }).length,
        ),
        detail: 'Core operating team',
        tone: 'success',
    },
    {
        label: 'Invites pending',
        value: String(users.value.filter((user) => user.status === 'invited').length),
        detail: 'Accounts waiting to complete setup',
        tone: 'warning',
    },
    {
        label: 'Suspended accounts',
        value: String(users.value.filter((user) => user.status === 'suspended').length),
        detail: 'Needs admin review',
        tone: 'danger',
    },
]);

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

const roleOptions = [
    { value: 'admin', label: 'Admin' },
    { value: 'coach', label: 'Coach' },
    { value: 'parent', label: 'Parent' },
    { value: 'athlete', label: 'Athlete' },
];

const statusOptions = [
    { value: 'active', label: 'Active' },
    { value: 'invited', label: 'Invited' },
    { value: 'suspended', label: 'Suspended' },
];

const statusTone: Record<AdminAccountRow['status'], 'success' | 'warning' | 'danger'> = {
    active: 'success',
    invited: 'warning',
    suspended: 'danger',
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
            text: user.status.charAt(0).toUpperCase() + user.status.slice(1),
            tone: statusTone[user.status],
        },
        createdAt: user.createdAt,
        deletedAt: user.deletedAt ?? '-',
    })),
);

function openEditByRow(row: TableRow) {
    const user = users.value.find((entry) => String(entry.id) === row.id);

    if (!user) {
        return;
    }

    openEdit(user);
}

// function viewProfile(row: TableRow) {
//     const userId = row.id;
//     router.visit(`/admin/accounts/${userId}`);
// }

const resetForm = () => {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    form.name = '';
    form.email = '';
    form.roles = ['athlete'];
    form.branch = '';
    form.status = 'active';
    form.password = '';
    form.password_confirmation = '';
    // profileForm.reset();
    // profileForm.bio = '';
    // profileForm.profile_picture = null;
};

const openCreate = () => {
    resetForm();
    isFormOpen.value = true;
};

const openEdit = (user: AdminAccountRow) => {
    editingId.value = user.id;
    form.name = user.name;
    form.email = user.email;
    form.roles = user.roles && user.roles.length > 0 ? [...user.roles] : [user.role];
    form.branch = user.branch;
    form.status = user.status;
    form.password = '';
    form.password_confirmation = '';
    // profileForm.bio = user.bio ?? '';
    // profileForm.profile_picture = null;
    isFormOpen.value = true;
};

// function onProfilePictureChange(event: Event) {
//     const target = event.target as HTMLInputElement;
//     profileForm.profile_picture = target.files?.[0] ?? null;
// }

function generatePassword() {
    const generated = Math.random().toString(36).slice(-8) + Math.random().toString(36).slice(-4).toUpperCase() + '!';
    form.password = generated;
    form.password_confirmation = generated;
}

const submit = () => {
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
        form.transform(() => payload).put(`/admin/accounts/${editingId.value}`, options);

        return;
    }

    form.transform(() => payload).post('/admin/accounts', options);
};

// function saveRosterProfile() {
//     if (editingId.value === null) return;
//     profileForm.post(`/admin/accounts/${editingId.value}/profile`, {
//         forceFormData: true,
//         preserveScroll: true,
//     });
// }

function deleteAccount(row: TableRow) {
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

function restoreAccount(row: TableRow) {
    const id = Number(row.id);
    if (!id) return;
    router.put(`/admin/accounts/${id}/restore`, {}, { preserveScroll: true });
}

function hardDeleteAccount(row: TableRow) {
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

function cancelPendingConfirmation() {
    pendingConfirmation.value = null;
}

function handleConfirmationOpenChange(open: boolean) {
    if (!open) {
        cancelPendingConfirmation();
    }
}

function confirmPendingAction() {
    const confirmation = pendingConfirmation.value;
    if (!confirmation) return;

    pendingConfirmation.value = null;

    if (confirmation.kind === 'hard-delete') {
        router.delete(`/admin/accounts/${confirmation.id}/hard-delete`, {
            preserveScroll: true,
        });
        return;
    }

    router.delete(`/admin/accounts/${confirmation.id}`, {
        preserveScroll: true,
    });
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
                    <Button type="button" variant="outline" @click="cancelPendingConfirmation"> Cancel </Button>
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
        
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <StatCard v-for="metric in stats" :key="metric.label" v-bind="metric" />
        </div>
        
        <ResourceTablePanel
            eyebrow="Admin panel"
            title="User Account Directory"
            description="Modeled after the JTE admin workspace: one place to review account types, manage access, and prepare new users for onboarding."
            create-label="New account"
            table-title="Account roster"
            table-description="A role-based management table for admins, coaches, parents, and athletes. This is the same operating shape as your JTE panel, adapted to RF IS roles."
            :columns="columns"
            :rows="rows"
            empty-text="No accounts available yet."
            action-label="Action"
            @create="openCreate"
        >

            <template #row-actions="{ row }">
                <div class="flex justify-end gap-2">
                    <!-- <Button v-if="row.deletedAt === '-'" variant="outline" class="gap-2" @click="viewProfile(row)">
                        <UserRoundCog class="size-4" />
                        View Profile -->
                    <Button v-if="row.deletedAt === '-'" variant="outline" class="gap-2" @click="openEditByRow(row)">
                        <PencilLine class="size-4" />
                        Edit
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
                    {{ editingId !== null ? 'Edit account' : 'Create account' }}
                </DialogTitle>
                <DialogDescription>
                    The form layout follows your JTE admin panel pattern and is ready to connect to Laravel actions
                    later.
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4 py-2 md:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="admin-name">Full name</Label>
                    <Input id="admin-name" v-model="form.name" placeholder="Enter full name" />
                    <p v-if="form.errors.name" class="text-sm text-destructive">
                        {{ form.errors.name }}
                    </p>
                </div>
                <div class="grid gap-2">
                    <Label for="admin-email">Email</Label>
                    <Input id="admin-email" v-model="form.email" placeholder="name@example.com" />
                    <p v-if="form.errors.email" class="text-sm text-destructive">
                        {{ form.errors.email }}
                    </p>
                </div>
                <div class="grid gap-2 md:col-span-2">
                    <Label>Roles</Label>
                    <div class="grid gap-2 rounded-md border border-input p-3">
                        <label
                            v-for="option in roleOptions"
                            :key="option.value"
                            class="flex items-center gap-2 text-sm"
                        >
                            <input
                                type="checkbox"
                                :checked="form.roles.includes(option.value as AdminAccountRole)"
                                @change="
                                    ($event) => {
                                        const role = option.value as AdminAccountRole;
                                        if (($event.target as HTMLInputElement).checked) {
                                            if (!form.roles.includes(role)) form.roles.push(role);
                                        } else {
                                            form.roles = form.roles.filter((entry) => entry !== role);
                                        }
                                    }
                                "
                            />
                            <span>{{ option.label }}</span>
                        </label>
                    </div>
                    <p v-if="rolesError" class="text-sm text-destructive">
                        {{ rolesError }}
                    </p>
                </div>
                <FormSelectField
                    id="admin-status"
                    v-model="form.status"
                    label="Status"
                    :options="statusOptions"
                    :error="form.errors.status"
                />
                <div class="grid gap-2">
                    <div class="flex items-center justify-between gap-3">
                        <Label for="admin-password">Password</Label>
                        <Button type="button" variant="outline" size="sm" @click="generatePassword"> Generate </Button>
                    </div>
                    <Input
                        id="admin-password"
                        v-model="form.password"
                        type="text"
                        :placeholder="
                            editingId !== null ? 'Leave blank to keep current password' : 'Set initial password'
                        "
                    />
                    <p v-if="form.errors.password" class="text-sm text-destructive">
                        {{ form.errors.password }}
                    </p>
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
                <!-- <div class="grid gap-2 md:col-span-2">
                    <Label for="admin-branch">Branch</Label>
                    <Input id="admin-branch" v-model="form.branch" placeholder="Branch is derived from linked role records" disabled />
                    <p class="text-sm text-muted-foreground">
                        Branch is currently read-only here and comes from the linked athlete, coach, or child records in the database.
                    </p>
                </div> -->
                <!-- <div v-if="editingId !== null" class="grid gap-2 md:col-span-2">
                    <Label for="admin-bio">Bio</Label>
                    <textarea id="admin-bio" v-model="profileForm.bio" rows="3" class="rounded-md border border-input bg-background px-3 py-2 text-sm" />
                    <p v-if="profileForm.errors.bio" class="text-sm text-destructive">{{ profileForm.errors.bio }}</p>
                </div>
                <div v-if="editingId !== null" class="grid gap-2 md:col-span-2">
                    <Label for="admin-profile-picture">Profile picture</Label>
                    <Input id="admin-profile-picture" type="file" accept="image/*" @change="onProfilePictureChange" />
                    <p v-if="profileForm.errors.profile_picture" class="text-sm text-destructive">{{ profileForm.errors.profile_picture }}</p>
                </div> -->
            </div>

            <DialogFooter class="gap-2">
                <Button variant="outline" @click="isFormOpen = false">Cancel</Button>
                <!-- <Button v-if="editingId !== null" variant="outline" :disabled="profileForm.processing" @click="saveRosterProfile">
                    Save profile
                </Button> -->
                <Button :disabled="form.processing" @click="submit">
                    {{ editingId !== null ? 'Save changes' : 'Create account' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
