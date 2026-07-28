<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AdminUserAccountPanel from '@/components/admin/AdminUserAccountPanel.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { index as adminIndex } from '@/routes/admin';
import type { BreadcrumbItem } from '@/types';
import type { AdminAccountRow } from '@/types/admin';

const { users } = defineProps<{
    users: AdminAccountRow[];
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
    { title: 'Dashboard', href: dashboard.url() },
    { title: 'Admin Panel', href: adminIndex.url() },
];
</script>

<template>
    <Head title="Admin Panel" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <AdminUserAccountPanel :initial-users="users" />
        </div>
    </AppLayout>
</template>
