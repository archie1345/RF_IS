<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DataTable from '@/components/shared/DataTable.vue';
import PageSection from '@/components/shared/PageSection.vue';
import StatCard from '@/components/shared/StatCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { index as paymentsIndex } from '@/routes/payments';
import type { BreadcrumbItem } from '@/types';
import type { Metric, TableColumn, TableRow } from '@/types/resource-table';

const props = defineProps<{
    metrics: Metric[];
    rows: TableRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard.url() },
    { title: 'My payroll', href: paymentsIndex.url() },
];

const columns: TableColumn[] = [
    { key: 'athlete', label: 'Coach' },
    { key: 'type', label: 'Payroll category' },
    { key: 'amount', label: 'Total payout', align: 'right' },
    { key: 'balance', label: 'Remaining', align: 'right' },
    { key: 'issued', label: 'Issue date' },
    { key: 'status', label: 'Status' },
];
</script>

<template>
    <Head title="My Coach Payroll" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageSection
                eyebrow="Coach finance"
                title="My coach payroll"
                description="Review payout records assigned to your coach profile. Payroll management and payment corrections remain admin-only."
            >
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <StatCard v-for="metric in props.metrics" :key="metric.label" v-bind="metric" />
                </div>
            </PageSection>

            <DataTable
                title="Payroll history"
                description="This page is read-only. Athlete tuition and payment-proof workflows are available only in Athlete or Parent mode."
                :columns="columns"
                :rows="props.rows"
                empty-text="No payroll records have been assigned to this coach account."
                searchable
                search-placeholder="Search payroll category, status, or date..."
            />
        </div>
    </AppLayout>
</template>
