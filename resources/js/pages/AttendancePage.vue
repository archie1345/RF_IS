<script setup lang="ts">
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
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

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

function submit() {
    form.post('/attendance', {
        onSuccess: () => form.reset('athlete_id', 'coach_session_id', 'date', 'checked_in_time', 'notes', 'follow_up_owner'),
    });
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
    const selected = props.sessions.find((session) => String(session.value) === sessionValue);
    if (selected?.date) {
        form.date = selected.date;
    }
}

function submitSession() {
    sessionForm.post('/sessions', {
        onSuccess: () => {
            sessionForm.reset();
            showSessionForm.value = false;
        },
    });
}
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

                <PageSection v-if="isCoach || isAdmin" title="Session attendance sheet" description="Pick a session, open the dedicated attendance sheet page, and update athlete statuses there.">
                    <form v-if="isCoach" class="mt-6 grid gap-3 border-t pt-5" @submit.prevent="openSessionFromCoachInput">
                        <FormInputField id="coach-session-name" v-model="coachSessionName" label="Coach session panel" placeholder="Type your session name" :error="coachSessionError" />
                        <Button type="submit" variant="outline">Open session attendance</Button>
                    </form>

                    <DataTable title="Session check-ins" description="Set athlete attendance directly. Once session time has passed, updates are hidden and locked." :columns="columns" :rows="props.rows" searchable search-placeholder="Search athlete/session/coach..." action-label="Attendance">
                        <template #row-actions="{ row }">
                            <span v-if="row.is_locked" class="text-xs text-muted-foreground">Closed</span>
                            <ActionButtonsRow v-else>
                                <Button type="button" size="sm" variant="outline" :disabled="(typeof row.status === 'object' ? row.status?.text : row.status) === 'Present'" @click="setAttendanceStatus(String(row.id), 'PRESENT')">
                                    Present
                                </Button>
                                <Button type="button" size="sm" variant="outline" :disabled="(typeof row.status === 'object' ? row.status?.text : row.status) === 'Absent'" @click="setAttendanceStatus(String(row.id), 'ABSENT')">
                                    Absent
                                </Button>
                                <Button type="button" size="sm" variant="outline" :disabled="(typeof row.status === 'object' ? row.status?.text : row.status) === 'Excused'" @click="setAttendanceStatus(String(row.id), 'EXCUSED')">
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

