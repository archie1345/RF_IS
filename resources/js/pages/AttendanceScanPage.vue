<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { CheckCircle2, Loader2, QrCode, Smartphone, XCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { show as attendanceScanShow } from '@/routes/attendance/scan';
import type { BreadcrumbItem } from '@/types';
import type { PagePropsWithAttendanceScan } from './AttendanceScanPage.types';

type ChildOption = {
    value: string | number;
    label: string;
    current_status?: string | null;
};

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
        athlete_id?: string | number;
        name: string | null;
        current_status: string | null;
    } | null;
    children?: ChildOption[];
}>();

const page = usePage<PagePropsWithAttendanceScan>();
const isSubmitting = ref(false);
const submitError = ref<string | null>(null);
const selectedAthleteId = ref(String(props.athlete?.athlete_id ?? props.children?.[0]?.value ?? ''));

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard.url() },
    { title: 'QR Attendance', href: attendanceScanShow.url(props.token) },
];

const childOptions = computed(() => props.children ?? []);
const isParentScan = computed(() => childOptions.value.length > 0);
const selectedChild = computed(
    () => childOptions.value.find((child) => String(child.value) === selectedAthleteId.value) ?? null,
);
const displayedAthleteName = computed(
    () => selectedChild.value?.label ?? props.athlete?.name ?? 'Athlete login required',
);
const displayedAthleteStatus = computed(
    () => selectedChild.value?.current_status ?? props.athlete?.current_status ?? null,
);
const scanFlash = computed(() => page.props.flash?.attendanceScan ?? null);
const pageErrors = computed(() => page.props.errors ?? {});
const selectedChildAlreadyPresent = computed(
    () => isParentScan.value && selectedChild.value?.current_status === 'PRESENT',
);
const hasSaved = computed(
    () =>
        selectedChildAlreadyPresent.value ||
        (!isParentScan.value && props.state === 'already_present') ||
        scanFlash.value?.status === 'recorded' ||
        scanFlash.value?.status === 'already_recorded',
);
const blockingError = computed(
    () =>
        submitError.value ??
        pageErrors.value.attendance ??
        pageErrors.value.athlete_id ??
        pageErrors.value.device ??
        pageErrors.value.token ??
        null,
);
const parentSessionBlocked = computed(() => ['invalid', 'not_open', 'closed', 'revoked'].includes(props.state));
const parentSelectionBlocked = computed(() => isParentScan.value && selectedAthleteId.value === '');
const canRecordNow = computed(() => {
    if (!props.deviceAllowed || isSubmitting.value || hasSaved.value || blockingError.value) return false;

    if (isParentScan.value) {
        return Boolean(props.session) && !parentSessionBlocked.value && !parentSelectionBlocked.value;
    }

    return props.canSubmit;
});

const stepState = computed(() => {
    if (!props.deviceAllowed) return 'blocked';
    if (
        blockingError.value ||
        parentSessionBlocked.value ||
        (!isParentScan.value &&
            ['invalid', 'not_open', 'closed', 'revoked', 'athlete_required', 'not_eligible'].includes(props.state))
    ) {
        return 'blocked';
    }
    if (hasSaved.value) return 'done';
    if (isSubmitting.value) return 'saving';
    if (canRecordNow.value || (isParentScan.value && !parentSessionBlocked.value)) return 'prompt';

    return 'waiting';
});

const headline = computed(() => {
    if (stepState.value === 'done') return isParentScan.value ? 'Child attendance saved' : 'Attendance saved';
    if (stepState.value === 'saving') return 'Saving attendance...';
    if (stepState.value === 'prompt') return isParentScan.value ? 'Select child and save' : 'QR detected';
    if (stepState.value === 'blocked') return 'Cannot record attendance';

    return 'QR attendance';
});

const statusMessage = computed(() => {
    if (selectedChildAlreadyPresent.value) return 'This child is already checked in for this session.';
    if (isParentScan.value && parentSelectionBlocked.value) return 'Select which child you want to check in.';

    return (
        scanFlash.value?.message ??
        blockingError.value ??
        props.message ??
        'Hold on while we verify your QR attendance.'
    );
});

function recordAttendance() {
    if (!canRecordNow.value) {
        submitError.value = statusMessage.value ?? 'Attendance cannot be saved from this QR yet.';
        return;
    }

    isSubmitting.value = true;
    submitError.value = null;

    router.post(
        attendanceScanShow.url(props.token),
        isParentScan.value ? { athlete_id: selectedAthleteId.value } : {},
        {
            preserveScroll: true,
            onError: (errors) => {
                submitError.value = Object.values(errors)[0] ?? 'Unable to save attendance from this QR.';
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
}
</script>

<template>
    <Head title="QR Attendance" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex min-h-[72vh] w-full max-w-md flex-col justify-center gap-4 p-4">
            <section class="overflow-hidden rounded-[2rem] border bg-card shadow-sm">
                <div
                    class="bg-gradient-to-br from-red-50 via-background to-blue-50 p-5 dark:from-red-950/30 dark:via-background dark:to-blue-950/30"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-black tracking-[0.24em] text-brand-coral uppercase">RF Attendance</p>
                            <h1 class="mt-2 text-3xl font-black tracking-tight">{{ headline }}</h1>
                        </div>
                        <div class="rounded-3xl bg-background/80 p-4 shadow-sm ring-1 ring-border">
                            <QrCode v-if="stepState === 'prompt'" class="size-8 text-brand-blue" />
                            <Loader2 v-else-if="stepState === 'saving'" class="size-8 animate-spin text-brand-blue" />
                            <CheckCircle2 v-else-if="stepState === 'done'" class="size-8 text-brand-lime" />
                            <XCircle v-else-if="stepState === 'blocked'" class="size-8 text-brand-coral" />
                            <Smartphone v-else class="size-8 text-muted-foreground" />
                        </div>
                    </div>

                    <p class="mt-3 text-sm text-muted-foreground">{{ statusMessage }}</p>
                </div>

                <div class="grid gap-3 p-5">
                    <div class="grid grid-cols-3 gap-2 text-center text-xs font-semibold">
                        <div
                            class="rounded-2xl border p-3"
                            :class="
                                stepState !== 'blocked'
                                    ? 'border-brand-blue/30 bg-brand-blue/10 text-brand-blue'
                                    : 'bg-muted text-muted-foreground'
                            "
                        >
                            1. Scan
                        </div>
                        <div
                            class="rounded-2xl border p-3"
                            :class="
                                stepState === 'saving' || stepState === 'done'
                                    ? 'border-brand-blue/30 bg-brand-blue/10 text-brand-blue'
                                    : 'bg-muted text-muted-foreground'
                            "
                        >
                            2. Save
                        </div>
                        <div
                            class="rounded-2xl border p-3"
                            :class="
                                stepState === 'done'
                                    ? 'border-brand-lime/30 bg-brand-lime/10 text-brand-lime'
                                    : 'bg-muted text-muted-foreground'
                            "
                        >
                            3. Done
                        </div>
                    </div>

                    <section v-if="isParentScan" class="rounded-3xl border bg-background p-4">
                        <FormSelectField
                            id="child-athlete"
                            v-model="selectedAthleteId"
                            label="Child to check in"
                            :options="childOptions"
                            placeholder="Select child"
                            :multiple="false"
                        />
                    </section>

                    <Button
                        v-if="canRecordNow"
                        type="button"
                        class="rounded-2xl"
                        :disabled="isSubmitting"
                        @click="recordAttendance"
                    >
                        <Loader2 v-if="isSubmitting" class="mr-2 size-4 animate-spin" />
                        {{
                            isSubmitting
                                ? 'Saving attendance...'
                                : isParentScan
                                  ? 'Save child attendance now'
                                  : 'Save attendance now'
                        }}
                    </Button>

                    <section v-if="props.session" class="rounded-3xl border bg-background p-4">
                        <p class="text-xs font-bold tracking-wide text-muted-foreground uppercase">Training session</p>
                        <h2 class="mt-1 text-xl font-black">{{ props.session.title }}</h2>
                        <div class="mt-4 grid gap-2 text-sm">
                            <p><span class="text-muted-foreground">Date:</span> {{ props.session.date }}</p>
                            <p><span class="text-muted-foreground">Time:</span> {{ props.session.time }}</p>
                            <p>
                                <span class="text-muted-foreground">Location:</span>
                                {{ props.session.location ?? props.session.branch ?? '-' }}
                            </p>
                            <p><span class="text-muted-foreground">Group:</span> {{ props.session.group ?? '-' }}</p>
                        </div>
                    </section>

                    <section class="rounded-3xl border bg-background p-4">
                        <p class="text-xs font-bold tracking-wide text-muted-foreground uppercase">
                            {{ isParentScan ? 'Selected child' : 'Athlete' }}
                        </p>
                        <p class="mt-1 text-lg font-black">{{ displayedAthleteName }}</p>
                        <p v-if="displayedAthleteStatus" class="mt-1 text-sm text-muted-foreground">
                            Current status: {{ displayedAthleteStatus }}
                        </p>
                    </section>

                    <a
                        :href="dashboard.url()"
                        class="rounded-2xl border px-4 py-3 text-center text-sm font-bold hover:bg-muted"
                    >
                        {{ hasSaved ? 'Done / Back to dashboard' : 'Back to dashboard' }}
                    </a>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
