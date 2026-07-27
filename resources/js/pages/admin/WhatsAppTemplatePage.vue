<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Copy, Eye, MessageCircleMore, RotateCcw } from 'lucide-vue-next';
import { computed } from 'vue';
import FormInputField from '@/components/forms/FormInputField.vue';
import PageSection from '@/components/shared/PageSection.vue';
import { Button } from '@/components/ui/button';
import { useAppPopup } from '@/composables/useAppPopup';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    template: { body: string };
    contactNumber: string;
    defaultTemplate: string;
    placeholders: Array<{ key: string; token: string; description: string }>;
}>();

const popup = useAppPopup();
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Beranda', href: '/dashboard' },
    { title: 'Aturan Tagihan', href: '/admin/billing-settings' },
    { title: 'WhatsApp', href: '/admin/whatsapp-template' },
];

const form = useForm({
    body: props.template.body,
    contact_number: props.contactNumber,
});

const sampleValues: Record<string, string> = {
    name: 'Ayu Pratama',
    invoice_number: 'INV-202607-000123',
    payment_type: 'Iuran / SPP',
    total_amount: 'Rp 150.000',
    remaining_amount: 'Rp 150.000',
    due_date: '15 Agustus 2026',
    payment_url: 'http://localhost:9200/payments',
};

const preview = computed(() =>
    Object.entries(sampleValues).reduce(
        (message, [key, value]) => message.replaceAll(`{${key}}`, value),
        form.body,
    ),
);

function saveTemplate(): void {
    form.put('/admin/whatsapp-template', { preserveScroll: true });
}

async function copyPlaceholder(token: string): Promise<void> {
    try {
        await navigator.clipboard.writeText(token);
        void popup.success('Placeholder disalin', `${token} siap ditempel ke template.`);
    } catch {
        void popup.error('Tidak dapat menyalin', `Salin placeholder ini secara manual: ${token}`);
    }
}

async function resetTemplate(): Promise<void> {
    const confirmed = await popup.confirm({
        title: 'Kembalikan template bawaan?',
        message: 'Isi editor akan diganti dengan template bawaan. Nomor kontak admin tidak akan berubah.',
        tone: 'warning',
        confirmLabel: 'Gunakan template bawaan',
    });
    if (!confirmed) return;
    form.body = props.defaultTemplate;
    form.clearErrors('body');
}
</script>

<template>
    <Head title="Pengaturan WhatsApp" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-w-0 flex-1 flex-col gap-5 p-3 sm:p-4 md:p-6">
            <PageSection
                eyebrow="Komunikasi"
                title="Pengaturan WhatsApp"
                description="Atur nomor admin untuk tombol pendaftaran publik dan template pengingat pembayaran."
            >
                <template #actions>
                    <Button type="button" variant="outline" class="gap-2" @click="resetTemplate">
                        <RotateCcw class="size-4" /> Template bawaan
                    </Button>
                </template>
            </PageSection>

            <form class="grid min-w-0 gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(20rem,0.75fr)]" @submit.prevent="saveTemplate">
                <section class="min-w-0 rounded-xl border bg-card p-4 shadow-sm sm:p-5">
                    <div class="mb-4 flex items-start gap-3">
                        <span class="rounded-xl bg-emerald-500/10 p-2 text-emerald-700 dark:text-emerald-300">
                            <MessageCircleMore class="size-5" />
                        </span>
                        <div>
                            <h2 class="font-semibold">Kontak dan isi pesan</h2>
                            <p class="text-sm text-muted-foreground">Nomor kontak ini dipakai oleh tombol Daftar pada halaman publik.</p>
                        </div>
                    </div>

                    <div class="grid gap-4">
                        <FormInputField
                            id="public-admin-whatsapp"
                            v-model="form.contact_number"
                            label="Nomor WhatsApp admin"
                            placeholder="Contoh: 6281234567890"
                            inputmode="tel"
                            required
                            :error="form.errors.contact_number"
                            help="Gunakan nomor yang aktif menerima pendaftaran anggota baru."
                        />

                        <label class="grid gap-2 text-sm font-semibold">
                            Template pengingat pembayaran
                            <textarea
                                v-model="form.body"
                                rows="10"
                                maxlength="3000"
                                class="min-h-56 w-full resize-y rounded-xl border bg-background px-3 py-3 text-sm leading-6 focus:ring-2 focus:ring-ring/20 focus:outline-none"
                                :class="form.errors.body ? 'border-destructive' : 'border-input'"
                                required
                            />
                        </label>
                        <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
                            <span>Pesan dibuka di WhatsApp dan tidak dikirim otomatis.</span>
                            <span>{{ form.body.length }} / 3000 karakter</span>
                        </div>
                        <p v-if="form.errors.body" class="rounded-lg bg-destructive/10 px-3 py-2 text-sm text-destructive">
                            {{ form.errors.body }}
                        </p>
                        <Button type="submit" class="w-full sm:w-fit" :disabled="form.processing">
                            {{ form.processing ? 'Menyimpan...' : 'Simpan pengaturan' }}
                        </Button>
                    </div>
                </section>

                <div class="grid content-start gap-5">
                    <section class="rounded-xl border bg-card p-4 shadow-sm sm:p-5">
                        <h2 class="font-semibold">Placeholder</h2>
                        <div class="mt-3 grid gap-2">
                            <button
                                v-for="placeholder in props.placeholders"
                                :key="placeholder.key"
                                type="button"
                                class="flex items-center justify-between gap-3 rounded-lg border bg-background p-3 text-left hover:bg-muted/30"
                                @click="copyPlaceholder(placeholder.token)"
                            >
                                <span class="min-w-0">
                                    <code class="break-all text-xs font-bold text-primary">{{ placeholder.token }}</code>
                                    <span class="mt-1 block text-xs text-muted-foreground">{{ placeholder.description }}</span>
                                </span>
                                <Copy class="size-4 shrink-0 text-muted-foreground" />
                            </button>
                        </div>
                    </section>

                    <section class="rounded-xl border bg-card p-4 shadow-sm sm:p-5">
                        <div class="flex items-center gap-2"><Eye class="size-5 text-primary" /><h2 class="font-semibold">Pratinjau</h2></div>
                        <div class="mt-3 whitespace-pre-wrap break-words rounded-xl bg-emerald-950 p-4 text-sm leading-6 text-emerald-50">
                            {{ preview || 'Template kosong.' }}
                        </div>
                    </section>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
