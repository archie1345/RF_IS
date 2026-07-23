<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { AlertTriangle, Download, ImagePlus, MessageCircle, PencilLine, Trash2, WalletCards } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import FormFileField from '@/components/forms/FormFileField.vue';
import FormInputField from '@/components/forms/FormInputField.vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import ActionButtonsRow from '@/components/shared/ActionButtonsRow.vue';
import DataTable from '@/components/shared/DataTable.vue';
import FormModal from '@/components/shared/FormModal.vue';
import PageSection from '@/components/shared/PageSection.vue';
import StatCard from '@/components/shared/StatCard.vue';
import { Button } from '@/components/ui/button';
import PaymentTransactionHistory from '@/features/payments/components/PaymentTransactionHistory.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { Metric, SelectOption, TableColumn, TableFilter, TableRow } from '@/types/resource-table';
import type { PaymentHistoryEntry } from './PaymentsPage.types';
import { dashboard } from '@/routes';
import {
    destroy as paymentDestroy,
    index as paymentsIndex,
    store as paymentStore,
    update as paymentUpdate,
} from '@/routes/payments';
import { review as paymentProofReview, submit as paymentProofSubmit } from '@/routes/payments/proof';

const props = withDefaults(
    defineProps<{
        isAdmin: boolean;
        canSubmitPaymentProof?: boolean;
        metrics: Metric[];
        rows: TableRow[];
        athletes: SelectOption[];
        users: SelectOption[];
        coaches: SelectOption[];
        financeAttention?: {
            proof_review_count: number;
            overdue_count: number;
            partial_count: number;
            ledger_mismatch_count: number;
        } | null;
        invoiceTemplate?: {
            company_name: string;
            company_address: string | null;
            company_phone: string | null;
            company_email: string | null;
            logo_url: string | null;
            header_text: string | null;
            footer_text: string | null;
            payment_notes: string | null;
        } | null;
        paymentInstructions: string;
        paginate?: boolean;
        initialLimit?: number;
        pageSize?: number;
        filters?: TableFilter[];
        filterable?: boolean;
    }>(),
    {
        paginate: true,
        pageSize: 10,
        initialLimit: 10,
        filters: () => [],
        filterable: false,
        financeAttention: null,
    },
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard.url() },
    { title: 'Keuangan', href: paymentsIndex.url() },
];

const invoiceTemplateModalOpen = ref(false);
const showPaymentForm = ref(false);
const editingPaymentId = ref<number | null>(null);
const editingPaymentRow = ref<TableRow | null>(null);
const showProofForm = ref(false);
const proofPaymentId = ref<number | null>(null);
const showReviewForm = ref(false);
const reviewPaymentRow = ref<TableRow | null>(null);
const showManualPaymentForm = ref(false);
const manualPaymentRow = ref<TableRow | null>(null);

const columns: TableColumn[] = [
    { key: 'invoice_number', label: 'No. tagihan' },
    { key: 'athlete', label: 'Penerima' },
    { key: 'type', label: 'Kategori' },
    { key: 'amount', label: 'Total', align: 'right' },
    { key: 'paid', label: 'Terbayar', align: 'right' },
    { key: 'balance', label: 'Sisa', align: 'right' },
    { key: 'due', label: 'Jatuh tempo' },
    { key: 'status', label: 'Status' },
    { key: 'proof_status_label', label: 'Bukti' },
    { key: 'next_action', label: 'Tindakan berikutnya' },
];

const paymentTypeOptions = [
    { value: 'TUITION', label: 'Iuran / SPP' },
    { value: 'UNIFORM', label: 'Seragam' },
    { value: 'LICENSE', label: 'Lisensi / UKT' },
    { value: 'CHAMPIONSHIP', label: 'Kejuaraan' },
    { value: 'OTHER', label: 'Lainnya' },
];

const collectionMethodOptions = [
    { value: 'CASH', label: 'Tunai' },
    { value: 'CARD', label: 'Kartu' },
    { value: 'TRANSFER', label: 'Transfer' },
    { value: 'OTHER', label: 'Lainnya' },
];

const paymentTableFilters = computed<TableFilter[]>(() => [
    {
        key: 'finance_focus',
        label: 'Prioritas admin',
        type: 'select',
        placeholder: 'Semua prioritas',
        options: [
            { value: 'review', label: 'Bukti perlu direview' },
            { value: 'overdue', label: 'Sudah jatuh tempo' },
            { value: 'partial', label: 'Pembayaran sebagian' },
            { value: 'unpaid', label: 'Belum dibayar' },
            { value: 'paid', label: 'Sudah lunas' },
            { value: 'ledger_mismatch', label: 'Ledger tidak sesuai' },
            { value: 'failed', label: 'Tagihan gagal' },
            { value: 'refunded', label: 'Sudah direfund' },
        ],
        match: (row, value) => {
            const paid = Number(row.paid_amount_raw ?? 0);
            const remaining = Number(row.remaining_amount_raw ?? 0);

            return (
                (value === 'review' && row.proof_status === 'SUBMITTED') ||
                (value === 'overdue' && row.is_overdue === true) ||
                (value === 'partial' && paid > 0 && remaining > 0) ||
                (value === 'unpaid' && paid <= 0 && remaining > 0) ||
                (value === 'paid' && remaining <= 0) ||
                (value === 'ledger_mismatch' && row.ledger_consistent === false) ||
                (value === 'failed' && row.status_value === 'FAILED') ||
                (value === 'refunded' && row.status_value === 'REFUNDED')
            );
        },
    },
    {
        key: 'proof_status',
        label: 'Status bukti',
        type: 'select',
        columnKey: 'proof_status',
        placeholder: 'Semua status bukti',
        options: [
            { value: 'SUBMITTED', label: 'Menunggu review' },
            { value: 'NONE', label: 'Belum ada bukti aktif' },
            { value: 'APPROVED', label: 'Disetujui' },
            { value: 'REJECTED', label: 'Ditolak' },
        ],
    },
    {
        key: 'bill_kind',
        label: 'Jenis pencatatan',
        type: 'select',
        columnKey: 'bill_kind',
        placeholder: 'Semua jenis',
        options: [
            { value: 'INVOICE', label: 'Tagihan anggota' },
            { value: 'PAYROLL', label: 'Pembayaran pelatih' },
        ],
    },
    {
        key: 'payment_type_raw',
        label: 'Kategori tagihan',
        type: 'select',
        columnKey: 'payment_type_raw',
        placeholder: 'Semua kategori',
        options: paymentTypeOptions,
    },
]);

function todayDate() {
    const date = new Date();
    date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
    return date.toISOString().slice(0, 10);
}

function defaultDueDate() {
    const date = new Date();
    date.setDate(date.getDate() + 14);
    date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
    return date.toISOString().slice(0, 10);
}

function paymentRouteId(value: unknown): number | null {
    const parsed = Number(value);
    return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
}

const form = useForm({
    athlete_id: '',
    billable_user_id: '',
    payee_user_id: '',
    bill_kind: 'INVOICE',
    payment_type: 'TUITION',
    collection_method: 'TRANSFER',
    total_amount: '',
    paid_amount: '0',
    payment_date: todayDate(),
    due_date: defaultDueDate(),
    notes: '',
});

const proofForm = useForm({
    notes: '',
    proof_file: null as File | null,
});

const reviewForm = useForm({
    decision: 'APPROVED',
    approved_amount: '',
    notes: '',
    proof_review: '',
});

const manualPaymentForm = useForm({
    amount: '',
    transaction_date: todayDate(),
    payment_method: 'TRANSFER',
    notes: '',
});

const invoiceTemplateForm = useForm({
    company_name: props.invoiceTemplate?.company_name ?? 'RF IS',
    company_address: props.invoiceTemplate?.company_address ?? '',
    company_phone: props.invoiceTemplate?.company_phone ?? '',
    company_email: props.invoiceTemplate?.company_email ?? '',
    logo_url: props.invoiceTemplate?.logo_url ?? '',
    header_text: props.invoiceTemplate?.header_text ?? '',
    footer_text: props.invoiceTemplate?.footer_text ?? '',
    payment_notes: props.invoiceTemplate?.payment_notes ?? '',
});

const activeProofRow = computed(
    () => props.rows.find((row) => Number(row.payment_id) === proofPaymentId.value) ?? null,
);
const identityLocked = computed(() => Number(editingPaymentRow.value?.transaction_count ?? 0) > 0);

function remainingAmount(row: TableRow) {
    return Number(row.remaining_amount_raw ?? 0);
}

function canUploadProof(row: TableRow) {
    return (
        Boolean(props.canSubmitPaymentProof) &&
        remainingAmount(row) > 0 &&
        row.status_value !== 'FAILED' &&
        row.status_value !== 'REFUNDED' &&
        row.proof_status !== 'SUBMITTED' &&
        row.proof_status !== 'APPROVED'
    );
}

function canReviewProof(row: TableRow) {
    return props.isAdmin && row.proof_status === 'SUBMITTED';
}

function canRecordPayment(row: TableRow) {
    return props.isAdmin && row.can_record_payment === true;
}

function paymentHistory(row: TableRow | null | undefined): PaymentHistoryEntry[] {
    const history = row?.transaction_history;
    return Array.isArray(history) ? (history as PaymentHistoryEntry[]) : [];
}

function submit() {
    if (form.bill_kind === 'PAYROLL') {
        form.payment_type = 'OTHER';
        form.athlete_id = '';
        form.billable_user_id = '';
        form.due_date = form.payment_date;
    } else {
        form.payee_user_id = '';
        form.athlete_id = '';
    }

    if (!form.payment_date) form.payment_date = todayDate();
    if (!form.due_date) form.due_date = form.bill_kind === 'PAYROLL' ? form.payment_date : defaultDueDate();
    form.paid_amount = '0';

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.paid_amount = '0';
            form.payment_date = todayDate();
            form.due_date = defaultDueDate();
            showPaymentForm.value = false;
            editingPaymentId.value = null;
            editingPaymentRow.value = null;
        },
    };

    if (editingPaymentId.value) {
        form.put(paymentUpdate.url(editingPaymentId.value), options);
        return;
    }

    form.post(paymentStore.url(), options);
}

function exportInvoice(paymentId: unknown) {
    const id = paymentRouteId(paymentId);
    if (!id) return;
    window.open(`/payments/${id}/export`, '_blank');
}

function exportPaymentCsv() {
    window.location.href = '/payments/export';
}

function openCreate() {
    editingPaymentId.value = null;
    editingPaymentRow.value = null;
    form.reset();
    form.bill_kind = 'INVOICE';
    form.payment_type = 'TUITION';
    form.collection_method = 'TRANSFER';
    form.paid_amount = '0';
    form.payment_date = todayDate();
    form.due_date = defaultDueDate();
    form.clearErrors();
    showPaymentForm.value = true;
}

function editPayment(row: TableRow) {
    const id = paymentRouteId(row.payment_id);
    if (!id) return;

    editingPaymentId.value = id;
    editingPaymentRow.value = row;
    form.athlete_id = String(row.athlete_id ?? '');
    form.billable_user_id = String(row.billable_user_id ?? row.athlete_user_id ?? '');
    form.payee_user_id = String(row.payee_user_id ?? '');
    form.bill_kind = String(row.bill_kind ?? 'INVOICE');
    form.payment_type = String(row.payment_type_raw ?? 'TUITION');
    form.collection_method = String(row.collection_method_raw ?? 'TRANSFER');
    form.total_amount = String(row.total_amount_raw ?? '0');
    form.paid_amount = '0';
    form.payment_date = String(row.payment_date_raw ?? '');
    form.due_date = String(row.due_date_raw ?? '');
    form.notes = String(row.notes_raw ?? '');
    form.clearErrors();
    showPaymentForm.value = true;
}

function deleteFromEdit() {
    if (editingPaymentRow.value) {
        deletePayment(editingPaymentRow.value);
        showPaymentForm.value = false;
    }
}

function deletePayment(row: TableRow) {
    const id = paymentRouteId(row.payment_id);
    if (!id || row.can_delete !== true || !confirm('Hapus tagihan kosong ini?')) return;
    router.delete(paymentDestroy.url(id), { preserveScroll: true });
}

function saveInvoiceTemplate() {
    invoiceTemplateForm.post('/admin/invoice-template', {
        preserveScroll: true,
        onSuccess: () => {
            invoiceTemplateModalOpen.value = false;
        },
    });
}

function openProofForm(row: TableRow) {
    const id = paymentRouteId(row.payment_id);
    if (!id) return;

    proofPaymentId.value = id;
    proofForm.reset();
    showProofForm.value = true;
}

function submitProof() {
    if (!proofPaymentId.value) return;
    proofForm.post(paymentProofSubmit.url(proofPaymentId.value), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            showProofForm.value = false;
        },
    });
}

function openReviewModal(row: TableRow) {
    reviewPaymentRow.value = row;
    reviewForm.decision = 'APPROVED';
    reviewForm.approved_amount = String(Math.max(remainingAmount(row), 0));
    reviewForm.notes = '';
    reviewForm.proof_review = '';
    reviewForm.clearErrors();
    showReviewForm.value = true;
}

function submitReview(decision: 'APPROVED' | 'REJECTED') {
    if (!reviewPaymentRow.value) return;
    const id = paymentRouteId(reviewPaymentRow.value.payment_id);
    if (!id) return;

    reviewForm.decision = decision;
    if (decision === 'REJECTED' && !confirm('Tolak bukti pembayaran ini?')) return;

    reviewForm.put(paymentProofReview.url(id), {
        preserveScroll: true,
        onSuccess: () => {
            showReviewForm.value = false;
            reviewPaymentRow.value = null;
        },
    });
}

function openManualPayment(row: TableRow) {
    manualPaymentRow.value = row;
    manualPaymentForm.reset();
    manualPaymentForm.amount = String(Math.max(remainingAmount(row), 0));
    manualPaymentForm.transaction_date = todayDate();
    manualPaymentForm.payment_method = String(row.collection_method_raw ?? 'TRANSFER');
    manualPaymentForm.notes = '';
    manualPaymentForm.clearErrors();
    showManualPaymentForm.value = true;
}

function submitManualPayment() {
    if (!manualPaymentRow.value) return;
    const id = paymentRouteId(manualPaymentRow.value.payment_id);
    if (!id) return;

    manualPaymentForm.post(`/payments/${id}/transactions`, {
        preserveScroll: true,
        onSuccess: () => {
            showManualPaymentForm.value = false;
            manualPaymentRow.value = null;
        },
    });
}
</script>

<template>
    <Head title="Keuangan" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageSection
                eyebrow="Pusat keuangan"
                title="Tagihan, pembayaran, dan payroll"
                description="Setiap perubahan saldo harus berasal dari transaksi yang tercatat. Bukti pembayaran, pembayaran tunai, cicilan, dan refund tetap dapat ditelusuri dari riwayat tagihan."
            >
                <template #actions>
                    <div class="flex flex-wrap gap-2">
                        <Button v-if="props.isAdmin" type="button" variant="outline" @click="invoiceTemplateModalOpen = true">
                            Pengaturan invoice
                        </Button>
                        <Button v-if="props.isAdmin" type="button" variant="outline" @click="exportPaymentCsv">
                            Export CSV
                        </Button>
                        <Button v-if="props.isAdmin" type="button" @click="openCreate">Buat tagihan</Button>
                    </div>
                </template>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                    <StatCard v-for="metric in props.metrics" :key="metric.label" v-bind="metric" />
                </div>
            </PageSection>

            <section
                v-if="props.isAdmin && props.financeAttention"
                class="grid gap-3 rounded-xl border border-border bg-card p-4 md:grid-cols-4"
            >
                <div class="flex items-start gap-3">
                    <WalletCards class="mt-0.5 size-5 text-muted-foreground" />
                    <div>
                        <p class="font-medium">{{ props.financeAttention.proof_review_count }} bukti perlu direview</p>
                        <p class="text-xs text-muted-foreground">Baris ini ditempatkan paling atas.</p>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <AlertTriangle class="mt-0.5 size-5 text-muted-foreground" />
                    <div>
                        <p class="font-medium">{{ props.financeAttention.overdue_count }} tagihan jatuh tempo</p>
                        <p class="text-xs text-muted-foreground">Gunakan tombol WhatsApp untuk tindak lanjut.</p>
                    </div>
                </div>
                <div>
                    <p class="font-medium">{{ props.financeAttention.partial_count }} pembayaran sebagian</p>
                    <p class="text-xs text-muted-foreground">Semua cicilan tetap berada pada tagihan yang sama.</p>
                </div>
                <div>
                    <p class="font-medium">{{ props.financeAttention.ledger_mismatch_count }} ledger perlu rekonsiliasi</p>
                    <p class="text-xs text-muted-foreground">Nilai di atas nol berarti saldo lama tidak memiliki transaksi lengkap.</p>
                </div>
            </section>

            <DataTable
                title="Antrean administrasi keuangan"
                description="Urutan otomatis: bukti menunggu review, tagihan jatuh tempo, tagihan aktif, lalu tagihan selesai."
                :columns="columns"
                :rows="props.rows"
                :filters="paymentTableFilters"
                filterable
                searchable
                search-placeholder="Cari nomor tagihan, penerima, kategori, atau status"
            >
                <template #row-actions="{ row }">
                    <ActionButtonsRow>
                        <Button type="button" size="sm" variant="outline" title="Download invoice" @click="exportInvoice(row.payment_id)">
                            <Download class="size-4" />
                        </Button>
                        <a
                            v-if="row.whatsapp_url && remainingAmount(row) > 0"
                            :href="String(row.whatsapp_url)"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex h-8 items-center gap-2 rounded-md border px-3 text-xs font-medium"
                        >
                            <MessageCircle class="size-4" /> WhatsApp
                        </a>
                        <Button
                            v-if="canUploadProof(row)"
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="openProofForm(row)"
                        >
                            Upload bukti
                        </Button>
                        <a
                            v-if="row.proof_url && !canReviewProof(row)"
                            :href="String(row.proof_url)"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex h-8 items-center rounded-md border px-3 text-xs font-medium"
                        >
                            Lihat bukti
                        </a>
                        <Button v-if="canReviewProof(row)" type="button" size="sm" @click="openReviewModal(row)">
                            Review bukti
                        </Button>
                        <Button
                            v-if="canRecordPayment(row)"
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="openManualPayment(row)"
                        >
                            Catat pembayaran
                        </Button>
                        <Button
                            v-if="props.isAdmin"
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="editPayment(row)"
                        >
                            <PencilLine class="size-4" /> Kelola
                        </Button>
                    </ActionButtonsRow>
                </template>
            </DataTable>
        </div>

        <FormModal
            :open="showPaymentForm && props.isAdmin"
            max-width-class="max-w-2xl"
            @close="
                showPaymentForm = false;
                editingPaymentId = null;
                editingPaymentRow = null;
            "
        >
            <PageSection
                :title="editingPaymentId ? `Kelola ${String(editingPaymentRow?.invoice_number ?? 'tagihan')}` : 'Buat tagihan baru'"
                description="Saldo terbayar tidak dapat diubah dari form ini. Gunakan Catat pembayaran atau Review bukti agar ledger tetap lengkap."
            >
                <form class="grid gap-4" @submit.prevent="submit">
                    <div
                        v-if="editingPaymentRow"
                        class="grid gap-3 rounded-lg border border-border bg-muted/30 p-3 text-sm md:grid-cols-3"
                    >
                        <div><span class="text-muted-foreground">Total</span><p class="font-medium">{{ editingPaymentRow.amount }}</p></div>
                        <div><span class="text-muted-foreground">Terbayar</span><p class="font-medium">{{ editingPaymentRow.paid }}</p></div>
                        <div><span class="text-muted-foreground">Sisa</span><p class="font-medium">{{ editingPaymentRow.balance }}</p></div>
                        <div class="md:col-span-3">
                            <span class="text-muted-foreground">Kesehatan ledger</span>
                            <p class="font-medium">{{ editingPaymentRow.ledger_consistent ? 'Sesuai' : 'Perlu rekonsiliasi' }}</p>
                        </div>
                    </div>

                    <FormSelectField
                        id="payment-kind"
                        v-model="form.bill_kind"
                        label="Jenis pencatatan"
                        :options="[
                            { value: 'INVOICE', label: 'Tagihan untuk anggota' },
                            { value: 'PAYROLL', label: 'Pembayaran untuk pelatih' },
                        ]"
                        :disabled="identityLocked"
                        :error="form.errors.bill_kind"
                    />
                    <FormSelectField
                        v-if="form.bill_kind === 'INVOICE'"
                        id="payment-recipient"
                        v-model="form.billable_user_id"
                        label="Penerima tagihan"
                        :options="props.users"
                        placeholder="Pilih atlet, orang tua, pelatih, atau anggota"
                        :disabled="identityLocked"
                        :error="form.errors.billable_user_id || form.errors.athlete_id"
                    />
                    <FormSelectField
                        v-if="form.bill_kind === 'PAYROLL'"
                        id="payment-coach"
                        v-model="form.payee_user_id"
                        label="Pelatih penerima pembayaran"
                        :options="props.coaches"
                        placeholder="Pilih pelatih"
                        required
                        :disabled="identityLocked"
                        :error="form.errors.payee_user_id"
                    />
                    <FormSelectField
                        v-if="form.bill_kind === 'INVOICE'"
                        id="payment-type"
                        v-model="form.payment_type"
                        label="Kategori tagihan"
                        :options="paymentTypeOptions"
                        required
                        :disabled="identityLocked"
                        :error="form.errors.payment_type"
                    />
                    <div class="grid gap-4 md:grid-cols-2">
                        <FormInputField
                            id="payment-total"
                            v-model="form.total_amount"
                            label="Total tagihan"
                            type="number"
                            inputmode="decimal"
                            min="0.01"
                            step="1000"
                            required
                            :error="form.errors.total_amount"
                        />
                        <FormSelectField
                            id="payment-method"
                            v-model="form.collection_method"
                            label="Metode pembayaran yang diharapkan"
                            :options="collectionMethodOptions"
                            :error="form.errors.collection_method"
                        />
                        <FormInputField
                            id="payment-date"
                            v-model="form.payment_date"
                            label="Tanggal diterbitkan"
                            type="date"
                            required
                            :error="form.errors.payment_date"
                        />
                        <FormInputField
                            id="payment-due-date"
                            v-model="form.due_date"
                            label="Tanggal jatuh tempo"
                            type="date"
                            required
                            :error="form.errors.due_date"
                        />
                    </div>
                    <FormInputField
                        id="payment-notes"
                        v-model="form.notes"
                        label="Keterangan tagihan"
                        placeholder="Contoh: Iuran bulan Juli 2026"
                        :error="form.errors.notes"
                    />

                    <PaymentTransactionHistory
                        v-if="editingPaymentId"
                        :entries="paymentHistory(editingPaymentRow)"
                        title="Riwayat ledger"
                        empty-text="Belum ada aktivitas finansial pada tagihan ini."
                    />

                    <div class="mt-4 flex flex-wrap items-center justify-between gap-4 border-t border-border pt-4">
                        <div class="flex flex-wrap gap-3">
                            <Button type="submit" :disabled="form.processing">
                                {{ editingPaymentId ? 'Simpan metadata' : 'Terbitkan tagihan' }}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                @click="
                                    showPaymentForm = false;
                                    editingPaymentId = null;
                                    editingPaymentRow = null;
                                "
                            >
                                Batal
                            </Button>
                        </div>
                        <Button
                            v-if="editingPaymentId && editingPaymentRow?.can_delete === true"
                            type="button"
                            variant="destructive"
                            size="sm"
                            @click="deleteFromEdit"
                        >
                            <Trash2 class="mr-2 size-4" /> Hapus tagihan kosong
                        </Button>
                    </div>
                </form>
            </PageSection>
        </FormModal>

        <FormModal
            :open="invoiceTemplateModalOpen && props.isAdmin"
            max-width-class="max-w-2xl"
            @close="invoiceTemplateModalOpen = false"
        >
            <PageSection
                title="Pengaturan invoice"
                description="Informasi ini tampil pada invoice yang diunduh dan instruksi pembayaran anggota."
            >
                <form class="grid gap-6" @submit.prevent="saveInvoiceTemplate">
                    <div class="grid gap-2">
                        <label class="text-sm font-medium">Logo</label>
                        <div class="flex items-center gap-4">
                            <div class="flex h-24 w-24 items-center justify-center rounded-lg border-2 border-dashed border-input bg-muted/50">
                                <ImagePlus class="size-8 text-muted-foreground" />
                            </div>
                            <FormInputField
                                id="invoice-logo-url"
                                v-model="invoiceTemplateForm.logo_url"
                                type="url"
                                label="URL logo"
                                placeholder="https://example.com/logo.png"
                                :error="invoiceTemplateForm.errors.logo_url"
                            />
                        </div>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <FormInputField id="invoice-company-name" v-model="invoiceTemplateForm.company_name" label="Nama klub" required :error="invoiceTemplateForm.errors.company_name" />
                        <FormInputField id="invoice-company-email" v-model="invoiceTemplateForm.company_email" label="Email keuangan" type="email" :error="invoiceTemplateForm.errors.company_email" />
                        <FormInputField id="invoice-company-phone" v-model="invoiceTemplateForm.company_phone" label="Nomor keuangan" :error="invoiceTemplateForm.errors.company_phone" />
                    </div>
                    <label class="grid gap-2 text-sm font-medium">
                        Alamat
                        <textarea v-model="invoiceTemplateForm.company_address" rows="2" class="rounded-lg border border-input bg-background px-3 py-2 text-sm"></textarea>
                    </label>
                    <FormInputField id="invoice-header-text" v-model="invoiceTemplateForm.header_text" label="Judul invoice" :error="invoiceTemplateForm.errors.header_text" />
                    <label class="grid gap-2 text-sm font-medium">
                        Catatan footer
                        <textarea v-model="invoiceTemplateForm.footer_text" rows="3" class="rounded-lg border border-input bg-background px-3 py-2 text-sm"></textarea>
                    </label>
                    <label class="grid gap-2 text-sm font-medium">
                        Instruksi pembayaran
                        <textarea v-model="invoiceTemplateForm.payment_notes" rows="3" class="rounded-lg border border-input bg-background px-3 py-2 text-sm"></textarea>
                    </label>
                    <div class="flex flex-wrap gap-3">
                        <Button type="submit" :disabled="invoiceTemplateForm.processing">Simpan pengaturan</Button>
                        <Button type="button" variant="outline" @click="invoiceTemplateModalOpen = false">Batal</Button>
                    </div>
                </form>
            </PageSection>
        </FormModal>

        <FormModal :open="showProofForm" max-width-class="max-w-xl" @close="showProofForm = false">
            <PageSection title="Upload bukti pembayaran" description="Bukti akan masuk ke antrean review admin dan tidak langsung mengubah saldo.">
                <div v-if="activeProofRow" class="grid gap-2 rounded-lg border border-border bg-muted/30 p-3 text-sm">
                    <p><span class="font-medium">Tagihan:</span> {{ activeProofRow.invoice_number }} — {{ activeProofRow.type }}</p>
                    <p><span class="font-medium">Sisa:</span> {{ activeProofRow.balance }}</p>
                    <p class="leading-6 text-muted-foreground">{{ props.paymentInstructions }}</p>
                </div>
                <PaymentTransactionHistory
                    :entries="paymentHistory(activeProofRow)"
                    title="Riwayat pembayaran"
                    empty-text="Belum ada pembayaran yang disetujui."
                    :show-verifier="false"
                    :bordered="true"
                />
                <form class="grid gap-4" @submit.prevent="submitProof">
                    <FormInputField id="proof-notes" v-model="proofForm.notes" label="Catatan bukti" placeholder="Contoh: Transfer cicilan pertama" :error="proofForm.errors.notes" />
                    <FormFileField id="proof-file" v-model="proofForm.proof_file" label="File bukti" accept="image/*,.pdf" :error="proofForm.errors.proof_file" />
                    <p v-if="!proofForm.errors.proof_file" class="text-xs text-muted-foreground">Gambar atau PDF, maksimal 10 MB.</p>
                    <div class="flex flex-wrap gap-3">
                        <Button type="submit" :disabled="proofForm.processing || !proofForm.proof_file">Kirim untuk review</Button>
                        <Button type="button" variant="outline" @click="showProofForm = false">Batal</Button>
                    </div>
                </form>
            </PageSection>
        </FormModal>

        <FormModal :open="showReviewForm" max-width-class="max-w-lg" @close="showReviewForm = false">
            <PageSection title="Review bukti pembayaran" description="Masukkan nilai yang benar-benar terlihat pada bukti. Nilai ini akan menjadi transaksi ledger.">
                <form class="grid gap-4">
                    <div v-if="reviewPaymentRow" class="grid gap-2 rounded-lg border border-border bg-muted/30 p-3 text-sm">
                        <p><span class="font-medium">Tagihan:</span> {{ reviewPaymentRow.invoice_number }}</p>
                        <p><span class="font-medium">Penerima:</span> {{ reviewPaymentRow.athlete }}</p>
                        <p><span class="font-medium">Total:</span> {{ reviewPaymentRow.amount }}</p>
                        <p><span class="font-medium">Sisa saat ini:</span> {{ reviewPaymentRow.balance }}</p>
                        <a v-if="reviewPaymentRow.proof_url" :href="String(reviewPaymentRow.proof_url)" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex h-8 w-fit items-center rounded-md border bg-background px-3 text-xs font-medium">
                            Buka dokumen bukti
                        </a>
                    </div>
                    <FormInputField id="review-approved-amount" v-model="reviewForm.approved_amount" label="Nominal yang disetujui" type="number" inputmode="decimal" min="0.01" step="0.01" required :error="reviewForm.errors.approved_amount" />
                    <FormInputField id="review-notes" v-model="reviewForm.notes" label="Catatan admin" placeholder="Contoh: Nominal sesuai mutasi bank" :error="reviewForm.errors.notes || reviewForm.errors.proof_review" />
                    <div class="mt-4 flex flex-wrap gap-3">
                        <Button type="button" :disabled="reviewForm.processing" @click="submitReview('APPROVED')">Setujui nominal</Button>
                        <Button type="button" variant="destructive" :disabled="reviewForm.processing" @click="submitReview('REJECTED')">Tolak bukti</Button>
                        <Button type="button" variant="outline" @click="showReviewForm = false">Batal</Button>
                    </div>
                </form>
            </PageSection>
        </FormModal>

        <FormModal :open="showManualPaymentForm && props.isAdmin" max-width-class="max-w-lg" @close="showManualPaymentForm = false">
            <PageSection title="Catat pembayaran" description="Gunakan untuk pembayaran tunai atau transfer yang diverifikasi langsung oleh admin tanpa upload bukti dari anggota.">
                <form class="grid gap-4" @submit.prevent="submitManualPayment">
                    <div v-if="manualPaymentRow" class="grid gap-2 rounded-lg border border-border bg-muted/30 p-3 text-sm">
                        <p><span class="font-medium">Tagihan:</span> {{ manualPaymentRow.invoice_number }}</p>
                        <p><span class="font-medium">Penerima:</span> {{ manualPaymentRow.athlete }}</p>
                        <p><span class="font-medium">Sisa sebelum transaksi:</span> {{ manualPaymentRow.balance }}</p>
                    </div>
                    <FormInputField id="manual-payment-amount" v-model="manualPaymentForm.amount" label="Nominal diterima" type="number" inputmode="decimal" min="0.01" step="0.01" required :error="manualPaymentForm.errors.amount" />
                    <div class="grid gap-4 md:grid-cols-2">
                        <FormInputField id="manual-payment-date" v-model="manualPaymentForm.transaction_date" label="Tanggal transaksi" type="date" required :error="manualPaymentForm.errors.transaction_date" />
                        <FormSelectField id="manual-payment-method" v-model="manualPaymentForm.payment_method" label="Metode" :options="collectionMethodOptions" required :error="manualPaymentForm.errors.payment_method" />
                    </div>
                    <FormInputField id="manual-payment-notes" v-model="manualPaymentForm.notes" label="Catatan transaksi" placeholder="Contoh: Diterima tunai oleh admin" :error="manualPaymentForm.errors.notes" />
                    <PaymentTransactionHistory
                        :entries="paymentHistory(manualPaymentRow)"
                        title="Riwayat ledger"
                        empty-text="Belum ada transaksi pada tagihan ini."
                    />
                    <div class="flex flex-wrap gap-3">
                        <Button type="submit" :disabled="manualPaymentForm.processing">Simpan transaksi</Button>
                        <Button type="button" variant="outline" @click="showManualPaymentForm = false">Batal</Button>
                    </div>
                </form>
            </PageSection>
        </FormModal>
    </AppLayout>
</template>
