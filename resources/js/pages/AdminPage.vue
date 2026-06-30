<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AdminUserAccountPanel from '@/components/admin/AdminUserAccountPanel.vue';
import BranchAdministrationPanel from '@/components/admin/BranchAdministrationPanel.vue';
import GroupAdministrationPanel from '@/components/admin/GroupAdministrationPanel.vue';
import { appRoutes } from '@/data/routes';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { AdminAccountRow } from '@/types/admin';
import type { Branch } from '@/types/branch';
import type { Group } from '@/types/group';

const { users, branches, groups } = defineProps<{
    users: AdminAccountRow[];
    branches?: Branch[];
    groups?: Group[];
    debugbar?: {
        enabled: boolean;
    };
    importResult?: {
        entity: string;
        imported: number;
        failed: number;
        errors: string[];
    } | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: appRoutes.dashboard },
    { title: 'Admin Panel', href: '/admin' },
];

function createBranch(payload: { name: string; location: string }) {
    router.post('/admin/branches', payload, { preserveScroll: true });
}

function updateBranch(payload: { id: string; name: string; location: string }) {
    router.put(`/admin/branches/${payload.id}`, payload, { preserveScroll: true });
}

function deleteBranch(id: string) {
    router.delete(`/admin/branches/${id}`, { preserveScroll: true });
}

function createGroup(payload: { name: string; description: string | null }) {
    router.post('/admin/groups', payload, { preserveScroll: true });
}

function updateGroup(payload: { id: string; name: string; description: string | null }) {
    router.put(`/admin/groups/${payload.id}`, payload, { preserveScroll: true });
}

function deleteGroup(id: string) {
    router.delete(`/admin/groups/${id}`, { preserveScroll: true });
}

</script>

<template>
    <Head title="Admin Panel" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">

            <AdminUserAccountPanel :initial-users="users" />
            <BranchAdministrationPanel
                :branches="branches ?? []"
                @create="createBranch"
                @update="updateBranch"
                @delete="deleteBranch"
            />
            <GroupAdministrationPanel
                :groups="groups ?? []"
                @create="createGroup"
                @update="updateGroup"
                @delete="deleteGroup"
            />
        </div>
    </AppLayout>
</template>
