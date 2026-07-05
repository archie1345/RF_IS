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
    router.delete(appRoutes.sessionAttendanceQr(props.sessionId), { preserveScroll: true });
}

function copyScanUrl() {
    if (scanUrl.value) {
        window.navigator.clipboard?.writeText(scanUrl.value);
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
        <form class="grid gap-4 lg:grid-cols-[1fr_auto]" @submit.prevent="generateQr">
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
            <div class="flex items-end gap-2">
                <Button type="submit" :disabled="form.processing">
                    {{ props.qr.is_active ? 'Regenerate QR' : 'Generate QR' }}
                </Button>
                <Button v-if="props.qr.is_active" type="button" variant="outline" @click="revokeQr">Close QR</Button>
            </div>
        </form>

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
            <p v-if="props.qr.revoked_at" class="text-sm text-muted-foreground">Closed: {{ props.qr.revoked_at }}</p>
            <p v-if="qrStatus" class="mt-2 text-sm text-muted-foreground">{{ qrStatus }}</p>

            <div v-if="scanUrl" class="mt-4 grid gap-3 md:grid-cols-[auto_1fr] md:items-center">
                <img
                    v-if="qrDataUrl"
                    :src="qrDataUrl"
                    alt="Session attendance QR code"
                    class="h-64 w-64 rounded border bg-white p-2"
                />
                <div
                    v-else
                    class="flex h-64 w-64 items-center justify-center rounded border bg-muted p-4 text-center text-sm text-muted-foreground"
                >
                    {{ renderError ?? 'Rendering QR code...' }}
                </div>
                <div class="space-y-2">
                    <p class="text-sm font-medium">Scan URL</p>
                    <p class="rounded bg-muted p-3 text-sm break-all">{{ scanUrl }}</p>
                    <Button type="button" variant="outline" @click="copyScanUrl">Copy URL</Button>
                </div>
            </div>
            <p v-else-if="props.qr.is_active" class="mt-4 text-sm text-muted-foreground">
                A QR window is active, but the plaintext token is only shown immediately after generation. Regenerate
                the QR to display a new scan code.
            </p>
        </div>
    </PageSection>
</template>
