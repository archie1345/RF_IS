<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Html5Qrcode, type CameraDevice } from 'html5-qrcode';
import { Camera, CheckCircle2, ClipboardPaste, Loader2, QrCode, RotateCcw, XCircle } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Scan QR Attendance', href: '/attendance/scan' },
];

const scannerElementId = 'attendance-browser-qr-reader';
const scanner = ref<Html5Qrcode | null>(null);
const cameras = ref<CameraDevice[]>([]);
const selectedCameraId = ref<string | null>(null);
const cameraRunning = ref(false);
const isStarting = ref(false);
const scanError = ref<string | null>(null);
const detectedText = ref<string | null>(null);
const pastedQrLink = ref('');

const isSecureCameraContext = computed(() => {
    if (typeof window === 'undefined') return false;

    return window.isSecureContext || ['localhost', '127.0.0.1', '::1'].includes(window.location.hostname);
});

const canUseCamera = computed(() => typeof navigator !== 'undefined' && Boolean(navigator.mediaDevices?.getUserMedia));

function resolveAttendanceUrl(rawValue: string): string | null {
    const value = rawValue.trim();

    if (!value) return null;

    const tokenMatch = value.match(/attendance\/scan\/([^/?#]+)/);
    if (tokenMatch?.[1]) {
        return `/attendance/scan/${encodeURIComponent(decodeURIComponent(tokenMatch[1]))}`;
    }

    if (/^[A-Za-z0-9_-]{24,}$/.test(value)) {
        return `/attendance/scan/${encodeURIComponent(value)}`;
    }

    try {
        const url = new URL(value, window.location.origin);
        const match = url.pathname.match(/\/attendance\/scan\/([^/]+)/);
        if (match?.[1]) {
            return `/attendance/scan/${encodeURIComponent(decodeURIComponent(match[1]))}`;
        }
    } catch {
        return null;
    }

    return null;
}

async function stopScanner() {
    if (!scanner.value) return;

    try {
        if (cameraRunning.value) {
            await scanner.value.stop();
        }
    } catch {
        // Ignore stop errors; the browser may already have closed the camera stream.
    } finally {
        try {
            scanner.value.clear();
        } catch {
            // Ignore clear errors when the reader has already been torn down.
        }
        scanner.value = null;
        cameraRunning.value = false;
    }
}

function handleDetectedText(text: string) {
    const targetUrl = resolveAttendanceUrl(text);
    detectedText.value = text;

    if (!targetUrl) {
        scanError.value = 'QR detected, but it is not an RF attendance QR link.';
        return;
    }

    scanError.value = null;
    void stopScanner();
    router.visit(targetUrl);
}

async function loadCameras() {
    cameras.value = await Html5Qrcode.getCameras();
    selectedCameraId.value = cameras.value.find((camera) => /back|rear|environment/i.test(camera.label))?.id ?? cameras.value[0]?.id ?? null;
}

function cameraErrorMessage(error: unknown): string {
    const message = error instanceof Error ? error.message : String(error ?? '');

    if (!isSecureCameraContext.value) {
        return 'Browser camera scanning needs HTTPS, localhost, or a trusted secure tunnel. Open this app with HTTPS/Tailscale Serve/ngrok/Cloudflare Tunnel, then try again.';
    }

    if (/permission|notallowed|denied/i.test(message)) {
        return 'Camera permission was blocked. Enable camera permission for this site in the browser settings, then try again.';
    }

    if (/notfound|devicesnotfound|overconstrained/i.test(message)) {
        return 'No usable camera was found. Try another browser, another device, or paste the QR link below.';
    }

    if (/notreadable|trackstart|could not start|in use/i.test(message)) {
        return 'Camera could not start. Close other apps/tabs using the camera, switch camera, or paste the QR link below.';
    }

    return 'Camera could not start. Use HTTPS, allow camera permission, close other camera apps, or paste the QR link below.';
}

async function startScanner() {
    scanError.value = null;
    detectedText.value = null;

    if (!canUseCamera.value) {
        scanError.value = 'This browser does not support camera scanning. Try Chrome/Safari on phone or paste the QR link below.';
        return;
    }

    if (!isSecureCameraContext.value) {
        scanError.value = 'Browser camera scanning needs HTTPS, localhost, or a trusted secure tunnel. Plain http://192.168.x.x usually cannot open the camera.';
        return;
    }

    isStarting.value = true;

    try {
        await nextTick();
        await stopScanner();
        await loadCameras();

        scanner.value = new Html5Qrcode(scannerElementId, { verbose: false });
        const cameraConfig = selectedCameraId.value ? { deviceId: { exact: selectedCameraId.value } } : { facingMode: 'environment' };

        await scanner.value.start(
            cameraConfig,
            { fps: 10, qrbox: { width: 260, height: 260 }, aspectRatio: 1 },
            (decodedText) => handleDetectedText(decodedText),
            () => {},
        );

        cameraRunning.value = true;
    } catch (error) {
        scanError.value = cameraErrorMessage(error);
        await stopScanner();
    } finally {
        isStarting.value = false;
    }
}

async function switchCamera(cameraId: string) {
    selectedCameraId.value = cameraId;
    if (cameraRunning.value) {
        await startScanner();
    }
}

function submitPastedQrLink() {
    const targetUrl = resolveAttendanceUrl(pastedQrLink.value);

    if (!targetUrl) {
        scanError.value = 'Paste a valid RF attendance QR link, for example /attendance/scan/{token}.';
        return;
    }

    router.visit(targetUrl);
}

onBeforeUnmount(() => {
    void stopScanner();
});
</script>

<template>
    <Head title="Scan QR Attendance" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex min-h-[72vh] w-full max-w-xl flex-col justify-center gap-4 p-4">
            <section class="overflow-hidden rounded-[2rem] border bg-card shadow-sm">
                <div class="bg-gradient-to-br from-blue-50 via-background to-red-50 p-5 dark:from-blue-950/30 dark:via-background dark:to-red-950/30">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-black uppercase tracking-[0.24em] text-blue-600">RF Attendance</p>
                            <h1 class="mt-2 text-3xl font-black tracking-tight">Scan QR with browser camera</h1>
                        </div>
                        <div class="rounded-3xl bg-background/80 p-4 shadow-sm ring-1 ring-border">
                            <Loader2 v-if="isStarting" class="size-8 animate-spin text-blue-600" />
                            <Camera v-else-if="cameraRunning" class="size-8 text-blue-600" />
                            <QrCode v-else class="size-8 text-muted-foreground" />
                        </div>
                    </div>

                    <p class="mt-3 text-sm text-muted-foreground">
                        Use this page when you want the browser itself to open the camera. Camera scanning needs HTTPS or localhost; plain LAN HTTP often cannot request permission.
                    </p>
                </div>

                <div class="grid gap-4 p-5">
                    <div class="overflow-hidden rounded-3xl border bg-black">
                        <div :id="scannerElementId" class="min-h-[320px] w-full"></div>
                    </div>

                    <div v-if="scanError" class="flex gap-3 rounded-3xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200">
                        <XCircle class="mt-0.5 size-5 shrink-0" />
                        <p>{{ scanError }}</p>
                    </div>

                    <div v-if="detectedText && !scanError" class="flex gap-3 rounded-3xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200">
                        <CheckCircle2 class="mt-0.5 size-5 shrink-0" />
                        <p>QR detected. Opening attendance page...</p>
                    </div>

                    <div class="grid gap-2 sm:grid-cols-2">
                        <Button type="button" class="rounded-2xl" :disabled="isStarting" @click="startScanner">
                            <Loader2 v-if="isStarting" class="mr-2 size-4 animate-spin" />
                            {{ cameraRunning ? 'Restart camera' : 'Start camera' }}
                        </Button>
                        <Button type="button" variant="outline" class="rounded-2xl" :disabled="!cameraRunning" @click="stopScanner">
                            Stop camera
                        </Button>
                    </div>

                    <label v-if="cameras.length > 1" class="grid gap-2 text-sm font-semibold">
                        Camera
                        <select class="rounded-2xl border bg-background px-3 py-2" :value="selectedCameraId ?? ''" @change="switchCamera(($event.target as HTMLSelectElement).value)">
                            <option v-for="camera in cameras" :key="camera.id" :value="camera.id">
                                {{ camera.label || `Camera ${camera.id}` }}
                            </option>
                        </select>
                    </label>

                    <section class="rounded-3xl border bg-background p-4">
                        <div class="flex items-center gap-2 text-sm font-black uppercase tracking-wide text-muted-foreground">
                            <ClipboardPaste class="size-4" />
                            Paste fallback
                        </div>
                        <p class="mt-2 text-sm text-muted-foreground">Paste a full QR link or token if browser camera permission is unavailable.</p>
                        <div class="mt-3 grid gap-2 sm:grid-cols-[1fr_auto]">
                            <input v-model="pastedQrLink" class="rounded-2xl border bg-background px-3 py-2 text-sm" placeholder="/attendance/scan/{token} or full link" @keyup.enter="submitPastedQrLink" />
                            <Button type="button" variant="secondary" class="rounded-2xl" @click="submitPastedQrLink">
                                Open link
                            </Button>
                        </div>
                    </section>

                    <Button type="button" variant="ghost" class="rounded-2xl" @click="startScanner">
                        <RotateCcw class="mr-2 size-4" />
                        Try camera again
                    </Button>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
