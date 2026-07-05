<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    token: string;
    session: {
        id: number;
        title: string;
        date: string;
        start_time: string;
        end_time: string;
        branch: string;
        group: string;
        status: string;
        attendance_status: string;
        attendance_opens_at?: string | null;
        attendance_closes_at?: string | null;
    } | null;
    athlete: {
        athlete_id: string;
        name: string;
    } | null;
    currentStatus?: string | null;
    canSubmit: boolean;
    message?: string | null;
}>();

type AttendanceScanFlash = {
    flash?: {
        attendanceScan?: {
            status: string;
            message: string;
        };
    };
    errors?: Record<string, string>;
};

const page = usePage<AttendanceScanFlash>();
const form = useForm({});

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Attendance scan', href: `/attendance/scan/${props.token}` }];

const flashMessage = computed(() => page.props.flash?.attendanceScan?.message ?? null);
const flashStatus = computed(() => page.props.flash?.attendanceScan?.status ?? null);
const attendanceError = computed(() => page.props.errors?.attendance ?? null);
const isAlreadyRecorded = computed(() => flashStatus.value === 'already_recorded' || props.currentStatus === 'PRESENT');
const canCheckIn = computed(
    () => props.canSubmit && !isAlreadyRecorded.value && !attendanceError.value && !!props.session,
);

const statusCard = computed(() => {
    if (flashStatus.value === 'recorded') {
        return {
            eyebrow: 'Recorded',
            title: 'Attendance recorded',
            message: flashMessage.value ?? 'You are checked in for this session.',
            tone: 'success',
        };
    }

    if (isAlreadyRecorded.value) {
        return {
            eyebrow: 'Already recorded',
            title: 'You are already checked in',
            message: flashMessage.value ?? 'No further action is needed for this QR code.',
            tone: 'success',
        };
    }

    if (attendanceError.value) {
        return {
            eyebrow: 'Cannot check in',
            title: 'Attendance was not recorded',
            message: attendanceError.value,
            tone: 'error',
        };
    }

    if (!props.session) {
        return {
            eyebrow: 'Unavailable',
            title: 'This QR code cannot be used',
            message: props.message ?? 'This attendance QR code is invalid, expired, or has been closed.',
            tone: 'error',
        };
    }

    if (!props.athlete) {
        return {
            eyebrow: 'Login required',
            title: 'Use an athlete account',
            message: 'Log in as the athlete who is attending this session, then return to this page to check in.',
            tone: 'warning',
        };
    }

    return {
        eyebrow: 'Ready',
        title: 'Ready to check in',
        message:
            'Confirm once you are at the training location. This records attendance for your athlete account only.',
        tone: 'info',
    };
});

const statusClasses = computed(() => {
    switch (statusCard.value.tone) {
        case 'success':
            return 'border-green-500/40 bg-green-500/10 text-green-900 dark:text-green-100';
        case 'error':
            return 'border-destructive/40 bg-destructive/10 text-destructive';
        case 'warning':
            return 'border-amber-500/40 bg-amber-500/10 text-amber-900 dark:text-amber-100';
        default:
            return 'border-primary/30 bg-primary/10 text-primary';
    }
});

function submitAttendance() {
    form.post(`/attendance/scan/${props.token}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Attendance Check-in" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="mx-auto flex min-h-[calc(100vh-6rem)] w-full max-w-xl flex-1 flex-col justify-center gap-4 p-4 sm:p-6"
        >
            <section class="rounded-3xl border bg-card p-5 shadow-sm sm:p-6">
                <div class="space-y-2 text-center sm:text-left">
                    <p class="text-xs font-semibold tracking-[0.24em] text-muted-foreground uppercase">RF attendance</p>
                    <h1 class="text-2xl font-semibold tracking-tight">Attendance Check-in</h1>
                    <p class="text-sm text-muted-foreground">
                        Scan the QR with your phone camera, confirm the session, then tap check in.
                    </p>
                </div>
            </section>

            <section v-if="props.session" class="rounded-3xl border bg-card p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-4">
                    <div>
                        <p class="text-xs font-semibold text-muted-foreground uppercase">Session</p>
                        <h2 class="mt-1 text-xl font-semibold">{{ props.session.title }}</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ props.session.date }} · {{ props.session.start_time }} - {{ props.session.end_time }}
                        </p>
                    </div>
                    <dl class="grid gap-3 text-sm sm:grid-cols-2">
                        <div class="rounded-2xl bg-muted/60 p-3">
                            <dt class="text-muted-foreground">Branch</dt>
                            <dd class="font-medium">{{ props.session.branch }}</dd>
                        </div>
                        <div class="rounded-2xl bg-muted/60 p-3">
                            <dt class="text-muted-foreground">Group</dt>
                            <dd class="font-medium">{{ props.session.group || 'All groups' }}</dd>
                        </div>
                        <div class="rounded-2xl bg-muted/60 p-3">
                            <dt class="text-muted-foreground">Athlete</dt>
                            <dd class="font-medium">{{ props.athlete?.name ?? 'Athlete login required' }}</dd>
                        </div>
                        <div class="rounded-2xl bg-muted/60 p-3">
                            <dt class="text-muted-foreground">Window</dt>
                            <dd class="font-medium">
                                {{ props.session.attendance_opens_at ?? 'Session start' }} →
                                {{ props.session.attendance_closes_at ?? 'Session end' }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section class="rounded-3xl border p-5 shadow-sm sm:p-6" :class="statusClasses">
                <p class="text-xs font-semibold tracking-wide uppercase">{{ statusCard.eyebrow }}</p>
                <h2 class="mt-2 text-xl font-semibold">{{ statusCard.title }}</h2>
                <p class="mt-2 text-sm leading-6">{{ statusCard.message }}</p>
                <p v-if="props.currentStatus" class="mt-3 text-xs font-medium">
                    Current status: {{ props.currentStatus }}
                </p>
            </section>

            <section class="rounded-3xl border bg-card p-5 shadow-sm sm:p-6">
                <div class="grid gap-3">
                    <Button
                        type="button"
                        class="h-12 w-full text-base"
                        :disabled="!canCheckIn || form.processing"
                        @click="submitAttendance"
                    >
                        {{ form.processing ? 'Recording...' : 'Check in now' }}
                    </Button>
                    <Button
                        v-if="!props.athlete"
                        as-child
                        type="button"
                        variant="outline"
                        class="h-12 w-full text-base"
                    >
                        <a href="/login">Log in to continue</a>
                    </Button>
                    <Button as-child type="button" variant="secondary" class="h-11 w-full">
                        <a href="/dashboard">{{
                            flashStatus || attendanceError || !props.session
                                ? 'Done / Back to dashboard'
                                : 'Back to dashboard'
                        }}</a>
                    </Button>
                </div>
                <p class="mt-4 text-center text-xs text-muted-foreground">
                    If this is not your session or your QR is no longer valid, return to the dashboard and ask your
                    coach for help.
                </p>
            </section>
        </div>
    </AppLayout>
</template>
