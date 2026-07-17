<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { CalendarDays, Pencil, RefreshCcw, Trash2, Users } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import FormModal from '@/components/shared/FormModal.vue';
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
import type { ClassAthleteRecord, ClassRecord, ClassScheduleMode, SelectOption } from '@/types/training';

const props = withDefaults(
    defineProps<{
        title?: string;
        subtitle?: string;
        classes?: ClassRecord[];
        branchOptions?: SelectOption[];
        trainingGroupOptions?: SelectOption[];
        coachOptions?: SelectOption[];
        beltOptions?: SelectOption[];
    }>(),
    {
        title: 'Kelas Latihan',
        subtitle: 'Master data kelas. Jadwal mingguan atau kelas satu hari otomatis membuat sesi latihan.',
        classes: () => [],
        branchOptions: () => [],
        trainingGroupOptions: () => [],
        coachOptions: () => [],
        beltOptions: () => [],
    },
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard.url() },
    { title: props.title, href: adminClasses.url() },
];

const dayOptions = [
    { value: 1, label: 'Senin' },
    { value: 2, label: 'Selasa' },
    { value: 3, label: 'Rabu' },
    { value: 4, label: 'Kamis' },
    { value: 5, label: 'Jumat' },
    { value: 6, label: 'Sabtu' },
    { value: 7, label: 'Minggu' },
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

const editingClassId = ref<number | null>(null);
const selectedClass = ref<ClassRecord | null>(null);
const selectedClassAthletes = ref<ClassAthleteRecord[]>([]);
const athleteModalLoading = ref(false);
const athleteModalError = ref('');
const showClassForm = ref(false);
const search = ref('');

const form = useForm({
    name: '',
    training_group_id: '',
    class_type: 'reguler',
    schedule_mode: 'weekly' as ClassScheduleMode,
    single_session_date: '',
    branch_id: '',
    coach_id: '',
    day_of_week: 1,
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
            form.training_group_id &&
            form.class_type &&
            form.branch_id &&
            form.start_time &&
            form.end_time &&
            (isOneDayClass.value ? form.single_session_date : form.day_of_week) &&
            (!isPrivateClass.value || form.coach_id),
    );
});

const activationHint = computed(() => {
    if (classCanBeActive.value) {
        return isOneDayClass.value
            ? 'Data lengkap. Kelas sekali jalan akan membuat satu sesi pada tanggal ini dan hanya grup terpilih yang bisa ikut.'
            : 'Data lengkap. Kelas mingguan akan menyinkronkan sesi otomatis dan hanya grup terpilih yang bisa ikut.';
    }

    return isPrivateClass.value
        ? 'Lengkapi nama, grup, tipe, lokasi aktif, coach private, jadwal/tanggal, jam mulai, dan jam selesai sebelum kelas bisa aktif.'
        : 'Lengkapi nama, grup, tipe, lokasi aktif, jadwal/tanggal, jam mulai, dan jam selesai sebelum kelas bisa aktif.';
});

const filteredClasses = computed(() => {
    const keyword = search.value.trim().toLowerCase();
    if (!keyword) return props.classes;

    return props.classes.filter((item) =>
        [item.name, item.training_group, classTypeLabel(item.class_type), scheduleModeLabel(item.schedule_mode), item.branch, item.coach, item.day_label, item.min_belt]
            .filter(Boolean)
            .join(' ')
            .toLowerCase()
            .includes(keyword),
    );
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
    const normalized = normalizeClassType(value);
    const option = classTypeOptions.find((item) => item.value === normalized);
    return option?.label ?? '-';
}

function scheduleModeLabel(value?: string | null): string {
    const normalized = normalizeScheduleMode(value);
    return scheduleModeOptions.find((item) => item.value === normalized)?.label ?? 'Mingguan';
}

function enforceActivationRules() {
    if (form.is_active && !classCanBeActive.value) {
        form.is_active = false;
    }
}

function resetForm() {
    editingClassId.value = null;
    form.clearErrors();
    form.name = '';
    form.training_group_id = '';
    form.class_type = 'reguler';
    form.schedule_mode = 'weekly';
    form.single_session_date = '';
    form.branch_id = '';
    form.coach_id = '';
    form.day_of_week = 1;
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

        if (!response.ok) {
            throw new Error('Gagal mengambil daftar atlet.');
        }

        const data = (await response.json()) as { athletes?: ClassAthleteRecord[] };
        selectedClassAthletes.value = data.athletes ?? [];
    } catch (error) {
        athleteModalError.value = error instanceof Error ? error.message : 'Gagal mengambil daftar atlet.';
    } finally {
        athleteModalLoading.value = false;
    }
}

function editClass(item: ClassRecord) {
    const classType = normalizeClassType(item.class_type);
    const scheduleMode = normalizeScheduleMode(item.schedule_mode);
    editingClassId.value = item.id;
    form.clearErrors();
    form.name = item.name;
    form.training_group_id = item.training_group_id ? String(item.training_group_id) : '';
    form.class_type = classType;
    form.schedule_mode = scheduleMode;
    form.single_session_date = item.single_session_date ?? '';
    form.branch_id = item.branch_id ? String(item.branch_id) : '';
    form.coach_id = classType === 'private' ? (item.coach_id ?? '') : '';
    form.day_of_week = item.day_of_week ?? 1;
    form.start_time = item.start_time || '16:00';
    form.end_time = item.end_time || '18:00';
    form.min_belt = item.min_belt ?? '';
    form.description = item.description ?? '';
    form.is_active = item.is_active;
    enforceActivationRules();
    showClassForm.value = true;
}

function saveClass() {
    if (!isPrivateClass.value) {
        form.coach_id = '';
    }

    if (!isOneDayClass.value) {
        form.single_session_date = '';
    }

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

watch(
    () => [
        form.is_active,
        form.name,
        form.training_group_id,
        form.class_type,
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
                    <button type="button" class="inline-flex h-10 items-center justify-center rounded-lg border px-4 text-sm font-bold" @click="router.reload()">
                        <RefreshCcw class="mr-2 size-4" /> Refresh
                    </button>
                </div>
            </section>

            <section class="rounded-2xl border bg-card p-5 shadow-sm">
                <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div class="flex items-center gap-2">
                        <h2 class="text-xl font-black">Daftar Kelas</h2>
                        <button type="button" class="rounded-lg bg-primary px-3 py-2 text-sm font-bold text-primary-foreground" @click="openCreateClass">
                            Tambah Kelas
                        </button>
                    </div>
                    <input v-model="search" class="h-10 rounded-lg border bg-background px-3 text-sm md:w-72" placeholder="Cari kelas/grup..." />
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1080px] text-sm">
                        <thead>
                            <tr class="border-b text-left">
                                <th class="px-3 py-3 font-black">Kelas</th>
                                <th class="px-3 py-3 font-black">Grup</th>
                                <th class="px-3 py-3 font-black">Lokasi</th>
                                <th class="px-3 py-3 font-black">Coach</th>
                                <th class="px-3 py-3 font-black">Jadwal</th>
                                <th class="px-3 py-3 font-black">Peserta</th>
                                <th class="px-3 py-3 font-black">Schedule</th>
                                <th class="px-3 py-3 font-black">Status</th>
                                <th class="px-3 py-3 font-black">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="filteredClasses.length === 0">
                                <td colspan="9" class="h-32 px-3 text-center text-muted-foreground">Belum ada kelas.</td>
                            </tr>
                            <tr v-for="item in filteredClasses" :key="item.id" class="cursor-pointer border-b hover:bg-muted/40" @click="openClassAthletes(item)">
                                <td class="max-w-[260px] px-3 py-4">
                                    <p class="truncate font-black">{{ item.name }}</p>
                                    <p class="truncate text-xs text-muted-foreground">
                                        {{ classTypeLabel(item.class_type) }} · {{ scheduleModeLabel(item.schedule_mode) }} · min {{ item.min_belt || '-' }}
                                    </p>
                                </td>
                                <td class="px-3 py-4 font-semibold">{{ item.training_group || 'Belum ada grup' }}</td>
                                <td class="max-w-[200px] px-3 py-4"><p class="truncate">{{ item.branch }}</p></td>
                                <td class="max-w-[200px] px-3 py-4"><p class="truncate">{{ normalizeClassType(item.class_type) === 'private' ? item.coach : '-' }}</p></td>
                                <td class="px-3 py-4">
                                    <CalendarDays class="mr-1 inline size-3" />{{ item.day_label }}
                                    <p class="text-xs text-muted-foreground">{{ item.start_time }} - {{ item.end_time }}</p>
                                </td>
                                <td class="px-3 py-4"><span class="inline-flex items-center gap-1 font-bold"><Users class="size-3" /> {{ item.athletes_count }} atlet</span></td>
                                <td class="px-3 py-4">{{ item.weekly_schedule_status }}</td>
                                <td class="px-3 py-4">
                                    <span class="rounded-full px-3 py-1 text-xs font-black" :class="item.is_active ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'">{{ item.is_active ? 'AKTIF' : 'NONAKTIF' }}</span>
                                </td>
                                <td class="px-3 py-4">
                                    <div class="flex gap-2">
                                        <button type="button" class="rounded border px-2 py-1" @click.stop="editClass(item)"><Pencil class="size-4" /></button>
                                        <button type="button" class="rounded border px-2 py-1 text-red-600" @click.stop="deleteClass(item)"><Trash2 class="size-4" /></button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <FormModal :open="showClassForm" max-width-class="max-w-3xl" @close="closeClassForm">
                <form class="grid gap-4" @submit.prevent="saveClass">
                    <h2 class="text-xl font-black">{{ editingClassId ? 'Edit Kelas' : 'Tambah Kelas' }}</h2>
                    <p class="mt-1 text-sm text-muted-foreground">Pilih grup wajib agar kelas hanya bisa diikuti atlet dari kategori yang sama.</p>

                    <div class="mt-5 grid gap-3">
                        <label class="grid gap-1 text-sm font-semibold">
                            Nama Kelas *
                            <input v-model="form.name" class="h-10 rounded-lg border bg-background px-3 text-sm" placeholder="Contoh: Junior Sparring" />
                            <span v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</span>
                        </label>

                        <label class="grid gap-1 text-sm font-semibold">
                            Grup Atlet *
                            <select v-model="form.training_group_id" class="h-10 rounded-lg border bg-background px-3 text-sm">
                                <option value="">Pilih grup atlet</option>
                                <option v-for="option in props.trainingGroupOptions" :key="String(option.value)" :value="String(option.value)">{{ option.label }}</option>
                            </select>
                            <span class="text-xs text-muted-foreground">Hanya atlet dari grup ini yang dibuatkan presensi dan boleh scan QR kelas ini.</span>
                            <span v-if="form.errors.training_group_id" class="text-xs text-destructive">{{ form.errors.training_group_id }}</span>
                        </label>

                        <label class="grid gap-1 text-sm font-semibold">
                            Tipe Kelas *
                            <select v-model="form.class_type" class="h-10 rounded-lg border bg-background px-3 text-sm">
                                <option v-for="option in classTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                            <span v-if="form.errors.class_type" class="text-xs text-destructive">{{ form.errors.class_type }}</span>
                        </label>

                        <label class="grid gap-1 text-sm font-semibold">
                            Pola Jadwal *
                            <select v-model="form.schedule_mode" class="h-10 rounded-lg border bg-background px-3 text-sm">
                                <option v-for="option in scheduleModeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                            <span class="text-xs text-muted-foreground">{{ scheduleModeOptions.find((option) => option.value === form.schedule_mode)?.detail }}</span>
                            <span v-if="form.errors.schedule_mode" class="text-xs text-destructive">{{ form.errors.schedule_mode }}</span>
                        </label>

                        <label class="grid gap-1 text-sm font-semibold">
                            Lokasi *
                            <select v-model="form.branch_id" class="h-10 rounded-lg border bg-background px-3 text-sm">
                                <option value="">Pilih lokasi aktif</option>
                                <option v-for="option in props.branchOptions" :key="String(option.value)" :value="String(option.value)">{{ option.label }}</option>
                            </select>
                        </label>

                        <label v-if="isPrivateClass" class="grid gap-1 text-sm font-semibold">
                            Coach Private *
                            <select v-model="form.coach_id" class="h-10 rounded-lg border bg-background px-3 text-sm">
                                <option value="">Pilih coach private</option>
                                <option v-for="option in props.coachOptions" :key="String(option.value)" :value="String(option.value)">{{ option.label }}</option>
                            </select>
                            <span v-if="form.errors.coach_id" class="text-xs text-destructive">{{ form.errors.coach_id }}</span>
                        </label>

                        <label v-if="isOneDayClass" class="grid gap-1 text-sm font-semibold">
                            Tanggal Sesi *
                            <input v-model="form.single_session_date" type="date" class="h-10 rounded-lg border bg-background px-3 text-sm" />
                            <span v-if="form.errors.single_session_date" class="text-xs text-destructive">{{ form.errors.single_session_date }}</span>
                        </label>

                        <label v-else class="grid gap-1 text-sm font-semibold">
                            Hari *
                            <select v-model="form.day_of_week" class="h-10 rounded-lg border bg-background px-3 text-sm">
                                <option v-for="day in dayOptions" :key="day.value" :value="day.value">{{ day.label }}</option>
                            </select>
                        </label>

                        <div class="grid gap-3 md:grid-cols-2">
                            <label class="grid gap-1 text-sm font-semibold">Mulai *<input v-model="form.start_time" type="time" class="h-10 rounded-lg border bg-background px-3 text-sm" /></label>
                            <label class="grid gap-1 text-sm font-semibold">Selesai *<input v-model="form.end_time" type="time" class="h-10 rounded-lg border bg-background px-3 text-sm" /></label>
                        </div>

                        <label class="grid gap-1 text-sm font-semibold">
                            Minimal Sabuk
                            <select v-model="form.min_belt" class="h-10 rounded-lg border bg-background px-3 text-sm">
                                <option value="">Tanpa minimal</option>
                                <option v-for="option in props.beltOptions" :key="String(option.value)" :value="String(option.value)">{{ option.label }}</option>
                            </select>
                            <span v-if="form.errors.min_belt" class="text-xs text-destructive">{{ form.errors.min_belt }}</span>
                        </label>

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
                            <button class="rounded-lg bg-primary px-4 py-2 text-sm font-bold text-primary-foreground" :disabled="form.processing">{{ form.processing ? 'Saving...' : 'Save Kelas' }}</button>
                            <button type="button" class="rounded-lg border px-4 py-2 text-sm font-bold" @click="closeClassForm">Batal</button>
                        </div>
                    </div>
                </form>
            </FormModal>

            <FormModal :open="Boolean(selectedClass)" max-width-class="max-w-3xl" @close="closeAthleteModal">
                <section v-if="selectedClass" class="grid gap-4">
                    <div>
                        <p class="text-xs font-black tracking-wide text-red-500 uppercase">Daftar Atlet</p>
                        <h2 class="text-2xl font-black">{{ selectedClass.name }}</h2>
                        <p class="text-sm text-muted-foreground">Grup: {{ selectedClass.training_group || 'Belum ada grup' }}</p>
                    </div>
                    <p v-if="athleteModalError" class="rounded-lg border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">{{ athleteModalError }}</p>
                    <p v-if="athleteModalLoading" class="text-sm text-muted-foreground">Loading athletes...</p>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead><tr class="border-b text-left"><th class="px-3 py-3 font-black">Atlet</th><th class="px-3 py-3 font-black">Branch</th><th class="px-3 py-3 font-black">Grup</th><th class="px-3 py-3 font-black">Geup</th></tr></thead>
                            <tbody>
                                <tr v-if="selectedClassAthletes.length === 0"><td colspan="4" class="h-24 px-3 text-center text-muted-foreground">Belum ada atlet.</td></tr>
                                <tr v-for="athlete in selectedClassAthletes" :key="athlete.id" class="border-b">
                                    <td class="px-3 py-3 font-semibold">{{ athlete.name }}</td>
                                    <td class="px-3 py-3">{{ athlete.branch || '-' }}</td>
                                    <td class="px-3 py-3">{{ athlete.training_group || '-' }}</td>
                                    <td class="px-3 py-3">{{ athlete.geup || '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </FormModal>
        </div>
    </AppLayout>
</template>
