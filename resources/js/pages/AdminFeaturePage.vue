<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CalendarDays, Download, MapPin, Pencil, Plus, RefreshCcw, Search, Trash2, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import FormModal from '@/components/shared/FormModal.vue';
import PageSection from '@/components/shared/PageSection.vue';
import StatCard from '@/components/shared/StatCard.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { Metric } from '@/types/resource-table';

type WeeklySession = { title: string; time: string; location: string; date?: string };
type WeeklySchedule = { id: number; title: string; branch: string; group: string; coach: string; day_of_week: number; time: string; location: string; is_active: boolean };
type SelectOption = { value: string | number; label: string };
type BillingSettings = { invoice_day: number; invoice_time: string; default_amount: string; is_active: boolean };
type ManagedLocation = { id: number; name: string; location?: string | null; address?: string | null; city?: string | null; province?: string | null; latitude?: string | number | null; longitude?: string | number | null; attendance_radius_meters: number; timezone?: string | null; is_active: boolean; groups_count?: number };
type ManagedClass = { id: number; name: string; class_type: string; coach_id?: string | null; coach: string; branch_id?: number | string | null; branch: string; day_of_week: number; schedule: string; time: string; start_time: string; end_time: string; capacity: number; athletes_count: number; min_belt?: string | null; description?: string | null; is_active: boolean };

const props = withDefaults(
    defineProps<{
        mode: string;
        title: string;
        subtitle: string;
        metrics?: Metric[];
        columns?: string[];
        rows?: Record<string, string>[];
        emptyText?: string;
        roleAccess?: string;
        todaySessions?: WeeklySession[];
        billingSettings?: BillingSettings | null;
        weeklySchedules?: WeeklySchedule[];
        branchOptions?: SelectOption[];
        groupOptions?: SelectOption[];
        coachOptions?: SelectOption[];
        locations?: ManagedLocation[];
        classes?: ManagedClass[];
    }>(),
    {
        metrics: () => [], columns: () => [], rows: () => [], emptyText: 'Tidak ada data', roleAccess: 'Admin only', todaySessions: () => [], billingSettings: null,
        weeklySchedules: () => [], branchOptions: () => [], groupOptions: () => [], coachOptions: () => [], locations: () => [], classes: () => [],
    },
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: props.title, href: `/admin/${props.mode}` },
];

const dayCards = [
    { id: 1, name: 'Senin', sub: 'Monday' }, { id: 2, name: 'Selasa', sub: 'Tuesday' }, { id: 3, name: 'Rabu', sub: 'Wednesday' },
    { id: 4, name: 'Kamis', sub: 'Thursday' }, { id: 5, name: 'Jumat', sub: 'Friday' }, { id: 6, name: 'Sabtu', sub: 'Saturday' }, { id: 7, name: 'Minggu', sub: 'Sunday' },
];

const showLocationForm = ref(false);
const showClassForm = ref(false);
const editingLocationId = ref<number | null>(null);
const editingClassId = ref<number | null>(null);

const billingForm = useForm({ invoice_day: props.billingSettings?.invoice_day ?? 1, invoice_time: props.billingSettings?.invoice_time ?? '01:10', default_amount: props.billingSettings?.default_amount ?? '150000', is_active: props.billingSettings?.is_active ?? true });
const weeklyForm = useForm({ title: '', branch_id: '', group_id: '', coach_id: '', day_of_week: 1, start_time: '', end_time: '', location: '', is_active: true });
const locationForm = useForm({ name: '', location: '', address: '', city: '', province: '', latitude: '-6.2088', longitude: '106.8456', attendance_radius_meters: 100, timezone: 'Asia/Jakarta', is_active: true });
const classForm = useForm({ name: '', class_type: 'Beginner', coach_id: '', branch_id: '', day_of_week: 1, capacity: 20, start_time: '16:00', end_time: '18:00', min_belt: '10th Geup - White Belt', description: '', is_active: true });

const sessionsByDay = computed(() => {
    const grouped = new Map<number, WeeklySession[]>();
    props.todaySessions.forEach((session) => {
        if (!session.date) return;
        const day = new Date(`${session.date}T00:00:00`).getDay();
        const mondayFirstDay = day === 0 ? 7 : day;
        grouped.set(mondayFirstDay, [...(grouped.get(mondayFirstDay) ?? []), session]);
    });
    return grouped;
});

const weeklySchedulesByDay = computed(() => {
    const grouped = new Map<number, WeeklySchedule[]>();
    props.weeklySchedules.forEach((schedule) => grouped.set(schedule.day_of_week, [...(grouped.get(schedule.day_of_week) ?? []), schedule]));
    return grouped;
});

const mapUrl = computed(() => {
    const lat = Number(locationForm.latitude || -6.2088);
    const lng = Number(locationForm.longitude || 106.8456);
    const bbox = `${lng - 0.01},${lat - 0.01},${lng + 0.01},${lat + 0.01}`;
    return `https://www.openstreetmap.org/export/embed.html?bbox=${encodeURIComponent(bbox)}&layer=mapnik&marker=${encodeURIComponent(`${lat},${lng}`)}`;
});

function generateMonthlyDues() { router.post('/admin/monthly-dues/generate', {}, { preserveScroll: true }); }
function saveBillingSettings() { billingForm.post('/admin/monthly-dues/settings', { preserveScroll: true }); }
function generateWeeklySessions() { router.post('/admin/schedules/generate-week', {}, { preserveScroll: true }); }
function saveWeeklySchedule() { weeklyForm.post('/admin/schedules', { preserveScroll: true, onSuccess: () => weeklyForm.reset() }); }

function openLocationForm(location?: ManagedLocation) {
    locationForm.clearErrors();
    editingLocationId.value = location?.id ?? null;
    locationForm.name = location?.name ?? '';
    locationForm.location = location?.location ?? '';
    locationForm.address = location?.address ?? '';
    locationForm.city = location?.city ?? '';
    locationForm.province = location?.province ?? '';
    locationForm.latitude = String(location?.latitude ?? '-6.2088');
    locationForm.longitude = String(location?.longitude ?? '106.8456');
    locationForm.attendance_radius_meters = location?.attendance_radius_meters ?? 100;
    locationForm.timezone = location?.timezone ?? 'Asia/Jakarta';
    locationForm.is_active = location?.is_active ?? true;
    showLocationForm.value = true;
}

function saveLocation() {
    const options = { preserveScroll: true, onSuccess: () => { showLocationForm.value = false; locationForm.reset(); editingLocationId.value = null; } };
    if (editingLocationId.value) locationForm.put(`/admin/branches/${editingLocationId.value}`, options);
    else locationForm.post('/admin/branches', options);
}

function deleteLocation(location: ManagedLocation) {
    if (window.confirm(`Delete location ${location.name}?`)) router.delete(`/admin/branches/${location.id}`, { preserveScroll: true });
}

function useCurrentLocation() {
    navigator.geolocation?.getCurrentPosition((position) => {
        locationForm.latitude = position.coords.latitude.toFixed(7);
        locationForm.longitude = position.coords.longitude.toFixed(7);
    });
}

function openClassForm(item?: ManagedClass) {
    classForm.clearErrors();
    editingClassId.value = item?.id ?? null;
    classForm.name = item?.name ?? '';
    classForm.class_type = item?.class_type ?? 'Beginner';
    classForm.coach_id = item?.coach_id ?? '';
    classForm.branch_id = item?.branch_id === null || item?.branch_id === undefined ? '' : String(item.branch_id);
    classForm.day_of_week = item?.day_of_week ?? 1;
    classForm.capacity = item?.capacity ?? 20;
    classForm.start_time = item?.start_time ?? '16:00';
    classForm.end_time = item?.end_time ?? '18:00';
    classForm.min_belt = item?.min_belt ?? '10th Geup - White Belt';
    classForm.description = item?.description ?? '';
    classForm.is_active = item?.is_active ?? true;
    showClassForm.value = true;
}

function saveClass() {
    const options = { preserveScroll: true, onSuccess: () => { showClassForm.value = false; classForm.reset(); editingClassId.value = null; } };
    if (editingClassId.value) classForm.put(`/admin/groups/${editingClassId.value}`, options);
    else classForm.post('/admin/groups', options);
}

function deleteClass(item: ManagedClass) {
    if (window.confirm(`Delete class ${item.name}?`)) router.delete(`/admin/groups/${item.id}`, { preserveScroll: true });
}

function isExternalUrl(value: unknown): value is string { return typeof value === 'string' && /^https?:\/\//i.test(value); }
function linkLabel(value: string) { return value.includes('wa.me') ? 'Open WA' : 'Open'; }
</script>

<template>
    <Head :title="props.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageSection :title="props.title" :description="props.subtitle">
                <template #actions>
                    <div class="flex flex-wrap gap-2">
                        <Button v-if="props.mode === 'monthly-dues'" type="button" size="sm" @click="generateMonthlyDues"><Plus class="mr-2 size-4" /> Generate bulan ini</Button>
                        <Button v-if="props.mode === 'weekly-schedule'" type="button" size="sm" @click="generateWeeklySessions"><Plus class="mr-2 size-4" /> Generate sesi minggu ini</Button>
                        <Button v-if="props.mode === 'locations'" type="button" size="sm" @click="openLocationForm()"><Plus class="mr-2 size-4" /> Tambah Lokasi</Button>
                        <Button v-if="props.mode === 'classes'" type="button" size="sm" @click="openClassForm()"><Plus class="mr-2 size-4" /> Tambah Kelas</Button>
                        <Button v-if="['finance-income', 'finance-output', 'payments', 'monthly-dues'].includes(props.mode)" as-child variant="outline" size="sm"><Link href="/payments">Open Payment Center</Link></Button>
                        <Button variant="secondary" size="sm" @click="router.reload({ preserveScroll: true })"><RefreshCcw class="mr-2 size-4" /> Refresh</Button>
                    </div>
                </template>

                <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-red-500">{{ props.roleAccess }}</p>
                <div v-if="props.metrics.length" class="mt-4 grid gap-4 md:grid-cols-4"><StatCard v-for="metric in props.metrics" :key="metric.label" v-bind="metric" /></div>
            </PageSection>

            <section v-if="props.mode === 'locations'" class="rounded-xl border bg-card p-5 shadow-sm">
                <div class="mb-5 grid gap-3 md:grid-cols-[1fr_auto] md:items-center">
                    <div class="relative"><Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" /><input class="h-10 w-full rounded-lg border bg-background pl-10 pr-3 text-sm" placeholder="Cari nama lokasi..." /></div>
                    <Button variant="outline" size="sm" @click="router.reload({ preserveScroll: true })">Refresh</Button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead><tr class="border-b text-left"><th class="px-3 py-3 font-black">Nama Lokasi & Kelas</th><th class="px-3 py-3 font-black">Alamat</th><th class="px-3 py-3 font-black">Status</th><th class="px-3 py-3 font-black">Aksi</th></tr></thead>
                        <tbody>
                            <tr v-if="props.locations.length === 0"><td colspan="4" class="h-36 px-3 text-center text-muted-foreground">Tidak ada data lokasi</td></tr>
                            <tr v-for="location in props.locations" :key="location.id" class="border-b hover:bg-muted/40">
                                <td class="px-3 py-4"><p class="font-black">{{ location.name }}</p><p class="text-xs text-muted-foreground">{{ location.groups_count ?? 0 }} kelas · radius {{ location.attendance_radius_meters }}m</p></td>
                                <td class="px-3 py-4"><p>{{ location.address ?? '-' }}</p><p class="text-xs text-muted-foreground">{{ location.city }} {{ location.province }}</p></td>
                                <td class="px-3 py-4"><span class="rounded-full bg-green-100 px-3 py-1 text-xs font-bold text-green-700">{{ location.is_active ? 'AKTIF' : 'NONAKTIF' }}</span></td>
                                <td class="px-3 py-4"><div class="flex gap-2"><Button size="sm" variant="ghost" @click="openLocationForm(location)"><Pencil class="size-4" /></Button><Button size="sm" variant="ghost" @click="deleteLocation(location)"><Trash2 class="size-4 text-red-500" /></Button></div></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section v-else-if="props.mode === 'classes'" class="rounded-xl border bg-card p-5 shadow-sm">
                <div class="mb-5 grid gap-3 md:grid-cols-[1fr_auto] md:items-center">
                    <div class="relative"><Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" /><input class="h-10 w-full rounded-lg border bg-background pl-10 pr-3 text-sm" placeholder="Cari nama kelas..." /></div>
                    <Button variant="outline" size="sm" @click="router.reload({ preserveScroll: true })">Refresh</Button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[920px] text-sm">
                        <thead><tr class="border-b text-left"><th class="px-3 py-3 font-black">Kelas</th><th class="px-3 py-3 font-black">Instruktur</th><th class="px-3 py-3 font-black">Jadwal</th><th class="px-3 py-3 font-black">Kapasitas</th><th class="px-3 py-3 font-black">Status</th><th class="px-3 py-3 font-black">Aksi</th></tr></thead>
                        <tbody>
                            <tr v-if="props.classes.length === 0"><td colspan="6" class="h-36 px-3 text-center text-muted-foreground">Tidak ada data kelas</td></tr>
                            <tr v-for="item in props.classes" :key="item.id" class="border-b hover:bg-muted/40">
                                <td class="px-3 py-4"><p class="font-black">{{ item.name }}</p><span class="rounded-full border border-blue-400 px-2 py-0.5 text-xs font-bold text-blue-600">{{ item.class_type }}</span><p class="mt-1 text-xs text-muted-foreground"><MapPin class="mr-1 inline size-3" />{{ item.branch }}</p></td>
                                <td class="px-3 py-4 text-muted-foreground">{{ item.coach }}</td>
                                <td class="px-3 py-4"><p class="font-bold">{{ item.schedule }}</p><p class="text-xs text-muted-foreground">{{ item.time }}</p></td>
                                <td class="px-3 py-4">{{ item.capacity }} Orang <span class="text-xs text-muted-foreground">({{ item.athletes_count }} terdaftar)</span></td>
                                <td class="px-3 py-4"><span class="rounded-full bg-green-100 px-3 py-1 text-xs font-bold text-green-700">{{ item.is_active ? 'AKTIF' : 'NONAKTIF' }}</span></td>
                                <td class="px-3 py-4"><div class="flex gap-2"><Button size="sm" variant="ghost" @click="openClassForm(item)"><Pencil class="size-4 text-blue-500" /></Button><Button size="sm" variant="ghost" @click="deleteClass(item)"><Trash2 class="size-4 text-red-500" /></Button></div></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section v-else-if="props.mode === 'monthly-dues' && props.billingSettings" class="rounded-xl border bg-card p-5 shadow-sm">
                <div class="mb-4"><h3 class="text-xl font-black">Pengaturan tagihan otomatis</h3><p class="text-sm text-muted-foreground">Atur kapan iuran bulanan dibuat, nominal default, dan apakah generator otomatis aktif.</p></div>
                <form class="grid gap-4 md:grid-cols-5 md:items-end" @submit.prevent="saveBillingSettings">
                    <label class="grid gap-2 text-sm font-semibold">Tanggal tagihan<input v-model="billingForm.invoice_day" type="number" min="1" max="28" class="h-10 rounded-lg border bg-background px-3 text-sm" /></label>
                    <label class="grid gap-2 text-sm font-semibold">Jam pengecekan<input v-model="billingForm.invoice_time" type="time" class="h-10 rounded-lg border bg-background px-3 text-sm" /></label>
                    <label class="grid gap-2 text-sm font-semibold">Nominal default<input v-model="billingForm.default_amount" type="number" min="0" step="1000" class="h-10 rounded-lg border bg-background px-3 text-sm" /></label>
                    <label class="flex h-10 items-center gap-2 rounded-lg border bg-background px-3 text-sm font-semibold"><input v-model="billingForm.is_active" type="checkbox" /> Aktif</label>
                    <Button type="submit" :disabled="billingForm.processing">{{ billingForm.processing ? 'Menyimpan...' : 'Simpan jadwal' }}</Button>
                </form>
            </section>

            <section v-else-if="props.mode === 'weekly-schedule'" class="rounded-xl border bg-card p-5 shadow-sm">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3"><div class="rounded-full border px-4 py-2 text-sm font-black">Today: {{ new Date().toLocaleDateString() }}</div><p class="text-sm text-muted-foreground">Weekly training templates generate real training sessions automatically.</p></div>
                <form class="mb-6 grid gap-3 rounded-xl border bg-background p-4 md:grid-cols-4 md:items-end" @submit.prevent="saveWeeklySchedule">
                    <label class="grid gap-1 text-sm font-semibold">Title<input v-model="weeklyForm.title" class="h-10 rounded-lg border bg-background px-3 text-sm" /></label>
                    <label class="grid gap-1 text-sm font-semibold">Branch<select v-model="weeklyForm.branch_id" class="h-10 rounded-lg border bg-background px-3 text-sm"><option value="">Select branch</option><option v-for="option in props.branchOptions" :key="String(option.value)" :value="option.value">{{ option.label }}</option></select></label>
                    <label class="grid gap-1 text-sm font-semibold">Group<select v-model="weeklyForm.group_id" class="h-10 rounded-lg border bg-background px-3 text-sm"><option value="">All groups</option><option v-for="option in props.groupOptions" :key="String(option.value)" :value="option.value">{{ option.label }}</option></select></label>
                    <label class="grid gap-1 text-sm font-semibold">Coach<select v-model="weeklyForm.coach_id" class="h-10 rounded-lg border bg-background px-3 text-sm"><option value="">No coach</option><option v-for="option in props.coachOptions" :key="String(option.value)" :value="option.value">{{ option.label }}</option></select></label>
                    <label class="grid gap-1 text-sm font-semibold">Day<select v-model="weeklyForm.day_of_week" class="h-10 rounded-lg border bg-background px-3 text-sm"><option v-for="day in dayCards" :key="day.id" :value="day.id">{{ day.name }}</option></select></label>
                    <label class="grid gap-1 text-sm font-semibold">Start<input v-model="weeklyForm.start_time" type="time" class="h-10 rounded-lg border bg-background px-3 text-sm" /></label>
                    <label class="grid gap-1 text-sm font-semibold">End<input v-model="weeklyForm.end_time" type="time" class="h-10 rounded-lg border bg-background px-3 text-sm" /></label>
                    <label class="grid gap-1 text-sm font-semibold">Location<input v-model="weeklyForm.location" class="h-10 rounded-lg border bg-background px-3 text-sm" /></label>
                    <Button type="submit" class="md:col-span-4" :disabled="weeklyForm.processing">{{ weeklyForm.processing ? 'Saving...' : 'Save weekly training' }}</Button>
                </form>
                <div class="grid gap-3 md:grid-cols-7"><div v-for="day in dayCards" :key="`body-${day.id}`" class="min-h-56 rounded-xl border bg-background p-4"><p class="font-black">{{ day.name }}</p><template v-if="(weeklySchedulesByDay.get(day.id) ?? []).length"><div v-for="schedule in weeklySchedulesByDay.get(day.id)" :key="schedule.id" class="mt-3 rounded-xl border-l-4 border-red-500 bg-card p-3 shadow-sm"><p class="font-black">{{ schedule.title }}</p><p class="mt-2 text-sm">{{ schedule.time }}</p><p class="text-xs text-muted-foreground">{{ schedule.coach }} · {{ schedule.location }}</p><span class="mt-2 inline-flex rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-bold uppercase text-green-700">{{ schedule.is_active ? 'Aktif' : 'Nonaktif' }}</span></div></template><div v-else class="flex h-full flex-col items-center justify-center text-xs font-bold uppercase text-muted-foreground"><CalendarDays class="mb-2 size-8 opacity-40" /> Libur</div></div></div>
            </section>

            <section v-else class="rounded-xl border bg-card p-5 shadow-sm">
                <div class="mb-5 grid gap-3 md:grid-cols-[1fr_auto] md:items-center"><div class="relative"><Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" /><input class="h-10 w-full rounded-lg border bg-background pl-10 pr-3 text-sm" placeholder="Cari data..." /></div><Button variant="outline" size="sm" @click="router.reload({ preserveScroll: true })"><RefreshCcw class="mr-2 size-4" />Muat Ulang</Button></div>
                <div class="overflow-x-auto"><table class="w-full min-w-[720px] text-sm"><thead><tr class="border-b text-left"><th v-for="column in props.columns" :key="column" class="px-3 py-3 font-black">{{ column }}</th></tr></thead><tbody><tr v-if="props.rows.length === 0"><td :colspan="Math.max(props.columns.length, 1)" class="h-40 px-3 text-center text-muted-foreground">{{ props.emptyText }}</td></tr><tr v-for="(row, index) in props.rows" :key="index" class="border-b hover:bg-muted/40"><td v-for="column in props.columns" :key="column" class="px-3 py-3"><a v-if="isExternalUrl(row[column])" :href="row[column]" target="_blank" rel="noreferrer" class="font-semibold text-primary underline-offset-4 hover:underline">{{ linkLabel(row[column]) }}</a><span v-else>{{ row[column] ?? '-' }}</span></td></tr></tbody></table></div>
            </section>

            <FormModal :open="showLocationForm" max-width-class="max-w-3xl" @close="showLocationForm = false">
                <form class="grid gap-4" @submit.prevent="saveLocation">
                    <div class="flex items-start justify-between"><div><h3 class="text-xl font-black">{{ editingLocationId ? 'Edit Lokasi' : 'Tambah Lokasi' }}</h3><p class="text-sm text-muted-foreground">Silakan lengkapi informasi lokasi latihan di bawah ini</p></div><Button type="button" variant="ghost" size="sm" @click="showLocationForm = false"><X class="size-4" /> Kembali</Button></div>
                    <label class="grid gap-1 text-sm font-semibold">Nama Lokasi *<input v-model="locationForm.name" class="h-10 rounded-lg border bg-background px-3 text-sm" placeholder="Contoh: GOR Pajajaran Hall A" /><span v-if="locationForm.errors.name" class="text-xs text-destructive">{{ locationForm.errors.name }}</span></label>
                    <label class="grid gap-1 text-sm font-semibold">Alamat Lengkap *<textarea v-model="locationForm.address" class="min-h-16 rounded-lg border bg-background px-3 py-2 text-sm" placeholder="Alamat lengkap lokasi latihan" /><span v-if="locationForm.errors.address" class="text-xs text-destructive">{{ locationForm.errors.address }}</span></label>
                    <div class="grid gap-3 md:grid-cols-2"><label class="grid gap-1 text-sm font-semibold">Kota *<input v-model="locationForm.city" class="h-10 rounded-lg border bg-background px-3 text-sm" placeholder="Contoh: Bogor" /></label><label class="grid gap-1 text-sm font-semibold">Provinsi *<input v-model="locationForm.province" class="h-10 rounded-lg border bg-background px-3 text-sm" placeholder="Contoh: Jawa Barat" /></label></div>
                    <div class="grid gap-2 text-sm font-semibold">Pilih Titik Lokasi (Map)<div class="overflow-hidden rounded-lg border"><iframe :src="mapUrl" class="h-64 w-full" loading="lazy" /></div><div class="flex flex-wrap gap-2 text-xs text-muted-foreground"><span><MapPin class="mr-1 inline size-3 text-red-500" />{{ locationForm.latitude }}, {{ locationForm.longitude }}</span><Button type="button" size="sm" variant="outline" @click="useCurrentLocation">Lokasi Saya</Button></div></div>
                    <div class="grid gap-3 md:grid-cols-2"><label class="grid gap-1 text-sm font-semibold">Latitude<input v-model="locationForm.latitude" class="h-10 rounded-lg border bg-background px-3 text-sm" /></label><label class="grid gap-1 text-sm font-semibold">Longitude<input v-model="locationForm.longitude" class="h-10 rounded-lg border bg-background px-3 text-sm" /></label></div>
                    <label class="grid gap-1 text-sm font-semibold">Radius Absensi (meter)<input v-model="locationForm.attendance_radius_meters" type="number" min="10" class="h-10 rounded-lg border bg-background px-3 text-sm" /></label>
                    <label class="grid gap-1 text-sm font-semibold">Zona Waktu<input v-model="locationForm.timezone" class="h-10 rounded-lg border bg-background px-3 text-sm" placeholder="Asia/Jakarta" /></label>
                    <label class="grid gap-1 text-sm font-semibold">Status<select v-model="locationForm.is_active" class="h-10 rounded-lg border bg-background px-3 text-sm"><option :value="true">Aktif</option><option :value="false">Nonaktif</option></select></label>
                    <div class="grid gap-3 md:grid-cols-2"><Button type="button" variant="ghost" @click="showLocationForm = false">Batal</Button><Button type="submit" :disabled="locationForm.processing">{{ locationForm.processing ? 'Menyimpan...' : 'Simpan Lokasi' }}</Button></div>
                </form>
            </FormModal>

            <FormModal :open="showClassForm" max-width-class="max-w-2xl" @close="showClassForm = false">
                <form class="grid gap-4" @submit.prevent="saveClass">
                    <div class="flex items-start justify-between"><h3 class="text-xl font-black">{{ editingClassId ? 'Edit Kelas' : 'Tambah Kelas Baru' }}</h3><Button type="button" variant="ghost" size="sm" @click="showClassForm = false"><X class="size-4" /></Button></div>
                    <label class="grid gap-1 text-sm font-semibold">Nama Kelas *<input v-model="classForm.name" class="h-10 rounded-lg border bg-background px-3 text-sm" /></label>
                    <div class="grid gap-3 md:grid-cols-2"><label class="grid gap-1 text-sm font-semibold">Tipe Kelas<select v-model="classForm.class_type" class="h-10 rounded-lg border bg-background px-3 text-sm"><option>Beginner</option><option>Intermediate</option><option>Advanced</option></select></label><label class="grid gap-1 text-sm font-semibold">Instruktur<select v-model="classForm.coach_id" class="h-10 rounded-lg border bg-background px-3 text-sm"><option value="">Pilih instruktur</option><option v-for="option in props.coachOptions" :key="String(option.value)" :value="option.value">{{ option.label }}</option></select></label></div>
                    <div class="grid gap-3 md:grid-cols-2"><label class="grid gap-1 text-sm font-semibold">Hari<select v-model="classForm.day_of_week" class="h-10 rounded-lg border bg-background px-3 text-sm"><option v-for="day in dayCards" :key="day.id" :value="day.id">{{ day.name }}</option></select></label><label class="grid gap-1 text-sm font-semibold">Maksimal Peserta<input v-model="classForm.capacity" type="number" min="1" class="h-10 rounded-lg border bg-background px-3 text-sm" /></label></div>
                    <div class="grid gap-3 md:grid-cols-2"><label class="grid gap-1 text-sm font-semibold">Jam Mulai *<input v-model="classForm.start_time" type="time" class="h-10 rounded-lg border bg-background px-3 text-sm" /></label><label class="grid gap-1 text-sm font-semibold">Jam Selesai *<input v-model="classForm.end_time" type="time" class="h-10 rounded-lg border bg-background px-3 text-sm" /></label></div>
                    <label class="grid gap-1 text-sm font-semibold">Minimal Sabuk<input v-model="classForm.min_belt" class="h-10 rounded-lg border bg-background px-3 text-sm" /></label>
                    <label class="grid gap-1 text-sm font-semibold">Lokasi Latihan<select v-model="classForm.branch_id" class="h-10 rounded-lg border bg-background px-3 text-sm"><option value="">Pilih lokasi latihan</option><option v-for="option in props.branchOptions" :key="String(option.value)" :value="option.value">{{ option.label }}</option></select></label>
                    <label class="grid gap-1 text-sm font-semibold">Deskripsi<textarea v-model="classForm.description" class="min-h-16 rounded-lg border bg-background px-3 py-2 text-sm" /></label>
                    <label class="grid gap-1 text-sm font-semibold">Status<select v-model="classForm.is_active" class="h-10 rounded-lg border bg-background px-3 text-sm"><option :value="true">Aktif</option><option :value="false">Nonaktif</option></select></label>
                    <Button type="submit" :disabled="classForm.processing">{{ classForm.processing ? 'Menyimpan...' : 'Simpan Kelas' }}</Button>
                </form>
            </FormModal>
        </div>
    </AppLayout>
</template>
