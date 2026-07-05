<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Download, ImagePlus, PencilLine, Trash2 } from 'lucide-vue-next';
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
import { appRoutes } from '@/data/routes';
import PaymentTransactionHistory from '@/features/payments/components/PaymentTransactionHistory.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { Metric, SelectOption, TableColumn, TableRow } from '@/types/resource-table';

const props = defineProps<{
    isAdmin: boolean;
    metrics: Metric[];
    rows: TableRow[];
    athletes: SelectOption[];
    users: SelectOption[];
    coaches: SelectOption[];
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
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: appRoutes.dashboard },
    { title: 'Payments', href: appRoutes.payments },
];

const invoiceTemplateModalOpen = ref(false);
const billStatusFilter = ref('');
const proofQueueFilter = ref('');
const billKindFilter = ref('');
const billTypeFilter = ref('');

const showReviewForm = ref(false);
const reviewPaymentRow = ref<TableRow | null>(null);

type PaymentHistoryEntry = {
    id: number | string;
    amount: string;
    date: string;
    method: string;
    type: string;
    verified_by: string;
    notes?: string;
    proof_notes?: string;
    proof_url?: string | null;
};

const filteredRows = computed(() =>
    props.rows.filter((row) => {
        const paid = Number(row.paid_amount_raw ?? 0);
        const remaining = Number(row.remaining_amount_raw ?? 0);
        const billStatusMatches =
            !billStatusFilter.value ||
            (billStatusFilter.value === 'pending' && paid <= 0 && remaining > 0) ||
            (billStatusFilter.value === 'partial' && paid > 0 && remaining > 0) ||
            (billStatusFilter.value === 'paid' && remaining <= 0);
        const proofMatches = !proofQueueFilter.value || String(row.proof_status ?? '') === proofQueueFilter.value;
        const kindMatches = !billKindFilter.value || String(row.bill_kind ?? '') === billKindFilter.value;
        const typeMatches = !billTypeFilter.value || String(row.payment_type_raw ?? '') === billTypeFilter.value;

        return billStatusMatches && proofMatches && kindMatches && typeMatches;
    }),
);

const columns: TableColumn[] = [
    { key: 'athlete', label: 'Person' },
    { key: 'bill_kind', label: 'Kind' },
    { key: 'type', label: 'Bill type' },
    { key: 'amount', label: 'Amount', align: 'right' },
    { key: 'balance', label: 'Balance', align: 'right' },
    { key: 'status', label: 'Status' },
    { key: 'proof_status_label', label: 'Proof' },
];

const paymentTypeOptions = [
    { value: 'TUITION', label: 'Tuition' },
    { value: 'UNIFORM', label: 'Uniform' },
    { value: 'LICENSE', label: 'License' },
    { value: 'CHAMPIONSHIP', label: 'Championship' },
    { value: 'OTHER', label: 'Other' },
];

const collectionMethodOptions = [
    { value: 'CASH', label: 'Cash' },
    { value: 'TRANSFER', label: 'Transfer' },
    { value: 'OTHER', label: 'Other' },
];

function todayDate() {
    const date = new Date();
    date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
    return date.toISOString().slice(0, 10);
}

const form = useForm({
    athlete_id: '',
    billable_user_id: '',
    payee_user_id: '',
    bill_kind: 'INVOICE',
    payment_type: 'TUITION',
    collection_method: 'CASH',
    total_amount: '',
    paid_amount: '0',
    payment_date: todayDate(),
    notes: '',
});
const showPaymentForm = ref(false);
const editingPaymentId = ref<number | null>(null);
const editingPaymentRow = ref<TableRow | null>(null);
const showProofForm = ref(false);
const proofPaymentId = ref<number | null>(null);
const proofForm = useForm({
    notes: '',
    proof_file: null as File | null,
});
const reviewForm = useForm({
    decision: 'APPROVED',
    approved_amount: '',
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

function remainingAmount(row: TableRow) {
    return Number(row.remaining_amount_raw ?? 0);
}

function canUploadProof(row: TableRow) {
    return (
        !props.isAdmin &&
        remainingAmount(row) > 0 &&
        row.proof_status !== 'SUBMITTED' &&
        row.proof_status !== 'APPROVED'
    );
}

function canReviewProof(row: TableRow) {
    return props.isAdmin && row.proof_status === 'SUBMITTED';
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
    } else {
        form.payee_user_id = '';
        form.athlete_id = '';
    }

    if (!form.payment_date) {
        form.payment_date = todayDate();
    }

    if (!form.paid_amount) {
        form.paid_amount = '0';
    }

    const options = {
        onSuccess: () => {
            form.reset();
            form.paid_amount = '0';
            form.payment_date = todayDate();
            showPaymentForm.value = false;
            editingPaymentId.value = null;
        },
    };

    if (editingPaymentId.value) {
        form.put(`/payments/${editingPaymentId.value}`, options);
        return;
    }

    form.post('/payments', options);
}

function exportInvoice(paymentId: number | string) {
    // Triggers the browser to download the file by hitting the Laravel endpoint
    window.open(`/payments/${paymentId}/export`, '_blank');
}

function exportPaymentCsv() {
    window.location.href = '/admin/data-transfer/export?entity=payments';
}

function openCreate() {
    editingPaymentId.value = null;
    form.reset();
    form.bill_kind = 'INVOICE';
    form.payment_type = 'TUITION';
    form.collection_method = 'TRANSFER';
    form.paid_amount = '0';
    form.payment_date = todayDate();
    form.clearErrors();
    showPaymentForm.value = true;
}

function editPayment(row: TableRow) {
    editingPaymentId.value = Number(row.payment_id);
    editingPaymentRow.value = row;
    form.athlete_id = String(row.athlete_id ?? '');
    form.billable_user_id = String(row.billable_user_id ?? row.athlete_user_id ?? '');
    form.payee_user_id = String(row.payee_user_id ?? '');
    form.bill_kind = String(row.bill_kind ?? 'INVOICE');
    form.payment_type = String(row.payment_type_raw ?? 'TUITION');
    form.collection_method = String(row.collection_method_raw ?? 'CASH');
    form.total_amount = String(row.total_amount_raw ?? '0');
    form.paid_amount = String(row.paid_amount_raw ?? '0');
    form.payment_date = String(row.payment_date_raw ?? '');
    form.notes = String(row.notes_raw ?? '');
    showPaymentForm.value = true;
}

function deleteFromEdit() {
    if (editingPaymentRow.value) {
        deletePayment(editingPaymentRow.value);
        showPaymentForm.value = false;
    }
}
function deletePayment(row: TableRow) {
    const id = Number(row.payment_id);
    if (!id) return;
    if (!confirm('Delete this invoice?')) return;
    router.delete(`/payments/${id}`, { preserveScroll: true });
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
    proofPaymentId.value = Number(row.payment_id);
    proofForm.reset();
    showProofForm.value = true;
}

function submitProof() {
    if (!proofPaymentId.value) return;
    proofForm.post(`/payments/${proofPaymentId.value}/proof`, {
        forceFormData: true,
        onSuccess: () => {
            showProofForm.value = false;
        },
    });
}

function openReviewModal(row: TableRow) {
    reviewPaymentRow.value = row;
    reviewForm.decision = 'APPROVED';
    const balance = Number(row.remaining_amount_raw ?? 0);
    reviewForm.approved_amount = String(Math.max(balance, 0));
    reviewForm.notes = String(row.proof_notes ?? '');
    showReviewForm.value = true;
}

function submitReview(decision: 'APPROVED' | 'REJECTED') {
    if (!reviewPaymentRow.value) return;
    const id = Number(reviewPaymentRow.value.payment_id);
    reviewForm.decision = decision;

    if (decision === 'REJECTED' && !confirm('Are you sure you want to reject this receipt?')) return;

    reviewForm.put(`/payments/${id}/proof-review`, {
        preserveScroll: true,
        onSuccess: () => {
            showReviewForm.value = false;
        },
    });
}
</script>

<template>
    <Head title="Payments" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageSection
                eyebrow="Payment center"
                title="Bills and payment proof"
                description="Admins issue bills first. Members pay outside the system, upload a receipt here, and admins approve it."
            >
                <template #actions>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-if="props.isAdmin"
                            type="button"
                            variant="outline"
                            @click="invoiceTemplateModalOpen = true"
                            >Invoice settings</Button
                        >
                        <Button v-if="props.isAdmin" type="button" variant="outline" @click="exportPaymentCsv"
                            >Export CSV</Button
                        >
                        <Button v-if="props.isAdmin" type="button" @click="openCreate">Issue bill</Button>
                    </div>
                </template>

                <div class="grid gap-4 md:grid-cols-3">
                    <StatCard v-for="metric in props.metrics" :key="metric.label" v-bind="metric" />
                </div>
            </PageSection>

            <div class="grid gap-3 rounded-xl border border-border/70 bg-card p-4 md:grid-cols-4">
                <FormSelectField
                    id="bill-status-filter"
                    v-model="billStatusFilter"
                    label="Bill status"
                    :options="[
                        { value: '', label: 'All statuses' },
                        { value: 'pending', label: 'Pending bills' },
                        { value: 'partial', label: 'Partial bills' },
                        { value: 'paid', label: 'Paid bills' },
                    ]"
                />
                <FormSelectField
                    id="proof-queue-filter"
                    v-model="proofQueueFilter"
                    label="Proof queue"
                    :options="[
                        { value: '', label: 'All proof states' },
                        { value: 'SUBMITTED', label: 'Submitted proofs' },
                        { value: 'NONE', label: 'No active proof' },
                        { value: 'APPROVED', label: 'Approved proof' },
                        { value: 'REJECTED', label: 'Rejected proof' },
                    ]"
                />
                <FormSelectField
                    id="bill-kind-filter"
                    v-model="billKindFilter"
                    label="Bill kind"
                    :options="[
                        { value: '', label: 'All kinds' },
                        { value: 'INVOICE', label: 'Invoice / member bill' },
                        { value: 'PAYROLL', label: 'Coach payout' },
                    ]"
                />
                <FormSelectField
                    id="bill-type-filter"
                    v-model="billTypeFilter"
                    label="Bill category"
                    :options="[{ value: '', label: 'All categories' }, ...paymentTypeOptions]"
                />
            </div>

            <DataTable
                title="Bills and receipts"
                description="Open a bill to download the invoice or upload payment proof. The balance changes only after admin approval."
                :columns="columns"
                :rows="filteredRows"
                searchable
                search-placeholder="Search by person, bill type, or status"
            >
                <template #row-actions="{ row }">
                    <ActionButtonsRow>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            title="Download Invoice"
                            @click="exportInvoice(row.payment_id as string | number)"
                        >
                            <Download class="size-4" />
                        </Button>

                        <Button
                            v-if="canUploadProof(row)"
                            type="button"
                            size="sm"
                            variant="outline"
                            class="gap-2"
                            @click="openProofForm(row)"
                        >
                            Pay / Upload
                        </Button>

                        <a
                            v-if="row.proof_url && !canReviewProof(row)"
                            :href="String(row.proof_url)"
                            target="_blank"
                            class="inline-flex h-8 items-center rounded-md border px-3 text-xs"
                            >Receipt</a
                        >

                        <Button
                            v-if="canReviewProof(row)"
                            type="button"
                            size="sm"
                            class="gap-2"
                            @click="openReviewModal(row)"
                        >
                            Review Receipt
                        </Button>

                        <Button
                            v-if="props.isAdmin"
                            type="button"
                            size="sm"
                            variant="outline"
                            class="gap-2"
                            @click="editPayment(row)"
                        >
                            <PencilLine class="size-4" /> Edit / Manage
                        </Button>
                    </ActionButtonsRow>
                </template>
            </DataTable>
        </div>

        <FormModal
            :open="showPaymentForm && props.isAdmin"
            max-width-class="max-w-xl"
            @close="
                showPaymentForm = false;
                editingPaymentId = null;
            "
        >
            <PageSection
                :title="editingPaymentId ? 'Edit bill' : 'Issue a bill'"
                description="Choose the person, enter the amount, and the system will keep the unpaid balance until a receipt is approved."
            >
                <form class="grid gap-4" @submit.prevent="submit">
                    <FormSelectField
                        id="payment-kind"
                        v-model="form.bill_kind"
                        label="What are you issuing?"
                        :options="[
                            { value: 'INVOICE', label: 'Bill for a member' },
                            { value: 'PAYROLL', label: 'Coach payout record' },
                        ]"
                        help="Most payments should stay as Bill for a member."
                        :error="form.errors.bill_kind"
                    />
                    <FormSelectField
                        v-if="form.bill_kind === 'INVOICE'"
                        id="payment-recipient"
                        v-model="form.billable_user_id"
                        label="Person receiving this bill"
                        :options="props.users"
                        placeholder="Select athlete, coach, parent, or member"
                        help="Admins can change this when editing. If the person has an athlete profile, the bill is linked to that athlete automatically."
                        :error="form.errors.billable_user_id || form.errors.athlete_id"
                    />
                    <FormSelectField
                        v-if="form.bill_kind === 'PAYROLL'"
                        id="payment-coach"
                        v-model="form.payee_user_id"
                        label="Coach receiving payout"
                        :options="props.coaches"
                        placeholder="Select coach"
                        required
                        :error="form.errors.payee_user_id"
                    />
                    <FormSelectField
                        v-if="form.bill_kind === 'INVOICE'"
                        id="payment-type"
                        v-model="form.payment_type"
                        label="Bill category"
                        :options="paymentTypeOptions"
                        required
                        :error="form.errors.payment_type"
                    />
                    <FormInputField
                        id="payment-total"
                        v-model="form.total_amount"
                        label="Amount to pay"
                        type="number"
                        inputmode="decimal"
                        min="0"
                        step="1000"
                        placeholder="Example: 250000"
                        required
                        :error="form.errors.total_amount"
                    />
                    <FormInputField
                        id="payment-notes"
                        v-model="form.notes"
                        label="Note for this bill"
                        placeholder="Example: May tuition"
                        help="Optional. Keep it short and clear for the member."
                        :error="form.errors.notes"
                    />
                    <input v-model="form.payment_date" type="hidden" />
                    <input v-if="!editingPaymentId" v-model="form.paid_amount" type="hidden" />
                    <details class="rounded-lg border border-border p-3">
                        <summary class="cursor-pointer text-sm font-medium">Admin details</summary>
                        <div class="mt-4 grid gap-4">
                            <FormSelectField
                                id="payment-method"
                                v-model="form.collection_method"
                                label="Expected payment method"
                                :options="collectionMethodOptions"
                                help="Used as a note on the bill."
                                :error="form.errors.collection_method"
                            />
                            <div v-if="editingPaymentId" class="grid gap-4 md:grid-cols-2">
                                <FormInputField
                                    id="payment-paid"
                                    v-model="form.paid_amount"
                                    label="Amount already approved"
                                    type="number"
                                    inputmode="decimal"
                                    min="0"
                                    step="1000"
                                    :error="form.errors.paid_amount"
                                />
                                <FormInputField
                                    id="payment-date"
                                    v-model="form.payment_date"
                                    label="Issue date"
                                    type="date"
                                    :error="form.errors.payment_date"
                                />
                            </div>
                        </div>
                    </details>

                    <PaymentTransactionHistory
                        v-if="editingPaymentId"
                        :entries="paymentHistory(editingPaymentRow)"
                        empty-text="No approved installments have been recorded for this bill yet."
                    />

                    <div class="mt-4 flex flex-wrap items-center justify-between gap-4 border-t border-border pt-4">
                        <div class="flex flex-wrap gap-3">
                            <Button type="submit" :disabled="form.processing">{{
                                editingPaymentId ? 'Save changes' : 'Issue bill'
                            }}</Button>
                            <Button
                                type="button"
                                variant="outline"
                                @click="
                                    showPaymentForm = false;
                                    editingPaymentId = null;
                                "
                                >Cancel</Button
                            >
                        </div>

                        <div v-if="editingPaymentId" class="flex flex-wrap gap-2">
                            <Button type="button" variant="destructive" size="sm" @click="deleteFromEdit">
                                <Trash2 class="mr-2 size-4" /> Delete Bill
                            </Button>
                        </div>
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
                title="Invoice settings"
                description="These details appear on downloaded invoices and in the payment instructions members see before uploading a receipt."
            >
                <form class="grid gap-6" @submit.prevent="saveInvoiceTemplate">
                    <div class="grid gap-2">
                        <label class="text-sm font-medium">Logo</label>
                        <div class="flex items-center gap-4">
                            <div
                                class="flex h-24 w-24 items-center justify-center rounded-lg border-2 border-dashed border-input bg-muted/50"
                            >
                                <ImagePlus class="size-8 text-muted-foreground" />
                            </div>
                            <div class="flex-1">
                                <FormInputField
                                    id="invoice-logo-url"
                                    v-model="invoiceTemplateForm.logo_url"
                                    type="url"
                                    label="Logo URL"
                                    placeholder="https://example.com/logo.png"
                                    help="Optional. Leave empty if you do not have a public logo link."
                                    :error="invoiceTemplateForm.errors.logo_url"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <FormInputField
                            id="invoice-company-name"
                            v-model="invoiceTemplateForm.company_name"
                            label="Club name"
                            required
                            :error="invoiceTemplateForm.errors.company_name"
                        />
                        <FormInputField
                            id="invoice-company-email"
                            v-model="invoiceTemplateForm.company_email"
                            label="Finance email"
                            type="email"
                            autocomplete="email"
                            :error="invoiceTemplateForm.errors.company_email"
                        />
                        <FormInputField
                            id="invoice-company-phone"
                            v-model="invoiceTemplateForm.company_phone"
                            label="Finance phone"
                            autocomplete="tel"
                            :error="invoiceTemplateForm.errors.company_phone"
                        />
                    </div>
                    <div class="grid gap-2">
                        <label for="invoice-company-address" class="text-sm font-medium">Company address</label>
                        <textarea
                            id="invoice-company-address"
                            v-model="invoiceTemplateForm.company_address"
                            rows="2"
                            class="rounded-lg border border-input bg-background px-3 py-2 text-sm"
                        ></textarea>
                    </div>
                    <FormInputField
                        id="invoice-header-text"
                        v-model="invoiceTemplateForm.header_text"
                        label="Invoice heading"
                        placeholder="Example: Official payment invoice"
                        :error="invoiceTemplateForm.errors.header_text"
                    />
                    <div class="grid gap-2">
                        <label for="invoice-footer-text" class="text-sm font-medium">Footer note</label>
                        <textarea
                            id="invoice-footer-text"
                            v-model="invoiceTemplateForm.footer_text"
                            rows="3"
                            class="rounded-lg border border-input bg-background px-3 py-2 text-sm"
                        ></textarea>
                    </div>
                    <div class="grid gap-2">
                        <label for="invoice-payment-notes" class="text-sm font-medium">Payment instructions</label>
                        <textarea
                            id="invoice-payment-notes"
                            v-model="invoiceTemplateForm.payment_notes"
                            rows="3"
                            placeholder="Example: Transfer to BCA 123456789 a.n. RF IS, then upload the receipt here."
                            class="rounded-lg border border-input bg-background px-3 py-2 text-sm"
                        ></textarea>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <Button type="submit" :disabled="invoiceTemplateForm.processing">Save invoice settings</Button>
                        <Button type="button" variant="outline" @click="invoiceTemplateModalOpen = false"
                            >Cancel</Button
                        >
                    </div>
                </form>
            </PageSection>
        </FormModal>

        <FormModal :open="showProofForm" max-width-class="max-w-xl" @close="showProofForm = false">
            <PageSection
                title="Pay this bill"
                description="Pay using the admin instructions, then upload a receipt so finance can approve it."
            >
                <div v-if="activeProofRow" class="grid gap-2 rounded-lg border border-border bg-muted/30 p-3 text-sm">
                    <p>
                        <span class="font-medium">Bill:</span> {{ activeProofRow.type }} for
                        {{ activeProofRow.athlete }}
                    </p>
                    <p><span class="font-medium">Balance:</span> {{ activeProofRow.balance }}</p>
                    <p class="leading-6 text-muted-foreground">{{ props.paymentInstructions }}</p>
                </div>
                <PaymentTransactionHistory
                    :entries="paymentHistory(activeProofRow)"
                    title="Approved installments"
                    empty-text="No approved installments yet. Upload proof for the remaining amount when ready."
                    :show-verifier="false"
                    :bordered="true"
                />
                <form class="grid gap-4" @submit.prevent="submitProof">
                    <FormInputField
                        id="proof-notes"
                        v-model="proofForm.notes"
                        label="Receipt note"
                        placeholder="Example: Paid by bank transfer today"
                        help="Optional, but useful if the receipt is hard to read."
                        :error="proofForm.errors.notes"
                    />
                    <FormFileField
                        id="proof-file"
                        v-model="proofForm.proof_file"
                        label="Payment proof file"
                        accept="image/*,.pdf"
                        :error="proofForm.errors.proof_file"
                    />
                    <p v-if="!proofForm.errors.proof_file" class="text-xs leading-5 text-muted-foreground">
                        Accepted: image or PDF, up to 10 MB.
                    </p>
                    <div class="flex gap-3">
                        <Button type="submit" :disabled="proofForm.processing || !proofForm.proof_file"
                            >Send receipt for review</Button
                        >
                        <Button type="button" variant="outline" @click="showProofForm = false">Cancel</Button>
                    </div>
                </form>
            </PageSection>
        </FormModal>

        <FormModal :open="showReviewForm" max-width-class="max-w-md" @close="showReviewForm = false">
            <PageSection
                title="Review Payment Proof"
                description="Review the receipt and confirm the amount paid in this specific transaction."
            >
                <form class="grid gap-4">
                    <div v-if="reviewPaymentRow" class="rounded-lg border border-border bg-muted/30 p-3 text-sm">
                        <p><span class="font-medium">Member:</span> {{ reviewPaymentRow.athlete }}</p>
                        <p><span class="font-medium">Total Bill:</span> {{ reviewPaymentRow.amount }}</p>
                        <p><span class="font-medium">Current Balance:</span> {{ reviewPaymentRow.balance }}</p>
                        <div class="mt-2" v-if="reviewPaymentRow.proof_url">
                            <a
                                :href="String(reviewPaymentRow.proof_url)"
                                target="_blank"
                                class="inline-flex h-8 items-center rounded-md border bg-background px-3 text-xs"
                                >View Receipt Document</a
                            >
                        </div>
                    </div>

                    <FormInputField
                        id="review-approved-amount"
                        v-model="reviewForm.approved_amount"
                        label="Approved amount for this proof"
                        type="number"
                        inputmode="decimal"
                        min="0.01"
                        step="0.01"
                        required
                        help="If this is a partial payment, change this number. The system will calculate the remaining balance."
                        :error="reviewForm.errors.approved_amount"
                    />

                    <FormInputField
                        id="review-notes"
                        v-model="reviewForm.notes"
                        label="Admin notes (optional)"
                        placeholder="Example: First installment received"
                        :error="reviewForm.errors.notes"
                    />

                    <div class="mt-4 flex gap-3">
                        <Button type="button" :disabled="reviewForm.processing" @click="submitReview('APPROVED')"
                            >Approve approved amount</Button
                        >
                        <Button
                            type="button"
                            variant="destructive"
                            :disabled="reviewForm.processing"
                            @click="submitReview('REJECTED')"
                            >Reject Proof</Button
                        >
                        <Button type="button" variant="outline" @click="showReviewForm = false">Cancel</Button>
                    </div>
                </form>
            </PageSection>
        </FormModal>
    </AppLayout>
</template>
