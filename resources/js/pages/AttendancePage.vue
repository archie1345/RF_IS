<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref, toRef, watch } from 'vue';
import AthleteQrScanner from '@/components/attendance/AthleteQrScanner.vue';
import FormInputField from '@/components/forms/FormInputField.vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import ActionButtonsRow from '@/components/shared/ActionButtonsRow.vue';
import AppAlert from '@/components/shared/AppAlert.vue';
import DataTable from '@/components/shared/DataTable.vue';
import FormModal from '@/components/shared/FormModal.vue';
import PageSection from '@/components/shared/PageSection.vue';
import StatCard from '@/components/shared/StatCard.vue';
import { Button } from '@/components/ui/button';
import { useRole } from '@/composables/useRole';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { AppRole, AttendanceRow } from '@/types/domain';
import type { Metric, SelectOption, TableBadgeCell, TableColumn, TableRow } from '@/types/resource-table';
import type { AttendanceStatusValue, AttendanceUpdateResponse } from './AttendancePage.types';
import { dashboard } from '@/routes';
import { index as attendanceIndex, store as attendanceStore, update as attendanceUpdate } from '@/routes/attendance';
import { store as sessionsStore } from '@/routes/sessions';

const props = defineProps<{
    metrics: Metric[];
    rows: AttendanceRow[];
    athletes: SelectOption[];
    sessions: (SelectOption & { href?: string; date?: string; title?: string })[];
    branches: SelectOption[];
    groups: SelectOption[];
    role: AppRole;
    activeAthleteId: string | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard.url() },
    { title: 'Attendance', href: attendanceIndex.url() },
];

const columns: TableColumn[] = [
    { key: 'athlete', label: 'Athlete' },
    { key: 'session', label: 'Session' },
    { key: 'coach', label: 'Coach' },
    { key: 'checkin', label: 'Check-in', align: 'right' },
    { key: 'status', label: 'Status' },
];

const form = useForm({
    athlete_id: props.activeAthleteId ? String(props.activeAthleteId) : '',
    training_session_id: '',
    date: '',
    status: 'PRESENT',
    checked_in_time: '',
    notes: '',
    follow_up_owner: '',
});

const sessionForm = useForm({
    title: '',
    branch_id: '',
    group_id: '',
    location: '',
    session_date: '',
    start_time: '',
    end_time: '',
    status: 'DRAFT',
});

const attendanceRows = ref<TableRow[]>(props.rows.map((row) => ({ ...row })));
const pendingAttendanceRowIds = ref<string[]>([]);
const attendanceUpdateError = ref('');
const coachSessionName = ref('');
const coachSessionError = ref('');
const showSessionForm = ref(false);
const showParentCheckInForm = ref(false);

const { isAdmin, isCoach, isParent, isAthlete } = useRole(toRef(props, 'role'));
const roleTitle = computed(() => {
    if (isAdmin.value) return 'Admin attendance management';
    if (isCoach.value) return 'Coach attendance management';
    if (isParent.value) return 'Children attendance tracking';
    if (isAthlete.value) return 'Athlete QR attendance';
    return 'Attendance tracking';
});
const childSessionOptions = computed(() => props.sessions.map((session) => ({ value: session.value, label: session.label })));

watch(
    () => props.rows,
    (rows) => {
        attendanceRows.value = rows.map((row) => ({ ...row }));
    },
);

function todayDate() {
    const date = new Date();
    date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
    return date.toISOString().slice(0, 10);
}

function currentTime() {
    const date = new Date();
    return `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
}

function routeNumber(value: string): number | null {
    const parsed = Number(value.replace('ATT-', ''));
    return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
}

function rowStatusText(row: AttendanceRow | TableRow) {
    const status = row.status;
    return typeof status === 'object' && status !== null && 'text' in status ? status.text : String(status ?? '');
}

function canUpdateRow(row: AttendanceRow | TableRow) {
    return Boolean(row.can_update);
}

function csrfHeaders(): HeadersInit {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
    const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
    const xsrf = document.cookie.split('; ').find((cookie) => cookie.startsWith('XSRF-TOKEN='))?.split('=')[1];
    if (csrf) headers['X-CSRF-TOKEN'] = csrf;
    if (xsrf) headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrf);
    return headers;
}

function fallbackAttendanceStatus(status: AttendanceStatusValue): TableBadgeCell {
    const map: Record<AttendanceStatusValue, TableBadgeCell> = {
        PRESENT: { kind: 'badge', text: 'Present', tone: 'success' },
        ABSENT: { kind: 'badge', text: 'Absent', tone: 'danger' },
        EXCUSED: { kind: 'badge', text: 'Excused', tone: 'warning' },
    };

    return map[status];
}

function isAttendancePending(rowId: string): boolean {
    return pendingAttendanceRowIds.value.includes(rowId);
}

function replaceAttendanceRow(rowId: string, row: TableRow) {
    attendanceRows.value = attendanceRows.value.map((currentRow) => (String(currentRow.id) === rowId ? row : currentRow));
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

async function setAttendanceStatus(id: string, status: AttendanceStatusValue) {
    if (isAttendancePending(id)) return;

    const attendanceId = routeNumber(id);
    if (!attendanceId) {
        attendanceUpdateError.value = 'Invalid attendance row selected.';
        return;
    }

    attendanceUpdateError.value = '';
    pendingAttendanceRowIds.value = [...pendingAttendanceRowIds.value, id];

    try {
        const response = await fetch(attendanceUpdate.url(attendanceId), {
            method: 'PUT',
            headers: csrfHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify({ status }),
        });
        const payload = (await response.json().catch(() => ({}))) as AttendanceUpdateResponse;

        if (!response.ok) throw new Error(payload.message ?? 'Attendance update failed.');

        if (payload.row) replaceAttendanceRow(id, payload.row);
        else applyFallbackAttendanceStatus(id, status);
    } catch (error) {
        attendanceUpdateError.value = error instanceof Error ? error.message : 'Attendance update failed.';
    } finally {
        pendingAttendanceRowIds.value = pendingAttendanceRowIds.value.filter((rowId) => rowId !== id);
    }
}

function openSessionAttendance(href?: string) {
    if (href) router.visit(href);
}

function openSessionFromCoachInput() {
    const query = coachSessionName.value.trim().toLowerCase();
    if (!query) {
        coachSessionError.value = 'Session name is required.';
        return;
    }

    const matchedSession =
        props.sessions.find((session) => session.title?.toLowerCase() === query) ??
        props.sessions.find((session) => session.title?.toLowerCase().includes(query)) ??
        props.sessions.find((session) => session.label.toLowerCase().includes(query));

    if (!matchedSession?.href) {
        coachSessionError.value = 'Session not found. Try the full session name.';
        return;
    }

    coachSessionError.value = '';
    openSessionAttendance(matchedSession.href);
}

function applySessionDate(sessionValue: string) {
    form.training_session_id = sessionValue;
    const selected = props.sessions.find((session) => String(session.value) === sessionValue);
    form.date = selected?.date ?? todayDate();
}

function openParentCheckIn() {
    form.reset();
    form.status = 'PRESENT';
    form.athlete_id = props.athletes.length === 1 ? String(props.athletes[0].value) : '';
    form.training_session_id = props.sessions.length === 1 ? String(props.sessions[0].value) : '';
    form.date = todayDate();
    form.checked_in_time = currentTime();
    form.notes = 'Checked in by parent.';
    if (form.training_session_id) applySessionDate(form.training_session_id);
    form.clearErrors();
    showParentCheckInForm.value = true;
}

function submitAttendance() {
    form.post(attendanceStore.url(), {
        preserveScroll: true,
        onSuccess: () => {
            showParentCheckInForm.value = false;
            form.reset();
        },
    });
}

function submitSession() {
    sessionForm.post(sessionsStore.url(), {
        onSuccess: () => {
            sessionForm.reset();
            showSessionForm.value = false;
        },
    });
}

onMounted(() => {
    if (!form.date) form.date = todayDate();
    if (props.sessions.length === 1) {
        form.training_session_id = String(props.sessions[0].value);
        applySessionDate(form.training_session_id);
    }
});
</script>

<template>
    <Head title="Attendance" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <AppAlert
                v-if="attendanceUpdateError"
                tone="danger"
                title="Attendance update failed"
                :description="attendanceUpdateError"
                :secondary-action="{ label: 'Dismiss', variant: 'outline' }"
                @secondary="attendanceUpdateError = ''"
            />

            <PageSection eyebrow="Attendance workspace" :title="roleTitle" description="Role-specific attendance flow for athlete, coach, parent, and admin.">
                <template #actions>
                    <div class="flex flex-wrap gap-2">
                        <Button v-if="isParent" type="button" variant="outline" @click="openParentCheckIn">Check in child</Button>
                        <Button v-if="isAdmin || isCoach" type="button" variant="outline" @click="showSessionForm = true">New attendance session</Button>
                    </div>
                </template>

                <div class="grid gap-4 md:grid-cols-3">
                    <StatCard v-for="metric in props.metrics" :key="metric.label" v-bind="metric" />
                </div>
            </PageSection>

            <div class="grid gap-6">
                <PageSection v-if="isAthlete" title="Scan QR attendance" description="Athlete attendance is QR-only. Scan the coach QR inside this page or use the phone camera QR link.">
                    <div class="grid gap-4 lg:grid-cols-[24rem_1fr]">
                        <AthleteQrScanner />
                        <DataTable title="My attendance records" description="Records update after a valid QR scan. Athlete rows are read-only from this page." :columns="columns" :rows="attendanceRows" empty-text="No attendance records yet. Scan the coach QR during training." searchable search-placeholder="Search session..." action-label="Attendance">
                            <template #row-actions>
                                <span class="text-xs text-muted-foreground">QR only</span>
                            </template>
                        </DataTable>
                    </div>
                </PageSection>

                <PageSection v-if="isParent" title="Children attendance" description="Track all linked children attendance records. Use coach QR on a phone, or check in a child manually for an eligible session.">
                    <DataTable title="Children attendance records" description="All linked child attendance rows are shown together." :columns="columns" :rows="attendanceRows" empty-text="No child attendance records yet." searchable search-placeholder="Search child/session/coach..." action-label="Attendance">
                        <template #row-actions="{ row }">
                            <span v-if="row.is_locked" class="text-xs text-muted-foreground">Closed</span>
                            <span v-else-if="!canUpdateRow(row)" class="text-xs text-muted-foreground">View only</span>
                            <ActionButtonsRow v-else>
                                <Button type="button" size="sm" variant="outline" :disabled="rowStatusText(row) === 'Present' || isAttendancePending(String(row.id))" @click="setAttendanceStatus(String(row.id), 'PRESENT')">Attend</Button>
                                <Button type="button" size="sm" variant="outline" :disabled="rowStatusText(row) === 'Absent' || isAttendancePending(String(row.id))" @click="setAttendanceStatus(String(row.id), 'ABSENT')">Not attend</Button>
                            </ActionButtonsRow>
                        </template>
                    </DataTable>
                </PageSection>

                <PageSection v-if="isCoach || isAdmin" title="Session attendance sheet" description="Use the table to update loaded attendance rows or open a dedicated session sheet.">
                    <form v-if="isCoach" class="mt-6 grid gap-3 border-t pt-5" @submit.prevent="openSessionFromCoachInput">
                        <FormInputField id="coach-session-name" v-model="coachSessionName" label="Open session by name" placeholder="Type your session name" :error="coachSessionError" />
                        <Button type="submit" variant="outline">Open session attendance</Button>
                    </form>

                    <DataTable title="Session check-ins" description="Coach rows are scoped to sessions they can access. Admin can correct all visible rows." :columns="columns" :rows="attendanceRows" searchable search-placeholder="Search athlete/session/coach..." action-label="Attendance">
                        <template #row-actions="{ row }">
                            <span v-if="row.is_locked && !isAdmin && !isCoach" class="text-xs text-muted-foreground">Closed</span>
                            <span v-else-if="!canUpdateRow(row)" class="text-xs text-muted-foreground">View only</span>
                            <ActionButtonsRow v-else>
                                <Button type="button" size="sm" variant="outline" :disabled="rowStatusText(row) === 'Present' || isAttendancePending(String(row.id))" @click="setAttendanceStatus(String(row.id), 'PRESENT')">Attend</Button>
                                <Button type="button" size="sm" variant="outline" :disabled="rowStatusText(row) === 'Absent' || isAttendancePending(String(row.id))" @click="setAttendanceStatus(String(row.id), 'ABSENT')">Not attend</Button>
                                <Button type="button" size="sm" variant="outline" :disabled="rowStatusText(row) === 'Excused' || isAttendancePending(String(row.id))" @click="setAttendanceStatus(String(row.id), 'EXCUSED')">Excused</Button>
                            </ActionButtonsRow>
                        </template>
                    </DataTable>
                </PageSection>
            </div>
        </div>

        <FormModal :open="showParentCheckInForm && isParent" max-width-class="max-w-2xl" @close="showParentCheckInForm = false">
            <PageSection title="Check in child" description="Mark a linked child as present for an eligible attendance session.">
                <form class="grid gap-4" @submit.prevent="submitAttendance">
                    <FormSelectField id="parent-child-athlete" v-model="form.athlete_id" label="Child" :options="props.athletes" placeholder="Select child" :multiple="false" :error="form.errors.athlete_id" />
                    <FormSelectField id="parent-child-session" v-model="form.training_session_id" label="Session" :options="childSessionOptions" placeholder="Select session" :multiple="false" :error="form.errors.training_session_id" @update:model-value="applySessionDate(String($event))" />
                    <div class="grid gap-4 md:grid-cols-2">
                        <FormInputField id="parent-check-date" v-model="form.date" label="Date" type="date" :error="form.errors.date" />
                        <FormInputField id="parent-check-time" v-model="form.checked_in_time" label="Check-in time" type="time" :error="form.errors.checked_in_time" />
                    </div>
                    <FormInputField id="parent-notes" v-model="form.notes" label="Notes" :error="form.errors.notes" />
                    <div class="flex flex-wrap gap-3">
                        <Button type="submit" class="w-full sm:w-auto" :disabled="form.processing">{{ form.processing ? 'Saving...' : 'Save child check-in' }}</Button>
                        <Button type="button" class="w-full sm:w-auto" variant="outline" @click="showParentCheckInForm = false">Cancel</Button>
                    </div>
                </form>
            </PageSection>
        </FormModal>

        <FormModal :open="showSessionForm && (isCoach || isAdmin)" max-width-class="max-w-2xl" @close="showSessionForm = false">
            <PageSection title="Create attendance session" description="Create a session and continue to attendance operations.">
                <form class="grid gap-4" @submit.prevent="submitSession">
                    <FormInputField id="session-name" v-model="sessionForm.title" label="Session name" placeholder="Evening attendance block" :error="sessionForm.errors.title" />
                    <FormSelectField id="session-branch" v-model="sessionForm.branch_id" label="Branch" :options="props.branches" :error="sessionForm.errors.branch_id" />
                    <FormSelectField id="session-group" v-model="sessionForm.group_id" label="Group" :options="props.groups" placeholder="All groups in branch" :error="sessionForm.errors.group_id" />
                    <FormInputField id="session-location" v-model="sessionForm.location" label="Location" placeholder="Hall A" :error="sessionForm.errors.location" />
                    <div class="grid gap-4 md:grid-cols-3">
                        <FormInputField id="session-date" v-model="sessionForm.session_date" label="Date" type="date" :error="sessionForm.errors.session_date" />
                        <FormInputField id="session-start" v-model="sessionForm.start_time" label="Start time (24h)" type="time" :error="sessionForm.errors.start_time" />
                        <FormInputField id="session-end" v-model="sessionForm.end_time" label="End time (24h)" type="time" :error="sessionForm.errors.end_time" />
                    </div>
                    <FormSelectField id="session-status" v-model="sessionForm.status" label="Status" :options="[
                        { value: 'DRAFT', label: 'Draft' },
                        { value: 'CONFIRMED', label: 'Confirmed' },
                        { value: 'NEEDS_ASSISTANT', label: 'Needs assistant' },
                        { value: 'CANCELED', label: 'Canceled' },
                    ]" :error="sessionForm.errors.status" />
                    <div class="flex flex-wrap gap-3">
                        <Button type="submit" class="w-full sm:w-auto" :disabled="sessionForm.processing">Create attendance session</Button>
                        <Button type="button" class="w-full sm:w-auto" variant="outline" @click="showSessionForm = false">Cancel</Button>
                    </div>
                </form>
            </PageSection>
        </FormModal>
    </AppLayout>
</template>
