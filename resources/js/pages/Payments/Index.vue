<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DataTable from '@/components/mvp/DataTable.vue';
import PageSection from '@/components/mvp/PageSection.vue';
import StatCard from '@/components/mvp/StatCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { managementRoutes, paymentRows } from '@/data/mvp';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { Metric, TableColumn } from '@/types/mvp';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: managementRoutes.dashboard },
    { title: 'Payments', href: managementRoutes.payments },
];

const metrics: Metric[] = [
    {
        label: 'Collected this month',
        value: 'Rp42.6M',
        detail: '91% of scheduled invoices cleared',
        tone: 'success',
    },
    {
        label: 'Outstanding balance',
        value: 'Rp18.4M',
        detail: '8 overdue accounts need follow-up',
        tone: 'warning',
    },
    {
        label: 'Installment plans',
        value: '11',
        detail: '3 plans close this week',
        tone: 'info',
    },
];

const columns: TableColumn[] = [
    { key: 'athlete', label: 'Athlete' },
    { key: 'type', label: 'Payment type' },
    { key: 'amount', label: 'Amount', align: 'right' },
    { key: 'balance', label: 'Balance', align: 'right' },
    { key: 'status', label: 'Status' },
];
</script>

<template>
    <Head title="Payments" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageSection
                eyebrow="Core module"
                title="Payment management"
                description="A reusable finance template for invoices, installment tracking, and collection follow-ups across tuition and championship fees."
            >
                <template #actions>
                    <Button>Create invoice</Button>
                </template>

                <div class="grid gap-4 md:grid-cols-3">
                    <StatCard v-for="metric in metrics" :key="metric.label" v-bind="metric" />
                </div>
            </PageSection>

            <div class="grid gap-6 xl:grid-cols-[1.55fr_1fr]">
                <DataTable
                    title="Recent payment activity"
                    description="Shared listing pattern that can later be backed by the payments endpoint and status filters."
                    :columns="columns"
                    :rows="paymentRows"
                />

                <PageSection
                    title="Quick collection action"
                    description="This compact form is ready to become a shared drawer or modal for new charges and manual receipt entries."
                >
                    <div class="grid gap-4">
                        <div class="grid gap-2">
                            <Label for="payment-athlete">Athlete</Label>
                            <Input id="payment-athlete" placeholder="Search athlete name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="payment-type">Payment type</Label>
                            <Input id="payment-type" placeholder="Monthly tuition" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="payment-amount">Amount</Label>
                            <Input id="payment-amount" placeholder="Rp650.000" />
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <Button>Record payment</Button>
                            <Button variant="outline">Save draft</Button>
                        </div>
                    </div>
                </PageSection>
            </div>
        </div>
    </AppLayout>
</template>
