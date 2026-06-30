<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import PageSection from '@/components/shared/PageSection.vue';
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

function submitAttendance() {
    form.post(`/attendance/scan/${props.token}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="QR Attendance Scan" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageSection
                title="Confirm QR attendance"
                description="Review the session details before recording your own attendance. Parents cannot check in for a child."
            >
                <div v-if="props.session" class="grid gap-3 rounded-lg border p-4 text-sm md:grid-cols-2">
                    <div><strong>Session:</strong> {{ props.session.title }}</div>
                    <div><strong>Date:</strong> {{ props.session.date }}</div>
                    <div><strong>Time:</strong> {{ props.session.start_time }} - {{ props.session.end_time }}</div>
                    <div><strong>Branch:</strong> {{ props.session.branch }}</div>
                    <div><strong>Group:</strong> {{ props.session.group }}</div>
                    <div><strong>Athlete:</strong> {{ props.athlete?.name ?? 'Athlete account required' }}</div>
                    <div><strong>Opens:</strong> {{ props.session.attendance_opens_at ?? 'Now' }}</div>
                    <div><strong>Closes:</strong> {{ props.session.attendance_closes_at ?? 'Session close' }}</div>
                    <div><strong>Current status:</strong> {{ props.currentStatus ?? 'Not recorded' }}</div>
                </div>

                <div v-else class="rounded-lg border border-destructive/40 bg-destructive/5 p-4 text-sm text-destructive">
                    {{ props.message ?? 'This QR attendance code is invalid.' }}
                </div>

                <div v-if="page.props.flash?.attendanceScan" class="rounded-lg border border-green-500/40 bg-green-500/5 p-4 text-sm text-green-700">
                    {{ page.props.flash.attendanceScan.message }}
                </div>

                <div v-if="page.props.errors?.attendance" class="rounded-lg border border-destructive/40 bg-destructive/5 p-4 text-sm text-destructive">
                    {{ page.props.errors.attendance }}
                </div>

                <template #actions>
                    <Button type="button" :disabled="!props.canSubmit || form.processing" @click="submitAttendance">
                        Confirm attendance
                    </Button>
                </template>
            </PageSection>
        </div>
    </AppLayout>
</template>
