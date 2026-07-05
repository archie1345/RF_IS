<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';
import { Camera, Loader2, QrCode, XCircle } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { appRoutes } from '@/data/routes';

type BarcodeDetectorLike = {
    detect(source: HTMLVideoElement): Promise<{ rawValue?: string }[]>;
};

const videoRef = ref<HTMLVideoElement | null>(null);
const stream = ref<MediaStream | null>(null);
const isScanning = ref(false);
const isPosting = ref(false);
const scannerError = ref<string | null>(null);
const lastScanUrl = ref<string | null>(null);
const manualQrUrl = ref('');
let scanTimer: number | null = null;

const canUseNativeScanner = computed(() => typeof window !== 'undefined' && 'BarcodeDetector' in window && navigator.mediaDevices?.getUserMedia);

function extractAttendanceScanUrl(value: string): string | null {
    const raw = value.trim();
    if (!raw) return null;

    try {
        const parsed = new URL(raw, window.location.origin);
        const match = parsed.pathname.match(/^\/attendance\/scan\/([^/]+)$/);
        if (!match?.[1]) return null;
        return appRoutes.attendanceScan(match[1]);
    } catch {
        const match = raw.match(/\/attendance\/scan\/([^\s/]+)/);
        return match?.[1] ? appRoutes.attendanceScan(match[1]) : null;
    }
}

function submitScanUrl(url: string) {
    const scanUrl = extractAttendanceScanUrl(url);
    if (!scanUrl) {
        scannerError.value = 'This is not an RF attendance QR code.';
        return;
    }

    if (lastScanUrl.value === scanUrl || isPosting.value) return;
    lastScanUrl.value = scanUrl;
    isPosting.value = true;
    stopScanner();

    router.post(
        scanUrl,
        {},
        {
            preserveScroll: true,
            onError: (errors) => {
                scannerError.value = Object.values(errors)[0] ?? 'Attendance could not be saved from this QR.';
                lastScanUrl.value = null;
            },
            onSuccess: () => {
                scannerError.value = null;
            },
            onFinish: () => {
                isPosting.value = false;
            },
        },
    );
}

async function scanFrame(detector: BarcodeDetectorLike) {
    if (!isScanning.value || !videoRef.value) return;

    try {
        const results = await detector.detect(videoRef.value);
        const value = results.find((result) => result.rawValue)?.rawValue;
        if (value) {
            submitScanUrl(value);
            return;
        }
    } catch {
        scannerError.value = 'Camera is open, but QR scanning failed. Try better lighting or paste the QR link.';
    }

    scanTimer = window.setTimeout(() => scanFrame(detector), 350);
}

async function startScanner() {
    scannerError.value = null;

    if (!canUseNativeScanner.value) {
        scannerError.value = 'This browser cannot scan QR from camera here. Use the phone camera app, or paste the QR link below.';
        return;
    }

    try {
        const BarcodeDetectorCtor = (window as unknown as { BarcodeDetector: new (options: { formats: string[] }) => BarcodeDetectorLike }).BarcodeDetector;
        const detector = new BarcodeDetectorCtor({ formats: ['qr_code'] });
        stream.value = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
        isScanning.value = true;
        await nextTick();

        if (videoRef.value) {
            videoRef.value.srcObject = stream.value;
            await videoRef.value.play();
        }

        void scanFrame(detector);
    } catch {
        scannerError.value = 'Camera permission was denied or unavailable. Use the phone camera app, or paste the QR link below.';
        stopScanner();
    }
}

function stopScanner() {
    isScanning.value = false;
    if (scanTimer !== null) {
        window.clearTimeout(scanTimer);
        scanTimer = null;
    }
    stream.value?.getTracks().forEach((track) => track.stop());
    stream.value = null;
    if (videoRef.value) {
        videoRef.value.srcObject = null;
    }
}

function submitManualQrUrl() {
    submitScanUrl(manualQrUrl.value);
}

onBeforeUnmount(stopScanner);
</script>

<template>
    <div class="rounded-3xl border bg-muted/40 p-5">
        <div class="flex items-start gap-3">
            <div class="rounded-2xl bg-blue-100 p-3 text-blue-700 dark:bg-blue-950/40 dark:text-blue-200">
                <QrCode class="size-7" />
            </div>
            <div>
                <p class="text-xs font-black uppercase tracking-[0.22em] text-blue-600">QR scan menu</p>
                <h2 class="mt-1 text-2xl font-black">Scan coach QR inside this page</h2>
                <p class="mt-2 text-sm text-muted-foreground">Stay logged in as the athlete. Scan the coach QR here and attendance will save automatically.</p>
            </div>
        </div>

        <div class="mt-5 overflow-hidden rounded-3xl border bg-background">
            <video v-show="isScanning" ref="videoRef" muted playsinline class="aspect-video w-full bg-black object-cover"></video>
            <div v-if="!isScanning" class="flex aspect-video flex-col items-center justify-center gap-3 p-6 text-center text-sm text-muted-foreground">
                <Camera class="size-10" />
                <p>Open the scanner, point your phone at the coach QR, then wait for the saved confirmation.</p>
            </div>
        </div>

        <div class="mt-4 grid gap-2 sm:grid-cols-2">
            <Button v-if="!isScanning" type="button" :disabled="isPosting" @click="startScanner">
                <Loader2 v-if="isPosting" class="mr-2 size-4 animate-spin" />
                Start scan
            </Button>
            <Button v-else type="button" variant="outline" @click="stopScanner">Stop scan</Button>
            <Button type="button" variant="outline" :disabled="isPosting" @click="submitManualQrUrl">Use pasted link</Button>
        </div>

        <input
            v-model="manualQrUrl"
            type="url"
            inputmode="url"
            placeholder="Paste /attendance/scan/... link if camera scanner is unavailable"
            class="mt-3 h-10 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm"
        />

        <p v-if="scannerError" class="mt-3 flex items-start gap-2 rounded-2xl border border-red-200 bg-red-50 p-3 text-xs text-red-700 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-200">
            <XCircle class="mt-0.5 size-4 shrink-0" />
            <span>{{ scannerError }}</span>
        </p>
    </div>
</template>
