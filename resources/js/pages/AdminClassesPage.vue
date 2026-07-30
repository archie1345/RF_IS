<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { CalendarDays, Eye, Pencil, Trash2, UserRoundCheck, Users } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import FormInputField from '@/components/forms/FormInputField.vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import ActionButtonsRow from '@/components/shared/ActionButtonsRow.vue';
import DataTable from '@/components/shared/DataTable.vue';
import FormModal from '@/components/shared/FormModal.vue';
import { Button } from '@/components/ui/button';
import { useAppPopup } from '@/composables/useAppPopup';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { classes as adminClasses } from '@/routes/admin';
import {
    athletes as groupAthletes,
    destroy as groupDestroy,
    store as groupStore,
    update as groupUpdate,
} from '@/routes/admin/groups';
import type { BreadcrumbItem } from '@/types';
import type { TableColumn, TableRow } from '@/types/resource-table';
import type {
    ClassAthleteRecord,
    ClassRecord,
    ClassScheduleMode,
    ClassSessionRecord,
    SelectOption,
} from '@/types/training';

const props = withDefaults(
    defineProps<{
        title?: string;
        subtitle?: string;
        classes?: ClassRecord[];
        branchOptions?: SelectOption[];
        trainingGroupOptions?: SelectOption[];
        athleteOptions?: SelectOption[];
        coachOptions?: SelectOption[];
        beltOptions?: SelectOption[];
        canCreateClasses?: boolean;
        canEditClasses?: boolean;
        canDeleteClasses?: boolean;
    }>(),
    {
        title: 'Kelas Latihan',
        subtitle: 'Master data kelas, peserta, jadwal, dan pelatih kelas.',
        classes: () => [],
        branchOptions: () => [],
        trainingGroupOptions: () => [],
        athleteOptions: () => [],
        coachOptions: () => [],
        beltOptions: () => [],
        canCreateClasses: true,
        canEditClasses: true,
        canDeleteClasses: true,
    },
);
const popup = useAppPopup();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard.url() },
    { title: props.title, href: adminClasses.url() },
];

const dayOptions = [
    { value: '1', label: 'Senin' },
    { value: '2', label: 'Selasa' },
    { value: '3', label: 'Rabu' },
    { value: '4', label: 'Kamis' },
    { value: '5', label: 'Jumat' },
    { value: '6', label: 'Sabtu' },
    { value: '7', label: 'Minggu' },
];

const classTypeOptions = [
    { value: 'reguler', label: 'Reguler' },
    { value: 'prestasi', label: 'Prestasi' },
    { value: 'private', label: 'Private' },
    { value: 'pemula', label: 'Pemula' },
    { value: 'sparring', label: 'Sparring' },
];

const scheduleModeOptions: Array<{ value: ClassScheduleMode; label: string; detail: string }> = [
    { value: 'weekly', label: 'Mingguan', detail: 'Membuat dan menyinkronkan sesi berulang dari kelas ini.' },
    { value: 'one_day', label: 'Sekali jalan', detail: 'Membuat satu sesi hanya pada tanggal yang dipilih.' },
];

const classTableColumns: TableColumn[] = [
    { key: 'class', label: 'Kelas' },
    { key: 'branch', label: 'Lokasi' },
    { key: 'coach', label: 'Pelatih' },
    { key: 'schedule', label: 'Jadwal' },
    { key: 'participants', label: 'Peserta' },
    { key: 'session_summary', label: 'Sesi' },
    { key: 'weekly_schedule_status', label: 'Schedule' },
    { key: 'status', label: 'Status' },
];

const athleteTableColumns: TableColumn[] = [
    { key: 'name', label: 'Atlet' },
    { key: 'branch', label: 'Branch' },
    { key: 'training_group', label: 'Kategori' },
    { key: 'geup', label: 'Geup' },
];

const sessionTableColumns: TableColumn[] = [
    { key: 'title', label: 'Sesi' },
    { key: 'date', label: 'Tanggal' },
    { key: 'time', label: 'Jam' },
    { key: 'coach', label: 'Pelatih' },
    { key: 'status', label: 'Status' },
    { key: 'archive_state', label: 'Tampilan' },
];

const editingClassId = ref<number | null>(null);
const selectedClass = ref<ClassRecord | null>(null);
const selectedClassAthletes = ref<ClassAthleteRecord[]>([]);
const athleteModalLoading = ref(false);
const athleteModalError = ref('');
const selectedSessionClass = ref<ClassRecord | null>(null);
const sessionViewMode = ref<'active' | 'archived' | 'all'>('active');
const showClassForm = ref(false);

const athleteNameOptions = computed<SelectOption[]>(() =>
    props.athleteOptions.map((option) => ({
        ...option,
        label: option.label.split(' · ')[0]?.trim() || option.label,
    })),
);

const form = useForm({
    name: '',
    class_type: 'reguler',
    training_group_id: '',
    dedicated_athlete_ids: [] as string[],
    coach_ids: [] as string[],
    schedule_mode: 'weekly' as ClassScheduleMode,
    single_session_date: '',
    branch_id: '',
    day_of_week: '1',
    start_time: '16:00',
    end_time: '18:00',
    min_belt: '',
    description: '',
    is_active: false,
});

const isPrivateClass = computed(() => form.class_type === 'private');
const isOneDayClass = computed(() => form.schedule_mode === 'one_day');

const classCanBeActive = computed(() =>
    Boolean(
        form.name.trim() &&
        form.class_type &&
        (isPrivateClass.value ? form.dedicated_athlete_ids.length > 0 : form.training_group_id) &&
        form.branch_id &&
        form.start_time &&
        form.end_time &&
        (isOneDayClass.value ? form.single_session_date : form.day_of_week) &&
        (!isPrivateClass.value || form.coach_ids.length > 0),
    ),
);

const activationHint = computed(() => {
    if (classCanBeActive.value) {
        return isPrivateClass.value
            ? 'Data lengkap. Sesi private hanya berlaku untuk atlet dan pelatih yang dipilih.'
            : 'Data lengkap. Pelatih yang dipilih akan otomatis ditugaskan ke sesi kelas.';
    }

    return isPrivateClass.value
        ? 'Lengkapi kelas, atlet private, minimal satu pelatih, lokasi, dan jadwal sebelum diaktifkan.'
        : 'Lengkapi nama, tipe kelas, kategori atlet, lokasi, dan jadwal sebelum diaktifkan.';
});

const classTableRows = computed<TableRow[]>(() =>
    props.classes.map((item) => {
        const minBeltLabel: Record<string, string> = {
            GEUP_1: 'Geup 1',
            GEUP_2: 'Geup 2',
            GEUP_3: 'Geup 3',
            GEUP_4: 'Geup 4',
            GEUP_5: 'Geup 5',
            GEUP_6: 'Geup 6',
            GEUP_7: 'Geup 7',
            GEUP_8: 'Geup 8',
            GEUP_9: 'Geup 9',
            GEUP_10: 'Geup 10',
            DAN: 'Dan',
        };
        const typeLabel = classTypeLabel(item.class_type);
        const minBelt = minBeltLabel[item.min_belt ?? ''] ?? '-';
        const privateAthlete = privateAthleteLabel(item);
        const classMeta =
            normalizeClassType(item.class_type) === 'private'
                ? `${typeLabel} · ${privateAthlete}`
                : `${typeLabel} · Minimum Geup: ${minBelt}`;
        const activeSessions = Number(item.active_sessions_count ?? 0);
        const archivedSessions = Number(item.archived_sessions_count ?? 0);
        const coachNames = item.coaches?.length
            ? item.coaches
            : item.coach && item.coach !== 'Belum ada coach'
              ? [item.coach]
              : [];

        return {
            id: String(item.id),
            class_id: item.id,
            class: item.name,
            class_meta: classMeta,
            class_type_label: typeLabel,
            category:
                normalizeClassType(item.class_type) === 'private' ? '-' : item.training_group || 'Belum ada kategori',
            private_athlete: privateAthlete,
            branch: item.branch || '-',
            coach: coachNames.join(', ') || 'Belum ada pelatih',
            coach_names: coachNames,
            schedule: `${item.day_label} · ${item.start_time} - ${item.end_time}`,
            schedule_day: item.day_label,
            schedule_time: `${item.start_time} - ${item.end_time}`,
            participants: `${item.athletes_count} atlet`,
            session_summary: `${activeSessions} aktif · ${archivedSessions} arsip`,
            weekly_schedule_status: item.weekly_schedule_status,
            status: item.is_active ? 'AKTIF' : 'NONAKTIF',
            status_tone: item.is_active ? 'success' : 'neutral',
        };
    }),
);

const selectedClassAthleteRows = computed<TableRow[]>(() =>
    selectedClassAthletes.value.map((athlete) => ({
        id: String(athlete.id),
        name: athlete.name,
        branch: athlete.branch || '-',
        training_group: athlete.training_group || '-',
        geup: athlete.geup || '-',
    })),
);

const sessionRows = computed<TableRow[]>(() => {
    const sessions = selectedSessionClass.value?.sessions ?? [];

    return sessions
        .filter((session) => {
            if (sessionViewMode.value === 'all') return true;
            if (sessionViewMode.value === 'archived') return session.is_archived;
            return !session.is_archived;
        })
        .map((session: ClassSessionRecord) => ({
            id: String(session.id),
            title: session.title,
            date: session.date,
            time: session.time,
            coach: session.coach || '-',
            status: session.status,
            archive_state: session.is_archived ? 'Archived/Past' : 'Active/Upcoming',
            attendance_url: session.attendance_url || '',
        }));
});

function normalizeClassType(value?: string | null): string {
    const normalized = (value || 'reguler').toString().toLowerCase().replace(/\s+/g, '_');
    if (normalized === 'general') return 'reguler';
    return classTypeOptions.some((item) => item.value === normalized) ? normalized : 'reguler';
}

function normalizeScheduleMode(value?: string | null): ClassScheduleMode {
    return value === 'one_day' ? 'one_day' : 'weekly';
}

function classTypeLabel(value?: string | null): string {
    const option = classTypeOptions.find((item) => item.value === normalizeClassType(value));
    return option?.label ?? '-';
}

function privateAthleteLabel(item: ClassRecord): string {
    return normalizeClassType(item.class_type) === 'private' ? item.dedicated_athlete || '-' : '-';
}

function classFromRow(row: TableRow): ClassRecord | null {
    const id = Number(row.class_id ?? row.id);
    return props.classes.find((item) => item.id === id) ?? null;
}

function enforceActivationRules() {
    if (form.is_active && !classCanBeActive.value) form.is_active = false;
}

function resetForm() {
    editingClassId.value = null;
    form.clearErrors();
    form.name = '';
    form.class_type = 'reguler';
    form.training_group_id = '';
    form.dedicated_athlete_ids = [];
    form.coach_ids = [];
    form.schedule_mode = 'weekly';
    form.single_session_date = '';
    form.branch_id = '';
    form.day_of_week = '1';
    form.start_time = '16:00';
    form.end_time = '18:00';
    form.min_belt = '';
    form.description = '';
    form.is_active = false;
}

function openCreateClass() {
    resetForm();
    showClassForm.value = true;
}

function closeClassForm() {
    showClassForm.value = false;
    resetForm();
}

function closeAthleteModal() {
    selectedClass.value = null;
    selectedClassAthletes.value = [];
    athleteModalError.value = '';
    athleteModalLoading.value = false;
}

function closeSessionModal() {
    selectedSessionClass.value = null;
    sessionViewMode.value = 'active';
}

async function openClassAthletes(item: ClassRecord) {
    selectedClass.value = { ...item, athletes: item.athletes ?? [] };
    selectedClassAthletes.value = item.athletes ?? [];
    athleteModalError.value = '';
    athleteModalLoading.value = true;

    try {
        const response = await fetch(groupAthletes.url(item.id), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (!response.ok) throw new Error('Gagal mengambil daftar atlet.');
        const data = (await response.json()) as { athletes?: ClassAthleteRecord[] };
        selectedClassAthletes.value = data.athletes ?? [];
    } catch (error) {
        athleteModalError.value = error instanceof Error ? error.message : 'Gagal mengambil daftar atlet.';
    } finally {
        athleteModalLoading.value = false;
    }
}

function openClassAthletesFromRow(row: TableRow) {
    const item = classFromRow(row);
    if (item) void openClassAthletes(item);
}

function openClassSessionsFromRow(row: TableRow) {
    const item = classFromRow(row);
    if (item) selectedSessionClass.value = item;
}

function openAttendance(row: TableRow) {
    const url = String(row.attendance_url ?? '');
    if (url) router.visit(url);
}

function editClass(item: ClassRecord) {
    const classType = normalizeClassType(item.class_type);
    editingClassId.value = item.id;
    form.clearErrors();
    form.name = item.name;
    form.class_type = classType;
    form.training_group_id =
        classType === 'private' ? '' : item.training_group_id ? String(item.training_group_id) : '';
    form.dedicated_athlete_ids =
        classType === 'private' ? (item.dedicated_athlete_ids ?? []).map((id) => String(id)) : [];
    form.coach_ids = (item.coach_ids ?? (item.coach_id ? [item.coach_id] : [])).map((id) => String(id));
    form.schedule_mode = normalizeScheduleMode(item.schedule_mode);
    form.single_session_date = item.single_session_date ?? '';
    form.branch_id = item.branch_id ? String(item.branch_id) : '';
    form.day_of_week = String(item.day_of_week ?? 1);
    form.start_time = item.start_time || '16:00';
    form.end_time = item.end_time || '18:00';
    form.min_belt = item.min_belt ?? '';
    form.description = item.description ?? '';
    form.is_active = item.is_active;
    enforceActivationRules();
    showClassForm.value = true;
}

function editClassFromRow(row: TableRow) {
    const item = classFromRow(row);
    if (item) editClass(item);
}

function saveClass() {
    if (isPrivateClass.value) {
        form.training_group_id = '';
        form.min_belt = '';
    } else {
        form.dedicated_athlete_ids = [];
    }

    if (!isOneDayClass.value) form.single_session_date = '';

    enforceActivationRules();
    const options = { preserveScroll: true, onSuccess: closeClassForm };
    if (editingClassId.value) form.put(groupUpdate.url(editingClassId.value), options);
    else form.post(groupStore.url(), options);
}

async function deleteClass(item: ClassRecord): Promise<void> {
    const confirmed = await popup.confirm({
        title: 'Hapus atau nonaktifkan kelas?',
        message: `Kelas “${item.name}” akan dihapus bila belum memiliki riwayat. Kelas yang sudah memiliki sesi atau attendance akan dinonaktifkan agar seluruh riwayat tetap aman.`,
        tone: 'danger',
        confirmLabel: 'Lanjutkan',
    });
    if (!confirmed) return;

    router.delete(groupDestroy.url(item.id), { preserveScroll: true });
}

function deleteClassFromRow(row: TableRow) {
    const item = classFromRow(row);
    if (item) void deleteClass(item);
}

watch(
    () => [
        form.is_active,
        form.name,
        form.class_type,
        form.training_group_id,
        form.dedicated_athlete_ids.join(','),
        form.coach_ids.join(','),
        form.schedule_mode,
        form.single_session_date,
        form.branch_id,
        form.day_of_week,
        form.start_time,
        form.end_time,
    ],
    enforceActivationRules,
);
</script>

<template>
    <Head :title="props.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <DataTable
                title="Daftar Kelas"
                :description="props.subtitle"
                :columns="classTableColumns"
                :rows="classTableRows"
                searchable
                row-clickable
                row-click-label="Klik baris untuk melihat daftar atlet. Gunakan tombol mata untuk melihat semua sesi kelas."
                search-placeholder="Cari kelas, lokasi, atau pelatih..."
                empty-text="Belum ada kelas."
                action-label="Aksi"
                @row-click="openClassAthletesFromRow"
            >
                <template #actions>
                    <Button v-if="props.canCreateClasses" type="button" @click="openCreateClass">Tambah Kelas</Button>
                </template>

                <template #cell="{ row, column, value }">
                    <div v-if="column.key === 'class'" class="grid gap-0.5">
                        <span class="font-bold text-foreground">{{ value }}</span>
                        <span class="text-xs font-normal text-muted-foreground">{{ row.class_meta }}</span>
                    </div>
                    <div v-else-if="column.key === 'coach'" class="flex flex-wrap gap-1.5">
                        <span
                            v-for="coachName in Array.isArray(row.coach_names) ? row.coach_names : []"
                            :key="String(coachName)"
                            class="inline-flex items-center gap-1 rounded-full border border-primary/20 bg-primary/5 px-2.5 py-1 text-xs font-semibold text-primary"
                        >
                            <UserRoundCheck class="size-3.5" />
                            {{ coachName }}
                        </span>
                        <span
                            v-if="!Array.isArray(row.coach_names) || row.coach_names.length === 0"
                            class="text-muted-foreground"
                        >
                            Belum ada pelatih
                        </span>
                    </div>
                    <div v-else-if="column.key === 'schedule'" class="grid gap-0.5">
                        <span class="inline-flex items-center gap-1 font-medium text-foreground">
                            <CalendarDays class="size-3.5" /> {{ row.schedule_day }}
                        </span>
                        <span class="text-xs text-muted-foreground">{{ row.schedule_time }}</span>
                    </div>
                    <span
                        v-else-if="column.key === 'participants'"
                        class="inline-flex items-center gap-1 font-bold text-foreground"
                    >
                        <Users class="size-3.5" /> {{ value }}
                    </span>
                    <span
                        v-else-if="column.key === 'weekly_schedule_status'"
                        class="inline-flex rounded-full px-3 py-1 text-xs font-black"
                        :class="
                            String(value).toLowerCase().includes('active')
                                ? 'bg-blue-100 text-blue-700'
                                : 'bg-muted text-muted-foreground'
                        "
                    >
                        {{ value }}
                    </span>
                    <span
                        v-else-if="column.key === 'status'"
                        class="inline-flex rounded-full px-3 py-1 text-xs font-black"
                        :class="
                            row.status_tone === 'success'
                                ? 'bg-green-100 text-green-700'
                                : 'bg-muted text-muted-foreground'
                        "
                    >
                        {{ value }}
                    </span>
                    <span v-else>{{ value }}</span>
                </template>

                <template #row-actions="{ row }">
                    <ActionButtonsRow>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            title="Lihat sesi kelas"
                            @click="openClassSessionsFromRow(row)"
                        >
                            <Eye class="size-4" />
                        </Button>
                        <Button
                            v-if="props.canEditClasses"
                            type="button"
                            size="sm"
                            variant="outline"
                            title="Edit kelas"
                            @click="editClassFromRow(row)"
                        >
                            <Pencil class="size-4" />
                        </Button>
                        <Button
                            v-if="props.canDeleteClasses"
                            type="button"
                            size="sm"
                            variant="destructive"
                            title="Hapus/nonaktifkan kelas"
                            @click="deleteClassFromRow(row)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </ActionButtonsRow>
                </template>
            </DataTable>

            <FormModal :open="showClassForm" max-width-class="max-w-3xl" @close="closeClassForm">
                <form class="grid min-w-0 gap-4" @submit.prevent="saveClass">
                    <div>
                        <h2 class="text-xl font-black">{{ editingClassId ? 'Edit Kelas' : 'Tambah Kelas' }}</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Atur peserta, pelatih, lokasi, dan jadwal. Satu kelas dapat ditangani oleh beberapa pelatih.
                        </p>
                    </div>

                    <div class="grid gap-3">
                        <FormInputField
                            id="class-name"
                            v-model="form.name"
                            label="Nama Kelas"
                            placeholder="Contoh: Junior Sparring"
                            :error="form.errors.name"
                            required
                        />

                        <FormSelectField
                            id="class-type"
                            v-model="form.class_type"
                            label="Tipe Kelas"
                            :options="classTypeOptions"
                            placeholder="Pilih tipe kelas"
                            search-placeholder="Cari tipe kelas..."
                            :error="form.errors.class_type"
                            required
                        />

                        <FormSelectField
                            v-if="!isPrivateClass"
                            id="training-group"
                            v-model="form.training_group_id"
                            label="Kategori Atlet"
                            :options="props.trainingGroupOptions"
                            placeholder="Pilih kategori atlet"
                            search-placeholder="Cari kategori..."
                            help="Hanya atlet dari kategori ini yang dibuatkan presensi dan boleh scan QR kelas ini."
                            :error="form.errors.training_group_id"
                            required
                        />

                        <FormSelectField
                            v-if="isPrivateClass"
                            id="private-athletes"
                            v-model="form.dedicated_athlete_ids"
                            label="Atlet Private"
                            :options="athleteNameOptions"
                            placeholder="Pilih atlet private"
                            search-placeholder="Cari atlet..."
                            help="Pilih satu atau beberapa atlet. Hanya atlet ini yang dibuatkan presensi dan boleh scan QR kelas private."
                            :error="form.errors.dedicated_athlete_ids"
                            required
                            multiple
                        />

                        <section class="grid gap-3 rounded-xl border border-primary/20 bg-primary/5 p-4">
                            <div class="flex items-start gap-3">
                                <div class="rounded-lg bg-primary/10 p-2 text-primary">
                                    <UserRoundCheck class="size-5" />
                                </div>
                                <div class="min-w-0">
                                    <h3 class="font-bold text-foreground">Pelatih Kelas</h3>
                                    <p class="text-xs leading-5 text-muted-foreground">
                                        Pilih satu atau beberapa pelatih. Semua pelatih terpilih akan melihat dan
                                        menangani sesi yang dibuat dari kelas ini.
                                    </p>
                                </div>
                            </div>
                            <FormSelectField
                                id="class-coaches"
                                v-model="form.coach_ids"
                                label="Pelatih yang ditugaskan"
                                :options="props.coachOptions"
                                placeholder="Pilih pelatih"
                                search-placeholder="Cari nama pelatih..."
                                :error="form.errors.coach_ids"
                                :required="isPrivateClass"
                                multiple
                            />
                        </section>

                        <FormSelectField
                            id="schedule-mode"
                            v-model="form.schedule_mode"
                            label="Pola Jadwal"
                            :options="scheduleModeOptions"
                            placeholder="Pilih pola jadwal"
                            search-placeholder="Cari pola..."
                            :help="scheduleModeOptions.find((option) => option.value === form.schedule_mode)?.detail"
                            :error="form.errors.schedule_mode"
                            required
                        />

                        <FormSelectField
                            id="branch"
                            v-model="form.branch_id"
                            label="Lokasi"
                            :options="props.branchOptions"
                            placeholder="Pilih lokasi aktif"
                            search-placeholder="Cari lokasi..."
                            :error="form.errors.branch_id"
                            required
                        />

                        <FormInputField
                            v-if="isOneDayClass"
                            id="single-session-date"
                            v-model="form.single_session_date"
                            label="Tanggal Sesi"
                            type="date"
                            :error="form.errors.single_session_date"
                            required
                        />

                        <FormSelectField
                            v-else
                            id="day-of-week"
                            v-model="form.day_of_week"
                            label="Hari"
                            :options="dayOptions"
                            placeholder="Pilih hari"
                            search-placeholder="Cari hari..."
                            :error="form.errors.day_of_week"
                            required
                        />

                        <div class="grid gap-3 md:grid-cols-2">
                            <FormInputField
                                id="class-start-time"
                                v-model="form.start_time"
                                label="Mulai"
                                type="time"
                                :error="form.errors.start_time"
                                required
                            />
                            <FormInputField
                                id="class-end-time"
                                v-model="form.end_time"
                                label="Selesai"
                                type="time"
                                :error="form.errors.end_time"
                                required
                            />
                        </div>

                        <FormSelectField
                            v-if="!isPrivateClass"
                            id="minimum-belt"
                            v-model="form.min_belt"
                            label="Minimal Sabuk"
                            :options="props.beltOptions"
                            placeholder="Tanpa minimal"
                            search-placeholder="Cari sabuk..."
                            :error="form.errors.min_belt"
                        />

                        <label class="grid gap-1 text-sm font-semibold">
                            Deskripsi
                            <textarea
                                v-model="form.description"
                                class="min-h-20 rounded-lg border bg-background px-3 py-2 text-sm"
                                placeholder="Deskripsi kelas"
                            />
                        </label>

                        <label
                            class="grid gap-1 rounded-lg border bg-background px-3 py-2 text-sm font-semibold"
                            :class="!classCanBeActive ? 'opacity-80' : ''"
                        >
                            <span class="flex min-h-7 items-center gap-2">
                                <input
                                    v-model="form.is_active"
                                    type="checkbox"
                                    :disabled="!classCanBeActive"
                                    class="disabled:cursor-not-allowed disabled:opacity-50"
                                />
                                Aktif
                            </span>
                            <span
                                class="text-xs"
                                :class="classCanBeActive ? 'text-green-600' : 'text-muted-foreground'"
                            >
                                {{ activationHint }}
                            </span>
                        </label>

                        <div class="grid grid-cols-1 gap-2 sm:flex sm:justify-end">
                            <Button type="button" variant="outline" @click="closeClassForm">Batal</Button>
                            <Button type="submit" :disabled="form.processing">
                                {{ form.processing ? 'Menyimpan...' : 'Simpan Kelas' }}
                            </Button>
                        </div>
                    </div>
                </form>
            </FormModal>

            <FormModal :open="Boolean(selectedClass)" max-width-class="max-w-3xl" @close="closeAthleteModal">
                <section v-if="selectedClass" class="grid gap-4">
                    <div>
                        <p class="text-xs font-black tracking-wide text-red-500 uppercase">Daftar Atlet</p>
                        <h2 class="text-2xl font-black">{{ selectedClass.name }}</h2>
                        <p class="text-sm text-muted-foreground">
                            {{
                                normalizeClassType(selectedClass.class_type) === 'private'
                                    ? 'Atlet private'
                                    : `Kategori: ${selectedClass.training_group || 'Belum ada kategori'}`
                            }}
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Pelatih: {{ selectedClass.coach || 'Belum ada pelatih' }}
                        </p>
                    </div>
                    <p
                        v-if="athleteModalError"
                        class="rounded-lg border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                    >
                        {{ athleteModalError }}
                    </p>
                    <p v-if="athleteModalLoading" class="text-sm text-muted-foreground">Loading athletes...</p>
                    <DataTable
                        v-else
                        title="Atlet kelas"
                        description="Daftar atlet yang dapat mengikuti kelas ini."
                        :columns="athleteTableColumns"
                        :rows="selectedClassAthleteRows"
                        empty-text="Belum ada atlet."
                    />
                </section>
            </FormModal>

            <FormModal :open="Boolean(selectedSessionClass)" max-width-class="max-w-5xl" @close="closeSessionModal">
                <section v-if="selectedSessionClass" class="grid gap-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-black tracking-wide text-red-500 uppercase">Riwayat Sesi Kelas</p>
                            <h2 class="text-2xl font-black">{{ selectedSessionClass.name }}</h2>
                            <p class="text-sm text-muted-foreground">
                                {{ selectedSessionClass.active_sessions_count ?? 0 }} sesi aktif/mendatang ·
                                {{ selectedSessionClass.archived_sessions_count ?? 0 }} sesi arsip/past
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                size="sm"
                                :variant="sessionViewMode === 'active' ? 'default' : 'outline'"
                                @click="sessionViewMode = 'active'"
                            >
                                Aktif
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                :variant="sessionViewMode === 'archived' ? 'default' : 'outline'"
                                @click="sessionViewMode = 'archived'"
                            >
                                Archived/Past
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                :variant="sessionViewMode === 'all' ? 'default' : 'outline'"
                                @click="sessionViewMode = 'all'"
                            >
                                Semua
                            </Button>
                        </div>
                    </div>
                    <DataTable
                        title="Sesi kelas"
                        description="Buka presensi dari sesi tertentu tanpa pindah ke daftar semua sesi."
                        :columns="sessionTableColumns"
                        :rows="sessionRows"
                        empty-text="Belum ada sesi untuk kelas ini."
                        searchable
                        action-label="Presensi"
                    >
                        <template #row-actions="{ row }">
                            <Button type="button" size="sm" variant="outline" @click="openAttendance(row)">
                                Buka Presensi
                            </Button>
                        </template>
                    </DataTable>
                </section>
            </FormModal>
        </div>
    </AppLayout>
</template>
