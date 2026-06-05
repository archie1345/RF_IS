<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import FormInputField from '@/components/forms/FormInputField.vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import ActionButtonsRow from '@/components/shared/ActionButtonsRow.vue';
import DataTable from '@/components/shared/DataTable.vue';
import FormModal from '@/components/shared/FormModal.vue';
import PageSection from '@/components/shared/PageSection.vue';
import StatCard from '@/components/shared/StatCard.vue';
import { Button } from '@/components/ui/button';
import { managementRoutes } from '@/data/management';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { Metric, SelectOption, TableColumn, TableRow } from '@/types/management';

const props = defineProps<{
    metrics: Metric[];
    rows: TableRow[];
    athletes: SelectOption[];
    sessions: (SelectOption & { href?: string; date?: string; title?: string })[];
    branches: SelectOption[];
    groups: SelectOption[];
    role: 'admin' | 'coach' | 'parent' | 'athlete';
    activeAthleteId: number | null;
}>();
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: managementRoutes.dashboard },
    { title: 'Attendance', href: managementRoutes.attendance },
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
    coach_session_id: '',
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
const coachSessionName = ref('');
const coachSessionError = ref('');
const showSessionForm = ref(false);

const isAdmin = computed(() => props.role === 'admin');
const isCoach = computed(() => props.role === 'coach');
const isAthlete = computed(() => props.role === 'athlete');
const roleTitle = computed(() => {
    if (isAdmin.value) return 'Admin attendance management';
    if (isCoach.value) return 'Coach attendance management';
    if (isAthlete.value) return 'Athlete self attendance';
    return 'Attendance tracking';
});

function todayDate() {
    const date = new Date();
    date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
    return date.toISOString().slice(0, 10);
}

function rowStatusText(row: TableRow) {
    const status = row.status;
    return typeof status === 'object' && status !== null && 'text' in status ? status.text : String(status ?? '');
}

function canUpdateRow(row: TableRow) {
    return Boolean(row.can_update);
}

function submit() {
    form.post('/attendance', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('checked_in_time', 'notes', 'follow_up_owner');
            if (props.activeAthleteId) {
                form.athlete_id = String(props.activeAthleteId);
            }
            if (!form.date) {
                form.date = todayDate();
            }
        },
    });
}

function submitSelfAttendance(status: 'PRESENT' | 'ABSENT') {
    form.status = status;
    if (!form.date) {
        form.date = todayDate();
    }
    submit();
}

function setAttendanceStatus(id: string, status: 'PRESENT' | 'ABSENT' | 'EXCUSED') {
    const attendanceId = id.replace('ATT-', '');
    router.put(`/attendance/${attendanceId}`, { status }, { preserveScroll: true });
}

function openSessionAttendance(href?: string) {
    if (!href) return;
    router.visit(href);
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
    form.coach_session_id = sessionValue;
    const selected = props.sessions.find((session) => String(session.value) === sessionValue);
    if (selected?.date) {
        form.date = selected.date;
        return;
    }

    form.date = todayDate();
}

function submitSession() {
    sessionForm.post('/sessions', {
        onSuccess: () => {
            sessionForm.reset();
            showSessionForm.value = false;
        },
    });
}

onMounted(() => {
    if (!form.date) {
        form.date = todayDate();
    }

    if (props.sessions.length === 1) {
        form.coach_session_id = String(props.sessions[0].value);
        applySessionDate(form.coach_session_id);
    }
});
</script>

<template>
    <Head title="Attendance" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageSection eyebrow="Basic module" :title="roleTitle" description="Role-specific attendance flow for athlete, coach, and admin.">
                <template #actions>
                    <Button v-if="isAdmin || isCoach" type="button" variant="outline" @click="showSessionForm = true">
                        New attendance session
                    </Button>
                </template>

                <div class="grid gap-4 md:grid-cols-3">
                    <StatCard v-for="metric in props.metrics" :key="metric.label" v-bind="metric" />
                </div>
            </PageSection>

            <div class="grid gap-6">

                <PageSection v-if="isAthlete" title="My attendance" description="Mark your own attendance for today or update an open session record.">
                    <form class="grid gap-4 md:grid-cols-[1fr_auto]" @submit.prevent>
                        <FormSelectField
                            v-if="props.sessions.length > 1"
                            id="attendance-session"
                            :model-value="form.coach_session_id"
                            label="Training session"
                            :options="props.sessions"
                            placeholder="General attendance"
                            help="Choose today’s session if it is listed. Otherwise leave it as general attendance."
                            :error="form.errors.coach_session_id"
                            @update:model-value="applySessionDate"
                        />
                        <div v-else-if="props.sessions.length === 1" class="grid gap-2">
                            <label class="text-sm font-medium">Training session</label>
                            <input :value="props.sessions[0].label" disabled class="h-10 rounded-lg border border-input bg-muted px-3 py-2 text-sm">
                        </div>
                        <div v-else class="grid gap-2">
                            <label class="text-sm font-medium">Training session</label>
                            <input value="General attendance" disabled class="h-10 rounded-lg border border-input bg-muted px-3 py-2 text-sm">
                        </div>
                        <div class="flex items-end gap-2">
                            <Button type="button" :disabled="form.processing" @click="submitSelfAttendance('PRESENT')">Attend</Button>
                            <Button type="button" variant="outline" :disabled="form.processing" @click="submitSelfAttendance('ABSENT')">Not attend</Button>
                        </div>
                    </form>

                    <DataTable
                        title="My attendance records"
                        description="Open records can be corrected until the session closes."
                        :columns="columns"
                        :rows="props.rows"
                        empty-text="No attendance records yet."
                        searchable
                        search-placeholder="Search session..."
                        action-label="Attendance"
                    >
                        <template #row-actions="{ row }">
                            <span v-if="row.is_locked" class="text-xs text-muted-foreground">Closed</span>
                            <span v-else-if="!canUpdateRow(row)" class="text-xs text-muted-foreground">View only</span>
                            <ActionButtonsRow v-else>
                                <Button type="button" size="sm" variant="outline" :disabled="rowStatusText(row) === 'Present'" @click="setAttendanceStatus(String(row.id), 'PRESENT')">
                                    Attend
                                </Button>
                                <Button type="button" size="sm" variant="outline" :disabled="rowStatusText(row) === 'Absent'" @click="setAttendanceStatus(String(row.id), 'ABSENT')">
                                    Not attend
                                </Button>
                            </ActionButtonsRow>
                        </template>
                    </DataTable>
                </PageSection>

                <PageSection v-if="isCoach || isAdmin" title="Session attendance sheet" description="Pick a session, open the dedicated attendance sheet page, and update athlete statuses there.">
                    <form v-if="isCoach" class="mt-6 grid gap-3 border-t pt-5" @submit.prevent="openSessionFromCoachInput">
                        <FormInputField id="coach-session-name" v-model="coachSessionName" label="Coach session panel" placeholder="Type your session name" :error="coachSessionError" />
                        <Button type="submit" variant="outline">Open session attendance</Button>
                    </form>

                    <DataTable title="Session check-ins" description="Set athlete attendance directly. Once session time has passed, updates are hidden and locked." :columns="columns" :rows="props.rows" searchable search-placeholder="Search athlete/session/coach..." action-label="Attendance">
                        <template #row-actions="{ row }">
                            <span v-if="row.is_locked" class="text-xs text-muted-foreground">Closed</span>
                            <span v-else-if="!canUpdateRow(row)" class="text-xs text-muted-foreground">View only</span>
                            <ActionButtonsRow v-else>
                                <Button type="button" size="sm" variant="outline" :disabled="rowStatusText(row) === 'Present'" @click="setAttendanceStatus(String(row.id), 'PRESENT')">
                                    Attend
                                </Button>
                                <Button type="button" size="sm" variant="outline" :disabled="rowStatusText(row) === 'Absent'" @click="setAttendanceStatus(String(row.id), 'ABSENT')">
                                    Not attend
                                </Button>
                                <Button type="button" size="sm" variant="outline" :disabled="rowStatusText(row) === 'Excused'" @click="setAttendanceStatus(String(row.id), 'EXCUSED')">
                                    Excused
                                </Button>
                            </ActionButtonsRow>
                        </template>
                    </DataTable>
                </PageSection>
            </div>
        </div>

        <FormModal :open="showSessionForm && (isCoach || isAdmin)" max-width-class="max-w-2xl" @close="showSessionForm = false">
                <PageSection title="Create attendance session" description="Create a session and continue to attendance operations.">
                    <form class="grid gap-4" @submit.prevent="submitSession">
                        <FormInputField id="session-name" v-model="sessionForm.title" label="Session name" placeholder="Evening attendance block" :error="sessionForm.errors.title" />
                        <div class="grid gap-4 md:grid-cols-2">
                            <FormSelectField id="session-branch" v-model="sessionForm.branch_id" label="Branch" :options="props.branches" :error="sessionForm.errors.branch_id" />
                        </div>
                        <FormSelectField id="session-group" v-model="sessionForm.group_id" label="Group" :options="props.groups" placeholder="All groups in branch" :error="sessionForm.errors.group_id" />
                        <FormInputField id="session-location" v-model="sessionForm.location" label="Location" placeholder="Hall A" :error="sessionForm.errors.location" />
                        <div class="grid gap-4 md:grid-cols-3">
                            <FormInputField id="session-date" v-model="sessionForm.session_date" label="Date" type="date" :error="sessionForm.errors.session_date" />
                            <FormInputField id="session-start" v-model="sessionForm.start_time" label="Start time (24h)" type="time" :error="sessionForm.errors.start_time" />
                            <FormInputField id="session-end" v-model="sessionForm.end_time" label="End time (24h)" type="time" :error="sessionForm.errors.end_time" />
                        </div>
                        <FormSelectField id="session-status" v-model="sessionForm.status" label="Status" :options="[{ value: 'DRAFT', label: 'Draft' }, { value: 'CONFIRMED', label: 'Confirmed' }, { value: 'NEEDS_ASSISTANT', label: 'Needs assistant' }, { value: 'CANCELED', label: 'Canceled' }]" :error="sessionForm.errors.status" />
                        <div class="flex flex-wrap gap-3">
                            <Button type="submit" class="w-full sm:w-auto" :disabled="sessionForm.processing">Create attendance session</Button>
                            <Button type="button" class="w-full sm:w-auto" variant="outline" @click="showSessionForm = false">Cancel</Button>
                        </div>
                    </form>
                </PageSection>
        </FormModal>
    </AppLayout>
</template>

