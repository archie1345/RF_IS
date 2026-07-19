<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, RefreshCcw, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import ActionButtonsRow from '@/components/shared/ActionButtonsRow.vue';
import DataTable from '@/components/shared/DataTable.vue';
import FormModal from '@/components/shared/FormModal.vue';
import { Button } from '@/components/ui/button';
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
import type { TableColumn, TableFilter, TableRow } from '@/types/resource-table';
import type { ClassAthleteRecord, ClassRecord, ClassScheduleMode, SelectOption } from '@/types/training';

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
    }>(),
    {
        title: 'Kelas Latihan',
        subtitle: 'Master data kelas. Private adalah tipe kelas dengan satu atau beberapa atlet khusus.',
        classes: () => [],
        branchOptions: () => [],
        trainingGroupOptions: () => [],
        athleteOptions: () => [],
        coachOptions: () => [],
        beltOptions: () => [],
    },
);

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
    { key: 'category', label: 'Kategori' },
    { key: 'private_athlete', label: 'Atlet Private' },
    { key: 'branch', label: 'Lokasi' },
    { key: 'coach', label: 'Coach' },
    { key: 'schedule', label: 'Jadwal' },
    { key: 'participants', label: 'Peserta' },
    { key: 'weekly_schedule_status', label: 'Schedule' },
    { key: 'status', label: 'Status' },
];

const athleteTableColumns: TableColumn[] = [
    { key: 'name', label: 'Atlet' },
    { key: 'branch', label: 'Branch' },
    { key: 'training_group', label: 'Kategori' },
    { key: 'geup', label: 'Geup' },
];

const classTableFilters = computed<TableFilter[]>(() => [
    { key: 'class', label: 'Kelas', type: 'text', columnKey: 'class', placeholder: 'Cari kelas...' },
    {
        key: 'class_type_label',
        label: 'Tipe Kelas',
        type: 'select',
        columnKey: 'class_type_label',
        placeholder: 'Semua tipe',
        options: classTypeOptions.map((option) => ({ value: option.label, label: option.label })),
    },
    { key: 'category', label: 'Kategori', type: 'select', columnKey: 'category', placeholder: 'Semua kategori' },
    { key: 'branch', label: 'Lokasi', type: 'select', columnKey: 'branch', placeholder: 'Semua lokasi' },
    { key: 'coach', label: 'Coach', type: 'select', columnKey: 'coach', placeholder: 'Semua coach' },
    { key: 'status', label: 'Status', type: 'select', columnKey: 'status', placeholder: 'Semua status' },
]);

const editingClassId = ref<number | null>(null);
const selectedClass = ref<ClassRecord | null>(null);
const selectedClassAthletes = ref<ClassAthleteRecord[]>([]);
const athleteModalLoading = ref(false);
const athleteModalError = ref('');
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
    schedule_mode: 'weekly' as ClassScheduleMode,
    single_session_date: '',
    branch_id: '',
    coach_id: '',
    day_of_week: '1',
    start_time: '16:00',
    end_time: '18:00',
    min_belt: '',
    description: '',
    is_active: false,
});

const isPrivateClass = computed(() => form.class_type === 'private');
const isOneDayClass = computed(() => form.schedule_mode === 'one_day');

const classCanBeActive = computed(() => {
    return Boolean(
        form.name.trim() &&
            form.class_type &&
            (isPrivateClass.value ? form.dedicated_athlete_ids.length > 0 : form.training_group_id) &&
            form.branch_id &&
            form.start_time &&
            form.end_time &&
            (isOneDayClass.value ? form.single_session_date : form.day_of_week) &&
            (!isPrivateClass.value || form.coach_id),
    );
});

const activationHint = computed(() => {
    if (classCanBeActive.value) {
        return isPrivateClass.value
            ? 'Data lengkap. Kelas private hanya membuat presensi untuk atlet-atlet khusus yang dipilih.'
            : 'Data lengkap. Kelas hanya bisa diikuti atlet dari kategori yang dipilih.';
    }

    return isPrivateClass.value
        ? 'Lengkapi nama, tipe private, atlet private, coach private, lokasi aktif, jadwal/tanggal, jam mulai, dan jam selesai.'
        : 'Lengkapi nama, tipe kelas, kategori atlet, lokasi aktif, jadwal/tanggal, jam mulai, dan jam selesai.';
});

const classTableRows = computed<TableRow[]>(() =>
    props.classes.map((item) => ({
        id: String(item.id),
        class_id: item.id,
        class: `${item.name} · ${classTypeLabel(item.class_type)} · min ${item.min_belt || '-'}`,
        class_type_label: classTypeLabel(item.class_type),
        category: normalizeClassType(item.class_type) === 'private' ? '-' : item.training_group || 'Belum ada kategori',
        private_athlete: privateAthleteLabel(item),
        branch: item.branch || '-',
        coach: normalizeClassType(item.class_type) === 'private' ? item.coach || '-' : '-',
        schedule: `${item.day_label} · ${item.start_time} - ${item.end_time}`,
        participants: `${item.athletes_count} atlet`,
        weekly_schedule_status: item.weekly_schedule_status,
        status: { kind: 'badge', text: item.is_active ? 'AKTIF' : 'NONAKTIF', tone: item.is_active ? 'success' : 'neutral' },
    })),
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

function normalizeClassType(value?: string | null): string {
    const normalized = (value || 'reguler').toString().toLowerCase().replace(/\s+/g, '_');
    if (normalized === 'general') return 'reguler';
    return classTypeOptions.some((item) => item.value === normalized) ? normalized : 'reguler';
}

function normalizeScheduleMode(value?: string | null): ClassScheduleMode {
    return value === 'one_day' ? 'one_day' : 'weekly';
}

function classTypeLabel(value?: string | null): string {
    const normalized = normalizeClassType(value);
    const option = classTypeOptions.find((item) => item.value === normalized);
    return option?.label ?? '-';
}

function scheduleModeLabel(value?: string | null): string {
    const normalized = normalizeScheduleMode(value);
    return scheduleModeOptions.find((option) => option.value === normalized)?.label ?? 'Mingguan';
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
    form.schedule_mode = 'weekly';
    form.single_session_date = '';
    form.branch_id = '';
    form.coach_id = '';
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

function editClass(item: ClassRecord) {
    const classType = normalizeClassType(item.class_type);
    const scheduleMode = normalizeScheduleMode(item.schedule_mode);
    editingClassId.value = item.id;
    form.clearErrors();
    form.name = item.name;
    form.class_type = classType;
    form.training_group_id = classType === 'private' ? '' : item.training_group_id ? String(item.training_group_id) : '';
    form.dedicated_athlete_ids = classType === 'private'
        ? (item.dedicated_athlete_ids ?? []).map((id) => String(id))
        : [];
    form.schedule_mode = scheduleMode;
    form.single_session_date = item.single_session_date ?? '';
    form.branch_id = item.branch_id ? String(item.branch_id) : '';
    form.coach_id = classType === 'private' ? (item.coach_id ?? '') : '';
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
        form.coach_id = '';
        form.dedicated_athlete_ids = [];
    }

    if (!isOneDayClass.value) form.single_session_date = '';

    enforceActivationRules();
    const options = { preserveScroll: true, onSuccess: closeClassForm };
    if (editingClassId.value) form.put(groupUpdate.url(editingClassId.value), options);
    else form.post(groupStore.url(), options);
}

function deleteClass(item: ClassRecord) {
    if (window.confirm(`Delete/deactivate kelas ${item.name}?`)) {
        router.delete(groupDestroy.url(item.id), { preserveScroll: true });
    }
}

function deleteClassFromRow(row: TableRow) {
    const item = classFromRow(row);
    if (item) deleteClass(item);
}

watch(
    () => [
        form.is_active,
        form.name,
        form.class_type,
        form.training_group_id,
        form.dedicated_athlete_ids.join(','),
        form.schedule_mode,
        form.single_session_date,
        form.branch_id,
        form.coach_id,
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
            <section class="rounded-2xl border bg-card p-5 shadow-sm">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h1 class="text-3xl font-black">{{ props.title }}</h1>
                        <p class="mt-1 text-sm text-muted-foreground">{{ props.subtitle }}</p>
                    </div>
                    <Button type="button" variant="outline" class="gap-2" @click="router.reload()">
                        <RefreshCcw class="size-4" /> Refresh
                    </Button>
                </div>
            </section>

            <DataTable
                title="Daftar Kelas"
                description="Kelola kelas, kategori, jadwal, dan peserta dari tabel bersama agar filter dan pagination konsisten."
                :columns="classTableColumns"
                :rows="classTableRows"
                :filters="classTableFilters"
                filterable
                searchable
                search-placeholder="Cari kelas, kategori, atlet, lokasi, atau coach..."
                empty-text="Belum ada kelas."
                action-label="Aksi"
            >
                <template #row-actions="{ row }">
                    <ActionButtonsRow>
                        <Button type="button" size="sm" variant="outline" @click="openClassAthletesFromRow(row)">Peserta</Button>
                        <Button type="button" size="sm" variant="outline" @click="editClassFromRow(row)">
                            <Pencil class="size-4" />
                        </Button>
                        <Button type="button" size="sm" variant="destructive" @click="deleteClassFromRow(row)">
                            <Trash2 class="size-4" />
                        </Button>
                    </ActionButtonsRow>
                </template>
            </DataTable>

            <Button type="button" class="w-fit" @click="openCreateClass">Tambah Kelas</Button>

            <FormModal :open="showClassForm" max-width-class="max-w-3xl" @close="closeClassForm">
                <form class="grid gap-4" @submit.prevent="saveClass">
                    <h2 class="text-xl font-black">{{ editingClassId ? 'Edit Kelas' : 'Tambah Kelas' }}</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Kategori membatasi kelas reguler. Untuk kelas private, pilih Tipe Kelas = Private lalu pilih satu atau beberapa atlet private.
                    </p>

                    <div class="mt-5 grid gap-3">
                        <label class="grid gap-1 text-sm font-semibold">
                            Nama Kelas *
                            <input v-model="form.name" class="h-10 rounded-lg border bg-background px-3 text-sm" placeholder="Contoh: Junior Sparring" />
                            <span v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</span>
                        </label>

                        <FormSelectField id="class-type" v-model="form.class_type" label="Tipe Kelas" :options="classTypeOptions" placeholder="Pilih tipe kelas" search-placeholder="Cari tipe kelas..." :error="form.errors.class_type" required />

                        <FormSelectField v-if="!isPrivateClass" id="training-group" v-model="form.training_group_id" label="Kategori Atlet" :options="props.trainingGroupOptions" placeholder="Pilih kategori atlet" search-placeholder="Cari kategori..." help="Hanya atlet dari kategori ini yang dibuatkan presensi dan boleh scan QR kelas ini." :error="form.errors.training_group_id" required />

                        <FormSelectField v-if="isPrivateClass" id="private-athletes" v-model="form.dedicated_athlete_ids" label="Atlet Private" :options="athleteNameOptions" placeholder="Pilih atlet private" search-placeholder="Cari atlet..." help="Pilih satu atau beberapa atlet. Hanya atlet ini yang dibuatkan presensi dan boleh scan QR kelas private." :error="form.errors.dedicated_athlete_ids" required multiple />

                        <FormSelectField id="schedule-mode" v-model="form.schedule_mode" label="Pola Jadwal" :options="scheduleModeOptions" placeholder="Pilih pola jadwal" search-placeholder="Cari pola..." :help="scheduleModeOptions.find((option) => option.value === form.schedule_mode)?.detail" :error="form.errors.schedule_mode" required />

                        <FormSelectField id="branch" v-model="form.branch_id" label="Lokasi" :options="props.branchOptions" placeholder="Pilih lokasi aktif" search-placeholder="Cari lokasi..." :error="form.errors.branch_id" required />

                        <FormSelectField v-if="isPrivateClass" id="private-coach" v-model="form.coach_id" label="Coach Private" :options="props.coachOptions" placeholder="Pilih coach private" search-placeholder="Cari coach..." :error="form.errors.coach_id" required />

                        <label v-if="isOneDayClass" class="grid gap-1 text-sm font-semibold">
                            Tanggal Sesi *
                            <input v-model="form.single_session_date" type="date" class="h-10 rounded-lg border bg-background px-3 text-sm" />
                            <span v-if="form.errors.single_session_date" class="text-xs text-destructive">{{ form.errors.single_session_date }}</span>
                        </label>

                        <FormSelectField v-else id="day-of-week" v-model="form.day_of_week" label="Hari" :options="dayOptions" placeholder="Pilih hari" search-placeholder="Cari hari..." :error="form.errors.day_of_week" required />

                        <div class="grid gap-3 md:grid-cols-2">
                            <label class="grid gap-1 text-sm font-semibold">Mulai *<input v-model="form.start_time" type="time" class="h-10 rounded-lg border bg-background px-3 text-sm" /></label>
                            <label class="grid gap-1 text-sm font-semibold">Selesai *<input v-model="form.end_time" type="time" class="h-10 rounded-lg border bg-background px-3 text-sm" /></label>
                        </div>

                        <FormSelectField v-if="!isPrivateClass" id="minimum-belt" v-model="form.min_belt" label="Minimal Sabuk" :options="props.beltOptions" placeholder="Tanpa minimal" search-placeholder="Cari sabuk..." :error="form.errors.min_belt" />

                        <label class="grid gap-1 text-sm font-semibold">
                            Deskripsi
                            <textarea v-model="form.description" class="min-h-20 rounded-lg border bg-background px-3 py-2 text-sm" placeholder="Deskripsi kelas"></textarea>
                        </label>

                        <label class="grid gap-1 rounded-lg border bg-background px-3 py-2 text-sm font-semibold" :class="!classCanBeActive ? 'opacity-80' : ''">
                            <span class="flex h-7 items-center gap-2">
                                <input v-model="form.is_active" type="checkbox" :disabled="!classCanBeActive" class="disabled:cursor-not-allowed disabled:opacity-50" /> Aktif
                            </span>
                            <span class="text-xs" :class="classCanBeActive ? 'text-green-600' : 'text-muted-foreground'">{{ activationHint }}</span>
                        </label>

                        <div class="flex gap-2">
                            <Button type="submit" :disabled="form.processing">{{ form.processing ? 'Saving...' : 'Save Kelas' }}</Button>
                            <Button type="button" variant="outline" @click="closeClassForm">Batal</Button>
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
                            {{ normalizeClassType(selectedClass.class_type) === 'private' ? 'Atlet private' : `Kategori: ${selectedClass.training_group || 'Belum ada kategori'}` }}
                        </p>
                    </div>
                    <p v-if="athleteModalError" class="rounded-lg border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">{{ athleteModalError }}</p>
                    <p v-if="athleteModalLoading" class="text-sm text-muted-foreground">Loading athletes...</p>
                    <DataTable v-else title="Atlet kelas" description="Daftar atlet yang dapat mengikuti kelas ini." :columns="athleteTableColumns" :rows="selectedClassAthleteRows" empty-text="Belum ada atlet." />
                </section>
            </FormModal>
        </div>
    </AppLayout>
</template>
