<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type Option = { value: number | string; label: string };
type Branch = { id: number; name: string; location?: string | null; address?: string | null; city?: string | null; province?: string | null; latitude?: string | number | null; longitude?: string | number | null; attendance_radius_meters: number; timezone?: string | null; is_active: boolean; groups_count: number; athletes_count: number };
type Group = { id: number; name: string; class_type: string; branch_id?: number | null; branch: string; coach_id?: string | null; coach: string; day_of_week?: number | null; day_label: string; start_time: string; end_time: string; min_belt?: string | null; description?: string | null; athletes_count: number; is_active: boolean; weekly_schedule_id?: number | null; weekly_schedule_status: string };
type WeeklySchedule = { id: number; title: string; branch_id?: number | null; branch: string; group_id?: number | null; group: string; coach_id?: string | null; coach: string; day_of_week: number; day_label: string; start_time: string; end_time: string; location?: string | null; is_active: boolean; generated_sessions_count: number; can_manage: boolean };
type Session = { id: number; weekly_training_schedule_id?: number | null; title: string; date?: string | null; day_label: string; time: string; branch: string; group: string; coach: string; status: string };

const props = withDefaults(defineProps<{
    title?: string;
    subtitle?: string;
    redirectTo?: string | null;
    canManageStructure?: boolean;
    canManageSchedule?: boolean;
    currentCoachId?: string | null;
    weekRange?: { from: string; to: string };
    branches?: Branch[];
    groups?: Group[];
    weeklySchedules?: WeeklySchedule[];
    sessions?: Session[];
    branchOptions?: Option[];
    groupOptions?: Option[];
    coachOptions?: Option[];
    beltOptions?: Option[];
}>(), {
    title: 'Jadwal Latihan',
    subtitle: 'Lokasi → Kelas → Jadwal Mingguan → Sesi Latihan → Attendance / QR.',
    redirectTo: null,
    canManageStructure: false,
    canManageSchedule: false,
    currentCoachId: null,
    weekRange: () => ({ from: '', to: '' }),
    branches: () => [],
    groups: () => [],
    weeklySchedules: () => [],
    sessions: () => [],
    branchOptions: () => [],
    groupOptions: () => [],
    coachOptions: () => [],
    beltOptions: () => [],
});

onMounted(() => {
    if (props.redirectTo) window.location.href = props.redirectTo;
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: props.title, href: props.canManageStructure ? '/admin/training-management' : '/training-schedule' },
];

const activeTab = ref<'branches' | 'groups' | 'schedules' | 'sessions'>(props.canManageStructure ? 'branches' : 'schedules');
const editingBranchId = ref<number | null>(null);
const editingGroupId = ref<number | null>(null);
const editingScheduleId = ref<number | null>(null);

const dayOptions = [
    { value: 1, label: 'Senin' },
    { value: 2, label: 'Selasa' },
    { value: 3, label: 'Rabu' },
    { value: 4, label: 'Kamis' },
    { value: 5, label: 'Jumat' },
    { value: 6, label: 'Sabtu' },
    { value: 7, label: 'Minggu' },
];

const weekForm = useForm({ from: props.weekRange.from, to: props.weekRange.to });
const branchForm = useForm({ name: '', location: '', address: '', city: '', province: '', latitude: '', longitude: '', attendance_radius_meters: 100, timezone: 'Asia/Jakarta', is_active: true });
const groupForm = useForm({ name: '', class_type: 'General', branch_id: '', coach_id: '', day_of_week: 1, capacity: 0, start_time: '16:00', end_time: '18:00', min_belt: '', description: '', is_active: true });
const scheduleForm = useForm({ title: '', branch_id: '', group_id: '', coach_id: props.currentCoachId ?? '', day_of_week: 1, start_time: '16:00', end_time: '18:00', location: '', is_active: true });

const canShowStructure = computed(() => props.canManageStructure || props.branches.length > 0 || props.groups.length > 0);

function resetBranchForm() {
    editingBranchId.value = null;
    branchForm.clearErrors();
    branchForm.name = '';
    branchForm.location = '';
    branchForm.address = '';
    branchForm.city = '';
    branchForm.province = '';
    branchForm.latitude = '';
    branchForm.longitude = '';
    branchForm.attendance_radius_meters = 100;
    branchForm.timezone = 'Asia/Jakarta';
    branchForm.is_active = true;
}

function editBranch(branch: Branch) {
    editingBranchId.value = branch.id;
    branchForm.clearErrors();
    branchForm.name = branch.name;
    branchForm.location = branch.location ?? '';
    branchForm.address = branch.address ?? '';
    branchForm.city = branch.city ?? '';
    branchForm.province = branch.province ?? '';
    branchForm.latitude = branch.latitude === null || branch.latitude === undefined ? '' : String(branch.latitude);
    branchForm.longitude = branch.longitude === null || branch.longitude === undefined ? '' : String(branch.longitude);
    branchForm.attendance_radius_meters = branch.attendance_radius_meters ?? 100;
    branchForm.timezone = branch.timezone ?? 'Asia/Jakarta';
    branchForm.is_active = branch.is_active;
}

function saveBranch() {
    const options = { preserveScroll: true, onSuccess: resetBranchForm };
    if (editingBranchId.value) branchForm.put(`/admin/branches/${editingBranchId.value}`, options);
    else branchForm.post('/admin/branches', options);
}

function deleteBranch(branch: Branch) {
    if (window.confirm(`Delete/deactivate lokasi ${branch.name}?`)) router.delete(`/admin/branches/${branch.id}`, { preserveScroll: true });
}

function resetGroupForm() {
    editingGroupId.value = null;
    groupForm.clearErrors();
    groupForm.name = '';
    groupForm.class_type = 'General';
    groupForm.branch_id = '';
    groupForm.coach_id = '';
    groupForm.day_of_week = 1;
    groupForm.capacity = 0;
    groupForm.start_time = '16:00';
    groupForm.end_time = '18:00';
    groupForm.min_belt = props.beltOptions[0]?.value ? String(props.beltOptions[0].value) : '';
    groupForm.description = '';
    groupForm.is_active = true;
}

function editGroup(group: Group) {
    editingGroupId.value = group.id;
    groupForm.clearErrors();
    groupForm.name = group.name;
    groupForm.class_type = group.class_type;
    groupForm.branch_id = group.branch_id ? String(group.branch_id) : '';
    groupForm.coach_id = group.coach_id ?? '';
    groupForm.day_of_week = group.day_of_week ?? 1;
    groupForm.capacity = 0;
    groupForm.start_time = group.start_time || '16:00';
    groupForm.end_time = group.end_time || '18:00';
    groupForm.min_belt = group.min_belt ?? '';
    groupForm.description = group.description ?? '';
    groupForm.is_active = group.is_active;
}

function saveGroup() {
    const options = { preserveScroll: true, onSuccess: resetGroupForm };
    if (editingGroupId.value) groupForm.put(`/admin/groups/${editingGroupId.value}`, options);
    else groupForm.post('/admin/groups', options);
}

function deleteGroup(group: Group) {
    if (window.confirm(`Delete/deactivate kelas ${group.name}?`)) router.delete(`/admin/groups/${group.id}`, { preserveScroll: true });
}

function resetScheduleForm() {
    editingScheduleId.value = null;
    scheduleForm.clearErrors();
    scheduleForm.title = '';
    scheduleForm.branch_id = '';
    scheduleForm.group_id = '';
    scheduleForm.coach_id = props.currentCoachId ?? '';
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
    scheduleForm.day_of_week = schedule.day_of_week;
    scheduleForm.start_time = schedule.start_time || '16:00';
    scheduleForm.end_time = schedule.end_time || '18:00';
    scheduleForm.location = schedule.location ?? '';
    scheduleForm.is_active = schedule.is_active;
}

function saveSchedule() {
    const options = { preserveScroll: true, onSuccess: resetScheduleForm };
    if (editingScheduleId.value) scheduleForm.put(`/training-schedules/${editingScheduleId.value}`, options);
    else scheduleForm.post('/training-schedules', options);
}

function deleteSchedule(schedule: WeeklySchedule) {
    if (window.confirm(`Delete/deactivate jadwal ${schedule.title}?`)) router.delete(`/training-schedules/${schedule.id}`, { preserveScroll: true });
}

function generateSessions() {
    weekForm.post('/training-schedules/generate', { preserveScroll: true });
}

function applyWeekFilter() {
    router.get(props.canManageStructure ? '/admin/training-management' : '/training-schedule', { from: weekForm.from, to: weekForm.to }, { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <Head :title="props.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <section class="rounded-xl border bg-card p-5 shadow-sm">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-muted-foreground">Training flow</p>
                        <h1 class="text-2xl font-black">{{ props.title }}</h1>
                        <p class="mt-1 max-w-3xl text-sm text-muted-foreground">{{ props.subtitle }}</p>
                        <p class="mt-3 rounded-lg bg-muted px-3 py-2 text-sm">Lokasi adalah dojang fisik. Kelas adalah grup latihan di lokasi. Jadwal Mingguan adalah template berulang. Jadwal aktif otomatis membuat Sesi Latihan bertanggal setiap hari sesuai jadwal. Tombol Generate tetap tersedia untuk membuat sesi manual pada rentang tanggal tertentu. Attendance dan QR terjadi di Sesi Latihan.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button v-if="props.canManageSchedule" class="rounded-lg bg-primary px-4 py-2 text-sm font-bold text-primary-foreground" @click="generateSessions">Generate sessions manually</button>
                        <Link class="rounded-lg border px-4 py-2 text-sm font-bold" href="/sessions">Open Sessions</Link>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 md:grid-cols-4">
                <div class="rounded-xl border bg-card p-4"><p class="text-xs text-muted-foreground">Lokasi</p><p class="text-2xl font-black">{{ props.branches.length }}</p></div>
                <div class="rounded-xl border bg-card p-4"><p class="text-xs text-muted-foreground">Kelas</p><p class="text-2xl font-black">{{ props.groups.length }}</p></div>
                <div class="rounded-xl border bg-card p-4"><p class="text-xs text-muted-foreground">Jadwal Mingguan</p><p class="text-2xl font-black">{{ props.weeklySchedules.length }}</p></div>
                <div class="rounded-xl border bg-card p-4"><p class="text-xs text-muted-foreground">Sesi di range</p><p class="text-2xl font-black">{{ props.sessions.length }}</p></div>
            </section>

            <section class="rounded-xl border bg-card p-4 shadow-sm">
                <div class="flex flex-wrap gap-2">
                    <button v-if="canShowStructure" class="rounded-lg px-4 py-2 text-sm font-bold" :class="activeTab === 'branches' ? 'bg-primary text-primary-foreground' : 'bg-muted'" @click="activeTab = 'branches'">Lokasi</button>
                    <button v-if="canShowStructure" class="rounded-lg px-4 py-2 text-sm font-bold" :class="activeTab === 'groups' ? 'bg-primary text-primary-foreground' : 'bg-muted'" @click="activeTab = 'groups'">Kelas</button>
                    <button class="rounded-lg px-4 py-2 text-sm font-bold" :class="activeTab === 'schedules' ? 'bg-primary text-primary-foreground' : 'bg-muted'" @click="activeTab = 'schedules'">Jadwal Mingguan</button>
                    <button class="rounded-lg px-4 py-2 text-sm font-bold" :class="activeTab === 'sessions' ? 'bg-primary text-primary-foreground' : 'bg-muted'" @click="activeTab = 'sessions'">Sesi Latihan</button>
                </div>
            </section>

            <section v-if="activeTab === 'branches'" class="grid gap-4 lg:grid-cols-[380px_1fr]">
                <form v-if="props.canManageStructure" class="rounded-xl border bg-card p-5 shadow-sm" @submit.prevent="saveBranch">
                    <h2 class="text-lg font-black">{{ editingBranchId ? 'Edit Lokasi' : 'Tambah Lokasi' }}</h2>
                    <div class="mt-4 grid gap-3">
                        <input v-model="branchForm.name" class="rounded-lg border bg-background px-3 py-2" placeholder="Nama lokasi / dojang" />
                        <input v-model="branchForm.location" class="rounded-lg border bg-background px-3 py-2" placeholder="Label lokasi" />
                        <textarea v-model="branchForm.address" class="rounded-lg border bg-background px-3 py-2" placeholder="Alamat" />
                        <div class="grid grid-cols-2 gap-2"><input v-model="branchForm.city" class="rounded-lg border bg-background px-3 py-2" placeholder="Kota" /><input v-model="branchForm.province" class="rounded-lg border bg-background px-3 py-2" placeholder="Provinsi" /></div>
                        <div class="grid grid-cols-2 gap-2"><input v-model="branchForm.latitude" class="rounded-lg border bg-background px-3 py-2" placeholder="Latitude" /><input v-model="branchForm.longitude" class="rounded-lg border bg-background px-3 py-2" placeholder="Longitude" /></div>
                        <input v-model="branchForm.attendance_radius_meters" type="number" class="rounded-lg border bg-background px-3 py-2" placeholder="Radius absensi meter" />
                        <label class="flex items-center gap-2 text-sm"><input v-model="branchForm.is_active" type="checkbox" /> Aktif</label>
                        <div class="flex gap-2"><button class="rounded-lg bg-primary px-4 py-2 text-sm font-bold text-primary-foreground">Save</button><button type="button" class="rounded-lg border px-4 py-2 text-sm font-bold" @click="resetBranchForm">Reset</button></div>
                    </div>
                </form>
                <div class="overflow-x-auto rounded-xl border bg-card shadow-sm">
                    <table class="w-full min-w-[760px] text-sm"><thead><tr class="border-b text-left"><th class="p-3">Lokasi</th><th class="p-3">Alamat</th><th class="p-3">Kelas</th><th class="p-3">Status</th><th class="p-3">Aksi</th></tr></thead><tbody>
                        <tr v-if="props.branches.length === 0"><td colspan="5" class="p-6 text-center text-muted-foreground">No locations yet. Create a location first.</td></tr>
                        <tr v-for="branch in props.branches" :key="branch.id" class="border-b"><td class="p-3 font-bold">{{ branch.name }}<p class="text-xs font-normal text-muted-foreground">{{ branch.location }}</p></td><td class="p-3">{{ branch.address ?? '-' }}<p class="text-xs text-muted-foreground">{{ branch.city }} {{ branch.province }}</p></td><td class="p-3">{{ branch.groups_count }} kelas · {{ branch.athletes_count }} atlet</td><td class="p-3">{{ branch.is_active ? 'Aktif' : 'Nonaktif' }}</td><td class="p-3"><div v-if="props.canManageStructure" class="flex gap-2"><button class="rounded border px-2 py-1" @click="editBranch(branch)">Edit</button><button class="rounded border px-2 py-1" @click="deleteBranch(branch)">Delete</button></div></td></tr>
                    </tbody></table>
                </div>
            </section>

            <section v-if="activeTab === 'groups'" class="grid gap-4 lg:grid-cols-[380px_1fr]">
                <form v-if="props.canManageStructure" class="rounded-xl border bg-card p-5 shadow-sm" @submit.prevent="saveGroup">
                    <h2 class="text-lg font-black">{{ editingGroupId ? 'Edit Kelas' : 'Tambah Kelas' }}</h2>
                    <div class="mt-4 grid gap-3">
                        <input v-model="groupForm.name" class="rounded-lg border bg-background px-3 py-2" placeholder="Nama kelas" />
                        <select v-model="groupForm.branch_id" class="rounded-lg border bg-background px-3 py-2"><option value="">Pilih lokasi</option><option v-for="option in props.branchOptions" :key="option.value" :value="String(option.value)">{{ option.label }}</option></select>
                        <select v-model="groupForm.coach_id" class="rounded-lg border bg-background px-3 py-2"><option value="">Pilih coach</option><option v-for="option in props.coachOptions" :key="option.value" :value="String(option.value)">{{ option.label }}</option></select>
                        <select v-model="groupForm.day_of_week" class="rounded-lg border bg-background px-3 py-2"><option v-for="day in dayOptions" :key="day.value" :value="day.value">{{ day.label }}</option></select>
                        <div class="grid grid-cols-2 gap-2"><input v-model="groupForm.start_time" type="time" class="rounded-lg border bg-background px-3 py-2" /><input v-model="groupForm.end_time" type="time" class="rounded-lg border bg-background px-3 py-2" /></div>
                        <select v-model="groupForm.min_belt" class="rounded-lg border bg-background px-3 py-2"><option value="">Minimal sabuk</option><option v-for="option in props.beltOptions" :key="option.value" :value="String(option.value)">{{ option.label }}</option></select>
                        <textarea v-model="groupForm.description" class="rounded-lg border bg-background px-3 py-2" placeholder="Deskripsi" />
                        <label class="flex items-center gap-2 text-sm"><input v-model="groupForm.is_active" type="checkbox" /> Aktif</label>
                        <div class="flex gap-2"><button class="rounded-lg bg-primary px-4 py-2 text-sm font-bold text-primary-foreground">Save</button><button type="button" class="rounded-lg border px-4 py-2 text-sm font-bold" @click="resetGroupForm">Reset</button></div>
                    </div>
                </form>
                <div class="overflow-x-auto rounded-xl border bg-card shadow-sm"><table class="w-full min-w-[900px] text-sm"><thead><tr class="border-b text-left"><th class="p-3">Kelas</th><th class="p-3">Lokasi</th><th class="p-3">Coach</th><th class="p-3">Jadwal</th><th class="p-3">Link Schedule</th><th class="p-3">Aksi</th></tr></thead><tbody>
                    <tr v-if="props.groups.length === 0"><td colspan="6" class="p-6 text-center text-muted-foreground">No classes yet. Create a class and attach it to a location.</td></tr>
                    <tr v-for="group in props.groups" :key="group.id" class="border-b"><td class="p-3 font-bold">{{ group.name }}<p class="text-xs font-normal text-muted-foreground">{{ group.class_type }} · {{ group.athletes_count }} atlet</p></td><td class="p-3">{{ group.branch }}</td><td class="p-3">{{ group.coach }}</td><td class="p-3">{{ group.day_label }} {{ group.start_time }}-{{ group.end_time }}</td><td class="p-3">{{ group.weekly_schedule_status }}</td><td class="p-3"><div v-if="props.canManageStructure" class="flex gap-2"><button class="rounded border px-2 py-1" @click="editGroup(group)">Edit</button><button class="rounded border px-2 py-1" @click="deleteGroup(group)">Delete</button></div></td></tr>
                </tbody></table></div>
            </section>

            <section v-if="activeTab === 'schedules'" class="grid gap-4 lg:grid-cols-[380px_1fr]">
                <form v-if="props.canManageSchedule" class="rounded-xl border bg-card p-5 shadow-sm" @submit.prevent="saveSchedule">
                    <h2 class="text-lg font-black">{{ editingScheduleId ? 'Edit Jadwal Mingguan' : 'Tambah Jadwal Mingguan' }}</h2>
                    <div class="mt-4 grid gap-3">
                        <input v-model="scheduleForm.title" class="rounded-lg border bg-background px-3 py-2" placeholder="Judul jadwal" />
                        <select v-model="scheduleForm.branch_id" class="rounded-lg border bg-background px-3 py-2"><option value="">Pilih lokasi</option><option v-for="option in props.branchOptions" :key="option.value" :value="String(option.value)">{{ option.label }}</option></select>
                        <select v-model="scheduleForm.group_id" class="rounded-lg border bg-background px-3 py-2"><option value="">All groups</option><option v-for="option in props.groupOptions" :key="option.value" :value="String(option.value)">{{ option.label }}</option></select>
                        <select v-if="!props.currentCoachId" v-model="scheduleForm.coach_id" class="rounded-lg border bg-background px-3 py-2"><option value="">Pilih coach</option><option v-for="option in props.coachOptions" :key="option.value" :value="String(option.value)">{{ option.label }}</option></select>
                        <select v-model="scheduleForm.day_of_week" class="rounded-lg border bg-background px-3 py-2"><option v-for="day in dayOptions" :key="day.value" :value="day.value">{{ day.label }}</option></select>
                        <div class="grid grid-cols-2 gap-2"><input v-model="scheduleForm.start_time" type="time" class="rounded-lg border bg-background px-3 py-2" /><input v-model="scheduleForm.end_time" type="time" class="rounded-lg border bg-background px-3 py-2" /></div>
                        <input v-model="scheduleForm.location" class="rounded-lg border bg-background px-3 py-2" placeholder="Override lokasi opsional" />
                        <label class="flex items-center gap-2 text-sm"><input v-model="scheduleForm.is_active" type="checkbox" /> Aktif</label>
                        <div class="flex gap-2"><button class="rounded-lg bg-primary px-4 py-2 text-sm font-bold text-primary-foreground">Save</button><button type="button" class="rounded-lg border px-4 py-2 text-sm font-bold" @click="resetScheduleForm">Reset</button></div>
                    </div>
                </form>
                <div class="overflow-x-auto rounded-xl border bg-card shadow-sm"><table class="w-full min-w-[900px] text-sm"><thead><tr class="border-b text-left"><th class="p-3">Jadwal</th><th class="p-3">Lokasi</th><th class="p-3">Kelas</th><th class="p-3">Coach</th><th class="p-3">Auto-created</th><th class="p-3">Aksi</th></tr></thead><tbody>
                    <tr v-if="props.weeklySchedules.length === 0"><td colspan="6" class="p-6 text-center text-muted-foreground">No weekly schedules yet. Create a class or schedule.</td></tr>
                    <tr v-for="schedule in props.weeklySchedules" :key="schedule.id" class="border-b"><td class="p-3 font-bold">{{ schedule.title }}<p class="text-xs font-normal text-muted-foreground">{{ schedule.day_label }} {{ schedule.start_time }}-{{ schedule.end_time }} · {{ schedule.is_active ? 'Aktif' : 'Nonaktif' }}</p></td><td class="p-3">{{ schedule.branch }}</td><td class="p-3">{{ schedule.group }}</td><td class="p-3">{{ schedule.coach }}</td><td class="p-3">{{ schedule.generated_sessions_count }} sesi di range</td><td class="p-3"><div v-if="schedule.can_manage" class="flex gap-2"><button class="rounded border px-2 py-1" @click="editSchedule(schedule)">Edit</button><button class="rounded border px-2 py-1" @click="deleteSchedule(schedule)">Delete</button></div></td></tr>
                </tbody></table></div>
            </section>

            <section v-if="activeTab === 'sessions'" class="rounded-xl border bg-card p-5 shadow-sm">
                <div class="mb-4 flex flex-wrap items-end gap-2">
                    <label class="grid gap-1 text-sm">From<input v-model="weekForm.from" type="date" class="rounded-lg border bg-background px-3 py-2" /></label>
                    <label class="grid gap-1 text-sm">To<input v-model="weekForm.to" type="date" class="rounded-lg border bg-background px-3 py-2" /></label>
                    <button class="rounded-lg border px-4 py-2 text-sm font-bold" @click="applyWeekFilter">Apply</button>
                    <button v-if="props.canManageSchedule" class="rounded-lg bg-primary px-4 py-2 text-sm font-bold text-primary-foreground" @click="generateSessions">Generate for range</button>
                </div>
                <div class="overflow-x-auto"><table class="w-full min-w-[900px] text-sm"><thead><tr class="border-b text-left"><th class="p-3">Sesi</th><th class="p-3">Tanggal</th><th class="p-3">Lokasi</th><th class="p-3">Kelas</th><th class="p-3">Coach</th><th class="p-3">Status</th><th class="p-3">Attendance</th></tr></thead><tbody>
                    <tr v-if="props.sessions.length === 0"><td colspan="7" class="p-6 text-center text-muted-foreground">No sessions in this range yet. Active schedules are generated automatically each day, or click Generate for range to create them manually.</td></tr>
                    <tr v-for="session in props.sessions" :key="session.id" class="border-b"><td class="p-3 font-bold">{{ session.title }}<p class="text-xs font-normal text-muted-foreground">From schedule #{{ session.weekly_training_schedule_id ?? '-' }}</p></td><td class="p-3">{{ session.day_label }}<br />{{ session.date }} · {{ session.time }}</td><td class="p-3">{{ session.branch }}</td><td class="p-3">{{ session.group }}</td><td class="p-3">{{ session.coach }}</td><td class="p-3">{{ session.status }}</td><td class="p-3"><Link class="rounded border px-2 py-1" :href="`/sessions/${session.id}/attendance`">Open</Link></td></tr>
                </tbody></table></div>
            </section>
        </div>
    </AppLayout>
</template>
