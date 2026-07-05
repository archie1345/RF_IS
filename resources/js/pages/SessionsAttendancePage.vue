<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import InputError from '@/components/InputError.vue';
import ActionButtonsRow from '@/components/shared/ActionButtonsRow.vue';
import DataTable from '@/components/shared/DataTable.vue';
import PageSection from '@/components/shared/PageSection.vue';
import { Button } from '@/components/ui/button';
import { managementRoutes } from '@/data/management';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { SelectOption, TableColumn, TableRow } from '@/types/management';

const props = defineProps<{
    session: {
        id: number;
        title: string;
        date: string;
        time?: string | null;
        location?: string | null;
        branch: string;
        group: string;
        coach: string;
        athlete_attendance_summary: string;
        coach_attendance_summary: string;
    };
    qr: {
        is_active: boolean;
        scan_url: string | null;
        opens_at: string;
        closes_at: string;
        generated_at: string | null;
        revoked_at: string | null;
        session_start: string;
        session_end: string;
    };
    rows: TableRow[];
    coachRows: TableRow[];
    coachOptions: SelectOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: managementRoutes.dashboard },
    { title: 'Coach Sessions', href: managementRoutes.sessions },
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

const qrForm = useForm({
    opens_at: props.qr.opens_at,
    closes_at: props.qr.closes_at,
});

function updateStatus(rowId: string, status: string) {
    const attendanceId = rowId.replace('ATT-', '');
    router.put(`/attendance/${attendanceId}`, { status }, { preserveScroll: true });
}

function bulkUpdate(status: 'PRESENT' | 'ABSENT') {
    const attendanceIds = props.rows.map((row) => Number(String(row.id).replace('ATT-', ''))).filter((id) => ! Number.isNaN(id));
    router.post('/attendance/bulk-update', { attendance_ids: attendanceIds, status }, { preserveScroll: true });
}

function generateQr() {
    qrForm.post(`/sessions/${props.session.id}/attendance-qr`, {
        preserveScroll: true,
    });
}

function revokeQr() {
    if (!confirm('Revoke this QR? Athletes will no longer be able to check in with the current code.')) {
        return;
    }

    router.delete(`/sessions/${props.session.id}/attendance-qr`, { preserveScroll: true });
}

function addCoach() {
    coachForm.post(`/sessions/${props.session.id}/coach-attendance`, {
        preserveScroll: true,
        onSuccess: () => coachForm.reset(),
    });
}

function updateCoachStatus(rowId: string, status: 'TEACH' | 'NOT_TEACH') {
    const coachAttendanceId = rowId.replace('SCA-', '');
    router.put(`/sessions/coach-attendance/${coachAttendanceId}`, { status }, { preserveScroll: true });
}

function removeCoach(rowId: string) {
    const coachAttendanceId = rowId.replace('SCA-', '');
    router.delete(`/sessions/coach-attendance/${coachAttendanceId}`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Session Attendance - ${props.session.title}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageSection
                eyebrow="Session attendance"
                :title="props.session.title"
                :description="`Date: ${props.session.date} | Time: ${props.session.time ?? '-'} | Location: ${props.session.location ?? '-'} | Branch: ${props.session.branch} | Group: ${props.session.group} | Coaches: ${props.session.coach} | Athlete attendance: ${props.session.athlete_attendance_summary} | Coach attendance: ${props.session.coach_attendance_summary}`"
            >
                <template #actions>
                    <div class="flex flex-wrap gap-2">
                        <Button type="button" variant="outline" @click="bulkUpdate('PRESENT')">All attend</Button>
                        <Button type="button" variant="outline" @click="bulkUpdate('ABSENT')">All not attend</Button>
                        <Button as-child variant="outline">
                            <a href="/sessions">Back to sessions</a>
                        </Button>
                    </div>
                </template>
            </PageSection>

            <PageSection title="Phone QR attendance" description="Generate an attendance link for athletes to open only on a phone. Laptops are for coaches/admins to manage manual attendance.">
                <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
                    <form class="grid gap-4 rounded-xl border p-4" @submit.prevent="generateQr">
                        <div class="grid gap-1 text-sm text-muted-foreground">
                            <p>Session window: {{ props.qr.session_start.replace('T', ' ') }} to {{ props.qr.session_end.replace('T', ' ') }}</p>
                            <p>Set the QR window inside the session time. Athletes must open the generated link from a phone.</p>
                        </div>

                        <label class="grid gap-2 text-sm font-medium">
                            QR opens at
                            <input v-model="qrForm.opens_at" class="rounded-md border bg-background px-3 py-2" type="datetime-local" />
                            <InputError :message="qrForm.errors.opens_at" />
                        </label>

                        <label class="grid gap-2 text-sm font-medium">
                            QR closes at
                            <input v-model="qrForm.closes_at" class="rounded-md border bg-background px-3 py-2" type="datetime-local" />
                            <InputError :message="qrForm.errors.closes_at" />
                        </label>

                        <div class="flex flex-wrap gap-2">
                            <Button type="submit" :disabled="qrForm.processing">{{ props.qr.is_active ? 'Regenerate phone link' : 'Generate phone link' }}</Button>
                            <Button type="button" variant="outline" @click="qrForm.reset()">Reset window</Button>
                            <Button v-if="props.qr.is_active" type="button" variant="destructive" @click="revokeQr">Revoke</Button>
                        </div>
                    </form>

                    <div class="rounded-xl border p-4 text-center">
                        <div v-if="props.qr.is_active && props.qr.scan_url" class="grid gap-3">
                            <p class="text-sm font-semibold">Active phone-only attendance link</p>
                            <p class="rounded-lg border bg-muted p-3 text-xs text-muted-foreground">Show this link as a QR using your phone/browser QR tool, or copy it into a QR generator. The backend will reject desktop/laptop check-ins.</p>
                            <p class="break-all rounded-md bg-muted p-2 text-xs">{{ props.qr.scan_url }}</p>
                            <Button as-child size="sm" variant="outline">
                                <a :href="props.qr.scan_url" target="_blank">Open test page</a>
                            </Button>
                            <p class="text-xs text-muted-foreground">Generated: {{ props.qr.generated_at ?? '-' }}</p>
                        </div>
                        <div v-else class="grid gap-2 text-sm text-muted-foreground">
                            <p class="font-semibold text-foreground">No active QR link</p>
                            <p>Generate a phone-only attendance link before athletes check in.</p>
                            <p v-if="props.qr.revoked_at">Last revoked: {{ props.qr.revoked_at }}</p>
                        </div>
                    </div>
                </div>
            </PageSection>

            <PageSection title="Coach attendance table" description="Add coaches to this session and mark whether they teach or not.">
                <form class="mb-4 grid gap-2 md:grid-cols-[1fr_auto]" @submit.prevent="addCoach">
                    <div class="grid gap-2">
                        <FormSelectField id="coach-picker" v-model="coachForm.coach_id" label="Add coach" :options="props.coachOptions" placeholder="Select coach" />
                        <InputError :message="coachForm.errors.coach_id" />
                    </div>
                    <div class="flex items-end">
                        <Button type="submit" :disabled="coachForm.processing">Add coach</Button>
                    </div>
                </form>

                <DataTable
                    title="Coach teaching status"
                    description="Use only Teach / Not teach. Delete removes mistaken coach entry."
                    :columns="coachColumns"
                    :rows="props.coachRows"
                    searchable
                    search-placeholder="Search coach..."
                    action-label="Actions"
                >
                    <template #row-actions="{ row }">
                        <ActionButtonsRow>
                            <Button type="button" size="sm" variant="outline" @click="updateCoachStatus(String(row.id), 'TEACH')">Teach</Button>
                            <Button type="button" size="sm" variant="outline" @click="updateCoachStatus(String(row.id), 'NOT_TEACH')">Not teach</Button>
                            <Button type="button" size="sm" variant="destructive" @click="removeCoach(String(row.id))">Delete</Button>
                        </ActionButtonsRow>
                    </template>
                </DataTable>
            </PageSection>

            <DataTable
                title="Athlete attendance form"
                description="Use this laptop/tablet table for manual attendance or exceptions after phone check-in."
                :columns="columns"
                :rows="props.rows"
                searchable
                search-placeholder="Search athlete..."
                action-label="Actions"
            >
                <template #row-actions="{ row }">
                    <ActionButtonsRow>
                        <Button type="button" size="sm" variant="outline" @click="updateStatus(String(row.id), 'PRESENT')">Attend</Button>
                        <Button type="button" size="sm" variant="outline" @click="updateStatus(String(row.id), 'ABSENT')">Not attend</Button>
                    </ActionButtonsRow>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
