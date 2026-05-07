<script setup lang="ts">
import DataTable from '@/components/mvp/DataTable.vue';
import PageSection from '@/components/mvp/PageSection.vue';
import { managementRoutes } from '@/data/mvp';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { TableColumn, TableRow } from '@/types/mvp';
import { Head, router } from '@inertiajs/vue3';
import { onMounted, onUnmounted } from 'vue';

const props = defineProps<{
    rows: TableRow[];
    total: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: managementRoutes.dashboard },
    { title: 'User Activity Log', href: managementRoutes.activityLogs },
];

const columns: TableColumn[] = [
    { key: 'time', label: 'Time' },
    { key: 'actor', label: 'Actor' },
    { key: 'email', label: 'Email' },
    { key: 'action', label: 'Action' },
    { key: 'context', label: 'Context' },
    { key: 'description', label: 'Description' },
    { key: 'ip', label: 'IP Address' },
];

let intervalId: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    intervalId = setInterval(() => {
        router.reload({ only: ['rows', 'total'], preserveScroll: true });
    }, 10000);
});

onUnmounted(() => {
    if (intervalId) {
        clearInterval(intervalId);
    }
});
</script>

<template>
    <Head title="User Activity Log" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageSection
                eyebrow="Admin"
                title="Users activity log"
                description="Live activity stream for account, attendance, payment, session, and event operations. Auto-refresh every 10 seconds."
            />

            <DataTable
                title="Log entries"
                :description="`Total entries: ${props.total}`"
                :columns="columns"
                :rows="props.rows"
            />
        </div>
    </AppLayout>
</template>
