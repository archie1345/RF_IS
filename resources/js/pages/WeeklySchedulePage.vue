<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import WeeklyScheduleBoard from '@/features/training/components/WeeklyScheduleBoard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { SelectOption, WeeklySchedule } from '@/types/training';

const props = withDefaults(
    defineProps<{
        title?: string;
        subtitle?: string;
        canManageSchedule?: boolean;
        currentCoachId?: string | null;
        weekRange?: { from: string; to: string };
        weeklySchedules?: WeeklySchedule[];
        branchOptions?: SelectOption[];
        groupOptions?: SelectOption[];
        coachOptions?: SelectOption[];
        athleteOptions?: SelectOption[];
    }>(),
    {
        title: 'Jadwal Latihan',
        subtitle: 'Jadwal latihan rutin Rhino Fighter',
        canManageSchedule: false,
        currentCoachId: null,
        weekRange: () => ({ from: '', to: '' }),
        weeklySchedules: () => [],
        branchOptions: () => [],
        groupOptions: () => [],
        coachOptions: () => [],
        athleteOptions: () => [],
    },
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: props.title, href: '/training-schedule' },
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

const editingScheduleId = ref<number | null>(null);
const scheduleView = ref<'cards' | 'table'>('cards');

const scheduleForm = useForm({
    title: '',
    branch_id: '',
    group_id: '',
    coach_id: props.currentCoachId ?? '',
    session_type: 'reguler',
    dedicated_athlete_id: '',
    day_of_week: 1,
    start_time: '16:00',
    end_time: '18:00',
    location: '',
    is_active: true,
});

const scheduleFormTitle = computed(() => (editingScheduleId.value ? 'Edit Jadwal Mingguan' : 'Tambah Jadwal Mingguan'));
const requiresDedicatedAthlete = computed(() => scheduleForm.session_type === 'private');
const canSubmitSchedule = computed(() => !requiresDedicatedAthlete.value || Boolean(scheduleForm.dedicated_athlete_id));

function resetScheduleForm() {
    editingScheduleId.value = null;
    scheduleForm.clearErrors();
    scheduleForm.title = '';
    scheduleForm.branch_id = '';
    scheduleForm.group_id = '';
    scheduleForm.coach_id = props.currentCoachId ?? '';
    scheduleForm.session_type = 'reguler';
    scheduleForm.dedicated_athlete_id = '';
    scheduleForm.day_of_week = 1;
    scheduleForm.start_time = '16:00';
    scheduleForm.end_time = '18:00';
    scheduleForm.location = '';
    scheduleForm.is_active = true;
}

function editSchedule(schedule: WeeklySchedule) {
    editingScheduleId.value = schedule.id;
    scheduleForm.clearErrors();
    scheduleForm.title = schedule.title;
    scheduleForm.branch_id = schedule.branch_id ? String(schedule.branch_id) : '';
    scheduleForm.group_id = schedule.group_id ? String(schedule.group_id) : '';
    scheduleForm.coach_id = schedule.coach_id ?? props.currentCoachId ?? '';
    scheduleForm.session_type = schedule.session_type ?? 'reguler';
    scheduleForm.dedicated_athlete_id = schedule.dedicated_athlete_id ? String(schedule.dedicated_athlete_id) : '';
    scheduleForm.day_of_week = schedule.day_of_week;
    scheduleForm.start_time = schedule.start_time || '16:00';
    scheduleForm.end_time = schedule.end_time || '18:00';
    scheduleForm.location = schedule.location ?? '';
    scheduleForm.is_active = schedule.is_active ?? true;
}

function saveSchedule() {
    if (!canSubmitSchedule.value) {
        scheduleForm.setError('dedicated_athlete_id', 'Pilih atlet untuk sesi private/dedicated.');
        return;
    }

    if (!requiresDedicatedAthlete.value) scheduleForm.dedicated_athlete_id = '';
    if (requiresDedicatedAthlete.value) scheduleForm.group_id = '';

    const options = { preserveScroll: true, onSuccess: resetScheduleForm };
    if (editingScheduleId.value) scheduleForm.put(`/training-schedules/${editingScheduleId.value}`, options);
    else scheduleForm.post('/training-schedules', options);
}

function deleteSchedule(schedule: WeeklySchedule) {
    if (window.confirm(`Delete/deactivate jadwal ${schedule.title}?`))
        router.delete(`/training-schedules/${schedule.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="props.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <div v-if="props.canManageSchedule" class="flex flex-wrap justify-end gap-2">
                <button
                    type="button"
                    class="rounded-lg border px-4 py-2 text-sm font-bold"
                    :class="scheduleView === 'cards' ? 'bg-primary text-primary-foreground' : 'bg-card'"
                    @click="scheduleView = 'cards'"
                >
                    Desain Lama
                </button>
                <button
                    type="button"
                    class="rounded-lg border px-4 py-2 text-sm font-bold"
                    :class="scheduleView === 'table' ? 'bg-primary text-primary-foreground' : 'bg-card'"
                    @click="scheduleView = 'table'"
                >
                    Tabel
                </button>
            </div>

            <WeeklyScheduleBoard
                v-if="!props.canManageSchedule || scheduleView === 'cards'"
                :schedules="props.weeklySchedules"
                :can-manage="props.canManageSchedule"
                :show-management-hint="false"
                :title="props.title"
                :subtitle="props.subtitle"
                @edit="editSchedule"
                @delete="deleteSchedule"
                @refresh="router.reload()"
            />

            <section
                v-if="props.canManageSchedule && scheduleView === 'table'"
                class="rounded-2xl border bg-card p-5 shadow-sm"
            >
                <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-xs font-black tracking-wide text-red-500 uppercase">Admin / Coach only</p>
                        <h1 class="text-3xl font-black">{{ props.title }}</h1>
                        <p class="mt-1 text-sm text-muted-foreground">{{ props.subtitle }}</p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-10 items-center justify-center rounded-lg border px-4 text-sm font-bold"
                        @click="router.reload()"
                    >
                        Refresh
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[980px] text-sm">
                        <thead>
                            <tr class="border-b text-left">
                                <th class="px-3 py-3 font-black">Jadwal</th>
                                <th class="px-3 py-3 font-black">Hari</th>
                                <th class="px-3 py-3 font-black">Waktu</th>
                                <th class="px-3 py-3 font-black">Lokasi</th>
                                <th class="px-3 py-3 font-black">Tipe</th>
                                <th class="px-3 py-3 font-black">Kelas / Atlet</th>
                                <th class="px-3 py-3 font-black">Coach</th>
                                <th class="px-3 py-3 font-black">Generated</th>
                                <th class="px-3 py-3 font-black">Status</th>
                                <th class="px-3 py-3 font-black">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="props.weeklySchedules.length === 0">
                                <td colspan="10" class="h-32 px-3 text-center text-muted-foreground">
                                    Belum ada jadwal latihan.
                                </td>
                            </tr>
                            <tr
                                v-for="schedule in props.weeklySchedules"
                                :key="schedule.id"
                                class="border-b hover:bg-muted/40"
                            >
                                <td class="px-3 py-4">
                                    <p class="font-black">{{ schedule.title }}</p>
                                    <p class="text-xs text-muted-foreground">{{ schedule.class_type ?? '-' }}</p>
                                </td>
                                <td class="px-3 py-4">{{ schedule.day_label ?? '-' }}</td>
                                <td class="px-3 py-4">
                                    {{ schedule.start_time || '--:--' }} - {{ schedule.end_time || '--:--' }}
                                </td>
                                <td class="px-3 py-4">{{ schedule.location || schedule.branch || '-' }}</td>
                                <td class="px-3 py-4 font-bold capitalize">
                                    {{ (schedule.session_type ?? 'reguler').replace('_', ' ') }}
                                </td>
                                <td class="px-3 py-4">
                                    {{
                                        schedule.session_type === 'private'
                                            ? schedule.dedicated_athlete || '-'
                                            : schedule.group || 'All groups'
                                    }}
                                </td>
                                <td class="px-3 py-4">{{ schedule.coach || 'Belum ada coach' }}</td>
                                <td class="px-3 py-4">{{ schedule.generated_sessions_count ?? 0 }} sesi</td>
                                <td class="px-3 py-4">
                                    <span
                                        class="rounded-full px-3 py-1 text-xs font-black"
                                        :class="
                                            schedule.is_active === false
                                                ? 'bg-slate-100 text-slate-500'
                                                : 'bg-green-100 text-green-700'
                                        "
                                        >{{ schedule.is_active === false ? 'NONAKTIF' : 'AKTIF' }}</span
                                    >
                                </td>
                                <td class="px-3 py-4">
                                    <div class="flex gap-2">
                                        <button
                                            type="button"
                                            class="rounded border px-2 py-1"
                                            :disabled="!schedule.can_manage"
                                            @click="editSchedule(schedule)"
                                        >
                                            Edit</button
                                        ><button
                                            type="button"
                                            class="rounded border px-2 py-1 text-red-600"
                                            :disabled="!schedule.can_manage"
                                            @click="deleteSchedule(schedule)"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section v-if="props.canManageSchedule" class="rounded-2xl border bg-card p-5 shadow-sm">
                <div class="mb-5">
                    <p class="text-xs font-black tracking-wide text-red-500 uppercase">Admin / Coach only</p>
                    <h2 class="text-2xl font-black">{{ scheduleFormTitle }}</h2>
                    <p class="text-sm text-muted-foreground">
                        Form pembuatan jadwal dipindah ke bawah board agar alur tetap: lihat jadwal dulu, baru tambah
                        atau edit bila punya akses.
                    </p>
                </div>

                <form class="grid gap-3 md:grid-cols-4 md:items-end" @submit.prevent="saveSchedule">
                    <label class="grid gap-1 text-sm font-semibold md:col-span-2">
                        Judul Jadwal
                        <input
                            v-model="scheduleForm.title"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                            placeholder="Contoh: Junior Sparring"
                        />
                        <span v-if="scheduleForm.errors.title" class="text-xs text-destructive">{{
                            scheduleForm.errors.title
                        }}</span>
                    </label>

                    <label class="grid gap-1 text-sm font-semibold">
                        Lokasi
                        <select
                            v-model="scheduleForm.branch_id"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                        >
                            <option value="">Pilih lokasi</option>
                            <option
                                v-for="option in props.branchOptions"
                                :key="String(option.value)"
                                :value="String(option.value)"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                        <span v-if="scheduleForm.errors.branch_id" class="text-xs text-destructive">{{
                            scheduleForm.errors.branch_id
                        }}</span>
                    </label>

                    <label class="grid gap-1 text-sm font-semibold">
                        Kelas
                        <select
                            v-model="scheduleForm.group_id"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                        >
                            <option value="">All groups</option>
                            <option
                                v-for="option in props.groupOptions"
                                :key="String(option.value)"
                                :value="String(option.value)"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </label>

                    <label class="grid gap-1 text-sm font-semibold">
                        Tipe Sesi
                        <select
                            v-model="scheduleForm.session_type"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                        >
                            <option value="reguler">Reguler</option>
                            <option value="prestasi">Prestasi</option>
                            <option value="private">Private / Dedicated</option>
                            <option value="pemula">Pemula</option>
                            <option value="sparring">Sparring</option>
                        </select>
                    </label>

                    <label v-if="requiresDedicatedAthlete" class="grid gap-1 text-sm font-semibold">
                        Atlet Dedicated
                        <select
                            v-model="scheduleForm.dedicated_athlete_id"
                            required
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                        >
                            <option value="">Pilih atlet</option>
                            <option
                                v-for="option in props.athleteOptions"
                                :key="String(option.value)"
                                :value="String(option.value)"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                        <span v-if="scheduleForm.errors.dedicated_athlete_id" class="text-xs text-destructive">{{
                            scheduleForm.errors.dedicated_athlete_id
                        }}</span>
                    </label>

                    <label v-if="!props.currentCoachId" class="grid gap-1 text-sm font-semibold">
                        Coach
                        <select
                            v-model="scheduleForm.coach_id"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                        >
                            <option value="">Pilih coach</option>
                            <option
                                v-for="option in props.coachOptions"
                                :key="String(option.value)"
                                :value="String(option.value)"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </label>

                    <label class="grid gap-1 text-sm font-semibold">
                        Hari
                        <select
                            v-model="scheduleForm.day_of_week"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                        >
                            <option v-for="day in dayOptions" :key="day.value" :value="day.value">
                                {{ day.label }}
                            </option>
                        </select>
                    </label>

                    <label class="grid gap-1 text-sm font-semibold">
                        Mulai
                        <input
                            v-model="scheduleForm.start_time"
                            type="time"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                        />
                    </label>

                    <label class="grid gap-1 text-sm font-semibold">
                        Selesai
                        <input
                            v-model="scheduleForm.end_time"
                            type="time"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                        />
                    </label>

                    <label class="grid gap-1 text-sm font-semibold md:col-span-2">
                        Override Lokasi Opsional
                        <input
                            v-model="scheduleForm.location"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                            placeholder="Kosongkan untuk pakai lokasi dojang"
                        />
                    </label>

                    <label
                        class="flex h-10 items-center gap-2 rounded-lg border bg-background px-3 text-sm font-semibold"
                    >
                        <input v-model="scheduleForm.is_active" type="checkbox" /> Aktif
                    </label>

                    <div class="flex gap-2 md:col-span-4">
                        <button
                            class="rounded-lg bg-primary px-4 py-2 text-sm font-bold text-primary-foreground"
                            :disabled="scheduleForm.processing || !canSubmitSchedule"
                        >
                            {{
                                scheduleForm.processing
                                    ? 'Saving...'
                                    : editingScheduleId
                                      ? 'Update Jadwal'
                                      : 'Save Jadwal'
                            }}
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border px-4 py-2 text-sm font-bold"
                            @click="resetScheduleForm"
                        >
                            Reset
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </AppLayout>
</template>
