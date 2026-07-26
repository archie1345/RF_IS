<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { AlertTriangle, CheckCircle2, CircleAlert, Info, X } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    dismissAppPopup,
    settleAppPopup,
    useAppPopup,
    type AppPopupTone,
} from '@/composables/useAppPopup';

const popup = useAppPopup();
const page = usePage<{
    flash?: {
        status?: string | null;
        error?: string | null;
        warning?: string | null;
        info?: string | null;
    };
}>();
const primaryButton = ref<{ $el?: HTMLElement } | null>(null);
let removeValidationListener: (() => void) | undefined;
let removeInvalidResponseListener: (() => void) | undefined;
let removeExceptionListener: (() => void) | undefined;
let lastFeedbackKey = '';
let lastFeedbackAt = 0;

const icon = computed(
    () =>
        ({
            success: CheckCircle2,
            warning: AlertTriangle,
            danger: CircleAlert,
            info: Info,
        })[popup.state.tone],
);

const iconClass = computed(
    () =>
        ({
            success: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
            warning: 'bg-amber-500/10 text-amber-700 dark:text-amber-300',
            danger: 'bg-rose-500/10 text-rose-700 dark:text-rose-300',
            info: 'bg-sky-500/10 text-sky-700 dark:text-sky-300',
        })[popup.state.tone],
);

const primaryVariant = computed(() => (popup.state.tone === 'danger' ? 'destructive' : 'default'));

function showFeedback(tone: AppPopupTone, title: string, message?: string | null): void {
    if (!message) return;

    const key = `${tone}:${title}:${message}`;
    const now = Date.now();
    if (key === lastFeedbackKey && now - lastFeedbackAt < 1200) return;

    lastFeedbackKey = key;
    lastFeedbackAt = now;
    void popup.show({ title, message, tone });
}

function validationMessage(errors: unknown): string {
    if (!errors || typeof errors !== 'object') {
        return 'Periksa kembali data yang diisi, lalu coba simpan lagi.';
    }

    const messages = Object.values(errors as Record<string, unknown>)
        .flatMap((value) => (Array.isArray(value) ? value : [value]))
        .filter((value): value is string => typeof value === 'string' && value.trim() !== '')
        .map((value) => value.trim());

    if (messages.length === 0) {
        return 'Periksa kembali data yang diisi, lalu coba simpan lagi.';
    }

    const visible = [...new Set(messages)].slice(0, 6);
    const suffix = messages.length > visible.length ? `\n…dan ${messages.length - visible.length} kesalahan lainnya.` : '';

    return visible.map((message, index) => `${index + 1}. ${message}`).join('\n') + suffix;
}

function responseErrorMessage(status: number): string {
    if (status === 401) return 'Sesi login tidak lagi valid. Silakan masuk kembali.';
    if (status === 403) return 'Akun atau peran aktif tidak memiliki izin untuk melakukan tindakan ini.';
    if (status === 404) return 'Halaman atau data yang diminta tidak ditemukan.';
    if (status === 419) return 'Sesi keamanan telah kedaluwarsa. Muat ulang halaman, lalu coba lagi.';
    if (status === 422) return 'Data yang dikirim belum valid. Periksa semua kolom yang ditandai.';
    if (status === 429) return 'Terlalu banyak permintaan. Tunggu sebentar sebelum mencoba lagi.';
    if (status >= 500) return 'Server mengalami kesalahan saat memproses permintaan. Data belum tentu tersimpan.';

    return status > 0
        ? `Server mengembalikan respons HTTP ${status} yang tidak dapat diproses oleh aplikasi.`
        : 'Respons server tidak dapat diproses oleh aplikasi.';
}

function errorDetail(error: unknown): string {
    if (error instanceof Error && error.message.trim()) return error.message;
    if (typeof error === 'string' && error.trim()) return error;

    return 'Terjadi gangguan yang tidak terduga. Muat ulang halaman bila masalah berlanjut.';
}

function flashPopup(tone: AppPopupTone, title: string, value?: string | null): void {
    showFeedback(tone, title, value);
}

function handleKeydown(event: KeyboardEvent): void {
    if (!popup.state.open) return;

    if (event.key === 'Escape') {
        event.preventDefault();
        dismissAppPopup();
    }
}

function handleWindowError(event: ErrorEvent): void {
    showFeedback('danger', 'Kesalahan aplikasi', event.message || 'Antarmuka mengalami kesalahan yang tidak terduga.');
}

function handleUnhandledRejection(event: PromiseRejectionEvent): void {
    showFeedback('danger', 'Proses tidak dapat diselesaikan', errorDetail(event.reason));
}

function handleOffline(): void {
    showFeedback('warning', 'Koneksi terputus', 'Perangkat sedang offline. Perubahan belum dapat dikirim ke server.');
}

function handleOnline(): void {
    showFeedback('info', 'Koneksi kembali aktif', 'Koneksi internet tersedia kembali. Silakan ulangi tindakan yang sebelumnya gagal.');
}

watch(
    () => popup.state.open,
    (open) => {
        if (!open) return;
        void nextTick(() => primaryButton.value?.$el?.focus());
    },
);

watch(() => page.props.flash?.status, (value) => flashPopup('success', 'Berhasil', value), { immediate: true });
watch(() => page.props.flash?.error, (value) => flashPopup('danger', 'Terjadi kesalahan', value), { immediate: true });
watch(() => page.props.flash?.warning, (value) => flashPopup('warning', 'Perlu perhatian', value), { immediate: true });
watch(() => page.props.flash?.info, (value) => flashPopup('info', 'Informasi', value), { immediate: true });

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
    window.addEventListener('error', handleWindowError);
    window.addEventListener('unhandledrejection', handleUnhandledRejection);
    window.addEventListener('offline', handleOffline);
    window.addEventListener('online', handleOnline);

    removeValidationListener = router.on('error', (event) => {
        showFeedback('danger', 'Data belum valid', validationMessage(event.detail.errors));
    });
    removeInvalidResponseListener = router.on('invalid', (event) => {
        showFeedback(
            'danger',
            'Respons server tidak dapat diproses',
            responseErrorMessage(event.detail.response.status),
        );
    });
    removeExceptionListener = router.on('exception', (event) => {
        event.preventDefault();
        showFeedback(
            'danger',
            navigator.onLine ? 'Koneksi atau aplikasi bermasalah' : 'Koneksi terputus',
            navigator.onLine
                ? errorDetail(event.detail.exception)
                : 'Perangkat sedang offline. Perubahan belum dapat dikirim ke server.',
        );
    });
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleKeydown);
    window.removeEventListener('error', handleWindowError);
    window.removeEventListener('unhandledrejection', handleUnhandledRejection);
    window.removeEventListener('offline', handleOffline);
    window.removeEventListener('online', handleOnline);
    removeValidationListener?.();
    removeInvalidResponseListener?.();
    removeExceptionListener?.();
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="popup.state.open"
                class="fixed inset-0 z-[200] flex items-center justify-center bg-black/55 p-4 backdrop-blur-[2px]"
                @mousedown.self="dismissAppPopup"
            >
                <Transition
                    appear
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="translate-y-3 scale-95 opacity-0"
                    enter-to-class="translate-y-0 scale-100 opacity-100"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="translate-y-0 scale-100 opacity-100"
                    leave-to-class="translate-y-2 scale-95 opacity-0"
                >
                    <section
                        role="dialog"
                        aria-modal="true"
                        :aria-labelledby="'global-popup-title'"
                        :aria-describedby="popup.state.message ? 'global-popup-message' : undefined"
                        class="relative w-full max-w-md overflow-hidden rounded-2xl border bg-card shadow-2xl"
                    >
                        <div class="h-1.5 bg-primary" :class="popup.state.tone === 'danger' ? 'bg-destructive' : ''" />

                        <button
                            v-if="popup.state.dismissible"
                            type="button"
                            class="absolute top-4 right-4 rounded-lg p-1.5 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                            aria-label="Tutup popup"
                            @click="dismissAppPopup"
                        >
                            <X class="size-4" />
                        </button>

                        <div class="p-5 sm:p-6">
                            <div class="flex items-start gap-4 pr-7">
                                <div class="flex size-11 shrink-0 items-center justify-center rounded-xl" :class="iconClass">
                                    <component :is="icon" class="size-5" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h2 id="global-popup-title" class="text-lg font-bold tracking-tight">
                                        {{ popup.state.title }}
                                    </h2>
                                    <p
                                        v-if="popup.state.message"
                                        id="global-popup-message"
                                        class="mt-2 whitespace-pre-line text-sm leading-6 text-muted-foreground"
                                    >
                                        {{ popup.state.message }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                                <Button
                                    v-if="popup.state.showCancel"
                                    type="button"
                                    variant="outline"
                                    @click="settleAppPopup(false)"
                                >
                                    {{ popup.state.cancelLabel }}
                                </Button>
                                <Button
                                    ref="primaryButton"
                                    type="button"
                                    :variant="primaryVariant"
                                    @click="settleAppPopup(true)"
                                >
                                    {{ popup.state.confirmLabel }}
                                </Button>
                            </div>
                        </div>
                    </section>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
