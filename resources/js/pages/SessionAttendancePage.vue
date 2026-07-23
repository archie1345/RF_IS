<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { CalendarDays, Clock3, MapPin, PencilLine, QrCode, UsersRound } from 'lucide-vue-next';
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
import { destroy as destroySessionQr, store as storeSessionQr } from '@/routes/sessions/attendance-qr';
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
            scan_url?: string | null;
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
    { title: 'Sesi latihan', href: sessionsIndex.url() },
    { title: 'Attendance sesi', href: sessionAttendance.url(props.session.id) },
];

const athleteColumns: TableColumn[] = [
    { key: 'athlete', label: 'Atlet' },
    { key: 'status', label: 'Status kehadiran' },
];
const coachColumns: TableColumn[] = [
    { key: 'coach', label: 'Pelatih' },
    { key: 'status', label: 'Status mengajar' },
    { key: 'checked_at', label: 'Terakhir diperbarui' },
];

const coachForm = useForm({ coach_id: '' });
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

const attendanceRows = ref<TableRow[]>([...props.rows]);
const attendanceUpdateError = ref('');
const pendingAttendanceRowIds = ref<string[]>([]);
const pendingBulkStatus = ref<'PRESENT' | 'ABSENT' | null>(null);
const showSessionForm = ref(false);
const pendingCoachDeleteId = ref<string | null>(null);
const qrProcessing = ref(false);

watch(
    () => props.rows,
    (rows) => {
        attendanceRows.value = [...rows];
    },
);

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
    const map: Record<AttendanceStatusValue, TableBadgeCell> = {
        PRESENT: { kind: 'badge', text: 'Hadir', tone: 'success' },
        ABSENT: { kind: 'badge', text: 'Tidak hadir', tone: 'danger' },
        EXCUSED: { kind: 'badge', text: 'Izin', tone: 'warning' },
        LATE: { kind: 'badge', text: 'Terlambat', tone: 'warning' },
    };

    return map[status];
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

function isAttendancePending(rowId: string): boolean {
    return pendingAttendanceRowIds.value.includes(rowId);
}

async function updateStatus(rowId: string, status: AttendanceStatusValue): Promise<void> {
    if (isAttendancePending(rowId)) return;

    const attendanceId = routeId(rowId);
    if (!attendanceId) {
        attendanceUpdateError.value = 'Baris attendance tidak valid.';
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

        if (!response.ok) throw new Error(payload.message ?? 'Attendance gagal diperbarui.');

        if (payload.row) replaceAttendanceRow(rowId, payload.row);
        else applyFallbackAttendanceStatus(rowId, status);
    } catch (error) {
        attendanceUpdateError.value = error instanceof Error ? error.message : 'Attendance gagal diperbarui.';
    } finally {
        pendingAttendanceRowIds.value = pendingAttendanceRowIds.value.filter((id) => id !== rowId);
    }
}

function requestBulkUpdate(status: 'PRESENT' | 'ABSENT'): void {
    pendingBulkStatus.value = status;
}

function confirmBulkUpdate(): void {
    if (!pendingBulkStatus.value) return;

    const status = pendingBulkStatus.value;
    pendingBulkStatus.value = null;
    const attendanceIds = attendanceRows.value
        .map((row) => routeId(row.id))
        .filter((id): id is number => id !== null);

    router.post(attendanceBulkUpdate.url(), { attendance_ids: attendanceIds, status }, { preserveScroll: true });
}

function quickToggleQr(): void {
    if (qrProcessing.value) return;

    if (props.session.attendance_qr.is_active) {
        if (!window.confirm('Tutup QR attendance sekarang? Kode langsung tidak dapat digunakan lagi.')) return;
        qrProcessing.value = true;
        router.delete(destroySessionQr.url(props.session.id), {
            preserveScroll: true,
            onFinish: () => {
                qrProcessing.value = false;
            },
        });
        return;
    }

    qrProcessing.value = true;
    router.post(
        storeSessionQr.url(props.session.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                qrProcessing.value = false;
            },
        },
    );
}

function addCoach(): void {
    if (!props.session.is_private) return;

    coachForm.post(sessionCoachAttendanceStore.url(props.session.id), {
        preserveScroll: true,
        onSuccess: () => coachForm.reset(),
    });
}

function updateCoachStatus(rowId: string, status: 'TEACH' | 'NOT_TEACH'): void {
    const coachAttendanceId = routeId(rowId);
    if (!coachAttendanceId) return;

    router.put(sessionCoachAttendanceUpdate.url(coachAttendanceId), { status }, { preserveScroll: true });
}

function requestRemoveCoach(rowId: string): void {
    pendingCoachDeleteId.value = rowId;
}

function confirmRemoveCoach(): void {
    if (!pendingCoachDeleteId.value) return;

    const coachAttendanceId = routeId(pendingCoachDeleteId.value);
    pendingCoachDeleteId.value = null;
    if (!coachAttendanceId) return;

    router.delete(sessionCoachAttendanceDestroy.url(coachAttendanceId), { preserveScroll: true });
}

function resetCoachForm(): void {
    coachForm.reset();
    coachForm.clearErrors();
}

function openEditSessionForm(): void {
    sessionForm.title = props.session.title;
    sessionForm.branch_id = props.session.branch_id === null || props.session.branch_id === undefined
        ? ''
        : String(props.session.branch_id);
    sessionForm.group_id = props.session.group_id === null || props.session.group_id === undefined
        ? ''
        : String(props.session.group_id);
    sessionForm.location = props.session.location ?? '';
    sessionForm.session_date = props.session.date;
    sessionForm.start_time = props.session.start_time ?? '';
    sessionForm.end_time = props.session.end_time ?? '';
    sessionForm.status = props.session.status ?? 'DRAFT';
    sessionForm.clearErrors();
    showSessionForm.value = true;
}

function cancelSessionForm(): void {
    sessionForm.reset();
    sessionForm.clearErrors();
    showSessionForm.value = false;
}

function saveSession(): void {
    sessionForm.put(sessionUpdate.url(props.session.id), {
        preserveScroll: true,
        onSuccess: () => {
            showSessionForm.value = false;
        },
    });
}
</script>

<template>
    <Head :title="`Attendance - ${props.session.title}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-w-0 flex-1 flex-col gap-6 p-3 sm:p-4 md:p-6">
            <section class="overflow-hidden rounded-2xl border bg-card shadow-sm">
                <div class="grid gap-5 p-4 md:grid-cols-[1fr_auto] md:items-start md:p-6">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-muted px-3 py-1 text-xs font-semibold">Attendance sesi</span>
                            <span class="rounded-full border px-3 py-1 text-xs font-semibold">{{ props.session.status }}</span>
                        </div>
                        <h1 class="mt-3 text-2xl font-bold tracking-tight md:text-3xl">{{ props.session.title }}</h1>
                        <p class="mt-2 text-sm leading-6 text-muted-foreground">
                            Kelola QR check-in, status atlet, dan status mengajar pelatih dari satu halaman.
                        </p>
                    </div>

                    <div class="grid gap-2 sm:grid-cols-2 md:flex md:flex-wrap md:justify-end">
                        <Button
                            type="button"
                            :variant="props.session.attendance_qr.is_active ? 'destructive' : 'default'"
                            :disabled="qrProcessing"
                            @click="quickToggleQr"
                        >
                            <QrCode class="mr-2 size-4" />
                            {{
                                qrProcessing
                                    ? 'Memproses...'
                                    : props.session.attendance_qr.is_active
                                        ? 'Tutup QR'
                                        : 'Buka QR cepat'
                            }}
                        </Button>
                        <Button type="button" variant="outline" @click="openEditSessionForm">
                            <PencilLine class="mr-2 size-4" /> Ubah sesi
                        </Button>
                        <Button as-child type="button" variant="outline">
                            <a :href="sessionsIndex.url()">Kembali ke sesi</a>
                        </Button>
                    </div>
                </div>

                <div class="grid border-t sm:grid-cols-2 lg:grid-cols-5">
                    <div class="flex items-start gap-3 border-b p-4 sm:border-r lg:border-b-0">
                        <CalendarDays class="mt-0.5 size-5 text-muted-foreground" />
                        <div><p class="text-xs text-muted-foreground">Tanggal</p><p class="font-semibold">{{ props.session.date }}</p></div>
                    </div>
                    <div class="flex items-start gap-3 border-b p-4 lg:border-r lg:border-b-0">
                        <Clock3 class="mt-0.5 size-5 text-muted-foreground" />
                        <div><p class="text-xs text-muted-foreground">Waktu</p><p class="font-semibold">{{ props.session.start_time ?? '-' }}–{{ props.session.end_time ?? '-' }}</p></div>
                    </div>
                    <div class="flex items-start gap-3 border-b p-4 sm:border-r lg:border-b-0">
                        <MapPin class="mt-0.5 size-5 text-muted-foreground" />
                        <div><p class="text-xs text-muted-foreground">Lokasi & kelas</p><p class="font-semibold">{{ props.session.branch }} · {{ props.session.group }}</p><p class="text-xs text-muted-foreground">{{ props.session.location ?? '-' }}</p></div>
                    </div>
                    <div class="flex items-start gap-3 border-b p-4 lg:border-r lg:border-b-0">
                        <UsersRound class="mt-0.5 size-5 text-muted-foreground" />
                        <div><p class="text-xs text-muted-foreground">Atlet hadir</p><p class="font-semibold">{{ props.session.athlete_attendance_summary }}</p></div>
                    </div>
                    <div class="flex items-start gap-3 p-4">
                        <UsersRound class="mt-0.5 size-5 text-muted-foreground" />
                        <div><p class="text-xs text-muted-foreground">Pelatih mengajar</p><p class="font-semibold">{{ props.session.coach_attendance_summary }}</p><p class="text-xs text-muted-foreground">{{ props.session.coach }}</p></div>
                    </div>
                </div>
            </section>

            <SessionAttendanceQrPanel :session-id="props.session.id" :qr="props.session.attendance_qr" />

            <PageSection
                eyebrow="Daftar atlet"
                title="Attendance atlet"
                description="QR scan memperbarui kehadiran otomatis. Admin dan pelatih tetap dapat melakukan koreksi jika terjadi kesalahan operasional."
            >
                <template #actions>
                    <ActionButtonsRow>
                        <Button type="button" size="sm" variant="outline" @click="requestBulkUpdate('PRESENT')">
                            Tandai semua hadir
                        </Button>
                        <Button type="button" size="sm" variant="outline" @click="requestBulkUpdate('ABSENT')">
                            Tandai semua tidak hadir
                        </Button>
                    </ActionButtonsRow>
                </template>

                <DataTable
                    title="Daftar kehadiran atlet"
                    description="Atlet yang belum memindai QR tetap berstatus tidak hadir sampai dikoreksi oleh petugas yang berwenang."
                    :columns="athleteColumns"
                    :rows="attendanceRows"
                    empty-text="Tidak ada atlet yang memenuhi syarat untuk sesi ini. Periksa kelas dan cabang sesi."
                    searchable
                    search-placeholder="Cari atlet..."
                    action-label="Koreksi"
                >
                    <template #row-actions="{ row }">
                        <ActionButtonsRow>
                            <Button type="button" size="sm" variant="outline" :disabled="isAttendancePending(String(row.id))" @click="updateStatus(String(row.id), 'PRESENT')">Hadir</Button>
                            <Button type="button" size="sm" variant="outline" :disabled="isAttendancePending(String(row.id))" @click="updateStatus(String(row.id), 'LATE')">Terlambat</Button>
                            <Button type="button" size="sm" variant="outline" :disabled="isAttendancePending(String(row.id))" @click="updateStatus(String(row.id), 'EXCUSED')">Izin</Button>
                            <Button type="button" size="sm" variant="outline" :disabled="isAttendancePending(String(row.id))" @click="updateStatus(String(row.id), 'ABSENT')">Tidak hadir</Button>
                        </ActionButtonsRow>
                    </template>
                </DataTable>
            </PageSection>

            <PageSection
                eyebrow="Pelatih sesi"
                title="Status mengajar pelatih"
                :description="props.session.is_private ? 'Sesi privat dapat menambahkan pelatih khusus dari bagian ini.' : 'Daftar ini mengikuti semua pelatih yang ditugaskan ke kelas atau sesi.'"
            >
                <form v-if="props.session.is_private" class="mb-5 grid gap-3 rounded-xl border bg-muted/20 p-4 md:grid-cols-[1fr_auto] md:items-end" @submit.prevent="addCoach">
                    <div class="grid gap-2">
                        <FormSelectField id="coach-picker" v-model="coachForm.coach_id" label="Tambahkan pelatih privat" :options="props.coachOptions" placeholder="Pilih pelatih" />
                        <InputError :message="coachForm.errors.coach_id" />
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button type="submit" :disabled="coachForm.processing">Tambah pelatih</Button>
                        <Button type="button" variant="outline" @click="resetCoachForm">Reset</Button>
                    </div>
                </form>

                <DataTable
                    title="Daftar pelatih sesi"
                    description="Status mengajar dipisahkan dari attendance atlet, termasuk untuk akun yang memiliki banyak peran."
                    :columns="coachColumns"
                    :rows="props.coachRows"
                    empty-text="Belum ada pelatih pada sesi ini."
                    searchable
                    search-placeholder="Cari pelatih..."
                    action-label="Tindakan"
                >
                    <template #row-actions="{ row }">
                        <ActionButtonsRow>
                            <Button type="button" size="sm" variant="outline" @click="updateCoachStatus(String(row.id), 'TEACH')">Mengajar</Button>
                            <Button type="button" size="sm" variant="outline" @click="updateCoachStatus(String(row.id), 'NOT_TEACH')">Tidak mengajar</Button>
                            <Button type="button" size="sm" variant="destructive" @click="requestRemoveCoach(String(row.id))">Hapus</Button>
                        </ActionButtonsRow>
                    </template>
                </DataTable>
            </PageSection>
        </div>

        <FormModal :open="Boolean(attendanceUpdateError)" max-width-class="max-w-lg" @close="attendanceUpdateError = ''">
            <PageSection title="Attendance gagal diperbarui" :description="attendanceUpdateError">
                <div class="flex justify-end"><Button type="button" variant="outline" @click="attendanceUpdateError = ''">Tutup</Button></div>
            </PageSection>
        </FormModal>

        <FormModal :open="Boolean(pendingBulkStatus)" max-width-class="max-w-xl" @close="pendingBulkStatus = null">
            <PageSection
                v-if="pendingBulkStatus"
                title="Ubah seluruh atlet?"
                :description="`Semua atlet pada sesi ini akan ditandai ${pendingBulkStatus === 'PRESENT' ? 'hadir' : 'tidak hadir'}. Status lama akan diganti.`"
            >
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <Button type="button" variant="outline" @click="pendingBulkStatus = null">Batal</Button>
                    <Button type="button" variant="destructive" @click="confirmBulkUpdate">Terapkan perubahan</Button>
                </div>
            </PageSection>
        </FormModal>

        <FormModal :open="Boolean(pendingCoachDeleteId)" max-width-class="max-w-xl" @close="pendingCoachDeleteId = null">
            <PageSection title="Hapus pelatih dari sesi?" description="Entri attendance mengajar pelatih ini akan dihapus dari sesi.">
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <Button type="button" variant="outline" @click="pendingCoachDeleteId = null">Batal</Button>
                    <Button type="button" variant="destructive" @click="confirmRemoveCoach">Hapus pelatih</Button>
                </div>
            </PageSection>
        </FormModal>

        <FormModal :open="showSessionForm" max-width-class="max-w-2xl" @close="cancelSessionForm">
            <PageSection title="Ubah sesi latihan" description="Perbarui waktu, kelas, cabang, lokasi, dan status sesi tanpa mengubah riwayat attendance.">
                <form class="grid gap-4" @submit.prevent="saveSession">
                    <FormInputField id="session-name" v-model="sessionForm.title" label="Nama sesi" :error="sessionForm.errors.title" />
                    <div class="grid gap-4 md:grid-cols-2">
                        <FormSelectField id="session-group" v-model="sessionForm.group_id" label="Kelas" :options="props.groups" placeholder="Semua kelas" :error="sessionForm.errors.group_id" />
                        <FormSelectField id="session-branch" v-model="sessionForm.branch_id" label="Cabang" :options="props.branches" :error="sessionForm.errors.branch_id" />
                    </div>
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
                        <Button type="button" variant="outline" @click="cancelSessionForm">Batal</Button>
                        <Button type="submit" :disabled="sessionForm.processing">{{ sessionForm.processing ? 'Menyimpan...' : 'Simpan perubahan' }}</Button>
                    </div>
                </form>
            </PageSection>
        </FormModal>
    </AppLayout>
</template>
