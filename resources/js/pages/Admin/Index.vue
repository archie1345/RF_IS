<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AdminAccountManagementPanel from '@/components/admin/AdminAccountManagementPanel.vue';
import { managementRoutes } from '@/data/mvp';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AdminAccountRow } from '@/types/admin';
import type { BreadcrumbItem } from '@/types';
import BranchManagement from '@/components/admin/BranchManagement.vue';
import GroupManagement from '@/components/admin/GroupManagement.vue';
import type { Branch } from '@/types/branch';
import type { Group } from '@/types/group';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    users: AdminAccountRow[];
    branches?: Branch[];
    groups?: Group[];
    debugbar?: {
        enabled: boolean;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: managementRoutes.dashboard },
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
            <div class="flex justify-end">
                <Button as-child variant="outline">
                    <Link :href="managementRoutes.activityLogs">Open User Activity Log</Link>
                </Button>
            </div>
            <Alert v-if="!props.debugbar?.enabled" variant="default">
                <AlertTitle>Debugbar is not installed yet</AlertTitle>
                <AlertDescription>
                    The package is referenced in `composer.json`, but Laravel does not currently detect it. Run `composer install` or `composer require --dev barryvdh/laravel-debugbar` in the PHP environment you actually use to serve the app.
                </AlertDescription>
            </Alert>

            <AdminAccountManagementPanel :initial-users="users" />
            <BranchManagement
                :branches="branches ?? []"
                @create="createBranch"
                @update="updateBranch"
                @delete="deleteBranch"
            />
            <GroupManagement
                :groups="groups ?? []"
                @create="createGroup"
                @update="updateGroup"
                @delete="deleteGroup"
            />
        </div>
    </AppLayout>
</template>
