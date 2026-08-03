<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Download } from 'lucide-vue-next';
import ActionButtonsRow from '@/components/shared/ActionButtonsRow.vue';
import DataTable from '@/components/shared/DataTable.vue';
import PageSection from '@/components/shared/PageSection.vue';
import StatCard from '@/components/shared/StatCard.vue';
import { Button } from '@/components/ui/button';
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
    { title: 'Beranda', href: dashboard.url() },
    { title: 'Payroll Saya', href: paymentsIndex.url() },
];

const columns: TableColumn[] = [
    { key: 'invoice_number', label: 'No. slip' },
    { key: 'payroll_period', label: 'Periode' },
    { key: 'payroll_basis', label: 'Dasar hitung' },
    { key: 'payroll_base', label: 'Honor dasar', align: 'right' },
    { key: 'payroll_bonus', label: 'Bonus', align: 'right' },
    { key: 'amount', label: 'Total dibayar', align: 'right' },
    { key: 'issued', label: 'Tanggal dibayar' },
    { key: 'status', label: 'Status' },
];
</script>

<template>
    <Head title="Payroll Saya" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
            <PageSection
                eyebrow="Keuangan pelatih"
                title="Payroll saya"
                description="Lihat dasar perhitungan honor, bonus, total yang sudah dibayar, dan unduh slip sebagai bukti pembayaran."
            >
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <StatCard v-for="metric in props.metrics" :key="metric.label" v-bind="metric" />
                </div>
            </PageSection>

            <DataTable
                title="Riwayat payroll"
                description="Data ini hanya-baca. Perubahan dan penerbitan slip dilakukan oleh admin."
                :columns="columns"
                :rows="props.rows"
                empty-text="Belum ada payroll yang ditetapkan ke akun pelatih ini."
                searchable
                search-placeholder="Cari periode, nomor slip, atau status"
            >
                <template #row-actions="{ row }">
                    <ActionButtonsRow>
                        <Button v-if="row.receipt_url" as-child type="button" size="sm" variant="outline">
                            <a :href="String(row.receipt_url)" target="_blank" rel="noopener noreferrer">
                                <Download class="size-4" /> Unduh slip
                            </a>
                        </Button>
                    </ActionButtonsRow>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
