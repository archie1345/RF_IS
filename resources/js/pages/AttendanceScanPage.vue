<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { CheckCircle2, Loader2, QrCode, ShieldCheck, Smartphone, XCircle } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    token: string;
    deviceAllowed: boolean;
    state: string;
    message: string | null;
    canSubmit: boolean;
    session: {
        title: string;
        date: string;
        time: string;
        location: string | null;
        branch: string | null;
        group: string | null;
        opens_at: string | null;
        closes_at: string | null;
    } | null;
    athlete: {
        name: string | null;
        current_status: string | null;
    } | null;
}>();

type AttendanceScanFlash = {
    status?: string;
    message?: string;
};

type PagePropsWithAttendanceScan = {
    flash?: {
        attendanceScan?: AttendanceScanFlash;
    };
    errors?: Record<string, string>;
};

const page = usePage<PagePropsWithAttendanceScan>();
const isSubmitting = ref(false);
const attemptedAutoSubmit = ref(false);
const autoSubmitError = ref<string | null>(null);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'QR Attendance', href: `/attendance/scan/${props.token}` },
];

const scanFlash = computed(() => page.props.flash?.attendanceScan ?? null);
const pageErrors = computed(() => page.props.errors ?? {});
const hasSaved = computed(() => props.state === 'already_present' || scanFlash.value?.status === 'recorded' || scanFlash.value?.status === 'already_recorded');
const blockingError = computed(() => autoSubmitError.value ?? pageErrors.value.device ?? pageErrors.value.token ?? null);

const stepState = computed(() => {
    if (!props.deviceAllowed) return 'blocked';
    if (blockingError.value || ['invalid', 'not_open', 'closed', 'revoked', 'athlete_required', 'not_eligible'].includes(props.state)) return 'blocked';
    if (hasSaved.value) return 'done';
    if (isSubmitting.value) return 'saving';
    if (props.canSubmit) return 'prompt';

    return 'waiting';
});

const headline = computed(() => {
    if (stepState.value === 'done') return 'Attendance saved';
    if (stepState.value === 'saving') return 'Saving attendance...';
    if (stepState.value === 'prompt') return 'QR detected';
    if (stepState.value === 'blocked') return 'Cannot record attendance';

    return 'QR attendance';
});

const statusMessage = computed(() => scanFlash.value?.message ?? blockingError.value ?? props.message ?? 'Hold on while we verify your QR attendance.');

function autoRecordAttendance() {
    if (!props.canSubmit || hasSaved.value || attemptedAutoSubmit.value || isSubmitting.value) {
        return;
    }

    attemptedAutoSubmit.value = true;
    isSubmitting.value = true;

    router.post(
        `/attendance/scan/${props.token}`,
        {},
        {
            preserveScroll: true,
            onError: (errors) => {
                autoSubmitError.value = Object.values(errors)[0] ?? 'Unable to save attendance from this QR.';
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
}

onMounted(() => {
    window.setTimeout(autoRecordAttendance, 450);
});
</script>

<template>
    <Head title="QR Attendance" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex min-h-[72vh] w-full max-w-md flex-col justify-center gap-4 p-4">
            <section class="overflow-hidden rounded-[2rem] border bg-card shadow-sm">
                <div class="bg-gradient-to-br from-red-50 via-background to-blue-50 p-5 dark:from-red-950/30 dark:via-background dark:to-blue-950/30">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-black uppercase tracking-[0.24em] text-red-500">RF Attendance</p>
                            <h1 class="mt-2 text-3xl font-black tracking-tight">{{ headline }}</h1>
                        </div>
                        <div class="rounded-3xl bg-background/80 p-4 shadow-sm ring-1 ring-border">
                            <QrCode v-if="stepState === 'prompt'" class="size-8 text-blue-600" />
                            <Loader2 v-else-if="stepState === 'saving'" class="size-8 animate-spin text-blue-600" />
                            <CheckCircle2 v-else-if="stepState === 'done'" class="size-8 text-emerald-600" />
                            <XCircle v-else-if="stepState === 'blocked'" class="size-8 text-red-600" />
                            <Smartphone v-else class="size-8 text-muted-foreground" />
                        </div>
                    </div>

                    <p class="mt-3 text-sm text-muted-foreground">{{ statusMessage }}</p>
                </div>

                <div class="grid gap-3 p-5">
                    <div class="grid grid-cols-3 gap-2 text-center text-xs font-semibold">
                        <div class="rounded-2xl border p-3" :class="stepState !== 'blocked' ? 'border-blue-200 bg-blue-50 text-blue-700' : 'bg-muted text-muted-foreground'">
                            1. Scan
                        </div>
                        <div class="rounded-2xl border p-3" :class="stepState === 'saving' || stepState === 'done' ? 'border-blue-200 bg-blue-50 text-blue-700' : 'bg-muted text-muted-foreground'">
                            2. Save
                        </div>
                        <div class="rounded-2xl border p-3" :class="stepState === 'done' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'bg-muted text-muted-foreground'">
                            3. Done
                        </div>
                    </div>

                    <section v-if="props.session" class="rounded-3xl border bg-background p-4">
                        <p class="text-xs font-bold uppercase tracking-wide text-muted-foreground">Training session</p>
                        <h2 class="mt-1 text-xl font-black">{{ props.session.title }}</h2>
                        <div class="mt-4 grid gap-2 text-sm">
                            <p><span class="text-muted-foreground">Date:</span> {{ props.session.date }}</p>
                            <p><span class="text-muted-foreground">Time:</span> {{ props.session.time }}</p>
                            <p><span class="text-muted-foreground">Location:</span> {{ props.session.location ?? props.session.branch ?? '-' }}</p>
                            <p><span class="text-muted-foreground">Group:</span> {{ props.session.group ?? '-' }}</p>
                        </div>
                    </section>

                    <section class="rounded-3xl border bg-background p-4">
                        <p class="text-xs font-bold uppercase tracking-wide text-muted-foreground">Athlete</p>
                        <p class="mt-1 text-lg font-black">{{ props.athlete?.name ?? 'Athlete login required' }}</p>
                        <p v-if="props.athlete?.current_status" class="mt-1 text-sm text-muted-foreground">Current status: {{ props.athlete.current_status }}</p>
                        <div class="mt-4 flex items-start gap-3 rounded-2xl bg-muted/70 p-3 text-xs text-muted-foreground">
                            <ShieldCheck class="mt-0.5 size-4 shrink-0" />
                            <p>No manual attendance button is shown here. Valid phone QR links are saved automatically after verification.</p>
                        </div>
                    </section>

                    <a href="/dashboard" class="rounded-2xl border px-4 py-3 text-center text-sm font-bold hover:bg-muted">
                        {{ hasSaved ? 'Done / Back to dashboard' : 'Back to dashboard' }}
                    </a>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
