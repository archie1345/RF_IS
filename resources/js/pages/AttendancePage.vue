<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { QrCode, ShieldCheck } from 'lucide-vue-next';
import { computed, ref, toRef, watch } from 'vue';
import AthleteQrScanner from '@/components/attendance/AthleteQrScanner.vue';
import FormInputField from '@/components/forms/FormInputField.vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import ActionButtonsRow from '@/components/shared/ActionButtonsRow.vue';
import DataTable from '@/components/shared/DataTable.vue';
import FormModal from '@/components/shared/FormModal.vue';
import PageSection from '@/components/shared/PageSection.vue';
import StatCard from '@/components/shared/StatCard.vue';
import { Button } from '@/components/ui/button';
import { useAppPopup } from '@/composables/useAppPopup';
import { useRole } from '@/composables/useRole';
import AppLayout from '@/layouts/AppLayout.vue';
import { routeId } from '@/lib/routeIds';
import type { BreadcrumbItem } from '@/types';
import type { AppRole, AttendanceRow } from '@/types/domain';
import type { Metric, SelectOption, TableBadgeCell, TableColumn, TableRow } from '@/types/resource-table';
import type { AttendanceStatusValue, AttendanceUpdateResponse } from './AttendancePage.types';
import { dashboard } from '@/routes';
import { coachAttend, index as attendanceIndex, update as attendanceUpdate } from '@/routes/attendance';
import { store as sessionsStore } from '@/routes/sessions';

const props = defineProps<{
    metrics: Metric[];
    rows: AttendanceRow[];
    coachSessions: TableRow[];
    athletes: SelectOption[];
    sessions: (SelectOption & { href?: string; date?: string; title?: string })[];
    branches: SelectOption[];
    groups: SelectOption[];
    role: AppRole;
    availableModes: AppRole[];
    activeAthleteId: string | null;
}>();
const popup = useAppPopup();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard.url() },
    { title: 'Attendance', href: attendanceIndex.url() },
];

const attendanceColumns: TableColumn[] = [
    { key: 'athlete', label: 'Atlet' },
    { key: 'session', label: 'Sesi' },
    { key: 'coach', label: 'Pelatih' },
    { key: 'checkin', label: 'Check-in', align: 'right' },
    { key: 'status', label: 'Status' },
];

const coachSessionColumns: TableColumn[] = [
    { key: 'session', label: 'Sesi' },
    { key: 'branch', label: 'Cabang' },
    { key: 'group', label: 'Kelas' },
    { key: 'schedule', label: 'Jadwal' },
    { key: 'session_status', label: 'Status sesi' },
    { key: 'attendance_status', label: 'Attendance saya' },
    { key: 'checked_at', label: 'Check-in' },
];

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
const pendingCoachSessionIds = ref<number[]>([]);
const showSessionForm = ref(false);

const { isAdmin, isCoach, isParent, isAthlete } = useRole(toRef(props, 'role'));
const roleTitle = computed(() => {
    if (isAdmin.value) return 'Pengelolaan attendance';
    if (isCoach.value) return 'Attendance mengajar saya';
    if (isParent.value) return 'Attendance anak';
    if (isAthlete.value) return 'Attendance atlet saya';
    return 'Attendance';
});

watch(
    () => props.rows,
    (rows) => {
        attendanceRows.value = rows.map((row) => ({ ...row }));
    },
);

function modeLabel(mode: AppRole): string {
    return ({ admin: 'Admin', coach: 'Pelatih', parent: 'Orang tua', athlete: 'Atlet' } as Record<AppRole, string>)[mode];
}

function switchAttendanceMode(mode: AppRole): void {
    if (mode === props.role) return;

    router.get(attendanceIndex.url(), { mode }, {
        preserveScroll: true,
        preserveState: false,
        replace: true,
    });
}

function rowStatusText(row: AttendanceRow | TableRow): string {
    const status = row.status;
    return typeof status === 'object' && status !== null && 'text' in status ? status.text : String(status ?? '');
}

function canUpdateRow(row: AttendanceRow | TableRow): boolean {
    return Boolean(row.can_update);
}

function csrfHeaders(): HeadersInit {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
    const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
    const xsrf = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];
    if (csrf) headers['X-CSRF-TOKEN'] = csrf;
    if (xsrf) headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrf);
    return headers;
}

function fallbackAttendanceStatus(status: AttendanceStatusValue): TableBadgeCell {
    return ({
        PRESENT: { kind: 'badge', text: 'Hadir', tone: 'success' },
        ABSENT: { kind: 'badge', text: 'Tidak hadir', tone: 'danger' },
        EXCUSED: { kind: 'badge', text: 'Izin', tone: 'warning' },
        LATE: { kind: 'badge', text: 'Terlambat', tone: 'warning' },
    } as Record<AttendanceStatusValue, TableBadgeCell>)[status];
}

function isAttendancePending(rowId: string): boolean {
    return pendingAttendanceRowIds.value.includes(rowId);
}

function isCoachSessionPending(sessionId: number): boolean {
    return pendingCoachSessionIds.value.includes(sessionId);
}

function replaceAttendanceRow(rowId: string, row: TableRow): void {
    attendanceRows.value = attendanceRows.value.map((currentRow) =>
        String(currentRow.id) === rowId ? row : currentRow,
    );
}

function applyFallbackAttendanceStatus(rowId: string, status: AttendanceStatusValue): void {
    attendanceRows.value = attendanceRows.value.map((row) =>
        String(row.id) === rowId
            ? { ...row, status_value: status, status: fallbackAttendanceStatus(status) }
            : row,
    );
}

async function setAttendanceStatus(id: string, status: AttendanceStatusValue): Promise<void> {
    if (isAttendancePending(id)) return;

    const attendanceId = routeId(id);
    if (!attendanceId) {
        await popup.error('Attendance gagal diperbarui', 'Baris attendance tidak valid.');
        return;
    }

    pendingAttendanceRowIds.value = [...pendingAttendanceRowIds.value, id];

    try {
        const response = await fetch(attendanceUpdate.url(attendanceId), {
            method: 'PUT',
            headers: csrfHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify({ status }),
        });
        const payload = (await response.json().catch(() => ({}))) as AttendanceUpdateResponse;

        if (!response.ok) throw new Error(payload.message ?? 'Attendance gagal diperbarui.');

        if (payload.row) replaceAttendanceRow(id, payload.row);
        else applyFallbackAttendanceStatus(id, status);
    } catch (error) {
        await popup.error(
            'Attendance gagal diperbarui',
            error instanceof Error ? error.message : 'Attendance gagal diperbarui.',
        );
    } finally {
        pendingAttendanceRowIds.value = pendingAttendanceRowIds.value.filter((rowId) => rowId !== id);
    }
}

function attendCoachSession(row: TableRow): void {
    const sessionId = routeId(row.session_id ?? row.id);
    if (!sessionId || isCoachSessionPending(sessionId)) {
        if (!sessionId) void popup.error('Attendance pelatih gagal', 'Sesi latihan tidak valid.');
        return;
    }

    pendingCoachSessionIds.value = [...pendingCoachSessionIds.value, sessionId];

    router.post(coachAttend.url(sessionId), {}, {
        preserveScroll: true,
        onError: (errors) => {
            void popup.error(
                'Attendance pelatih gagal',
                String(errors.session ?? 'Attendance pelatih gagal disimpan.'),
            );
        },
        onFinish: () => {
            pendingCoachSessionIds.value = pendingCoachSessionIds.value.filter((id) => id !== sessionId);
        },
    });
}

function submitSession(): void {
    sessionForm.post(sessionsStore.url(), {
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
        <div class="flex min-w-0 flex-1 flex-col gap-6 p-3 sm:p-4 md:p-6">
            <PageSection
                eyebrow="Attendance workspace"
                :title="roleTitle"
                description="Pilih konteks peran yang sedang digunakan. Attendance pelatih dan atlet tetap dicatat secara terpisah."
            >
                <template #actions>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-for="mode in props.availableModes"
                            :key="mode"
                            type="button"
                            size="sm"
                            :variant="props.role === mode ? 'default' : 'outline'"
                            @click="switchAttendanceMode(mode)"
                        >
                            {{ modeLabel(mode) }}
                        </Button>
                        <Button v-if="isAdmin" type="button" variant="outline" @click="showSessionForm = true">
                            Buat sesi attendance
                        </Button>
                    </div>
                </template>

                <div class="grid gap-4 md:grid-cols-3">
                    <StatCard v-for="metric in props.metrics" :key="metric.label" v-bind="metric" />
                </div>
            </PageSection>

            <PageSection
                v-if="isCoach"
                title="Sesi mengajar yang tersedia"
                description="Tekan Hadir hanya untuk sesi terkonfirmasi yang ditugaskan kepada Anda. Attendance atlet dikelola dari halaman sesi."
            >
                <DataTable
                    title="Attendance pelatih"
                    description="Attendance mengajar tidak digabung dengan attendance atlet meskipun akun memiliki kedua peran."
                    :columns="coachSessionColumns"
                    :rows="props.coachSessions"
                    empty-text="Tidak ada sesi mengajar yang tersedia."
                    searchable
                    search-placeholder="Cari sesi, cabang, kelas, atau tanggal..."
                    action-label="Check-in"
                >
                    <template #row-actions="{ row }">
                        <span v-if="row.has_attended" class="text-xs font-medium text-emerald-600">Sudah hadir</span>
                        <Button
                            v-else-if="row.can_attend"
                            type="button"
                            size="sm"
                            :disabled="isCoachSessionPending(Number(row.session_id))"
                            @click="attendCoachSession(row)"
                        >
                            {{ isCoachSessionPending(Number(row.session_id)) ? 'Menyimpan...' : 'Hadir' }}
                        </Button>
                        <span v-else class="text-xs text-muted-foreground">Belum tersedia</span>
                    </template>
                </DataTable>
            </PageSection>

            <PageSection
                v-if="isAthlete || isParent"
                :title="isParent ? 'Scan QR untuk anak' : 'Scan QR attendance'"
                :description="isParent
                    ? 'Orang tua tetap wajib memindai QR pelatih. Setelah QR terbuka, pilih anak yang terhubung dan konfirmasi check-in.'
                    : 'Attendance atlet hanya dapat dilakukan melalui QR sesi yang dibuka oleh pelatih atau admin.'"
            >
                <div class="mb-4 flex items-start gap-3 rounded-xl border bg-muted/30 p-4">
                    <ShieldCheck class="mt-0.5 size-5 shrink-0 text-emerald-600" />
                    <div>
                        <p class="font-medium">QR wajib digunakan</p>
                        <p class="mt-1 text-sm leading-6 text-muted-foreground">
                            Tidak tersedia tombol hadir manual untuk atlet maupun orang tua. Koreksi manual hanya dapat dilakukan oleh admin atau pelatih yang berwenang.
                        </p>
                    </div>
                </div>

                <div class="grid gap-5 lg:grid-cols-[24rem_minmax(0,1fr)]">
                    <div class="rounded-xl border bg-card p-4">
                        <div class="mb-3 flex items-center gap-2 font-medium">
                            <QrCode class="size-5" /> Pemindai QR
                        </div>
                        <AthleteQrScanner />
                    </div>

                    <DataTable
                        :title="isParent ? 'Riwayat attendance anak' : 'Riwayat attendance atlet'"
                        :description="isParent ? 'Semua anak yang terhubung ditampilkan bersama dan bersifat hanya-baca.' : 'Riwayat ini berasal dari QR scan atau koreksi petugas.'"
                        :columns="attendanceColumns"
                        :rows="attendanceRows"
                        :empty-text="isParent ? 'Belum ada riwayat attendance anak.' : 'Belum ada riwayat attendance atlet.'"
                        searchable
                        search-placeholder="Cari atlet, sesi, atau pelatih..."
                        action-label="Metode"
                    >
                        <template #row-actions>
                            <span class="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground">
                                <QrCode class="size-3.5" /> QR saja
                            </span>
                        </template>
                    </DataTable>
                </div>
            </PageSection>

            <PageSection
                v-if="isAdmin"
                title="Pengelolaan attendance atlet"
                description="Admin dapat melihat dan mengoreksi seluruh attendance. Check-in normal tetap diarahkan melalui QR sesi."
            >
                <DataTable
                    title="Daftar check-in sesi"
                    description="Gunakan halaman attendance sesi untuk operasi detail dan pembukaan QR cepat."
                    :columns="attendanceColumns"
                    :rows="attendanceRows"
                    searchable
                    search-placeholder="Cari atlet, sesi, atau pelatih..."
                    action-label="Koreksi"
                >
                    <template #row-actions="{ row }">
                        <span v-if="!canUpdateRow(row)" class="text-xs text-muted-foreground">Hanya lihat</span>
                        <ActionButtonsRow v-else>
                            <Button type="button" size="sm" variant="outline" :disabled="rowStatusText(row) === 'Present' || isAttendancePending(String(row.id))" @click="setAttendanceStatus(String(row.id), 'PRESENT')">Hadir</Button>
                            <Button type="button" size="sm" variant="outline" :disabled="rowStatusText(row) === 'Absent' || isAttendancePending(String(row.id))" @click="setAttendanceStatus(String(row.id), 'ABSENT')">Tidak hadir</Button>
                            <Button type="button" size="sm" variant="outline" :disabled="rowStatusText(row) === 'Excused' || isAttendancePending(String(row.id))" @click="setAttendanceStatus(String(row.id), 'EXCUSED')">Izin</Button>
                        </ActionButtonsRow>
                    </template>
                </DataTable>
            </PageSection>
        </div>

        <FormModal :open="showSessionForm && isAdmin" max-width-class="max-w-2xl" @close="showSessionForm = false">
            <PageSection title="Buat sesi attendance" description="Buat sesi latihan lalu lanjutkan ke halaman sesi untuk membuka QR dan mengelola attendance.">
                <form class="grid gap-4" @submit.prevent="submitSession">
                    <FormInputField id="session-name" v-model="sessionForm.title" label="Nama sesi" placeholder="Latihan sore" :error="sessionForm.errors.title" />
                    <FormSelectField id="session-branch" v-model="sessionForm.branch_id" label="Cabang" :options="props.branches" :error="sessionForm.errors.branch_id" />
                    <FormSelectField id="session-group" v-model="sessionForm.group_id" label="Kelas" :options="props.groups" placeholder="Semua kelas" :error="sessionForm.errors.group_id" />
                    <FormInputField id="session-location" v-model="sessionForm.location" label="Lokasi" :error="sessionForm.errors.location" />
                    <div class="grid gap-4 md:grid-cols-3">
                        <FormInputField id="session-date" v-model="sessionForm.session_date" label="Tanggal" type="date" :error="sessionForm.errors.session_date" />
                        <FormInputField id="session-start" v-model="sessionForm.start_time" label="Mulai" type="time" :error="sessionForm.errors.start_time" />
                        <FormInputField id="session-end" v-model="sessionForm.end_time" label="Selesai" type="time" :error="sessionForm.errors.end_time" />
                    </div>
                    <FormSelectField
                        id="session-status"
                        v-model="sessionForm.status"
                        label="Status"
                        :options="[
                            { value: 'DRAFT', label: 'Draft' },
                            { value: 'CONFIRMED', label: 'Dikonfirmasi' },
                            { value: 'NEEDS_ASSISTANT', label: 'Butuh pelatih tambahan' },
                            { value: 'CANCELED', label: 'Dibatalkan' },
                        ]"
                        :error="sessionForm.errors.status"
                    />
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <Button type="button" variant="outline" @click="showSessionForm = false">Batal</Button>
                        <Button type="submit" :disabled="sessionForm.processing">{{ sessionForm.processing ? 'Menyimpan...' : 'Buat sesi' }}</Button>
                    </div>
                </form>
            </PageSection>
        </FormModal>
    </AppLayout>
</template>
