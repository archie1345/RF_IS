<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AdminAccountManagementPanel from '@/components/admin/AdminAccountManagementPanel.vue';
import { adminAccountRows } from '@/data/admin';
import { managementRoutes } from '@/data/mvp';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: managementRoutes.dashboard },
    { title: 'Admin Panel', href: '/admin' },
];
</script>

<template>
    <Head title="Admin Panel" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <AdminAccountManagementPanel :initial-users="adminAccountRows" />
        </div>
    </AppLayout>
</template>
