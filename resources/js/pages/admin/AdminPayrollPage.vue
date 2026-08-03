<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { AlertTriangle, Download, Plus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import FormInputField from '@/components/forms/FormInputField.vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import ActionButtonsRow from '@/components/shared/ActionButtonsRow.vue';
import DataTable from '@/components/shared/DataTable.vue';
import FormModal from '@/components/shared/FormModal.vue';
import PageSection from '@/components/shared/PageSection.vue';
import StatCard from '@/components/shared/StatCard.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { Metric, SelectOption, TableColumn, TableRow } from '@/types/resource-table';

type PayrollEstimate = {
    units: number;
    session_count: number;
    hours: number;
    months: number;
    suggested_rate: number;
    source_label: string;
};

const props = defineProps<{
    reminder: { needed: boolean; month: string; count: number; expected: number; missing: number };
    metrics: Metric[];
    coaches: SelectOption[];
    rows: TableRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Beranda', href: '/dashboard' },
    { title: 'Keuangan', href: '/payments' },
    { title: 'Payroll Pelatih', href: '/admin/payroll' },
];

const columns: TableColumn[] = [
    { key: 'invoice_number', label: 'No. slip' },
    { key: 'coach', label: 'Pelatih' },
    { key: 'period', label: 'Periode' },
    { key: 'basis', label: 'Dasar hitung' },
    { key: 'base', label: 'Honor dasar', align: 'right' },
    { key: 'bonus', label: 'Bonus', align: 'right' },
    { key: 'total', label: 'Total dibayar', align: 'right' },
    { key: 'paid_at', label: 'Dibayar' },
    { key: 'status', label: 'Status' },
];

const basisOptions = [
    { value: 'SESSION', label: 'Jumlah sesi dari presensi × tarif per sesi' },
    { value: 'HOUR', label: 'Jumlah jam dari sesi mengajar × tarif per jam' },
    { value: 'MONTH', label: '1 periode bulan × tarif bulanan' },
    { value: 'FIXED', label: 'Nominal tetap' },
    { value: 'CUSTOM', label: 'Nominal kustom' },
];
const paymentMethodOptions = [
    { value: 'TRANSFER', label: 'Transfer' },
    { value: 'CASH', label: 'Tunai' },
    { value: 'CARD', label: 'Kartu' },
    { value: 'OTHER', label: 'Lainnya' },
];

const modalOpen = ref(false);
const estimateLoading = ref(false);
const estimateSource = ref('');
const estimateError = ref('');
let estimateSequence = 0;

const form = useForm({
    coach_user_id: '',
    payroll_period: new Date().toISOString().slice(0, 7),
    basis_type: 'SESSION',
    units: '0',
    rate: '0',
    base_amount: '0',
    bonus_amount: '0',
    paid_at: new Date().toISOString().slice(0, 10),
    payment_method: 'TRANSFER',
    notes: '',
});

const usesCalculatedBase = computed(() => ['SESSION', 'HOUR', 'MONTH'].includes(form.basis_type));
const calculatedBase = computed(() =>
    usesCalculatedBase.value ? Number(form.units || 0) * Number(form.rate || 0) : Number(form.base_amount || 0),
);
const totalPayroll = computed(() => calculatedBase.value + Number(form.bonus_amount || 0));
const unitLabel = computed(() => {
    if (form.basis_type === 'SESSION') return 'Jumlah sesi dari database';
    if (form.basis_type === 'HOUR') return 'Jumlah jam dari database';
    return 'Jumlah bulan dari periode';
});

function rupiah(value: number): string {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(
        value || 0,
    );
}

async function refreshEstimate(): Promise<void> {
    if (!usesCalculatedBase.value || !form.coach_user_id || !form.payroll_period) {
        estimateSource.value = '';
        estimateError.value = '';
        if (usesCalculatedBase.value) form.units = '0';
        return;
    }

    const sequence = ++estimateSequence;
    estimateLoading.value = true;
    estimateError.value = '';

    const params = new URLSearchParams({
        coach_user_id: String(form.coach_user_id),
        payroll_period: form.payroll_period,
        basis_type: form.basis_type,
    });

    try {
        const response = await fetch(`/admin/payroll/estimate?${params.toString()}`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        const payload = (await response.json()) as
            | PayrollEstimate
            | { message?: string; errors?: Record<string, string[]> };

        if (!response.ok) {
            const errors = 'errors' in payload ? payload.errors : undefined;
            const firstError = errors ? Object.values(errors).flat()[0] : undefined;
            throw new Error(
                firstError ||
                    ('message' in payload ? payload.message : undefined) ||
                    'Data payroll tidak dapat dimuat.',
            );
        }
        if (sequence !== estimateSequence) return;

        const estimate = payload as PayrollEstimate;
        form.units = String(estimate.units ?? 0);
        form.rate = String(estimate.suggested_rate ?? 0);
        estimateSource.value = estimate.source_label;
    } catch (error) {
        if (sequence !== estimateSequence) return;
        form.units = '0';
        estimateSource.value = '';
        estimateError.value = error instanceof Error ? error.message : 'Data payroll tidak dapat dimuat.';
    } finally {
        if (sequence === estimateSequence) estimateLoading.value = false;
    }
}

watch([() => form.coach_user_id, () => form.payroll_period, () => form.basis_type], () => void refreshEstimate());

function openCreate(): void {
    form.reset();
    form.payroll_period = new Date().toISOString().slice(0, 7);
    form.basis_type = 'SESSION';
    form.units = '0';
    form.rate = '0';
    form.base_amount = '0';
    form.bonus_amount = '0';
    form.paid_at = new Date().toISOString().slice(0, 10);
    form.payment_method = 'TRANSFER';
    form.clearErrors();
    estimateSource.value = '';
    estimateError.value = '';
    modalOpen.value = true;
}

function submit(): void {
    if (usesCalculatedBase.value) form.base_amount = String(calculatedBase.value);
    form.post('/admin/payroll', {
        preserveScroll: true,
        onSuccess: () => {
            modalOpen.value = false;
            form.reset();
        },
    });
}
</script>

<template>
    <Head title="Payroll Pelatih" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-w-0 flex-1 flex-col gap-5 p-3 sm:p-4 md:p-6">
            <PageSection
                eyebrow="Keuangan pelatih"
                title="Payroll dan slip pembayaran"
                description="Jumlah sesi dan jam diambil otomatis dari presensi mengajar. Basis bulanan menggunakan satu periode bulan. Admin hanya mengisi tarif, bonus, dan data pembayaran."
            >
                <template #actions>
                    <Button type="button" class="gap-2" @click="openCreate"
                        ><Plus class="size-4" /> Buat payroll</Button
                    >
                </template>
            </PageSection>

            <section
                v-if="props.reminder.needed"
                class="flex items-start gap-3 rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm"
            >
                <AlertTriangle class="mt-0.5 size-5 shrink-0 text-amber-700 dark:text-amber-300" />
                <div>
                    <p class="font-bold">
                        {{ props.reminder.missing }} payroll {{ props.reminder.month }} masih perlu dibuat
                    </p>
                    <p class="text-muted-foreground">
                        {{ props.reminder.count }} dari {{ props.reminder.expected }} pelatih sudah memiliki slip
                        pembayaran bulan ini.
                    </p>
                </div>
            </section>

            <div class="grid gap-3 sm:grid-cols-2">
                <StatCard v-for="metric in props.metrics" :key="metric.label" v-bind="metric" />
            </div>

            <DataTable
                title="Riwayat payroll"
                description="Setiap baris adalah bukti pembayaran yang dapat diunduh sebagai slip atau invoice."
                :columns="columns"
                :rows="props.rows"
                searchable
                filterable
                search-placeholder="Cari pelatih, periode, atau nomor slip"
                empty-text="Belum ada payroll pelatih."
            >
                <template #row-actions="{ row }">
                    <ActionButtonsRow>
                        <Button v-if="row.receipt_url" as-child type="button" size="sm" variant="outline">
                            <a :href="String(row.receipt_url)" target="_blank" rel="noopener noreferrer">
                                <Download class="size-4" /> Slip
                            </a>
                        </Button>
                    </ActionButtonsRow>
                </template>
            </DataTable>
        </div>

        <FormModal :open="modalOpen" max-width-class="max-w-2xl" @close="modalOpen = false">
            <PageSection
                title="Buat payroll pelatih"
                description="Pilih pelatih, periode, dan dasar hitung. Sistem mengambil beban mengajar dari database sebelum slip diterbitkan."
            >
                <form class="grid gap-4" @submit.prevent="submit">
                    <FormSelectField
                        id="payroll-coach"
                        v-model="form.coach_user_id"
                        label="Pelatih"
                        :options="props.coaches"
                        placeholder="Pilih pelatih"
                        required
                        :error="form.errors.coach_user_id"
                    />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormInputField
                            id="payroll-period"
                            v-model="form.payroll_period"
                            label="Periode payroll"
                            type="month"
                            required
                            :error="form.errors.payroll_period"
                        />
                        <FormInputField
                            id="payroll-paid-at"
                            v-model="form.paid_at"
                            label="Tanggal dibayar"
                            type="date"
                            required
                            :error="form.errors.paid_at"
                        />
                    </div>
                    <FormSelectField
                        id="payroll-basis"
                        v-model="form.basis_type"
                        label="Dasar perhitungan"
                        :options="basisOptions"
                        required
                        :error="form.errors.basis_type"
                    />
                    <div v-if="usesCalculatedBase" class="grid gap-3">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <FormInputField
                                id="payroll-units"
                                v-model="form.units"
                                :label="unitLabel"
                                type="number"
                                min="0"
                                step="0.25"
                                disabled
                                :error="form.errors.units"
                            />
                            <FormInputField
                                id="payroll-rate"
                                v-model="form.rate"
                                label="Tarif per unit"
                                type="number"
                                min="0"
                                step="1000"
                                required
                                :error="form.errors.rate"
                            />
                        </div>
                        <p v-if="estimateLoading" class="text-sm text-muted-foreground">
                            Mengambil data mengajar dari database...
                        </p>
                        <p v-else-if="estimateError" class="text-sm font-medium text-destructive">
                            {{ estimateError }}
                        </p>
                        <p v-else-if="estimateSource" class="text-sm text-muted-foreground">
                            Sumber perhitungan: {{ estimateSource }}.
                        </p>
                    </div>
                    <FormInputField
                        v-else
                        id="payroll-base"
                        v-model="form.base_amount"
                        label="Honor dasar"
                        type="number"
                        min="0"
                        step="1000"
                        required
                        :error="form.errors.base_amount"
                    />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormInputField
                            id="payroll-bonus"
                            v-model="form.bonus_amount"
                            label="Bonus"
                            type="number"
                            min="0"
                            step="1000"
                            :error="form.errors.bonus_amount"
                        />
                        <FormSelectField
                            id="payroll-method"
                            v-model="form.payment_method"
                            label="Metode pembayaran"
                            :options="paymentMethodOptions"
                            required
                            :error="form.errors.payment_method"
                        />
                    </div>
                    <FormInputField
                        id="payroll-notes"
                        v-model="form.notes"
                        label="Catatan"
                        placeholder="Contoh: honor mengajar + bonus kejuaraan"
                        :error="form.errors.notes"
                    />

                    <div class="rounded-xl border bg-muted/30 p-4 text-sm">
                        <div class="flex justify-between gap-3">
                            <span>Honor dasar</span><strong>{{ rupiah(calculatedBase) }}</strong>
                        </div>
                        <div class="mt-2 flex justify-between gap-3">
                            <span>Bonus</span><strong>{{ rupiah(Number(form.bonus_amount || 0)) }}</strong>
                        </div>
                        <div class="mt-3 flex justify-between gap-3 border-t pt-3 text-base">
                            <span>Total dibayar</span><strong>{{ rupiah(totalPayroll) }}</strong>
                        </div>
                    </div>

                    <div class="flex flex-wrap justify-end gap-2">
                        <Button type="button" variant="outline" @click="modalOpen = false">Batal</Button>
                        <Button type="submit" :disabled="form.processing || estimateLoading || totalPayroll <= 0">
                            {{ form.processing ? 'Menyimpan...' : 'Terbitkan slip dibayar' }}
                        </Button>
                    </div>
                </form>
            </PageSection>
        </FormModal>
    </AppLayout>
</template>
