<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, CheckCircle2, ImageUp } from 'lucide-vue-next';
import FormFileField from '@/components/forms/FormFileField.vue';
import FormInputField from '@/components/forms/FormInputField.vue';
import PageSection from '@/components/shared/PageSection.vue';
import { Button } from '@/components/ui/button';
import QrisPaymentPanel from '@/features/payments/components/QrisPaymentPanel.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { index as paymentsIndex } from '@/routes/payments';
import type { BreadcrumbItem } from '@/types';

interface QrisSettings {
    enabled: boolean;
    label: string;
    instructions: string;
    imageUrl: string | null;
    configured: boolean;
}

interface OutstandingPayment {
    payment_id: number;
    invoice_number: string;
    recipient: string;
    category: string;
    balance: string;
    due: string | null;
    is_overdue: boolean;
}

const props = defineProps<{
    isAdmin: boolean;
    qris: QrisSettings;
    outstandingPayments: OutstandingPayment[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Beranda', href: dashboard.url() },
    { title: 'Keuangan & Bayar', href: paymentsIndex.url() },
    { title: 'Pembayaran QRIS', href: '/payments/qris' },
];

const form = useForm({
    qris_enabled: props.qris.enabled,
    qris_label: props.qris.label,
    qris_instructions: props.qris.instructions,
    qris_image: null as File | null,
    remove_qris_image: false,
});

function saveQrisSettings(): void {
    form.post('/admin/invoice-template', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.qris_image = null;
            form.remove_qris_image = false;
        },
    });
}
</script>

<template>
    <Head title="Pembayaran QRIS" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageSection
                eyebrow="Kanal pembayaran"
                title="Pembayaran QRIS statis"
                description="QRIS ini belum terhubung ke Midtrans. Pembayaran tetap diverifikasi melalui bukti yang diunggah pada tagihan terkait."
            >
                <template #actions>
                    <Button as-child type="button" variant="outline">
                        <Link :href="paymentsIndex.url()"> <ArrowLeft class="mr-2 size-4" /> Kembali ke keuangan </Link>
                    </Button>
                </template>

                <QrisPaymentPanel
                    :enabled="props.qris.enabled"
                    :image-url="props.qris.imageUrl"
                    :label="props.qris.label"
                    :instructions="props.qris.instructions"
                />

                <div
                    v-if="!props.qris.enabled"
                    class="rounded-xl border border-dashed border-border bg-muted/30 p-4 text-sm text-muted-foreground"
                >
                    Kanal QRIS sedang dinonaktifkan oleh admin. Gunakan metode pembayaran lain yang tercantum pada
                    tagihan.
                </div>
            </PageSection>

            <PageSection
                title="Tagihan yang masih terbuka"
                description="Bayar sesuai nilai sisa pada tagihan, kemudian kembali ke halaman keuangan untuk mengunggah bukti."
            >
                <div v-if="props.outstandingPayments.length" class="grid gap-3">
                    <article
                        v-for="payment in props.outstandingPayments"
                        :key="payment.payment_id"
                        class="grid gap-3 rounded-xl border border-border bg-card p-4 md:grid-cols-[1fr_auto] md:items-center"
                    >
                        <div class="grid gap-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold">{{ payment.invoice_number }}</p>
                                <span
                                    v-if="payment.is_overdue"
                                    class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium"
                                >
                                    <AlertTriangle class="size-3" /> Jatuh tempo
                                </span>
                            </div>
                            <p class="text-sm text-muted-foreground">
                                {{ payment.recipient }} · {{ payment.category }} · Jatuh tempo {{ payment.due || '-' }}
                            </p>
                        </div>
                        <div class="text-left md:text-right">
                            <p class="text-xs tracking-wide text-muted-foreground uppercase">Sisa pembayaran</p>
                            <p class="font-semibold">{{ payment.balance }}</p>
                        </div>
                    </article>
                </div>
                <div v-else class="flex items-center gap-3 rounded-xl border border-border bg-card p-4">
                    <CheckCircle2 class="size-5 text-muted-foreground" />
                    <p class="text-sm">Tidak ada tagihan terbuka pada konteks peran yang sedang aktif.</p>
                </div>
            </PageSection>

            <PageSection
                v-if="props.isAdmin"
                title="Edit QRIS"
                description="Unggah gambar QRIS resmi. Jangan gunakan QR dinamis atau gambar yang berasal dari sumber yang tidak diverifikasi."
            >
                <form class="grid gap-5" @submit.prevent="saveQrisSettings">
                    <label class="flex items-center gap-3 rounded-lg border border-border p-3 text-sm font-medium">
                        <input v-model="form.qris_enabled" type="checkbox" class="size-4" />
                        Tampilkan QRIS kepada pengguna
                    </label>

                    <FormInputField
                        id="qris-label"
                        v-model="form.qris_label"
                        label="Judul QRIS"
                        required
                        :error="form.errors.qris_label"
                    />

                    <label class="grid gap-2 text-sm font-medium">
                        Instruksi pembayaran
                        <textarea
                            v-model="form.qris_instructions"
                            rows="4"
                            class="rounded-lg border border-input bg-background px-3 py-2 text-sm"
                            placeholder="Contoh: Pindai QRIS, bayar sesuai sisa tagihan, lalu unggah bukti."
                        ></textarea>
                        <span v-if="form.errors.qris_instructions" class="text-sm text-destructive">
                            {{ form.errors.qris_instructions }}
                        </span>
                    </label>

                    <div class="grid gap-4 rounded-xl border border-border p-4 md:grid-cols-[1fr_auto] md:items-end">
                        <div class="grid gap-2">
                            <FormFileField
                                id="qris-image"
                                v-model="form.qris_image"
                                label="Gambar QRIS resmi"
                                accept="image/png,image/jpeg,image/webp"
                                :error="form.errors.qris_image"
                            />
                            <p class="text-xs text-muted-foreground">PNG, JPG, atau WebP. Maksimal 5 MB.</p>
                        </div>
                        <ImageUp class="hidden size-8 text-muted-foreground md:block" aria-hidden="true" />
                    </div>

                    <label
                        v-if="props.qris.configured"
                        class="flex items-center gap-3 rounded-lg border border-border p-3 text-sm"
                    >
                        <input v-model="form.remove_qris_image" type="checkbox" class="size-4" />
                        Hapus gambar QRIS saat ini dan kembali ke placeholder
                    </label>

                    <div class="flex flex-wrap gap-3">
                        <Button type="submit" :disabled="form.processing">Simpan QRIS</Button>
                        <Button as-child type="button" variant="outline">
                            <Link :href="paymentsIndex.url()">Batal</Link>
                        </Button>
                    </div>
                </form>
            </PageSection>
        </div>
    </AppLayout>
</template>
