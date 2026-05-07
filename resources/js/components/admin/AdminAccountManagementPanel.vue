<script setup lang="ts">
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { PencilLine, UserRoundCog } from 'lucide-vue-next';
import CrudManagementPanel from '@/components/admin/CrudManagementPanel.vue';
import StatCard from '@/components/mvp/StatCard.vue';
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
import type { Metric, TableColumn, TableRow } from '@/types/mvp';

const props = defineProps<{
    initialUsers: AdminAccountRow[];
}>();

const isFormOpen = ref(false);
const editingId = ref<number | null>(null);

const users = computed(() => props.initialUsers);

const form = useForm({
    name: '',
    email: '',
    role: 'athlete' as AdminAccountRole,
    branch: '',
    status: 'active' as AdminAccountRow['status'],
    password: '',
    password_confirmation: '',
});

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
            users.value.filter((user) => user.role === 'admin' || user.role === 'coach').length,
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

const statusTone: Record<AdminAccountRow['status'], 'success' | 'warning' | 'danger'> = {
    active: 'success',
    invited: 'warning',
    suspended: 'danger',
};

const columns: TableColumn[] = [
    { key: 'name', label: 'Name' },
    { key: 'email', label: 'Contact' },
    { key: 'roleLabel', label: 'Role' },
    { key: 'branch', label: 'Branch' },
    { key: 'statusLabel', label: 'Status' },
    { key: 'createdAt', label: 'Created' },
];

const rows = computed<TableRow[]>(() =>
    users.value.map((user) => ({
        id: String(user.id),
        name: user.name,
        email: user.email,
        roleLabel: {
            kind: 'badge',
            text: roleLabel[user.role],
            tone: roleTone[user.role],
        },
        branch: user.branch,
        statusLabel: {
            kind: 'badge',
            text: user.status.charAt(0).toUpperCase() + user.status.slice(1),
            tone: statusTone[user.status],
        },
        createdAt: user.createdAt,
    })),
);

function openEditByRow(row: TableRow) {
    const user = users.value.find((entry) => String(entry.id) === row.id);

    if (!user) {
        return;
    }

    openEdit(user);
}

const resetForm = () => {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    form.name = '';
    form.email = '';
    form.role = 'athlete';
    form.branch = '';
    form.status = 'active';
    form.password = '';
    form.password_confirmation = '';
};

const openCreate = () => {
    resetForm();
    isFormOpen.value = true;
};

const openEdit = (user: AdminAccountRow) => {
    editingId.value = user.id;
    form.name = user.name;
    form.email = user.email;
    form.role = user.role;
    form.branch = user.branch;
    form.status = user.status;
    form.password = '';
    form.password_confirmation = '';
    isFormOpen.value = true;
};

function generatePassword() {
    const generated = Math.random().toString(36).slice(-8) + Math.random().toString(36).slice(-4).toUpperCase() + '!';
    form.password = generated;
    form.password_confirmation = generated;
}

const submit = () => {
    const payload = {
        name: form.name,
        email: form.email,
        role: form.role,
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
</script>

<template>
    <div class="space-y-6">
        <CrudManagementPanel
            eyebrow="Admin panel"
            title="Account Management"
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
            <template #stats>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <StatCard v-for="metric in stats" :key="metric.label" v-bind="metric" />
                </div>
            </template>

            <template #row-actions="{ row }">
                <Button variant="outline" class="gap-2" @click="openEditByRow(row)">
                    <PencilLine class="size-4" />
                    Edit
                </Button>
            </template>
        </CrudManagementPanel>
    </div>

    <Dialog v-model:open="isFormOpen">
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <UserRoundCog class="size-5" />
                    {{ editingId !== null ? 'Edit account' : 'Create account' }}
                </DialogTitle>
                <DialogDescription>
                    The form layout follows your JTE admin panel pattern and is ready to connect to Laravel actions later.
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
                <div class="grid gap-2">
                    <Label for="admin-role">Role</Label>
                    <select
                        id="admin-role"
                        v-model="form.role"
                        class="flex h-10 rounded-md border border-input bg-background px-3 py-2 text-sm"
                    >
                        <option value="admin">Admin</option>
                        <option value="coach">Coach</option>
                        <option value="parent">Parent</option>
                        <option value="athlete">Athlete</option>
                    </select>
                    <p v-if="form.errors.role" class="text-sm text-destructive">{{ form.errors.role }}</p>
                </div>
                <div class="grid gap-2">
                    <Label for="admin-status">Status</Label>
                    <select
                        id="admin-status"
                        v-model="form.status"
                        class="flex h-10 rounded-md border border-input bg-background px-3 py-2 text-sm"
                    >
                        <option value="active">Active</option>
                        <option value="invited">Invited</option>
                        <option value="suspended">Suspended</option>
                    </select>
                    <p v-if="form.errors.status" class="text-sm text-destructive">{{ form.errors.status }}</p>
                </div>
                <div class="grid gap-2">
                    <div class="flex items-center justify-between gap-3">
                        <Label for="admin-password">Password</Label>
                        <Button type="button" variant="outline" size="sm" @click="generatePassword">
                            Generate
                        </Button>
                    </div>
                    <Input
                        id="admin-password"
                        v-model="form.password"
                        type="text"
                        :placeholder="editingId !== null ? 'Leave blank to keep current password' : 'Set initial password'"
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
                <div class="grid gap-2 md:col-span-2">
                    <Label for="admin-branch">Branch</Label>
                    <Input id="admin-branch" v-model="form.branch" placeholder="Branch is derived from linked role records" disabled />
                    <p class="text-sm text-muted-foreground">
                        Branch is currently read-only here and comes from the linked athlete, coach, or child records in the database.
                    </p>
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
