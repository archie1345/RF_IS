<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Html5Qrcode } from 'html5-qrcode';
import { Camera, CheckCircle2, Loader2, QrCode, Smartphone, WifiOff, XCircle } from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { useAppToast } from '@/composables/useAppToast';
import { show as attendanceScanShow, store as attendanceScanStore } from '@/routes/attendance/scan';
import type { Auth } from '@/types/auth';

type PendingScan = {
    id: string;
    token: string;
    athleteId: string | null;
    capturedAt: string;
};

type SyncError = Error & {
    retryable?: boolean;
};

const PENDING_SCANS_STORAGE_KEY = 'rf-is.pending-attendance-scans';

const scanner = ref<Html5Qrcode | null>(null);
const scannerElementId = `athlete-qr-scanner-${Math.random().toString(36).slice(2)}`;
const isPortableDevice = ref(false);
const isOnline = ref(true);
const isSyncing = ref(false);
const isScanning = ref(false);
const isOpening = ref(false);
const scannerError = ref<string | null>(null);
const lastHandledToken = ref<string | null>(null);
const lastHandledTokenAt = ref(0);
const manualQrUrl = ref('');
const pendingScans = ref<PendingScan[]>([]);
const toast = useAppToast();
const page = usePage<{ auth?: Auth }>();

const pendingScanCount = computed(() => pendingScans.value.length);
const connectionLabel = computed(() => {
    if (!isOnline.value) {
        return pendingScanCount.value > 0
            ? `${pendingScanCount.value} scan menunggu sinkronisasi`
            : 'Perangkat sedang offline';
    }

    if (isSyncing.value && pendingScanCount.value > 0) {
        return `Menyinkronkan ${pendingScanCount.value} scan tersimpan`;
    }

    if (pendingScanCount.value > 0) {
        return `${pendingScanCount.value} scan tersimpan dan akan disinkronkan`;
    }

    return 'Koneksi aktif';
});

function isBrowser(): boolean {
    return typeof window !== 'undefined';
}

function createSyncError(message: string, retryable = false): SyncError {
    const error = new Error(message) as SyncError;
    error.retryable = retryable;

    return error;
}

function readPendingScans(): PendingScan[] {
    if (!isBrowser()) return [];

    try {
        const raw = window.localStorage.getItem(PENDING_SCANS_STORAGE_KEY);
        if (!raw) return [];

        const parsed = JSON.parse(raw);
        if (!Array.isArray(parsed)) return [];

        return parsed
            .map((entry): PendingScan | null => {
                if (!entry || typeof entry !== 'object') return null;

                const id = typeof (entry as Record<string, unknown>).id === 'string' ? (entry as Record<string, string>).id : '';
                const token = typeof (entry as Record<string, unknown>).token === 'string'
                    ? (entry as Record<string, string>).token
                    : '';
                const athleteIdValue = (entry as Record<string, unknown>).athleteId;
                const athleteId =
                    typeof athleteIdValue === 'string'
                        ? athleteIdValue
                        : athleteIdValue === null || athleteIdValue === undefined
                          ? null
                          : String(athleteIdValue);
                const capturedAt =
                    typeof (entry as Record<string, unknown>).capturedAt === 'string'
                        ? (entry as Record<string, string>).capturedAt
                        : '';

                if (!id || !token || !capturedAt) {
                    return null;
                }

                return { id, token, athleteId, capturedAt };
            })
            .filter((entry): entry is PendingScan => entry !== null);
    } catch {
        return [];
    }
}

function persistPendingScans(nextScans: PendingScan[]): void {
    pendingScans.value = nextScans;

    if (!isBrowser()) return;

    window.localStorage.setItem(PENDING_SCANS_STORAGE_KEY, JSON.stringify(nextScans));
}

function updatePendingScans(nextScans: PendingScan[]): void {
    const unique = nextScans.filter(
        (scan, index, all) =>
            index ===
            all.findIndex(
                (candidate) => candidate.token === scan.token && candidate.athleteId === scan.athleteId,
            ),
    );

    persistPendingScans(unique);
}

function queueScanForLater(token: string): void {
    const athleteId =
        page.props.auth?.activeChild?.athlete_id ??
        page.props.auth?.children?.[0]?.athlete_id ??
        null;
    const alreadyQueued = pendingScans.value.some(
        (scan) => scan.token === token && scan.athleteId === athleteId,
    );

    if (alreadyQueued) {
        toast.info('QR sudah disimpan', 'Scan ini sudah ada di antrean sinkronisasi.');
        return;
    }

    const nextScans = [
        ...pendingScans.value,
        {
            id: isBrowser() && 'randomUUID' in window.crypto ? window.crypto.randomUUID() : `${Date.now()}-${Math.random().toString(36).slice(2)}`,
            token,
            athleteId,
            capturedAt: new Date().toISOString(),
        },
    ];

    updatePendingScans(nextScans);
    toast.warning(
        'QR disimpan offline',
        `Perangkat sedang offline. ${nextScans.length} scan menunggu untuk dikirim ke server.`,
    );
}

function detectPhoneOrTablet(): void {
    const agent = window.navigator.userAgent.toLowerCase();
    const platform = window.navigator.platform.toLowerCase();
    const hasTouch = window.navigator.maxTouchPoints > 1;

    isPortableDevice.value =
        /iphone|ipod|ipad|android|tablet|mobile|windows phone|iemobile|blackberry|bb10|opera mini/.test(agent) ||
        (platform.includes('mac') && hasTouch);
}

function extractAttendanceScanToken(value: string): string | null {
    const raw = value.trim();
    if (!raw) return null;

    try {
        const parsed = new URL(raw, window.location.origin);
        const match = parsed.pathname.match(/^\/attendance\/scan\/([^/]+)\/?$/);
        return match?.[1] ?? null;
    } catch {
        const match = raw.match(/\/attendance\/scan\/([^\s/]+)/);
        return match?.[1] ?? null;
    }
}

function isSecureCameraContext(): boolean {
    return window.isSecureContext || ['localhost', '127.0.0.1', '::1'].includes(window.location.hostname);
}

function shouldIgnoreToken(token: string): boolean {
    return lastHandledToken.value === token && Date.now() - lastHandledTokenAt.value < 3500;
}

function markTokenHandled(token: string): void {
    lastHandledToken.value = token;
    lastHandledTokenAt.value = Date.now();
}

async function openScanUrl(token: string): Promise<void> {
    if (shouldIgnoreToken(token) || isOpening.value) return;

    markTokenHandled(token);
    isOpening.value = true;
    scannerError.value = null;
    await stopScanner();

    router.visit(attendanceScanShow.url(token), {
        preserveScroll: false,
        onError: (errors) => {
            scannerError.value = Object.values(errors)[0] ?? 'Attendance QR page could not be opened.';
            isOpening.value = false;
        },
        onFinish: () => {
            isOpening.value = false;
        },
    });
}

function handleDecodedScan(decodedText: string): void {
    const token = extractAttendanceScanToken(decodedText);

    if (!token) {
        scannerError.value = 'This is not an RF attendance QR code.';
        return;
    }

    if (shouldIgnoreToken(token)) {
        return;
    }

    markTokenHandled(token);

    if (!isOnline.value) {
        scannerError.value = null;
        queueScanForLater(token);
        return;
    }

    void openScanUrl(token);
}

function csrfHeaders(): HeadersInit {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
    const xsrf = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

    if (csrf) headers['X-CSRF-TOKEN'] = csrf;
    if (xsrf) headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrf);

    return headers;
}

function extractSyncErrorMessage(payload: unknown): string {
    if (!payload || typeof payload !== 'object') {
        return 'Attendance scan could not be synchronized.';
    }

    const response = payload as Record<string, unknown>;
    if (typeof response.message === 'string' && response.message.trim()) {
        return response.message.trim();
    }

    const errors = response.errors;
    if (errors && typeof errors === 'object') {
        const messages = Object.values(errors as Record<string, unknown>)
            .flatMap((value) => (Array.isArray(value) ? value : [value]))
            .filter((value): value is string => typeof value === 'string' && value.trim() !== '')
            .map((value) => value.trim());

        if (messages.length > 0) {
            return messages[0];
        }
    }

    return 'Attendance scan could not be synchronized.';
}

async function submitQueuedScan(scan: PendingScan): Promise<void> {
    const response = await fetch(attendanceScanStore.url(scan.token), {
        method: 'POST',
        headers: csrfHeaders(),
        credentials: 'same-origin',
        body: JSON.stringify(scan.athleteId ? { athlete_id: scan.athleteId } : {}),
    });

    if (response.redirected && new URL(response.url).pathname.startsWith('/login')) {
        throw createSyncError('Your session expired before the offline scans could sync.', false);
    }

    if (response.ok) {
        return;
    }

    if (response.status === 422) {
        const payload = await response.json().catch(() => null);
        throw createSyncError(extractSyncErrorMessage(payload), false);
    }

    if (response.status === 401 || response.status === 403 || response.status === 419) {
        throw createSyncError('Your session is no longer valid. Sign in again to sync offline scans.', false);
    }

    if (response.status >= 500) {
        throw createSyncError('The server is temporarily unavailable. Sync will retry later.', true);
    }

    throw createSyncError(`Unexpected HTTP ${response.status} while syncing offline scans.`, false);
}

async function syncPendingScans(): Promise<void> {
    if (!isBrowser() || !isOnline.value || isSyncing.value || pendingScans.value.length === 0) {
        return;
    }

    isSyncing.value = true;
    scannerError.value = null;

    let syncedCount = 0;
    const remaining = [...pendingScans.value];

    try {
        for (const scan of [...remaining]) {
            try {
                await submitQueuedScan(scan);
                syncedCount += 1;
                const index = remaining.findIndex((entry) => entry.id === scan.id);
                if (index !== -1) {
                    remaining.splice(index, 1);
                    persistPendingScans([...remaining]);
                }
            } catch (error) {
                const syncError = error as SyncError;

                if (syncError.retryable) {
                    toast.warning('Sinkronisasi tertunda', syncError.message);
                    break;
                }

                const index = remaining.findIndex((entry) => entry.id === scan.id);
                if (index !== -1) {
                    remaining.splice(index, 1);
                    persistPendingScans([...remaining]);
                }

                toast.error('QR gagal disinkronkan', syncError.message);
            }
        }
    } finally {
        isSyncing.value = false;
    }

    if (syncedCount > 0) {
        toast.success(
            'Scan offline tersinkronisasi',
            `${syncedCount} scan attendance berhasil dikirim ke server.`,
        );
    }
}

async function startScanner(): Promise<void> {
    scannerError.value = null;

    if (!isPortableDevice.value) {
        scannerError.value = 'QR scan menu is only available on phones and tablets.';
        return;
    }

    if (!isSecureCameraContext()) {
        scannerError.value = 'Camera access requires HTTPS, localhost, or a trusted secure tunnel.';
        return;
    }

    if (!navigator.mediaDevices?.getUserMedia) {
        scannerError.value =
            'This browser cannot access the camera. Use Chrome/Edge/Safari on your phone or tablet, or paste the QR link below.';
        return;
    }

    try {
        await nextTick();

        scanner.value = new Html5Qrcode(scannerElementId, { verbose: false });
        await scanner.value.start(
            { facingMode: 'environment' },
            {
                fps: 10,
                qrbox: { width: 240, height: 240 },
                aspectRatio: 1,
            },
            (decodedText: string) => void handleDecodedScan(decodedText),
            () => undefined,
        );

        isScanning.value = true;
    } catch (error) {
        const message = error instanceof Error ? error.message : String(error ?? '');
        scannerError.value =
            message.toLowerCase().includes('permission') || message.toLowerCase().includes('notallowed')
                ? 'Camera permission was denied. Allow camera access in the browser/site settings, then try again.'
                : 'Camera could not start. Close other apps using the camera, use a phone/tablet browser, or paste the QR link below.';
        await stopScanner();
    }
}

async function stopScanner(): Promise<void> {
    if (!scanner.value) {
        isScanning.value = false;
        return;
    }

    try {
        if (isScanning.value) {
            await scanner.value.stop();
        }
        await scanner.value.clear();
    } catch {
        // Ignore cleanup failures from already-stopped camera streams.
    } finally {
        scanner.value = null;
        isScanning.value = false;
    }
}

function submitManualQrUrl(): void {
    const token = extractAttendanceScanToken(manualQrUrl.value);

    if (!token) {
        scannerError.value = 'This is not an RF attendance QR code.';
        return;
    }

    if (!isOnline.value) {
        scannerError.value = null;
        queueScanForLater(token);
        return;
    }

    void openScanUrl(token);
}

function handleOffline(): void {
    isOnline.value = false;
    toast.warning('Koneksi terputus', 'Perangkat sedang offline. Scan baru akan disimpan lokal sementara.');
}

function handleOnline(): void {
    isOnline.value = true;
    toast.info('Koneksi kembali aktif', 'Scan attendance yang tersimpan akan dikirim ke server otomatis.');
    void syncPendingScans();
}

onMounted(() => {
    detectPhoneOrTablet();
    isOnline.value = window.navigator.onLine;
    pendingScans.value = readPendingScans();

    window.addEventListener('offline', handleOffline);
    window.addEventListener('online', handleOnline);

    if (isOnline.value) {
        void syncPendingScans();
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('offline', handleOffline);
    window.removeEventListener('online', handleOnline);
    void stopScanner();
});
</script>

<template>
    <div v-if="isPortableDevice" class="rounded-3xl border bg-muted/40 p-5">
        <div class="flex items-start gap-3">
            <div class="rounded-2xl bg-brand-blue/15 p-3 text-brand-blue dark:bg-brand-blue/15 dark:text-brand-blue/80">
                <QrCode class="size-7" />
            </div>
            <div>
                <p class="text-xs font-black tracking-[0.22em] text-blue-600 uppercase">QR scan menu</p>
                <h2 class="mt-1 text-2xl font-black">Scan coach QR inside this page</h2>
                <p class="mt-2 text-sm text-muted-foreground">
                    Stay logged in as the athlete. Scan the coach QR here. The secure attendance page will open, verify
                    eligibility, then save automatically.
                </p>
            </div>
        </div>

        <div
            v-if="!isOnline || pendingScanCount > 0"
            class="mt-4 flex items-center justify-between gap-3 rounded-2xl border px-4 py-3 text-sm"
            :class="
                isOnline
                    ? 'border-emerald-200 bg-emerald-50/80 text-emerald-950 dark:border-emerald-900/60 dark:bg-emerald-950/25 dark:text-emerald-50'
                    : 'border-amber-200 bg-amber-50/80 text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/25 dark:text-amber-50'
            "
        >
            <div class="flex min-w-0 items-center gap-2">
                <WifiOff v-if="!isOnline" class="size-4 shrink-0" />
                <Loader2 v-else-if="isSyncing && pendingScanCount > 0" class="size-4 shrink-0 animate-spin" />
                <CheckCircle2 v-else class="size-4 shrink-0" />
                <span class="truncate">{{ connectionLabel }}</span>
            </div>
            <span class="shrink-0 rounded-full border border-current/20 bg-background/80 px-2.5 py-1 text-xs font-semibold">
                {{ pendingScanCount }} pending
            </span>
        </div>

        <div class="mt-5 overflow-hidden rounded-3xl border bg-background">
            <div
                :id="scannerElementId"
                class="min-h-72 w-full bg-black [&_video]:h-full [&_video]:w-full [&_video]:object-cover"
            ></div>
            <div
                v-if="!isScanning"
                class="flex min-h-72 flex-col items-center justify-center gap-3 p-6 text-center text-sm text-muted-foreground"
            >
                <Camera class="size-10" />
                <p>
                    Open the scanner, allow camera permission, point your phone/tablet at the coach QR, then wait for
                    the attendance page.
                </p>
            </div>
        </div>

        <div class="mt-4 grid gap-2 sm:grid-cols-2">
            <Button v-if="!isScanning" type="button" :disabled="isOpening" @click="startScanner">
                <Loader2 v-if="isOpening" class="mr-2 size-4 animate-spin" />
                Start scan
            </Button>
            <Button v-else type="button" variant="outline" @click="stopScanner">Stop scan</Button>
            <Button type="button" variant="outline" :disabled="isOpening" @click="submitManualQrUrl"
                >Use pasted link</Button
            >
        </div>

        <input
            v-model="manualQrUrl"
            type="url"
            inputmode="url"
            placeholder="Paste /attendance/scan/... link if camera scanner is unavailable"
            class="mt-3 h-10 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm"
        />

        <p
            v-if="scannerError"
            class="mt-3 flex items-start gap-2 rounded-2xl border border-red-200 bg-red-50 p-3 text-xs text-red-700 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-200"
        >
            <XCircle class="mt-0.5 size-4 shrink-0" />
            <span>{{ scannerError }}</span>
        </p>
    </div>

    <div v-else class="rounded-3xl border bg-muted/40 p-5 text-center">
        <div class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-muted text-muted-foreground">
            <Smartphone class="size-7" />
        </div>
        <p class="mt-4 text-xs font-black tracking-[0.22em] text-muted-foreground uppercase">
            QR scan hidden on desktop
        </p>
        <h2 class="mt-1 text-2xl font-black">Use a phone or tablet</h2>
        <p class="mt-2 text-sm text-muted-foreground">
            The QR scan menu is intentionally hidden on desktop. Open this attendance page from a phone or tablet to
            scan.
        </p>
    </div>
</template>
