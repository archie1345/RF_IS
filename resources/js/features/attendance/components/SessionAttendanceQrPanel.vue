<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import QRCode from 'qrcode';
import { computed, ref, watch } from 'vue';
import PageSection from '@/components/shared/PageSection.vue';
import { Button } from '@/components/ui/button';
import { appRoutes } from '@/data/routes';
import AttendanceWindowFields from '@/features/attendance/components/AttendanceWindowFields.vue';

const props = defineProps<{
    sessionId: number;
    qr: {
        is_active: boolean;
        opens_at?: string | null;
        closes_at?: string | null;
        generated_at?: string | null;
        revoked_at?: string | null;
    };
    sessionDate?: string | null;
    sessionStartTime?: string | null;
    sessionEndTime?: string | null;
    backHref?: string;
}>();

type AttendanceQrFlash = {
    token?: string;
    scan_url?: string;
    opens_at?: string | null;
    closes_at?: string | null;
    generated_at?: string | null;
};

type PagePropsWithQrFlash = {
    flash?: {
        attendanceQr?: AttendanceQrFlash;
        attendanceQrStatus?: string;
    };
};

const page = usePage<PagePropsWithQrFlash>();
const qrDataUrl = ref<string | null>(null);
const renderError = ref<string | null>(null);
const copied = ref(false);

const qrFlash = computed(() => page.props.flash?.attendanceQr ?? null);
const qrStatus = computed(() => page.props.flash?.attendanceQrStatus ?? null);
const scanUrl = computed(() => qrFlash.value?.scan_url ?? null);

const form = useForm({
    attendance_opens_at: toDateTimeLocal(props.qr.opens_at),
    attendance_closes_at: toDateTimeLocal(props.qr.closes_at),
});

watch(
    scanUrl,
    async (url) => {
        qrDataUrl.value = null;
        renderError.value = null;

        if (!url) {
            return;
        }

        try {
            qrDataUrl.value = await QRCode.toDataURL(url, {
                errorCorrectionLevel: 'M',
                margin: 2,
                width: 320,
            });
        } catch {
            renderError.value = 'Unable to render the QR code. Use the scan URL below.';
        }
    },
    { immediate: true },
);

function generateQr() {
    form.post(appRoutes.sessionAttendanceQr(props.sessionId), {
        preserveScroll: true,
    });
}

function revokeQr() {
    if (!window.confirm('Close this QR window? Athletes will no longer be able to use this code.')) {
        return;
    }

    router.delete(appRoutes.sessionAttendanceQr(props.sessionId), { preserveScroll: true });
}

async function copyScanUrl() {
    if (!scanUrl.value) return;

    try {
        if (window.navigator.clipboard && window.isSecureContext) {
            await window.navigator.clipboard.writeText(scanUrl.value);
        } else {
            const textarea = document.createElement('textarea');
            textarea.value = scanUrl.value;
            textarea.setAttribute('readonly', '');
            textarea.style.position = 'fixed';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
        }

        copied.value = true;
        window.setTimeout(() => {
            copied.value = false;
        }, 1600);
    } catch {
        copied.value = false;
    }
}

function toDateTimeLocal(value?: string | null): string {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const offset = date.getTimezoneOffset();
    const local = new Date(date.getTime() - offset * 60_000);

    return local.toISOString().slice(0, 16);
}
</script>

<template>
    <PageSection
        title="QR attendance"
        description="Generate a secure one-time-display scan URL for athlete self check-in. Existing records remain visible in the attendance table."
    >
        <div class="mt-4 rounded-lg border p-4">
            <p class="text-sm font-medium">
                State:
                <span :class="props.qr.is_active ? 'text-green-600' : 'text-muted-foreground'">
                    {{ props.qr.is_active ? 'Active' : 'Inactive' }}
                </span>
            </p>

            <p v-if="props.qr.generated_at" class="text-sm text-muted-foreground">
                Generated: {{ props.qr.generated_at }}
            </p>

            <p v-if="props.qr.revoked_at" class="text-sm text-muted-foreground">
                Closed: {{ props.qr.revoked_at }}
            </p>

            <p v-if="qrStatus" class="mt-2 text-sm text-muted-foreground">
                {{ qrStatus }}
            </p>

            <div v-if="scanUrl" class="mt-5 flex flex-col items-center gap-4 text-center">
                <img
                    v-if="qrDataUrl"
                    :src="qrDataUrl"
                    alt="Session attendance QR code"
                    class="mx-auto h-64 w-64 rounded border bg-white p-2"
                />

                <div
                    v-else
                    class="mx-auto flex h-64 w-64 items-center justify-center rounded border bg-muted p-4 text-center text-sm text-muted-foreground"
                >
                    {{ renderError ?? 'Rendering QR code...' }}
                </div>

                <div class="w-full max-w-xl space-y-2">
                    <p class="text-left text-sm font-medium">Scan URL</p>
                    <button
                        type="button"
                        class="flex w-full items-center gap-2 rounded bg-muted p-3 text-left text-sm transition hover:bg-muted/80"
                        :title="scanUrl ?? 'Click to copy scan URL'"
                        @click="copyScanUrl"
                    >
                        <span class="block min-w-0 flex-1 truncate">
                            {{ scanUrl }}
                        </span>

                        <span class="shrink-0 text-xs font-semibold text-muted-foreground">
                            {{ copied ? 'Copied' : 'Copy' }}
                        </span>
                    </button>

                    <p class="text-center text-xs text-muted-foreground">
                        Next: ask athletes to scan this QR with their phone camera and check in.
                    </p>
                </div>
            </div>

            <p v-else-if="props.qr.is_active" class="mt-4 text-sm text-muted-foreground">
                A QR window is active, but the plaintext token is only shown immediately after generation.
                Regenerate the QR to display a new scan code.
            </p>
        </div>

        <form class="grid gap-4 py-2 lg:grid-cols-[1fr_auto]" @submit.prevent="generateQr">
            <AttendanceWindowFields
                :opens-at="form.attendance_opens_at"
                :closes-at="form.attendance_closes_at"
                :opens-at-error="form.errors.attendance_opens_at"
                :closes-at-error="form.errors.attendance_closes_at"
                :session-date="props.sessionDate"
                :session-start-time="props.sessionStartTime"
                :session-end-time="props.sessionEndTime"
                @update:opens-at="form.attendance_opens_at = $event"
                @update:closes-at="form.attendance_closes_at = $event"
            />

        </form>
        <div class="flex justify-center gap-2">
            <Button type="button" :disabled="form.processing" @click="generateQr">
                {{ props.qr.is_active ? 'Regenerate QR' : 'Generate QR' }}
            </Button>

            <Button v-if="props.qr.is_active" type="button" variant="outline" @click="revokeQr">
                Close QR
            </Button>

            <Button v-if="props.backHref" as-child type="button" variant="outline">
                <a :href="props.backHref">Back to attendance</a>
            </Button>
        </div>
    </PageSection>
</template>
