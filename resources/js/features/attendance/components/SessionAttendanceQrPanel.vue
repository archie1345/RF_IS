<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Check, Copy, QrCode, ShieldCheck, X } from '@lucide/vue';
import QRCode from 'qrcode';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { useAppPopup } from '@/composables/useAppPopup';
import { destroy as destroySessionQr, store as storeSessionQr } from '@/routes/sessions/attendance-qr';
import type { PagePropsWithQrFlash } from './SessionAttendanceQrPanel.types';

const props = defineProps<{
    sessionId: number;
    qr: {
        is_active: boolean;
        scan_url?: string | null;
        opens_at?: string | null;
        closes_at?: string | null;
        generated_at?: string | null;
        revoked_at?: string | null;
    };
}>();

const popup = useAppPopup();
const page = usePage<PagePropsWithQrFlash>();
const qrDataUrl = ref<string | null>(null);
const renderError = ref<string | null>(null);
const copied = ref(false);
const processing = ref(false);

const qrFlash = computed(() => page.props.flash?.attendanceQr ?? null);
const qrStatus = computed(() => page.props.flash?.attendanceQrStatus ?? null);
const scanUrl = computed(() => qrFlash.value?.scan_url ?? props.qr.scan_url ?? null);

watch(
    scanUrl,
    async (url) => {
        qrDataUrl.value = null;
        renderError.value = null;

        if (!url) return;

        try {
            qrDataUrl.value = await QRCode.toDataURL(url, {
                errorCorrectionLevel: 'M',
                margin: 2,
                width: 420,
            });
        } catch {
            renderError.value = 'QR tidak dapat dirender. Gunakan tautan pemindaian di bawah.';
        }
    },
    { immediate: true },
);

function openQr(): void {
    if (processing.value) return;
    processing.value = true;

    router.post(
        storeSessionQr.url(props.sessionId),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}

async function closeQr(): Promise<void> {
    if (processing.value) return;

    const confirmed = await popup.confirm({
        title: 'Tutup QR attendance?',
        message:
            'Kode ini langsung tidak dapat digunakan lagi. Atlet atau orang tua yang belum memindai harus menunggu QR dibuka kembali.',
        tone: 'danger',
        confirmLabel: 'Tutup QR',
    });
    if (!confirmed) return;

    processing.value = true;
    router.delete(destroySessionQr.url(props.sessionId), {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
        },
    });
}

async function copyScanUrl(): Promise<void> {
    if (!scanUrl.value) return;

    await window.navigator.clipboard?.writeText(scanUrl.value);
    copied.value = true;
    window.setTimeout(() => {
        copied.value = false;
    }, 1800);
}
</script>

<template>
    <section
        class="overflow-hidden rounded-2xl border bg-card shadow-sm"
        :class="props.qr.is_active ? 'border-emerald-300 dark:border-emerald-800' : ''"
    >
        <div class="flex flex-col gap-4 border-b p-4 sm:flex-row sm:items-center sm:justify-between md:p-5">
            <div class="flex min-w-0 items-start gap-3">
                <div
                    class="flex size-11 shrink-0 items-center justify-center rounded-xl"
                    :class="
                        props.qr.is_active
                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                            : 'bg-muted text-muted-foreground'
                    "
                >
                    <QrCode class="size-6" />
                </div>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="font-semibold">QR attendance atlet</h2>
                        <span
                            class="rounded-full px-2.5 py-1 text-xs font-semibold"
                            :class="
                                props.qr.is_active
                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                                    : 'bg-muted text-muted-foreground'
                            "
                        >
                            {{ props.qr.is_active ? 'Terbuka' : 'Tertutup' }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm leading-6 text-muted-foreground">
                        {{
                            props.qr.is_active
                                ? 'QR tetap aktif sampai admin atau pelatih menekan Tutup QR.'
                                : 'Tekan Buka QR saat atlet sudah siap melakukan check-in.'
                        }}
                    </p>
                </div>
            </div>

            <Button
                v-if="!props.qr.is_active"
                type="button"
                class="w-full sm:w-auto"
                :disabled="processing"
                @click="openQr"
            >
                <QrCode class="mr-2 size-4" />
                {{ processing ? 'Membuka...' : 'Buka QR sekarang' }}
            </Button>
            <Button
                v-else
                type="button"
                variant="destructive"
                class="w-full sm:w-auto"
                :disabled="processing"
                @click="closeQr"
            >
                <X class="mr-2 size-4" />
                {{ processing ? 'Menutup...' : 'Tutup QR' }}
            </Button>
        </div>

        <div v-if="props.qr.is_active" class="grid gap-5 p-4 md:grid-cols-[minmax(16rem,22rem)_1fr] md:p-5">
            <div class="flex items-center justify-center rounded-2xl border bg-white p-4">
                <img
                    v-if="qrDataUrl"
                    :src="qrDataUrl"
                    alt="QR attendance sesi"
                    class="aspect-square w-full max-w-sm rounded-lg"
                />
                <div
                    v-else
                    class="flex aspect-square w-full max-w-sm items-center justify-center rounded-lg bg-muted p-6 text-center text-sm text-muted-foreground"
                >
                    {{ renderError ?? 'Memuat QR...' }}
                </div>
            </div>

            <div class="flex min-w-0 flex-col justify-center gap-4">
                <div class="rounded-xl border bg-muted/30 p-4">
                    <div class="flex items-start gap-3">
                        <ShieldCheck class="mt-0.5 size-5 shrink-0 text-emerald-600" />
                        <div>
                            <p class="font-medium">Pemindaian tetap wajib</p>
                            <p class="mt-1 text-sm leading-6 text-muted-foreground">
                                Atlet atau orang tua harus membuka tautan dari QR ini menggunakan ponsel. Orang tua
                                kemudian memilih anak yang terhubung sebelum check-in disimpan.
                            </p>
                        </div>
                    </div>
                </div>

                <div>
                    <p class="text-xs font-medium text-muted-foreground">Dibuka</p>
                    <p class="mt-1 text-sm font-medium">{{ qrFlash?.generated_at ?? props.qr.generated_at ?? '-' }}</p>
                </div>

                <div v-if="scanUrl" class="min-w-0">
                    <p class="text-xs font-medium text-muted-foreground">Tautan scan</p>
                    <p class="mt-1 max-h-24 overflow-auto rounded-lg bg-muted p-3 text-xs break-all">{{ scanUrl }}</p>
                    <Button type="button" size="sm" variant="outline" class="mt-2" @click="copyScanUrl">
                        <Check v-if="copied" class="mr-2 size-4" />
                        <Copy v-else class="mr-2 size-4" />
                        {{ copied ? 'Tersalin' : 'Salin tautan' }}
                    </Button>
                </div>
            </div>
        </div>

        <div v-else class="p-5 text-sm text-muted-foreground">
            QR yang sudah ditutup tidak dapat digunakan kembali. Membuka QR baru akan menghasilkan kode baru.
        </div>

        <p v-if="qrStatus" class="border-t bg-muted/30 px-4 py-3 text-sm md:px-5">{{ qrStatus }}</p>
    </section>
</template>
