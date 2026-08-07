<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CalendarDays, MapPin, Pencil, Plus, RefreshCcw, Search, Trash2, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import FormModal from '@/components/shared/FormModal.vue';
import PageSection from '@/components/shared/PageSection.vue';
import StatCard from '@/components/shared/StatCard.vue';
import { Button } from '@/components/ui/button';
import { useAppPopup } from '@/composables/useAppPopup';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import {
    classes as adminClasses,
    locations as adminLocations,
    payments as adminPayments,
    monthlyDues as adminMonthlyDues,
} from '@/routes/admin';
import { destroy as branchDestroy, store as branchStore, update as branchUpdate } from '@/routes/admin/branches';
import { destroy as groupDestroy, store as groupStore, update as groupUpdate } from '@/routes/admin/groups';
import { generate as generateMonthlyDuesRoute, settings as monthlyDuesSettings } from '@/routes/admin/monthly-dues';
import { generateWeek as generateWeekRoute, store as scheduleStore } from '@/routes/admin/schedules';
import { index as paymentsIndex } from '@/routes/payments';
import type { BreadcrumbItem } from '@/types';
import type {
    AdminFeatureSelectOption,
    AdminFeatureWeeklySchedule,
    BillingSettings,
    ManagedClass,
    ManagedLocation,
} from '@/types/admin-feature';
import type { Metric } from '@/types/resource-table';

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
        billingSettings?: BillingSettings | null;
        weeklySchedules?: AdminFeatureWeeklySchedule[];
        branchOptions?: AdminFeatureSelectOption[];
        groupOptions?: AdminFeatureSelectOption[];
        coachOptions?: AdminFeatureSelectOption[];
        beltOptions?: AdminFeatureSelectOption[];
        locations?: ManagedLocation[];
        classes?: ManagedClass[];
    }>(),
    {
        metrics: () => [],
        columns: () => [],
        rows: () => [],
        emptyText: 'Tidak ada data',
        billingSettings: null,
        weeklySchedules: () => [],
        branchOptions: () => [],
        groupOptions: () => [],
        coachOptions: () => [],
        beltOptions: () => [],
        locations: () => [],
        classes: () => [],
    },
);
const popup = useAppPopup();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Beranda', href: dashboard.url() },
    {
        title: props.title,
        href:
            (
                {
                    locations: adminLocations.url(),
                    classes: adminClasses.url(),
                    payments: adminPayments.url(),
                    'monthly-dues': adminMonthlyDues.url(),
                } as Record<string, string>
            )[props.mode] ?? dashboard.url(),
    },
];

const dayCards = [
    { id: 1, name: 'Senin' },
    { id: 2, name: 'Selasa' },
    { id: 3, name: 'Rabu' },
    { id: 4, name: 'Kamis' },
    { id: 5, name: 'Jumat' },
    { id: 6, name: 'Sabtu' },
    { id: 7, name: 'Minggu' },
];

const showLocationForm = ref(false);
const showClassForm = ref(false);
const editingLocationId = ref<number | null>(null);
const editingClassId = ref<number | null>(null);

const billingForm = useForm({
    invoice_day: props.billingSettings?.invoice_day ?? 1,
    invoice_time: props.billingSettings?.invoice_time ?? '01:10',
    default_amount: props.billingSettings?.default_amount ?? '150000',
    is_active: props.billingSettings?.is_active ?? true,
});

const weeklyForm = useForm({
    title: '',
    branch_id: '',
    group_id: '',
    coach_id: '',
    day_of_week: 1,
    start_time: '',
    end_time: '',
    location: '',
    is_active: true,
});

const locationForm = useForm({
    name: '',
    location: '',
    address: '',
    city: '',
    province: '',
    latitude: '-6.2088',
    longitude: '106.8456',
    attendance_radius_meters: 100,
    timezone: 'Asia/Jakarta',
    is_active: true,
});

const classForm = useForm({
    name: '',
    class_type: 'Beginner',
    coach_id: '',
    branch_id: '',
    day_of_week: 1,
    start_time: '16:00',
    end_time: '18:00',
    min_belt: props.beltOptions[0]?.value ?? '',
    description: '',
    is_active: true,
});

const today = new Date();
const firstDayOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
const formatDate = (date: Date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};
const queryParams = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : new URLSearchParams();
const attendanceSearch = ref('');
const attendanceClass = ref('');
const attendanceStatus = ref('');
const attendanceRangeStart = ref(queryParams.get('from') || formatDate(firstDayOfMonth));
const attendanceRangeEnd = ref(queryParams.get('to') || formatDate(today));
const isAttendanceRecap = computed(() => ['attendance', 'instructor-attendance'].includes(props.mode));

const weeklySchedulesByDay = computed(() => {
    const grouped = new Map<number, AdminFeatureWeeklySchedule[]>();
    props.weeklySchedules.forEach((schedule) =>
        grouped.set(schedule.day_of_week, [...(grouped.get(schedule.day_of_week) ?? []), schedule]),
    );
    return grouped;
});

function uniqueValuesFromColumns(columns: string[]) {
    const seen = new Set<string>();
    props.rows.forEach((row) => {
        const value = columns
            .map((column) => row[column])
            .find(Boolean)
            ?.trim();
        if (value) seen.add(value);
    });
    return [...seen].sort((left, right) => left.localeCompare(right));
}

const attendanceClassOptions = computed(() => uniqueValuesFromColumns(['Kelas', 'Class']));
const attendanceStatusOptions = computed(() => uniqueValuesFromColumns(['Status']));
const displayedRows = computed(() => {
    if (!isAttendanceRecap.value) return props.rows;

    const keyword = attendanceSearch.value.trim().toLowerCase();
    const classValue = attendanceClass.value.trim().toLowerCase();
    const statusValue = attendanceStatus.value.trim().toLowerCase();

    return props.rows.filter((row) => {
        const memberText = [row.Atlet, row.Coach, row.Member, row.Anggota, row.Nama, row.No]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();
        const classText = String(row.Kelas ?? row.Class ?? '').toLowerCase();
        const statusText = String(row.Status ?? '').toLowerCase();

        return (
            (!keyword || memberText.includes(keyword)) &&
            (!classValue || classText === classValue) &&
            (!statusValue || statusText === statusValue)
        );
    });
});

const mapUrl = computed(() => {
    const latitude = Number(locationForm.latitude || -6.2088);
    const longitude = Number(locationForm.longitude || 106.8456);
    const bbox = `${longitude - 0.01},${latitude - 0.01},${longitude + 0.01},${latitude + 0.01}`;
    return `https://www.openstreetmap.org/export/embed.html?bbox=${encodeURIComponent(bbox)}&layer=mapnik&marker=${encodeURIComponent(`${latitude},${longitude}`)}`;
});

function applyAttendanceDateRange() {
    router.get(
        window.location.pathname,
        { from: attendanceRangeStart.value, to: attendanceRangeEnd.value },
        { preserveScroll: true, preserveState: true },
    );
}

function clearAttendanceFilters() {
    attendanceSearch.value = '';
    attendanceClass.value = '';
    attendanceStatus.value = '';
}

function generateMonthlyDues() {
    router.post(generateMonthlyDuesRoute.url(), {}, { preserveScroll: true });
}

function saveBillingSettings() {
    billingForm.post(monthlyDuesSettings.url(), { preserveScroll: true });
}

function generateWeeklySessions() {
    router.post(generateWeekRoute.url(), {}, { preserveScroll: true });
}

function saveWeeklySchedule() {
    weeklyForm.post(scheduleStore.url(), { preserveScroll: true, onSuccess: () => weeklyForm.reset() });
}

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
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showLocationForm.value = false;
            locationForm.reset();
            editingLocationId.value = null;
        },
    };
    if (editingLocationId.value) locationForm.put(branchUpdate.url(editingLocationId.value), options);
    else locationForm.post(branchStore.url(), options);
}

async function deleteLocation(location: ManagedLocation): Promise<void> {
    const confirmed = await popup.confirm({
        title: 'Hapus atau nonaktifkan lokasi?',
        message: `Lokasi “${location.name}” akan dihapus bila belum digunakan. Riwayat yang sudah ada akan tetap dipertahankan melalui penonaktifan.`,
        tone: 'danger',
        confirmLabel: 'Lanjutkan',
    });
    if (!confirmed) return;
    router.delete(branchDestroy.url(location.id), { preserveScroll: true });
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
    classForm.start_time = item?.start_time ?? '16:00';
    classForm.end_time = item?.end_time ?? '18:00';
    classForm.min_belt = item?.min_belt ?? props.beltOptions[0]?.value ?? '';
    classForm.description = item?.description ?? '';
    classForm.is_active = item?.is_active ?? true;
    showClassForm.value = true;
}

function saveClass() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showClassForm.value = false;
            classForm.reset();
            editingClassId.value = null;
        },
    };
    if (editingClassId.value) classForm.put(groupUpdate.url(editingClassId.value), options);
    else classForm.post(groupStore.url(), options);
}

async function deleteClass(item: ManagedClass): Promise<void> {
    const confirmed = await popup.confirm({
        title: 'Hapus atau nonaktifkan kelas?',
        message: `Kelas “${item.name}” akan dihapus bila belum memiliki riwayat. Kelas yang sudah dipakai akan dinonaktifkan.`,
        tone: 'danger',
        confirmLabel: 'Lanjutkan',
    });
    if (!confirmed) return;
    router.delete(groupDestroy.url(item.id), { preserveScroll: true });
}

function isExternalUrl(value: unknown): value is string {
    return typeof value === 'string' && /^https?:\/\//i.test(value);
}

function linkLabel(value: string) {
    return value.includes('wa.me') ? 'Open WA' : 'Open';
}
</script>

<template>
    <Head :title="props.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageSection :title="props.title" :description="props.subtitle">
                <template #actions>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-if="props.mode === 'monthly-dues'"
                            type="button"
                            size="sm"
                            @click="generateMonthlyDues"
                        >
                            <Plus class="mr-2 size-4" /> Generate bulan ini
                        </Button>
                        <Button
                            v-if="props.mode === 'weekly-schedule'"
                            type="button"
                            size="sm"
                            @click="generateWeeklySessions"
                        >
                            <Plus class="mr-2 size-4" /> Generate sesi minggu ini
                        </Button>
                        <Button v-if="props.mode === 'locations'" type="button" size="sm" @click="openLocationForm()">
                            <Plus class="mr-2 size-4" /> Tambah Lokasi
                        </Button>
                        <Button v-if="props.mode === 'classes'" type="button" size="sm" @click="openClassForm()">
                            <Plus class="mr-2 size-4" /> Tambah Kelas
                        </Button>
                        <Button
                            v-if="['payments', 'monthly-dues'].includes(props.mode)"
                            as-child
                            variant="outline"
                            size="sm"
                        >
                            <Link :href="paymentsIndex.url()">Payment Center</Link>
                        </Button>
                        <Button variant="secondary" size="sm" @click="router.reload({})">
                            <RefreshCcw class="mr-2 size-4" /> Refresh
                        </Button>
                    </div>
                </template>

                <p class="mt-1 text-xs font-semibold tracking-wide text-red-500 uppercase">{{ props.roleAccess }}</p>
                <div v-if="props.metrics.length" class="mt-4 grid gap-4 md:grid-cols-4">
                    <StatCard v-for="metric in props.metrics" :key="metric.label" v-bind="metric" />
                </div>
            </PageSection>

            <section v-if="props.mode === 'locations'" class="rounded-xl border bg-card p-5 shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead>
                            <tr class="border-b text-left">
                                <th class="px-3 py-3 font-black">Nama Lokasi & Kelas</th>
                                <th class="px-3 py-3 font-black">Alamat</th>
                                <th class="px-3 py-3 font-black">Status</th>
                                <th class="px-3 py-3 font-black">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="props.locations.length === 0">
                                <td colspan="4" class="h-36 px-3 text-center text-muted-foreground">
                                    Tidak ada data lokasi
                                </td>
                            </tr>
                            <tr
                                v-for="location in props.locations"
                                :key="location.id"
                                class="border-b hover:bg-muted/40"
                            >
                                <td class="px-3 py-4">
                                    <p class="font-black">{{ location.name }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ location.groups_count ?? 0 }} kelas · radius
                                        {{ location.attendance_radius_meters }}m
                                    </p>
                                </td>
                                <td class="px-3 py-4">
                                    <p>{{ location.address ?? '-' }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ location.city }} {{ location.province }}
                                    </p>
                                </td>
                                <td class="px-3 py-4">
                                    <span
                                        class="rounded-full bg-green-100 px-3 py-1 text-xs font-bold text-green-700"
                                        >{{ location.is_active ? 'AKTIF' : 'NONAKTIF' }}</span
                                    >
                                </td>
                                <td class="px-3 py-4">
                                    <div class="flex gap-2">
                                        <Button size="sm" variant="ghost" @click="openLocationForm(location)"
                                            ><Pencil class="size-4" /></Button
                                        ><Button size="sm" variant="ghost" @click="deleteLocation(location)"
                                            ><Trash2 class="size-4 text-red-500"
                                        /></Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section v-else-if="props.mode === 'classes'" class="rounded-xl border bg-card p-5 shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[920px] text-sm">
                        <thead>
                            <tr class="border-b text-left">
                                <th class="px-3 py-3 font-black">Kelas</th>
                                <th class="px-3 py-3 font-black">Instruktur</th>
                                <th class="px-3 py-3 font-black">Jadwal</th>
                                <th class="px-3 py-3 font-black">Peserta</th>
                                <th class="px-3 py-3 font-black">Minimal Sabuk</th>
                                <th class="px-3 py-3 font-black">Status</th>
                                <th class="px-3 py-3 font-black">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="props.classes.length === 0">
                                <td colspan="7" class="h-36 px-3 text-center text-muted-foreground">
                                    Tidak ada data kelas
                                </td>
                            </tr>
                            <tr v-for="item in props.classes" :key="item.id" class="border-b hover:bg-muted/40">
                                <td class="px-3 py-4">
                                    <p class="font-black">{{ item.name }}</p>
                                    <span
                                        class="rounded-full border border-blue-400 px-2 py-0.5 text-xs font-bold text-blue-600"
                                        >{{ item.class_type }}</span
                                    >
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        <MapPin class="mr-1 inline size-3" />{{ item.branch }}
                                    </p>
                                </td>
                                <td class="px-3 py-4 text-muted-foreground">{{ item.coach }}</td>
                                <td class="px-3 py-4">
                                    <p class="font-bold">{{ item.schedule }}</p>
                                    <p class="text-xs text-muted-foreground">{{ item.time }}</p>
                                </td>
                                <td class="px-3 py-4">{{ item.athletes_count }} terdaftar</td>
                                <td class="px-3 py-4">{{ item.min_belt ?? '-' }}</td>
                                <td class="px-3 py-4">
                                    <span
                                        class="rounded-full bg-green-100 px-3 py-1 text-xs font-bold text-green-700"
                                        >{{ item.is_active ? 'AKTIF' : 'NONAKTIF' }}</span
                                    >
                                </td>
                                <td class="px-3 py-4">
                                    <div class="flex gap-2">
                                        <Button size="sm" variant="ghost" @click="openClassForm(item)"
                                            ><Pencil class="size-4 text-blue-500" /></Button
                                        ><Button size="sm" variant="ghost" @click="deleteClass(item)"
                                            ><Trash2 class="size-4 text-red-500"
                                        /></Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section
                v-else-if="props.mode === 'monthly-dues' && props.billingSettings"
                class="rounded-xl border bg-card p-5 shadow-sm"
            >
                <div class="mb-4">
                    <h3 class="text-xl font-black">Pengaturan tagihan otomatis</h3>
                    <p class="text-sm text-muted-foreground">
                        Atur kapan iuran bulanan dibuat, nominal default, dan apakah generator otomatis aktif.
                    </p>
                </div>
                <form class="grid gap-4 md:grid-cols-5 md:items-end" @submit.prevent="saveBillingSettings">
                    <label class="grid gap-2 text-sm font-semibold"
                        >Tanggal tagihan<input
                            v-model="billingForm.invoice_day"
                            type="number"
                            min="1"
                            max="28"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                    /></label>
                    <label class="grid gap-2 text-sm font-semibold"
                        >Jam pengecekan<input
                            v-model="billingForm.invoice_time"
                            type="time"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                    /></label>
                    <label class="grid gap-2 text-sm font-semibold"
                        >Nominal default<input
                            v-model="billingForm.default_amount"
                            type="number"
                            min="0"
                            step="1000"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                    /></label>
                    <label
                        class="flex h-10 items-center gap-2 rounded-lg border bg-background px-3 text-sm font-semibold"
                        ><input v-model="billingForm.is_active" type="checkbox" /> Aktif</label
                    >
                    <Button type="submit" :disabled="billingForm.processing">{{
                        billingForm.processing ? 'Menyimpan...' : 'Simpan jadwal'
                    }}</Button>
                </form>
            </section>

            <section v-else-if="props.mode === 'weekly-schedule'" class="rounded-xl border bg-card p-5 shadow-sm">
                <form
                    class="mb-6 grid gap-3 rounded-xl border bg-background p-4 md:grid-cols-4 md:items-end"
                    @submit.prevent="saveWeeklySchedule"
                >
                    <label class="grid gap-1 text-sm font-semibold"
                        >Title<input
                            v-model="weeklyForm.title"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                    /></label>
                    <label class="grid gap-1 text-sm font-semibold"
                        >Branch<select
                            v-model="weeklyForm.branch_id"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                        >
                            <option value="">Select branch</option>
                            <option
                                v-for="option in props.branchOptions"
                                :key="String(option.value)"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select></label
                    >
                    <label class="grid gap-1 text-sm font-semibold"
                        >Group<select
                            v-model="weeklyForm.group_id"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                        >
                            <option value="">All groups</option>
                            <option
                                v-for="option in props.groupOptions"
                                :key="String(option.value)"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select></label
                    >
                    <label class="grid gap-1 text-sm font-semibold"
                        >Coach<select
                            v-model="weeklyForm.coach_id"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                        >
                            <option value="">No coach</option>
                            <option
                                v-for="option in props.coachOptions"
                                :key="String(option.value)"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select></label
                    >
                    <label class="grid gap-1 text-sm font-semibold"
                        >Day<select
                            v-model="weeklyForm.day_of_week"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                        >
                            <option v-for="day in dayCards" :key="day.id" :value="day.id">{{ day.name }}</option>
                        </select></label
                    >
                    <label class="grid gap-1 text-sm font-semibold"
                        >Start<input
                            v-model="weeklyForm.start_time"
                            type="time"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                    /></label>
                    <label class="grid gap-1 text-sm font-semibold"
                        >End<input
                            v-model="weeklyForm.end_time"
                            type="time"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                    /></label>
                    <label class="grid gap-1 text-sm font-semibold"
                        >Location<input
                            v-model="weeklyForm.location"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                    /></label>
                    <Button type="submit" class="md:col-span-4" :disabled="weeklyForm.processing">{{
                        weeklyForm.processing ? 'Saving...' : 'Save weekly training'
                    }}</Button>
                </form>
                <div class="grid gap-3 md:grid-cols-7">
                    <div v-for="day in dayCards" :key="day.id" class="min-h-56 rounded-xl border bg-background p-4">
                        <p class="font-black">{{ day.name }}</p>
                        <div
                            v-for="schedule in weeklySchedulesByDay.get(day.id)"
                            :key="schedule.id"
                            class="mt-3 rounded-xl border-l-4 border-red-500 bg-card p-3 shadow-sm"
                        >
                            <p class="font-black">{{ schedule.title }}</p>
                            <p class="mt-2 text-sm">{{ schedule.time }}</p>
                            <p class="text-xs text-muted-foreground">{{ schedule.coach }} · {{ schedule.location }}</p>
                        </div>
                        <div
                            v-if="!(weeklySchedulesByDay.get(day.id) ?? []).length"
                            class="flex h-full flex-col items-center justify-center text-xs font-bold text-muted-foreground uppercase"
                        >
                            <CalendarDays class="mb-2 size-8 opacity-40" /> Libur
                        </div>
                    </div>
                </div>
            </section>

            <section v-else class="rounded-xl border bg-card p-5 shadow-sm">
                <div v-if="isAttendanceRecap" class="mb-5 grid gap-4 lg:grid-cols-[1fr_1fr_1fr_1fr_1fr_auto]">
                    <label class="grid gap-1 text-sm font-semibold"
                        >Dari<input
                            v-model="attendanceRangeStart"
                            type="date"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                            @change="applyAttendanceDateRange"
                    /></label>
                    <label class="grid gap-1 text-sm font-semibold"
                        >Sampai<input
                            v-model="attendanceRangeEnd"
                            type="date"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                            @change="applyAttendanceDateRange"
                    /></label>
                    <label class="grid gap-1 text-sm font-semibold"
                        >Cari Member
                        <div class="relative">
                            <Search
                                class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            /><input
                                v-model="attendanceSearch"
                                class="h-10 w-full rounded-lg border bg-background pr-3 pl-10 text-sm"
                                placeholder="Nama atau Kode Member..."
                            /></div
                    ></label>
                    <label class="grid gap-1 text-sm font-semibold"
                        >Kelas<select
                            v-model="attendanceClass"
                            class="h-10 rounded-lg border bg-background px-3 text-sm text-muted-foreground"
                        >
                            <option value="">Filter per Kelas</option>
                            <option v-for="option in attendanceClassOptions" :key="option" :value="option">
                                {{ option }}
                            </option>
                        </select></label
                    >
                    <label class="grid gap-1 text-sm font-semibold"
                        >Status<select
                            v-model="attendanceStatus"
                            class="h-10 rounded-lg border bg-background px-3 text-sm text-muted-foreground"
                        >
                            <option value="">Filter per Status</option>
                            <option v-for="option in attendanceStatusOptions" :key="option" :value="option">
                                {{ option }}
                            </option>
                        </select></label
                    >
                    <Button type="button" variant="outline" size="sm" class="self-end" @click="clearAttendanceFilters"
                        >Reset</Button
                    >
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-sm">
                        <thead>
                            <tr class="border-b text-left">
                                <th v-for="column in props.columns" :key="column" class="px-3 py-3 font-black">
                                    {{ column }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="displayedRows.length === 0">
                                <td
                                    :colspan="Math.max(props.columns.length, 1)"
                                    class="h-40 px-3 text-center text-muted-foreground"
                                >
                                    {{ props.emptyText }}
                                </td>
                            </tr>
                            <tr v-for="(row, index) in displayedRows" :key="index" class="border-b hover:bg-muted/40">
                                <td v-for="column in props.columns" :key="column" class="px-3 py-3 whitespace-pre-line">
                                    <a
                                        v-if="isExternalUrl(row[column])"
                                        :href="row[column]"
                                        target="_blank"
                                        rel="noreferrer"
                                        class="font-semibold text-primary hover:underline"
                                        >{{ linkLabel(row[column]) }}</a
                                    ><span v-else>{{ row[column] ?? '-' }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <FormModal :open="showLocationForm" max-width-class="max-w-3xl" @close="showLocationForm = false">
                <form class="grid gap-4" @submit.prevent="saveLocation">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="text-xl font-black">
                                {{ editingLocationId ? 'Edit Lokasi' : 'Tambah Lokasi' }}
                            </h3>
                            <p class="text-sm text-muted-foreground">Silakan lengkapi informasi lokasi latihan.</p>
                        </div>
                        <Button type="button" variant="ghost" size="sm" @click="showLocationForm = false"
                            ><X class="size-4"
                        /></Button>
                    </div>
                    <label class="grid gap-1 text-sm font-semibold"
                        >Nama Lokasi *<input
                            v-model="locationForm.name"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                    /></label>
                    <label class="grid gap-1 text-sm font-semibold"
                        >Alamat Lengkap *<textarea
                            v-model="locationForm.address"
                            class="min-h-16 rounded-lg border bg-background px-3 py-2 text-sm"
                        ></textarea>
                    </label>
                    <div class="grid gap-3 md:grid-cols-2">
                        <label class="grid gap-1 text-sm font-semibold"
                            >Kota *<input
                                v-model="locationForm.city"
                                class="h-10 rounded-lg border bg-background px-3 text-sm" /></label
                        ><label class="grid gap-1 text-sm font-semibold"
                            >Provinsi *<input
                                v-model="locationForm.province"
                                class="h-10 rounded-lg border bg-background px-3 text-sm"
                        /></label>
                    </div>
                    <div class="overflow-hidden rounded-lg border">
                        <iframe :src="mapUrl" class="h-64 w-full" loading="lazy" />
                    </div>
                    <Button type="button" size="sm" variant="outline" class="w-fit" @click="useCurrentLocation"
                        >Lokasi Saya</Button
                    >
                    <div class="grid gap-3 md:grid-cols-2">
                        <label class="grid gap-1 text-sm font-semibold"
                            >Latitude<input
                                v-model="locationForm.latitude"
                                class="h-10 rounded-lg border bg-background px-3 text-sm" /></label
                        ><label class="grid gap-1 text-sm font-semibold"
                            >Longitude<input
                                v-model="locationForm.longitude"
                                class="h-10 rounded-lg border bg-background px-3 text-sm"
                        /></label>
                    </div>
                    <label class="grid gap-1 text-sm font-semibold"
                        >Radius Absensi (meter)<input
                            v-model="locationForm.attendance_radius_meters"
                            type="number"
                            min="10"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                    /></label>
                    <label class="grid gap-1 text-sm font-semibold"
                        >Zona Waktu<input
                            v-model="locationForm.timezone"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                    /></label>
                    <label class="grid gap-1 text-sm font-semibold"
                        >Status<select
                            v-model="locationForm.is_active"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                        >
                            <option :value="true">Aktif</option>
                            <option :value="false">Nonaktif</option>
                        </select></label
                    >
                    <div class="grid gap-3 md:grid-cols-2">
                        <Button type="button" variant="ghost" @click="showLocationForm = false">Batal</Button
                        ><Button type="submit" :disabled="locationForm.processing">{{
                            locationForm.processing ? 'Menyimpan...' : 'Simpan Lokasi'
                        }}</Button>
                    </div>
                </form>
            </FormModal>

            <FormModal :open="showClassForm" max-width-class="max-w-2xl" @close="showClassForm = false">
                <form class="grid gap-4" @submit.prevent="saveClass">
                    <div class="flex items-start justify-between">
                        <h3 class="text-xl font-black">{{ editingClassId ? 'Edit Kelas' : 'Tambah Kelas Baru' }}</h3>
                        <Button type="button" variant="ghost" size="sm" @click="showClassForm = false"
                            ><X class="size-4"
                        /></Button>
                    </div>
                    <label class="grid gap-1 text-sm font-semibold"
                        >Nama Kelas *<input
                            v-model="classForm.name"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                    /></label>
                    <div class="grid gap-3 md:grid-cols-2">
                        <label class="grid gap-1 text-sm font-semibold"
                            >Hari<select
                                v-model="classForm.day_of_week"
                                class="h-10 rounded-lg border bg-background px-3 text-sm"
                            >
                                <option v-for="day in dayCards" :key="day.id" :value="day.id">{{ day.name }}</option>
                            </select></label
                        ><label class="grid gap-1 text-sm font-semibold"
                            >Minimal Sabuk<select
                                v-model="classForm.min_belt"
                                class="h-10 rounded-lg border bg-background px-3 text-sm"
                            >
                                <option value="">Tanpa minimal</option>
                                <option
                                    v-for="option in props.beltOptions"
                                    :key="String(option.value)"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select></label
                        >
                    </div>
                    <div class="grid gap-3 md:grid-cols-2">
                        <label class="grid gap-1 text-sm font-semibold"
                            >Jam Mulai *<input
                                v-model="classForm.start_time"
                                type="time"
                                class="h-10 rounded-lg border bg-background px-3 text-sm" /></label
                        ><label class="grid gap-1 text-sm font-semibold"
                            >Jam Selesai *<input
                                v-model="classForm.end_time"
                                type="time"
                                class="h-10 rounded-lg border bg-background px-3 text-sm"
                        /></label>
                    </div>
                    <label class="grid gap-1 text-sm font-semibold"
                        >Lokasi Latihan<select
                            v-model="classForm.branch_id"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                        >
                            <option value="">Pilih lokasi latihan</option>
                            <option
                                v-for="option in props.branchOptions"
                                :key="String(option.value)"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select></label
                    >
                    <label class="grid gap-1 text-sm font-semibold"
                        >Deskripsi<textarea
                            v-model="classForm.description"
                            class="min-h-16 rounded-lg border bg-background px-3 py-2 text-sm"
                        ></textarea>
                    </label>
                    <label class="grid gap-1 text-sm font-semibold"
                        >Status<select
                            v-model="classForm.is_active"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                        >
                            <option :value="true">Aktif</option>
                            <option :value="false">Nonaktif</option>
                        </select></label
                    >
                    <Button type="submit" :disabled="classForm.processing">{{
                        classForm.processing ? 'Menyimpan...' : 'Simpan Kelas'
                    }}</Button>
                </form>
            </FormModal>
        </div>
    </AppLayout>
</template>
