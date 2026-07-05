<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import InputError from '@/components/InputError.vue';
import ActionButtonsRow from '@/components/shared/ActionButtonsRow.vue';
import DataTable from '@/components/shared/DataTable.vue';
import PageSection from '@/components/shared/PageSection.vue';
import { Button } from '@/components/ui/button';
import { appRoutes } from '@/data/routes';
import SessionAttendanceQrPanel from '@/features/attendance/components/SessionAttendanceQrPanel.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { SelectOption, TableColumn, TableRow } from '@/types/resource-table';

const props = defineProps<{
    session: {
        id: number;
        title: string;
        date: string;
        start_time?: string | null;
        end_time?: string | null;
        branch: string;
        group: string;
        coach: string;
        athlete_attendance_summary: string;
        coach_attendance_summary: string;
        attendance_qr: {
            is_active: boolean;
            opens_at?: string | null;
            closes_at?: string | null;
            generated_at?: string | null;
            revoked_at?: string | null;
        };
    };
    rows: TableRow[];
    coachRows: TableRow[];
    coachOptions: SelectOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: appRoutes.dashboard },
    { title: 'Coach Sessions', href: appRoutes.sessions },
    { title: 'Attendance Sheet', href: `/sessions/${props.session.id}/attendance` },
];

const columns: TableColumn[] = [
    { key: 'athlete', label: 'Athlete' },
    { key: 'status', label: 'Status' },
];
const coachColumns: TableColumn[] = [
    { key: 'coach', label: 'Coach' },
    { key: 'status', label: 'Status' },
    { key: 'checked_at', label: 'Updated At' },
];

const coachForm = useForm({
    coach_id: '',
});

const athleteProgress = computed(() => {
    const total = props.rows.length;
    const present = props.rows.filter((row) =>
        String((row.status as { text?: string })?.text ?? row.status)
            .toLowerCase()
            .includes('present'),
    ).length;
    const absent = props.rows.filter((row) =>
        String((row.status as { text?: string })?.text ?? row.status)
            .toLowerCase()
            .includes('absent'),
    ).length;

    return { total, present, absent };
});

const coachProgress = computed(() => {
    const total = props.coachRows.length;
    const teaching = props.coachRows.filter((row) =>
        String((row.status as { text?: string })?.text ?? row.status)
            .toLowerCase()
            .includes('teach'),
    ).length;

    return { total, teaching };
});

const qrStateLabel = computed(() => {
    if (props.session.attendance_qr.revoked_at) {
        return 'Closed';
    }

    return props.session.attendance_qr.is_active ? 'Active' : 'Not generated';
});

const qrStateClass = computed(() => {
    if (props.session.attendance_qr.is_active) {
        return 'border-green-500/40 bg-green-500/10 text-green-700';
    }

    if (props.session.attendance_qr.revoked_at) {
        return 'border-amber-500/40 bg-amber-500/10 text-amber-700';
    }

    return 'border-muted bg-muted text-muted-foreground';
});

function updateStatus(rowId: string, status: string) {
    const attendanceId = rowId.replace('ATT-', '');
    router.put(appRoutes.attendanceItem(attendanceId), { status }, { preserveScroll: true });
}

function bulkUpdate(status: 'PRESENT' | 'ABSENT') {
    const label = status === 'PRESENT' ? 'present' : 'absent';
    if (!window.confirm(`Mark all loaded athletes as ${label}? Existing statuses will be changed.`)) {
        return;
    }

    const attendanceIds = props.rows
        .map((row) => Number(String(row.id).replace('ATT-', '')))
        .filter((id) => !Number.isNaN(id));
    router.post(appRoutes.attendanceBulkUpdate, { attendance_ids: attendanceIds, status }, { preserveScroll: true });
}

function addCoach() {
    coachForm.post(appRoutes.sessionCoachAttendance(props.session.id), {
        preserveScroll: true,
        onSuccess: () => coachForm.reset(),
    });
}

function updateCoachStatus(rowId: string, status: 'TEACH' | 'NOT_TEACH') {
    const coachAttendanceId = rowId.replace('SCA-', '');
    router.put(appRoutes.sessionCoachAttendanceItem(coachAttendanceId), { status }, { preserveScroll: true });
}

function removeCoach(rowId: string) {
    if (!window.confirm('Remove this coach attendance row?')) {
        return;
    }

    const coachAttendanceId = rowId.replace('SCA-', '');
    router.delete(appRoutes.sessionCoachAttendanceItem(coachAttendanceId), { preserveScroll: true });
}
function resetCoachForm() {
    coachForm.reset();
    coachForm.clearErrors();
}
</script>

<template>
    <Head :title="`Session Attendance - ${props.session.title}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageSection
                eyebrow="Session attendance"
                :title="props.session.title"
                :description="`Follow the flow: confirm session details, generate QR, let athletes scan, then review or adjust attendance.`"
            >
                <div class="grid gap-3 md:grid-cols-4">
                    <div class="rounded-xl border bg-card p-4">
                        <p class="text-xs font-medium text-muted-foreground uppercase">When</p>
                        <p class="mt-1 font-semibold">{{ props.session.date }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ props.session.start_time ?? 'Start not set' }} -
                            {{ props.session.end_time ?? 'End not set' }}
                        </p>
                    </div>
                    <div class="rounded-xl border bg-card p-4">
                        <p class="text-xs font-medium text-muted-foreground uppercase">Group</p>
                        <p class="mt-1 font-semibold">{{ props.session.branch }}</p>
                        <p class="text-sm text-muted-foreground">{{ props.session.group || 'All groups in branch' }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ coachProgress.teaching }} / {{ coachProgress.total }} coaches teaching
                        </p>
                    </div>
                    <div class="rounded-xl border bg-card p-4">
                        <p class="text-xs font-medium text-muted-foreground uppercase">Athletes</p>
                        <p class="mt-1 font-semibold">
                            {{ athleteProgress.present }} / {{ athleteProgress.total }} present
                        </p>
                        <p class="text-sm text-muted-foreground">{{ athleteProgress.absent }} absent or pending</p>
                    </div>
                    <div class="rounded-xl border p-4" :class="qrStateClass">
                        <p class="text-xs font-medium uppercase">QR status</p>
                        <p class="mt-1 font-semibold">{{ qrStateLabel }}</p>
                        <p class="text-sm">
                            {{ props.session.attendance_qr.closes_at ?? 'Generate inside session time' }}
                        </p>
                    </div>
                </div>

                <div class="mt-4 rounded-xl border bg-muted/40 p-4 text-sm">
                    <p class="font-medium">Next steps</p>
                    <ol class="mt-2 grid gap-2 md:grid-cols-4">
                        <li>1. Confirm athletes and coaches.</li>
                        <li>2. Generate the QR window.</li>
                        <li>3. Let athletes scan by phone camera.</li>
                        <li>4. Review exceptions and save manual changes.</li>
                    </ol>
                </div>

                <template #actions>
                    <div class="flex flex-wrap gap-2">
                        <Button type="button" variant="outline" @click="bulkUpdate('PRESENT')">Mark all present</Button>
                        <Button type="button" variant="outline" @click="bulkUpdate('ABSENT')">Mark all absent</Button>
                        <Button as-child variant="outline">
                            <a href="/sessions">Back to sessions</a>
                        </Button>
                    </div>
                </template>
            </PageSection>

            <SessionAttendanceQrPanel
                :session-id="props.session.id"
                :back-href="`/sessions/${props.session.id}/attendance`"
                :qr="props.session.attendance_qr"
                :session-date="props.session.date"
                :session-start-time="props.session.start_time"
                :session-end-time="props.session.end_time"
            />

            <PageSection
                title="Coach attendance table"
                description="Add coaches to this session and mark whether they teach or not."
            >
                <form class="mb-4 grid gap-2 md:grid-cols-[1fr_auto]" @submit.prevent="addCoach">
                    <div class="grid gap-2">
                        <FormSelectField
                            id="coach-picker"
                            v-model="coachForm.coach_id"
                            label="Add coach"
                            :options="props.coachOptions"
                            placeholder="Select coach"
                        />
                        <InputError :message="coachForm.errors.coach_id" />
                    </div>
                    <div class="flex items-end gap-2">
                        <Button type="submit" :disabled="coachForm.processing">Add coach</Button>
                        <Button type="button" variant="outline" @click="resetCoachForm">Cancel</Button>
                    </div>
                </form>

                <DataTable
                    title="Coach teaching status"
                    description="Use only Teach / Not teach. Delete removes mistaken coach entry."
                    :columns="coachColumns"
                    :rows="props.coachRows"
                    empty-text="No coach attendance rows yet. Add a coach above if another coach helped with this session."
                    searchable
                    search-placeholder="Search coach..."
                    action-label="Actions"
                >
                    <template #row-actions="{ row }">
                        <ActionButtonsRow>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                @click="updateCoachStatus(String(row.id), 'TEACH')"
                                >Teach</Button
                            >
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                @click="updateCoachStatus(String(row.id), 'NOT_TEACH')"
                                >Not teach</Button
                            >
                            <Button type="button" size="sm" variant="destructive" @click="removeCoach(String(row.id))"
                                >Delete</Button
                            >
                        </ActionButtonsRow>
                    </template>
                </DataTable>
            </PageSection>

            <DataTable
                title="Athlete attendance form"
                description="All athletes registered in this session group are preloaded as not attend by default."
                :columns="columns"
                :rows="props.rows"
                empty-text="No eligible athletes were found for this session. Check the branch/group assignment or go back to sessions."
                searchable
                search-placeholder="Search athlete..."
                action-label="Actions"
            >
                <template #row-actions="{ row }">
                    <ActionButtonsRow>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="updateStatus(String(row.id), 'PRESENT')"
                            >Attend</Button
                        >
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="updateStatus(String(row.id), 'ABSENT')"
                            >Not attend</Button
                        >
                    </ActionButtonsRow>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
