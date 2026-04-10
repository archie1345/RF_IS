<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { Building2, Mail, PencilLine, Plus, ShieldCheck, UserRoundCog } from 'lucide-vue-next';
import PageSection from '@/components/mvp/PageSection.vue';
import StatCard from '@/components/mvp/StatCard.vue';
import StatusBadge from '@/components/mvp/StatusBadge.vue';
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
import type { Metric } from '@/types/mvp';

const props = defineProps<{
    initialUsers: AdminAccountRow[];
}>();

const users = ref<AdminAccountRow[]>([...props.initialUsers]);
const isFormOpen = ref(false);
const editingId = ref<number | null>(null);

const form = reactive({
    name: '',
    email: '',
    role: 'athlete' as AdminAccountRole,
    branch: '',
    status: 'active' as AdminAccountRow['status'],
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

const resetForm = () => {
    editingId.value = null;
    form.name = '';
    form.email = '';
    form.role = 'athlete';
    form.branch = '';
    form.status = 'active';
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
    isFormOpen.value = true;
};

const submit = () => {
    if (editingId.value !== null) {
        users.value = users.value.map((user) =>
            user.id === editingId.value
                ? {
                      ...user,
                      name: form.name,
                      email: form.email,
                      role: form.role,
                      branch: form.branch,
                      status: form.status,
                  }
                : user,
        );
    } else {
        users.value = [
            {
                id: Math.max(...users.value.map((user) => user.id), 0) + 1,
                name: form.name,
                email: form.email,
                role: form.role,
                branch: form.branch,
                status: form.status,
                createdAt: new Date().toLocaleDateString('en-GB', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                }),
            },
            ...users.value,
        ];
    }

    isFormOpen.value = false;
    resetForm();
};
</script>

<template>
    <div class="space-y-6">
        <PageSection
            eyebrow="Admin panel"
            title="Account Management"
            description="Modeled after the JTE admin workspace: one place to review account types, manage access, and prepare new users for onboarding."
        >
            <template #actions>
                <Button class="gap-2" @click="openCreate">
                    <Plus class="size-4" />
                    New account
                </Button>
            </template>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <StatCard v-for="metric in stats" :key="metric.label" v-bind="metric" />
            </div>
        </PageSection>

        <PageSection
            title="Account roster"
            description="A role-based management table for admins, coaches, parents, and athletes. This is the same operating shape as your JTE panel, adapted to RF IS roles."
        >
            <div class="overflow-x-auto">
                <table class="min-w-full border-separate border-spacing-y-2">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-[0.18em] text-muted-foreground">
                            <th class="px-3 py-2">Name</th>
                            <th class="px-3 py-2">Contact</th>
                            <th class="px-3 py-2">Role</th>
                            <th class="px-3 py-2">Branch</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Created</th>
                            <th class="px-3 py-2 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="user in users"
                            :key="user.id"
                            class="bg-muted/40 text-sm text-foreground"
                        >
                            <td class="rounded-l-2xl px-3 py-4">
                                <div class="font-medium">{{ user.name }}</div>
                            </td>
                            <td class="px-3 py-4 text-muted-foreground">{{ user.email }}</td>
                            <td class="px-3 py-4">
                                <StatusBadge :label="roleLabel[user.role]" :tone="roleTone[user.role]" />
                            </td>
                            <td class="px-3 py-4">{{ user.branch }}</td>
                            <td class="px-3 py-4">
                                <StatusBadge
                                    :label="user.status.charAt(0).toUpperCase() + user.status.slice(1)"
                                    :tone="statusTone[user.status]"
                                />
                            </td>
                            <td class="px-3 py-4 text-muted-foreground">{{ user.createdAt }}</td>
                            <td class="rounded-r-2xl px-3 py-4 text-right">
                                <Button variant="outline" class="gap-2" @click="openEdit(user)">
                                    <PencilLine class="size-4" />
                                    Edit
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </PageSection>

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="rounded-3xl border border-border/70 bg-card/80 p-6 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="rounded-2xl bg-rose-500/10 p-3 text-rose-600">
                        <ShieldCheck class="size-5" />
                    </div>
                    <div>
                        <h3 class="font-semibold">Access tiers</h3>
                        <p class="text-sm text-muted-foreground">Keep high-risk permissions visible.</p>
                    </div>
                </div>
                <div class="mt-4 space-y-3 text-sm text-muted-foreground">
                    <p>Admins can manage athlete, payment, event, and session operations.</p>
                    <p>Coaches focus on attendance, schedules, and athlete readiness.</p>
                    <p>Parents and athletes stay limited to their own visibility layer.</p>
                </div>
            </div>

            <div class="rounded-3xl border border-border/70 bg-card/80 p-6 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="rounded-2xl bg-sky-500/10 p-3 text-sky-600">
                        <Building2 class="size-5" />
                    </div>
                    <div>
                        <h3 class="font-semibold">Branch ownership</h3>
                        <p class="text-sm text-muted-foreground">See where accounts are attached operationally.</p>
                    </div>
                </div>
                <div class="mt-4 space-y-3 text-sm text-muted-foreground">
                    <p>Use branch ownership to scope coaching access and admin follow-up.</p>
                    <p>It also helps later when we connect roster, payment, and attendance filters.</p>
                </div>
            </div>

            <div class="rounded-3xl border border-border/70 bg-card/80 p-6 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="rounded-2xl bg-amber-500/10 p-3 text-amber-600">
                        <Mail class="size-5" />
                    </div>
                    <div>
                        <h3 class="font-semibold">Invitation flow</h3>
                        <p class="text-sm text-muted-foreground">Track who still needs setup help.</p>
                    </div>
                </div>
                <div class="mt-4 space-y-3 text-sm text-muted-foreground">
                    <p>Invited accounts can later connect to Fortify registration or password setup.</p>
                    <p>This panel keeps the management flow visible before backend actions are wired.</p>
                </div>
            </div>
        </div>
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
                </div>
                <div class="grid gap-2">
                    <Label for="admin-email">Email</Label>
                    <Input id="admin-email" v-model="form.email" placeholder="name@example.com" />
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
                </div>
                <div class="grid gap-2 md:col-span-2">
                    <Label for="admin-branch">Branch</Label>
                    <Input id="admin-branch" v-model="form.branch" placeholder="Jakarta Selatan" />
                </div>
            </div>

            <DialogFooter class="gap-2">
                <Button variant="outline" @click="isFormOpen = false">Cancel</Button>
                <Button @click="submit">
                    {{ editingId !== null ? 'Save changes' : 'Create account' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
