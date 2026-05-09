<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import DataTable from '@/components/shared/DataTable.vue';
import FormModal from '@/components/shared/FormModal.vue';
import PageSection from '@/components/shared/PageSection.vue';
import StatCard from '@/components/shared/StatCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { managementRoutes } from '@/data/management';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { Metric, SelectOption, TableColumn, TableRow } from '@/types/management';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    metrics: Metric[];
    rows: TableRow[];
    athletes: SelectOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: managementRoutes.dashboard },
    { title: 'Payments', href: managementRoutes.payments },
];

const columns: TableColumn[] = [
    { key: 'athlete', label: 'Athlete' },
    { key: 'type', label: 'Payment type' },
    { key: 'amount', label: 'Amount', align: 'right' },
    { key: 'balance', label: 'Balance', align: 'right' },
    { key: 'status', label: 'Status' },
];

const form = useForm({
    athlete_id: '',
    payment_type: 'TUITION',
    collection_method: 'CASH',
    total_amount: '',
    paid_amount: '',
    payment_date: '',
    notes: '',
});
const showPaymentForm = ref(false);

function submit() {
    form.post('/payments', {
        onSuccess: () => {
            form.reset();
            showPaymentForm.value = false;
        },
    });
}
</script>

<template>
    <Head title="Payments" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageSection eyebrow="Core module" title="Payment management" description="Record tuition, championship fees, and other invoice activity against the live payment ledger.">
                <template #actions>
                    <Button type="button" @click="showPaymentForm = true">Create invoice</Button>
                </template>

                <div class="grid gap-4 md:grid-cols-3">
                    <StatCard v-for="metric in props.metrics" :key="metric.label" v-bind="metric" />
                </div>
            </PageSection>

            <DataTable title="Recent payment activity" description="Live invoice and payment rows from the payments table." :columns="columns" :rows="props.rows" />
        </div>

        <FormModal :open="showPaymentForm" max-width-class="max-w-xl" @close="showPaymentForm = false">
                <PageSection title="Quick collection action" description="Create a payment record and let the backend calculate completion status and remaining balance.">
                    <form class="grid gap-4" @submit.prevent="submit">
                        <div class="grid gap-2">
                            <Label for="payment-athlete">Athlete</Label>
                            <select id="payment-athlete" v-model="form.athlete_id" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs">
                                <option value="">Select athlete</option>
                                <option v-for="athlete in props.athletes" :key="athlete.value" :value="athlete.value">
                                    {{ athlete.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.athlete_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="payment-type">Payment type</Label>
                            <select id="payment-type" v-model="form.payment_type" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs">
                                <option value="TUITION">Tuition</option>
                                <option value="UNIFORM">Uniform</option>
                                <option value="LICENSE">License</option>
                                <option value="CHAMPIONSHIP">Championship</option>
                                <option value="OTHER">Other</option>
                            </select>
                            <InputError :message="form.errors.payment_type" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="payment-method">Collection method</Label>
                            <select id="payment-method" v-model="form.collection_method" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs">
                                <option value="CASH">Cash</option>
                                <option value="TRANSFER">Transfer</option>
                                <option value="OTHER">Other</option>
                            </select>
                            <InputError :message="form.errors.collection_method" />
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="payment-total">Total amount</Label>
                                <Input id="payment-total" v-model="form.total_amount" type="number" min="0" step="0.01" />
                                <InputError :message="form.errors.total_amount" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="payment-paid">Paid amount</Label>
                                <Input id="payment-paid" v-model="form.paid_amount" type="number" min="0" step="0.01" />
                                <InputError :message="form.errors.paid_amount" />
                            </div>
                        </div>
                        <div class="grid gap-2">
                            <Label for="payment-date">Payment date</Label>
                            <Input id="payment-date" v-model="form.payment_date" type="date" />
                            <InputError :message="form.errors.payment_date" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="payment-notes">Notes</Label>
                            <Input id="payment-notes" v-model="form.notes" placeholder="Optional finance note" />
                            <InputError :message="form.errors.notes" />
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <Button type="submit" class="w-full sm:w-auto" :disabled="form.processing">Record payment</Button>
                            <Button type="button" class="w-full sm:w-auto" variant="outline" @click="showPaymentForm = false">Cancel</Button>
                        </div>
                    </form>
                </PageSection>
        </FormModal>
    </AppLayout>
</template>

