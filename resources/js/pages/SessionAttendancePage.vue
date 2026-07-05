<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
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
    { title: 'Edit Session', href: `/sessions/${props.session.id}/attendance` },
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
    <Head :title="`Edit Session - ${props.session.title}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageSection
                eyebrow="Unified session edit"
                :title="props.session.title"
                :description="`${props.session.date} · ${props.session.start_time ?? 'Start not set'} - ${props.session.end_time ?? 'End not set'} · ${props.session.branch} · ${props.session.group || 'All groups'}`"
            >
                <template #actions>
                    <div class="flex flex-wrap gap-2">
                        <Button as-child variant="outline">
                            <a href="/sessions">Back to sessions</a>
                        </Button>
                    </div>
                </template>
            </PageSection>

            <PageSection
                title="Coach attendance table"
                description="Add or update every coach assigned to this session. Use this area when the coach or session creator needs to modify who teaches."
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
                    description="Use Teach / Not teach to control which coaches are counted for this session. Delete removes mistaken coach entries."
                    :columns="coachColumns"
                    :rows="props.coachRows"
                    empty-text="No coach attendance rows yet. Add a coach above if another coach helped with this session."
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

            <SessionAttendanceQrPanel
                :session-id="props.session.id"
                :back-href="`/sessions/${props.session.id}/attendance`"
                :qr="props.session.attendance_qr"
                :session-date="props.session.date"
                :session-start-time="props.session.start_time"
                :session-end-time="props.session.end_time"
            />

            <DataTable
                title="Athlete attendance form"
                description="All athletes registered in this session group are preloaded as not attend by default. QR scans update the records automatically."
                :columns="columns"
                :rows="props.rows"
                empty-text="No eligible athletes were found for this session. Check the branch/group assignment or go back to sessions."
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
