<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { CalendarDays, ChevronLeft, ChevronRight, Download, RefreshCcw, Search, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import PageSection from '@/components/shared/PageSection.vue';
import StatCard from '@/components/shared/StatCard.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { Metric } from '@/types/resource-table';

type Period = {
    from: string;
    to: string;
    month: string;
    label: string;
    exportUrl: string;
};

type CalendarCell = {
    key: string;
    dateString: string;
    day: number;
    isCurrentMonth: boolean;
    isToday: boolean;
    isSelectedStart: boolean;
    isSelectedEnd: boolean;
    isInRange: boolean;
};

const props = withDefaults(
    defineProps<{
        mode: 'attendance' | 'instructor-attendance';
        title: string;
        subtitle: string;
        metrics?: Metric[];
        columns?: string[];
        rows?: Record<string, string>[];
        emptyText?: string;
        roleAccess?: string;
        period: Period;
    }>(),
    {
        metrics: () => [],
        columns: () => [],
        rows: () => [],
        emptyText: 'Tidak ada data',
        roleAccess: 'Admin only',
    },
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: props.title, href: window.location.pathname },
];

const today = new Date();
const attendanceMonth = ref(props.period.month);
const attendanceRangeStart = ref(props.period.from);
const attendanceRangeEnd = ref(props.period.to);
const attendanceSearch = ref('');
const attendanceClass = ref('');
const attendanceClassType = ref('');
const attendanceStatus = ref('');
const calendarOpen = ref(false);
const calendarMonth = ref(parseDate(props.period.from) ?? today);

const weekdays = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];
const dateRangeLabel = computed(() => `${attendanceRangeStart.value} – ${attendanceRangeEnd.value}`);
const calendarTitle = computed(() => calendarMonth.value.toLocaleDateString('en-US', { month: 'long', year: 'numeric' }));
const exportUrl = computed(() => {
    const url = new URL(props.period.exportUrl, window.location.origin);
    url.searchParams.set('from', attendanceRangeStart.value);
    url.searchParams.set('to', attendanceRangeEnd.value);
    if (attendanceMonth.value) url.searchParams.set('month', attendanceMonth.value);
    return `${url.pathname}?${url.searchParams.toString()}`;
});

const classOptions = computed(() => uniqueValuesFromColumns(['Kelas', 'Class']));
const classTypeOptions = computed(() => uniqueValuesFromColumns(['Tipe Kelas', 'Tipe', 'Class Type']));
const statusOptions = computed(() => uniqueValuesFromColumns(['Status']));

const calendarDays = computed<CalendarCell[]>(() => {
    const month = calendarMonth.value.getMonth();
    const year = calendarMonth.value.getFullYear();
    const monthStart = new Date(year, month, 1);
    const mondayOffset = (monthStart.getDay() + 6) % 7;
    const gridStart = new Date(year, month, 1 - mondayOffset);
    const rangeStart = parseDate(attendanceRangeStart.value);
    const rangeEnd = parseDate(attendanceRangeEnd.value);
    const normalizedStart = rangeStart && rangeEnd && rangeStart > rangeEnd ? rangeEnd : rangeStart;
    const normalizedEnd = rangeStart && rangeEnd && rangeStart > rangeEnd ? rangeStart : rangeEnd;
    const todayString = formatDate(today);

    return Array.from({ length: 42 }, (_, index) => {
        const date = new Date(gridStart);
        date.setDate(gridStart.getDate() + index);
        const dateString = formatDate(date);

        return {
            key: dateString,
            dateString,
            day: date.getDate(),
            isCurrentMonth: date.getMonth() === month,
            isToday: dateString === todayString,
            isSelectedStart: dateString === attendanceRangeStart.value,
            isSelectedEnd: dateString === attendanceRangeEnd.value,
            isInRange: Boolean(normalizedStart && normalizedEnd && date >= normalizedStart && date <= normalizedEnd),
        };
    });
});

const displayedRows = computed(() => {
    const keyword = attendanceSearch.value.trim().toLowerCase();
    const classValue = attendanceClass.value.trim().toLowerCase();
    const classTypeValue = attendanceClassType.value.trim().toLowerCase();
    const statusValue = attendanceStatus.value.trim().toLowerCase();

    return props.rows.filter((row) => {
        const memberText = [row.Atlet, row.Coach, row.Member, row.Anggota, row.Nama, row.No].filter(Boolean).join(' ').toLowerCase();
        const classText = String(row.Kelas ?? row.Class ?? '').toLowerCase();
        const classTypeText = String(row['Tipe Kelas'] ?? row.Tipe ?? row['Class Type'] ?? '').toLowerCase();
        const statusText = String(row.Status ?? '').toLowerCase();

        return (!keyword || memberText.includes(keyword))
            && (!classValue || classText === classValue)
            && (!classTypeValue || classTypeText === classTypeValue)
            && (!statusValue || statusText === statusValue);
    });
});

function formatDate(date: Date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function parseDate(dateString: string) {
    const [year, month, day] = dateString.split('-').map(Number);
    if (!year || !month || !day) return null;
    return new Date(year, month - 1, day);
}

function monthRange(monthValue: string) {
    const [year, month] = monthValue.split('-').map(Number);
    if (!year || !month) return null;
    return {
        from: formatDate(new Date(year, month - 1, 1)),
        to: formatDate(new Date(year, month, 0)),
    };
}

function uniqueValuesFromColumns(columns: string[]) {
    const seen = new Set<string>();
    props.rows.forEach((row) => {
        const value = columns.map((column) => row[column]).find(Boolean)?.trim();
        if (value) seen.add(value);
    });
    return [...seen].sort((a, b) => a.localeCompare(b));
}

function reloadPeriod(params: { month?: string; from: string; to: string }) {
    router.get(window.location.pathname, params, { preserveScroll: true, preserveState: true });
}

function applyMonth(monthValue = attendanceMonth.value) {
    const range = monthRange(monthValue);
    if (!range) return;
    attendanceMonth.value = monthValue;
    attendanceRangeStart.value = range.from;
    attendanceRangeEnd.value = range.to;
    calendarMonth.value = parseDate(range.from) ?? calendarMonth.value;
    reloadPeriod({ month: monthValue, ...range });
}

function quickMonth(offset: number) {
    const base = parseDate(`${attendanceMonth.value}-01`) ?? today;
    const target = new Date(base.getFullYear(), base.getMonth() + offset, 1);
    applyMonth(`${target.getFullYear()}-${String(target.getMonth() + 1).padStart(2, '0')}`);
}

function applyCurrentMonth() {
    applyMonth(`${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}`);
}

function changeCalendarMonth(delta: number) {
    calendarMonth.value = new Date(calendarMonth.value.getFullYear(), calendarMonth.value.getMonth() + delta, 1);
}

function selectDate(dateString: string) {
    const selected = parseDate(dateString);
    const start = parseDate(attendanceRangeStart.value);
    const end = parseDate(attendanceRangeEnd.value);

    if (!selected) return;

    if (!start || (start && end && start.getTime() !== end.getTime()) || selected < start) {
        attendanceRangeStart.value = dateString;
        attendanceRangeEnd.value = dateString;
        return;
    }

    attendanceRangeEnd.value = dateString;
    attendanceMonth.value = dateString.slice(0, 7);
    calendarOpen.value = false;
    reloadPeriod({ from: attendanceRangeStart.value, to: attendanceRangeEnd.value });
}

function clearFilters() {
    attendanceSearch.value = '';
    attendanceClass.value = '';
    attendanceClassType.value = '';
    attendanceStatus.value = '';
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
                        <a :href="exportUrl" class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-3 text-sm font-semibold text-primary-foreground shadow hover:bg-primary/90">
                            <Download class="mr-2 size-4" /> Export Excel
                        </a>
                        <Button variant="secondary" size="sm" @click="router.reload({ preserveScroll: true })">
                            <RefreshCcw class="mr-2 size-4" /> Refresh
                        </Button>
                    </div>
                </template>

                <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-red-500">{{ props.roleAccess }}</p>
                <div v-if="props.metrics.length" class="mt-4 grid gap-4 md:grid-cols-4">
                    <StatCard v-for="metric in props.metrics" :key="metric.label" v-bind="metric" />
                </div>
            </PageSection>

            <section class="rounded-xl border bg-card p-5 shadow-sm">
                <div class="mb-5 grid gap-4 xl:grid-cols-[220px_280px_1fr_1fr_1fr_1fr_auto]">
                    <label class="grid gap-1 text-sm font-semibold">
                        Bulan
                        <input v-model="attendanceMonth" type="month" class="h-10 rounded-lg border bg-background px-3 text-sm" @change="applyMonth()" />
                    </label>

                    <div class="relative grid gap-1 text-sm font-semibold">
                        Rentang Tanggal
                        <button type="button" class="flex h-10 items-center justify-between rounded-lg border bg-background px-3 text-left text-sm font-normal" @click="calendarOpen = !calendarOpen">
                            <span>{{ dateRangeLabel }}</span>
                            <X class="size-4 text-muted-foreground" @click.stop="applyCurrentMonth" />
                        </button>

                        <div v-if="calendarOpen" class="absolute left-0 top-[4.75rem] z-30 w-[330px] rounded-xl border bg-popover p-4 shadow-xl">
                            <div class="mb-4 flex items-center justify-between">
                                <button type="button" class="flex size-10 items-center justify-center rounded-lg hover:bg-muted" @click="changeCalendarMonth(-1)">
                                    <ChevronLeft class="size-5" />
                                </button>
                                <p class="font-black">{{ calendarTitle }}</p>
                                <button type="button" class="flex size-10 items-center justify-center rounded-lg hover:bg-muted" @click="changeCalendarMonth(1)">
                                    <ChevronRight class="size-5" />
                                </button>
                            </div>
                            <div class="grid grid-cols-7 text-center text-sm text-muted-foreground">
                                <div v-for="day in weekdays" :key="day" class="py-2">{{ day }}</div>
                            </div>
                            <div class="grid grid-cols-7 overflow-hidden rounded-lg text-center text-sm">
                                <button
                                    v-for="day in calendarDays"
                                    :key="day.key"
                                    type="button"
                                    class="h-10 border border-background transition hover:bg-blue-100"
                                    :class="[
                                        day.isInRange ? 'bg-blue-100 text-foreground' : 'bg-background',
                                        !day.isCurrentMonth ? 'text-muted-foreground/50' : '',
                                        day.isSelectedStart || day.isSelectedEnd ? 'rounded-lg bg-blue-600 font-black text-white hover:bg-blue-600' : '',
                                        day.isToday && !day.isSelectedStart && !day.isSelectedEnd ? 'font-black text-red-500' : '',
                                    ]"
                                    @click="selectDate(day.dateString)"
                                >
                                    {{ day.day }}
                                </button>
                            </div>
                            <p class="mt-3 text-xs text-muted-foreground">Pilih tanggal awal, lalu tanggal akhir. Data akan dimuat ulang otomatis.</p>
                        </div>
                    </div>

                    <label class="grid gap-1 text-sm font-semibold">
                        Cari Member
                        <div class="relative">
                            <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <input v-model="attendanceSearch" class="h-10 w-full rounded-lg border bg-background pl-10 pr-3 text-sm" placeholder="Nama atau Kode Member..." />
                        </div>
                    </label>

                    <label class="grid gap-1 text-sm font-semibold">
                        Kelas
                        <select v-model="attendanceClass" class="h-10 rounded-lg border bg-background px-3 text-sm text-muted-foreground">
                            <option value="">Filter per Kelas</option>
                            <option v-for="option in classOptions" :key="option" :value="option">{{ option }}</option>
                        </select>
                    </label>

                    <label class="grid gap-1 text-sm font-semibold">
                        Tipe Kelas
                        <select v-model="attendanceClassType" class="h-10 rounded-lg border bg-background px-3 text-sm text-muted-foreground">
                            <option value="">Filter per Tipe Kelas</option>
                            <option v-for="option in classTypeOptions" :key="option" :value="option">{{ option }}</option>
                        </select>
                    </label>

                    <label class="grid gap-1 text-sm font-semibold">
                        Status
                        <select v-model="attendanceStatus" class="h-10 rounded-lg border bg-background px-3 text-sm text-muted-foreground">
                            <option value="">Filter per Status</option>
                            <option v-for="option in statusOptions" :key="option" :value="option">{{ option }}</option>
                        </select>
                    </label>

                    <div class="flex flex-wrap items-end gap-2">
                        <a :href="exportUrl" class="inline-flex h-10 items-center justify-center rounded-md bg-primary px-3 text-sm font-semibold text-primary-foreground shadow hover:bg-primary/90">
                            <Download class="mr-2 size-4" /> Export
                        </a>
                        <Button type="button" variant="outline" size="sm" class="h-10" @click="quickMonth(-1)">Bulan lalu</Button>
                        <Button type="button" variant="outline" size="sm" class="h-10" @click="applyCurrentMonth">Bulan ini</Button>
                        <Button type="button" variant="ghost" size="sm" class="h-10" @click="clearFilters">Reset</Button>
                    </div>
                </div>

                <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-muted/30 px-4 py-3 text-sm">
                    <div class="flex items-center gap-2 font-semibold">
                        <CalendarDays class="size-4 text-muted-foreground" />
                        <span>Periode aktif: {{ props.period.label }}</span>
                    </div>
                    <span class="text-muted-foreground">{{ displayedRows.length }} dari {{ props.rows.length }} baris ditampilkan</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-sm">
                        <thead>
                            <tr class="border-b text-left">
                                <th v-for="column in props.columns" :key="column" class="px-3 py-3 font-black">{{ column }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="displayedRows.length === 0">
                                <td :colspan="Math.max(props.columns.length, 1)" class="h-40 px-3 text-center text-muted-foreground">{{ props.emptyText }}</td>
                            </tr>
                            <tr v-for="(row, index) in displayedRows" :key="index" class="border-b hover:bg-muted/40">
                                <td v-for="column in props.columns" :key="column" class="whitespace-pre-line px-3 py-3">
                                    <a v-if="isExternalUrl(row[column])" :href="row[column]" target="_blank" rel="noreferrer" class="font-semibold text-primary underline-offset-4 hover:underline">{{ linkLabel(row[column]) }}</a>
                                    <span v-else>{{ row[column] ?? '-' }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
