<script setup lang="ts">
import DataTable from '@/components/mvp/DataTable.vue';
import InputError from '@/components/InputError.vue';
import PageSection from '@/components/mvp/PageSection.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { managementRoutes } from '@/data/mvp';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { SelectOption, TableColumn, TableRow } from '@/types/mvp';
import { Head, router, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    session: {
        id: number;
        title: string;
        date: string;
        branch: string;
        group: string;
        coach: string;
        athlete_attendance_summary: string;
        coach_attendance_summary: string;
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

function updateStatus(rowId: string, status: string) {
    const attendanceId = rowId.replace('ATT-', '');
    router.put(`/attendance/${attendanceId}`, { status }, { preserveScroll: true });
}

function bulkUpdate(status: 'PRESENT' | 'ABSENT') {
    const attendanceIds = props.rows.map((row) => Number(String(row.id).replace('ATT-', ''))).filter((id) => ! Number.isNaN(id));
    router.post('/attendance/bulk-update', { attendance_ids: attendanceIds, status }, { preserveScroll: true });
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
                :description="`Date: ${props.session.date} | Branch: ${props.session.branch} | Group: ${props.session.group} | Coaches: ${props.session.coach} | Athlete attendance: ${props.session.athlete_attendance_summary} | Coach attendance: ${props.session.coach_attendance_summary}`"
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

            <PageSection title="Coach attendance table" description="Add coaches to this session and mark whether they teach or not.">
                <form class="mb-4 grid gap-2 md:grid-cols-[1fr_auto]" @submit.prevent="addCoach">
                    <div class="grid gap-2">
                        <Label for="coach-picker">Add coach</Label>
                        <select id="coach-picker" v-model="coachForm.coach_id" class="flex h-10 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none">
                            <option value="">Select coach</option>
                            <option v-for="coach in props.coachOptions" :key="coach.value" :value="coach.value">{{ coach.label }}</option>
                        </select>
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
                        <div class="flex items-center justify-end gap-2">
                            <Button type="button" size="sm" variant="outline" @click="updateCoachStatus(String(row.id), 'TEACH')">Teach</Button>
                            <Button type="button" size="sm" variant="outline" @click="updateCoachStatus(String(row.id), 'NOT_TEACH')">Not teach</Button>
                            <Button type="button" size="sm" variant="destructive" @click="removeCoach(String(row.id))">Delete</Button>
                        </div>
                    </template>
                </DataTable>
            </PageSection>

            <DataTable
                title="Athlete attendance form"
                description="All athletes registered in this session group are preloaded as not attend by default."
                :columns="columns"
                :rows="props.rows"
                searchable
                search-placeholder="Search athlete..."
                action-label="Actions"
            >
                <template #row-actions="{ row }">
                    <div class="flex items-center justify-end gap-2">
                        <Button type="button" size="sm" variant="outline" @click="updateStatus(String(row.id), 'PRESENT')">Attend</Button>
                        <Button type="button" size="sm" variant="outline" @click="updateStatus(String(row.id), 'ABSENT')">Not attend</Button>
                    </div>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
