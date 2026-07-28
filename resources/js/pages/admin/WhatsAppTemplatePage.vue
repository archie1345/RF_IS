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
    bubbleEnabled: boolean;
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
    bubble_enabled: props.bubbleEnabled,
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
    Object.entries(sampleValues).reduce((message, [key, value]) => message.replaceAll(`{${key}}`, value), form.body),
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
        message:
            'Isi editor akan diganti dengan template bawaan. Nomor kontak admin dan status bubble tidak akan berubah.',
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
                description="Atur nomor admin, bubble WhatsApp pada landing page, dan template pengingat pembayaran."
            >
                <template #actions>
                    <Button type="button" variant="outline" class="gap-2" @click="resetTemplate">
                        <RotateCcw class="size-4" /> Template bawaan
                    </Button>
                </template>
            </PageSection>

            <form
                class="grid min-w-0 gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(20rem,0.75fr)]"
                @submit.prevent="saveTemplate"
            >
                <section class="min-w-0 rounded-xl border bg-card p-4 shadow-sm sm:p-5">
                    <div class="mb-4 flex items-start gap-3">
                        <span class="rounded-xl bg-emerald-500/10 p-2 text-emerald-700 dark:text-emerald-300">
                            <MessageCircleMore class="size-5" />
                        </span>
                        <div>
                            <h2 class="font-semibold">Kontak dan isi pesan</h2>
                            <p class="text-sm text-muted-foreground">
                                Nomor kontak dipakai oleh tombol pendaftaran publik, halaman login, dan bubble landing
                                page.
                            </p>
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
                            help="Gunakan nomor yang aktif menerima permintaan pembuatan akun baru."
                        />

                        <button
                            type="button"
                            role="switch"
                            :aria-checked="form.bubble_enabled"
                            class="flex w-full items-center justify-between gap-4 rounded-xl border bg-background p-4 text-left transition hover:bg-muted/30 focus-visible:ring-2 focus-visible:ring-ring/30 focus-visible:outline-none"
                            @click="form.bubble_enabled = !form.bubble_enabled"
                        >
                            <span class="min-w-0">
                                <span class="block font-semibold">Bubble WhatsApp di landing page</span>
                                <span class="mt-1 block text-sm leading-5 text-muted-foreground">
                                    Tampilkan tombol WhatsApp mengambang di kanan bawah halaman publik.
                                </span>
                            </span>
                            <span
                                class="relative inline-flex h-7 w-12 shrink-0 rounded-full transition"
                                :class="form.bubble_enabled ? 'bg-emerald-500' : 'bg-muted-foreground/30'"
                            >
                                <span
                                    class="absolute top-1 size-5 rounded-full bg-white shadow-sm transition"
                                    :class="form.bubble_enabled ? 'left-6' : 'left-1'"
                                />
                            </span>
                        </button>
                        <p v-if="form.errors.bubble_enabled" class="text-sm text-destructive">
                            {{ form.errors.bubble_enabled }}
                        </p>

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
                        <p
                            v-if="form.errors.body"
                            class="rounded-lg bg-destructive/10 px-3 py-2 text-sm text-destructive"
                        >
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
                                    <code class="text-xs font-bold break-all text-primary">{{
                                        placeholder.token
                                    }}</code>
                                    <span class="mt-1 block text-xs text-muted-foreground">{{
                                        placeholder.description
                                    }}</span>
                                </span>
                                <Copy class="size-4 shrink-0 text-muted-foreground" />
                            </button>
                        </div>
                    </section>

                    <section class="rounded-xl border bg-card p-4 shadow-sm sm:p-5">
                        <div class="flex items-center gap-2">
                            <Eye class="size-5 text-primary" />
                            <h2 class="font-semibold">Pratinjau</h2>
                        </div>
                        <div
                            class="mt-3 rounded-xl bg-emerald-950 p-4 text-sm leading-6 break-words whitespace-pre-wrap text-emerald-50"
                        >
                            {{ preview || 'Template kosong.' }}
                        </div>
                    </section>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
