<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import FormInputField from '@/components/forms/FormInputField.vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import InputError from '@/components/InputError.vue';
import ActionButtonsRow from '@/components/shared/ActionButtonsRow.vue';
import DataTable from '@/components/shared/DataTable.vue';
import FormModal from '@/components/shared/FormModal.vue';
import PageSection from '@/components/shared/PageSection.vue';
import { Button } from '@/components/ui/button';
import SessionAttendanceQrPanel from '@/features/attendance/components/SessionAttendanceQrPanel.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { routeId } from '@/lib/routeIds';
import type { BreadcrumbItem } from '@/types';
import type { SelectOption, TableBadgeCell, TableColumn, TableRow } from '@/types/resource-table';
import type { AttendanceStatusValue, AttendanceUpdateResponse } from './SessionAttendancePage.types';
import { dashboard } from '@/routes';
import { bulkUpdate as attendanceBulkUpdate, update as attendanceUpdate } from '@/routes/attendance';
import { attendance as sessionAttendance, index as sessionsIndex, update as sessionUpdate } from '@/routes/sessions';
import {
    destroy as sessionCoachAttendanceDestroy,
    store as sessionCoachAttendanceStore,
    update as sessionCoachAttendanceUpdate,
} from '@/routes/sessions/coach-attendance';

const props = defineProps<{
    branches: SelectOption[];
    groups: SelectOption[];
    session: {
        id: number;
        title: string;
        date: string;
        start_time?: string | null;
        end_time?: string | null;
        branch_id?: string | number | null;
        group_id?: string | number | null;
        location?: string | null;
        status?: string | null;
        branch: string;
        group: string;
        coach: string;
        is_private: boolean;
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
    { title: 'Dashboard', href: dashboard.url() },
    { title: 'Coach Sessions', href: sessionsIndex.url() },
    { title: 'Edit Session', href: sessionAttendance.url(props.session.id) },
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

const form = useForm({
    title: '',
    branch_id: '',
    group_id: '',
    location: '',
    session_date: '',
    start_time: '',
    end_time: '',
    status: 'DRAFT',
});

const attendanceRows = ref<TableRow[]>([...props.rows]);
const attendanceUpdateError = ref('');
const pendingAttendanceRowIds = ref<string[]>([]);
const pendingBulkStatus = ref<'PRESENT' | 'ABSENT' | null>(null);
const showSessionForm = ref(false);
const openQrPanel = ref(false);
const pendingCoachDeleteId = ref<string | null>(null);

watch(
    () => props.rows,
    (rows) => {
        attendanceRows.value = [...rows];
    },
);

function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

function xsrfToken(): string {
    const token = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

    return token ? decodeURIComponent(token) : '';
}

function csrfHeaders(): HeadersInit {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    const csrf = csrfToken();
    const xsrf = xsrfToken();

    if (csrf) headers['X-CSRF-TOKEN'] = csrf;
    if (xsrf) headers['X-XSRF-TOKEN'] = xsrf;

    return headers;
}

function fallbackAttendanceStatus(status: AttendanceStatusValue): TableBadgeCell {
    const map: Record<AttendanceStatusValue, TableBadgeCell> = {
        PRESENT: { kind: 'badge', text: 'Present', tone: 'success' },
        ABSENT: { kind: 'badge', text: 'Absent', tone: 'danger' },
        EXCUSED: { kind: 'badge', text: 'Excused', tone: 'warning' },
        LATE: { kind: 'badge', text: 'Late', tone: 'warning' },
    };

    return map[status];
}

function replaceAttendanceRow(rowId: string, row: TableRow) {
    attendanceRows.value = attendanceRows.value.map((currentRow) =>
        String(currentRow.id) === rowId ? row : currentRow,
    );
}

function applyFallbackAttendanceStatus(rowId: string, status: AttendanceStatusValue) {
    attendanceRows.value = attendanceRows.value.map((row) =>
        String(row.id) === rowId
            ? {
                  ...row,
                  status_value: status,
                  status: fallbackAttendanceStatus(status),
              }
            : row,
    );
}

function isAttendancePending(rowId: string): boolean {
    return pendingAttendanceRowIds.value.includes(rowId);
}

async function updateStatus(rowId: string, status: AttendanceStatusValue) {
    if (isAttendancePending(rowId)) return;

    const attendanceId = routeId(rowId);
    if (!attendanceId) {
        attendanceUpdateError.value = 'Invalid attendance row selected.';
        return;
    }
    attendanceUpdateError.value = '';
    pendingAttendanceRowIds.value = [...pendingAttendanceRowIds.value, rowId];

    try {
        const response = await fetch(attendanceUpdate.url(attendanceId), {
            method: 'PUT',
            headers: csrfHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify({ status }),
        });
        const payload = (await response.json().catch(() => ({}))) as AttendanceUpdateResponse;

        if (!response.ok) {
            throw new Error(payload.message ?? 'Attendance update failed.');
        }

        if (payload.row) replaceAttendanceRow(rowId, payload.row);
        else applyFallbackAttendanceStatus(rowId, status);
    } catch (error) {
        attendanceUpdateError.value = error instanceof Error ? error.message : 'Attendance update failed.';
    } finally {
        pendingAttendanceRowIds.value = pendingAttendanceRowIds.value.filter((id) => id !== rowId);
    }
}

function requestBulkUpdate(status: 'PRESENT' | 'ABSENT') {
    pendingBulkStatus.value = status;
}

function confirmBulkUpdate() {
    if (!pendingBulkStatus.value) return;
    const status = pendingBulkStatus.value;
    pendingBulkStatus.value = null;
    const attendanceIds = attendanceRows.value
        .map((row) => routeId(row.id))
        .filter((id): id is number => id !== null);
    router.post(attendanceBulkUpdate.url(), { attendance_ids: attendanceIds, status }, { preserveScroll: true });
}

function addCoach() {
    if (!props.session.is_private) return;

    coachForm.post(sessionCoachAttendanceStore.url(props.session.id), {
        preserveScroll: true,
        onSuccess: () => coachForm.reset(),
    });
}

function updateCoachStatus(rowId: string, status: 'TEACH' | 'NOT_TEACH') {
    const coachAttendanceId = routeId(rowId);
    if (!coachAttendanceId) return;
    router.put(sessionCoachAttendanceUpdate.url(coachAttendanceId), { status }, { preserveScroll: true });
}

function requestRemoveCoach(rowId: string) {
    pendingCoachDeleteId.value = rowId;
}

function confirmRemoveCoach() {
    if (!pendingCoachDeleteId.value) return;
    const coachAttendanceId = routeId(pendingCoachDeleteId.value);
    pendingCoachDeleteId.value = null;
    if (!coachAttendanceId) return;
    router.delete(sessionCoachAttendanceDestroy.url(coachAttendanceId), { preserveScroll: true });
}
function resetCoachForm() {
    coachForm.reset();
    coachForm.clearErrors();
}

function openEditSessionForm() {
    form.title = props.session.title;
    form.branch_id =
        props.session.branch_id === null || props.session.branch_id === undefined
            ? ''
            : String(props.session.branch_id);
    form.group_id =
        props.session.group_id === null || props.session.group_id === undefined ? '' : String(props.session.group_id);
    form.location = props.session.location ?? '';
    form.session_date = props.session.date;
    form.start_time = props.session.start_time ?? '';
    form.end_time = props.session.end_time ?? '';
    form.status = props.session.status ?? 'DRAFT';
    form.clearErrors();
    openQrPanel.value = false;
    showSessionForm.value = true;
}

function cancelForm() {
    form.reset();
    form.clearErrors();
    showSessionForm.value = false;
}

function openQrPanelForm() {
    showSessionForm.value = false;
    openQrPanel.value = true;
}

function submit() {
    form.put(sessionUpdate.url(props.session.id), {
        preserveScroll: true,
        onSuccess: () => {
            showSessionForm.value = false;
        },
    });
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
                        <Button type="button" variant="outline" @click="requestBulkUpdate('PRESENT')"
                            >Mark all present</Button
                        >
                        <Button type="button" variant="outline" @click="requestBulkUpdate('ABSENT')"
                            >Mark all absent</Button
                        >
                        <Button type="button" @click="openEditSessionForm">Edit session</Button>
                        <Button type="button" @click="openQrPanelForm">Show QR panel</Button>
                        <Button as-child variant="outline"><a :href="sessionsIndex.url()">Back to sessions</a></Button>
                    </div>
                </template>
            </PageSection>

            <PageSection
                title="Coach attendance table"
                :description="props.session.is_private ? 'Private group sessions can add a private coach from this area.' : 'Regular group sessions use the assigned class/session coach. Extra coach selection is only available for private groups.'"
            >
                <form
                    v-if="props.session.is_private"
                    class="mb-4 grid gap-2 md:grid-cols-[1fr_auto]"
                    @submit.prevent="addCoach"
                >
                    <div class="grid gap-2">
                        <FormSelectField
                            id="coach-picker"
                            v-model="coachForm.coach_id"
                            label="Add private coach"
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
                <p v-else class="mb-4 rounded-xl border border-dashed bg-muted/30 px-4 py-3 text-sm text-muted-foreground">
                    Coach selection is hidden because this session belongs to a regular group. Change the class to private if it needs a dedicated private coach.
                </p>

                <DataTable
                    title="Coach teaching status"
                    description="Use Teach / Not teach to control which coaches are counted for this session. Delete removes mistaken coach entries."
                    :columns="coachColumns"
                    :rows="props.coachRows"
                    empty-text="No coach attendance rows yet."
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
                            <Button
                                type="button"
                                size="sm"
                                variant="destructive"
                                @click="requestRemoveCoach(String(row.id))"
                                >Delete</Button
                            >
                        </ActionButtonsRow>
                    </template>
                </DataTable>
            </PageSection>

            <FormModal :open="openQrPanel" max-width-class="max-w-2xl" @close="openQrPanel = false">
                <SessionAttendanceQrPanel
                    :session-id="props.session.id"
                    :back-href="sessionAttendance.url(props.session.id)"
                    :qr="props.session.attendance_qr"
                    :session-date="props.session.date"
                    :session-start-time="props.session.start_time"
                    :session-end-time="props.session.end_time"
                />
            </FormModal>

            <DataTable
                title="Athlete attendance form"
                description="All athletes registered in this session group are preloaded as not attend by default. QR scans update the records automatically."
                :columns="columns"
                :rows="attendanceRows"
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
                            :disabled="isAttendancePending(String(row.id))"
                            @click="updateStatus(String(row.id), 'PRESENT')"
                            >Attend</Button
                        >
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            :disabled="isAttendancePending(String(row.id))"
                            @click="updateStatus(String(row.id), 'ABSENT')"
                            >Not attend</Button
                        >
                    </ActionButtonsRow>
                </template>
            </DataTable>
        </div>

        <FormModal
            :open="Boolean(attendanceUpdateError)"
            max-width-class="max-w-lg"
            @close="attendanceUpdateError = ''"
        >
            <PageSection title="Attendance update failed" :description="attendanceUpdateError">
                <div class="flex justify-end">
                    <Button type="button" variant="outline" @click="attendanceUpdateError = ''">Close</Button>
                </div>
            </PageSection>
        </FormModal>

        <FormModal :open="Boolean(pendingBulkStatus)" max-width-class="max-w-xl" @close="pendingBulkStatus = null">
            <PageSection
                v-if="pendingBulkStatus"
                title="Update all loaded athletes?"
                :description="`This will mark every loaded athlete as ${pendingBulkStatus === 'PRESENT' ? 'present' : 'absent'}. Existing statuses will be changed.`"
            >
                <div class="flex flex-wrap gap-3">
                    <Button type="button" variant="destructive" @click="confirmBulkUpdate">Apply update</Button>
                    <Button type="button" variant="outline" @click="pendingBulkStatus = null">Cancel</Button>
                </div>
            </PageSection>
        </FormModal>

        <FormModal
            :open="Boolean(pendingCoachDeleteId)"
            max-width-class="max-w-xl"
            @close="pendingCoachDeleteId = null"
        >
            <PageSection
                title="Remove this coach row?"
                description="This removes the coach from this session attendance table."
            >
                <div class="flex flex-wrap gap-3">
                    <Button type="button" variant="destructive" @click="confirmRemoveCoach">Remove coach</Button>
                    <Button type="button" variant="outline" @click="pendingCoachDeleteId = null">Cancel</Button>
                </div>
            </PageSection>
        </FormModal>

        <FormModal :open="showSessionForm" max-width-class="max-w-2xl" @close="cancelForm">
            <PageSection
                title="Edit training session"
                description="Update this session's date, time, branch, group, location, and status."
            >
                <form class="grid gap-4" @submit.prevent="submit">
                    <FormInputField
                        id="session-name"
                        v-model="form.title"
                        label="Session name"
                        placeholder="Junior sparring block"
                        :error="form.errors.title"
                    />
                    <div class="grid gap-4 md:grid-cols-2">
                        <FormSelectField
                            id="session-group"
                            v-model="form.group_id"
                            label="Group"
                            :options="props.groups"
                            placeholder="All groups in branch"
                            :error="form.errors.group_id"
                        />
                        <FormSelectField
                            id="session-branch"
                            v-model="form.branch_id"
                            label="Branch"
                            :options="props.branches"
                            :error="form.errors.branch_id"
                        />
                    </div>
                    <FormInputField
                        id="session-location"
                        v-model="form.location"
                        label="Location"
                        placeholder="Hall A"
                        :error="form.errors.location"
                    />
                    <div class="grid gap-4 md:grid-cols-3">
                        <FormInputField
                            id="session-date"
                            v-model="form.session_date"
                            label="Date"
                            type="date"
                            :error="form.errors.session_date"
                        />
                        <FormInputField
                            id="session-start"
                            v-model="form.start_time"
                            label="Start time"
                            type="time"
                            :error="form.errors.start_time"
                        />
                        <FormInputField
                            id="session-end"
                            v-model="form.end_time"
                            label="End time"
                            type="time"
                            :error="form.errors.end_time"
                        />
                    </div>
                    <FormSelectField
                        id="session-status"
                        v-model="form.status"
                        label="Status"
                        :options="[
                            { value: 'DRAFT', label: 'Draft' },
                            { value: 'CONFIRMED', label: 'Confirmed' },
                            { value: 'CANCELED', label: 'Canceled' },
                        ]"
                        :error="form.errors.status"
                    />
                    <div class="flex flex-wrap gap-3">
                        <Button type="submit" class="w-full sm:w-auto" :disabled="form.processing">{{
                            form.processing ? 'Saving...' : 'Save changes'
                        }}</Button>
                        <Button type="button" class="w-full sm:w-auto" variant="outline" @click="cancelForm"
                            >Cancel</Button
                        >
                    </div>
                </form>
            </PageSection>
        </FormModal>
    </AppLayout>
</template>
