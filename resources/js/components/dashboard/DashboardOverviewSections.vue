<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { CalendarDays, CheckCircle2, QrCode, WalletCards } from 'lucide-vue-next';
import DataTable from '@/components/shared/DataTable.vue';
import { Button } from '@/components/ui/button';
import { dashboardColumns } from '@/data/dashboard';
import { appRoutes } from '@/data/routes';
import type { AppRole, TableBadgeCell, TableRow } from '@/types/resource-table';

type DashboardAttendanceRow = {
    id?: number | string;
    date?: string;
    session_date?: string;
    status: string | TableBadgeCell;
    status_value?: string;
};

type DashboardTrainingDay = {
    id: string;
    date: string;
    title: string;
    time: string;
    branch: string;
    group: string;
};

let timer: ReturnType<typeof setInterval> | null = null;

const props = withDefaults(
    defineProps<{
        role: AppRole;
        announcements?: TableRow[];
        upcomingEvents?: TableRow[];
        profileSummary?: Record<string, string>;
        medalRows?: TableRow[];
        activityPreviewRows?: TableRow[];
        attendanceRows?: DashboardAttendanceRow[];
        trainingDays?: DashboardTrainingDay[];
        paymentRows?: TableRow[];
    }>(),
    {
        announcements: () => [],
        upcomingEvents: () => [],
        profileSummary: () => ({}),
        medalRows: () => [],
        activityPreviewRows: () => [],
        attendanceRows: () => [],
        trainingDays: () => [],
        paymentRows: () => [],
    },
);

const currentDate = ref(new Date());
const timeString = ref('');
const selectedDay = ref<string | null>(null);

const currentYear = computed(() => currentDate.value.getFullYear());
const currentMonth = computed(() => currentDate.value.getMonth());
const monthName = computed(() => currentDate.value.toLocaleString('default', { month: 'long' }));
const daysInMonth = computed(() => Array.from({ length: new Date(currentYear.value, currentMonth.value + 1, 0).getDate() }, (_, i) => i + 1));
const firstDayOffset = computed(() => new Date(currentYear.value, currentMonth.value, 1).getDay());

const trainingDayMap = computed(() => {
    const map = new Map<string, DashboardTrainingDay[]>();
    props.trainingDays.forEach((day) => {
        const sessions = map.get(day.date) ?? [];
        sessions.push(day);
        map.set(day.date, sessions);
    });
    return map;
});

const attendanceStatusMap = computed(() => {
    const map = new Map<string, string>();
    props.attendanceRows.forEach((row) => {
        const date = row.date || row.session_date;
        if (date) map.set(date, String(row.status_value ?? ''));
    });
    return map;
});

const selectedTrainingSessions = computed(() => (selectedDay.value ? trainingDayMap.value.get(selectedDay.value) ?? [] : []));
const sevenDayLabels = computed(() => Array.from({ length: 7 }, (_, index) => {
    const date = new Date();
    date.setDate(date.getDate() - (6 - index));
    return date.toISOString().slice(0, 10);
}));
const attendanceTrend = computed(() => sevenDayLabels.value.map((date) => props.attendanceRows.filter((row) => row.date === date && row.status_value === 'PRESENT').length));
const maxAttendanceTrend = computed(() => Math.max(...attendanceTrend.value, 1));
const hasAttendanceTrend = computed(() => attendanceTrend.value.some((value) => value > 0));
const adminPaymentSummary = computed(() => {
    let paid = 0;
    let unpaid = 0;
    props.paymentRows.forEach((row) => {
        const remaining = String(row.remaining ?? row.status ?? '').toLowerCase();
        if (remaining.includes('rp 0') || remaining.includes('full')) paid += 1;
        else unpaid += 1;
    });
    return { paid, unpaid, total: Math.max(paid + unpaid, 1), unpaidPercent: Math.round((unpaid / Math.max(paid + unpaid, 1)) * 100) };
});
const beltRows = computed(() => {
    if (props.medalRows.length) {
        return props.medalRows.map((row, index) => ({ label: String(row.type ?? row.label ?? 'Level'), count: Number(row.count ?? 0), shade: index }));
    }

    return [
        { label: '5th Dan - Master', count: 1, shade: 0 },
        { label: '9th Geup - Yellow Belt', count: 1, shade: 1 },
    ];
});
const totalBeltRows = computed(() => Math.max(beltRows.value.reduce((total, row) => total + row.count, 0), 1));

function formatDate(day: number) {
    return `${currentYear.value}-${String(currentMonth.value + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

function getDayTraining(day: number) {
    return trainingDayMap.value.get(formatDate(day)) ?? [];
}

function getDayAttendanceStatus(day: number) {
    return attendanceStatusMap.value.get(formatDate(day));
}

function isToday(day: number) {
    const today = new Date();
    return today.getFullYear() === currentYear.value && today.getMonth() === currentMonth.value && today.getDate() === day;
}

function selectDay(day: number) {
    const date = formatDate(day);
    selectedDay.value = selectedDay.value === date ? null : date;
}

function updateClock() {
    const now = new Date();
    timeString.value = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}

onMounted(() => {
    updateClock();
    timer = setInterval(updateClock, 1000);
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
});
</script>

<template>
    <div class="space-y-6">
        <div v-if="props.role === 'admin'" class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-xl border bg-card p-6 shadow-sm">
                <h3 class="text-lg font-black">Tren Kehadiran (7 Hari)</h3>
                <div v-if="hasAttendanceTrend" class="mt-8 flex h-56 items-end gap-3">
                    <div v-for="(value, index) in attendanceTrend" :key="sevenDayLabels[index]" class="flex flex-1 flex-col items-center gap-2">
                        <div class="w-full rounded-t-xl bg-blue-500" :style="{ height: `${Math.max((value / maxAttendanceTrend) * 100, value ? 8 : 0)}%` }"></div>
                        <span class="text-[10px] text-muted-foreground">{{ sevenDayLabels[index].slice(5) }}</span>
                    </div>
                </div>
                <div v-else class="flex h-72 items-center justify-center text-sm text-muted-foreground">Belum ada data kehadiran minggu ini.</div>
            </section>

            <section class="rounded-xl border bg-card p-6 shadow-sm">
                <h3 class="text-lg font-black">Siswa Per Sabuk</h3>
                <div class="flex min-h-72 flex-col items-center justify-center gap-6">
                    <div class="relative size-44 rounded-full bg-[conic-gradient(#f3f400_0_50%,#050505_50%_100%)]">
                        <div class="absolute inset-10 rounded-full bg-card"></div>
                    </div>
                    <div class="flex flex-wrap justify-center gap-4 text-xs">
                        <div v-for="row in beltRows" :key="row.label" class="flex items-center gap-2">
                            <span class="size-3 rounded-sm" :class="row.shade % 2 === 0 ? 'bg-black dark:bg-white' : 'bg-[#f3f400]'"></span>
                            <span>{{ row.label }} ({{ row.count }})</span>
                        </div>
                    </div>
                    <p class="text-xs text-muted-foreground">Total shown: {{ totalBeltRows }}</p>
                </div>
            </section>

            <section class="rounded-xl border bg-card p-6 shadow-sm">
                <h3 class="text-lg font-black">Status Iuran Bulan Ini</h3>
                <div class="grid min-h-48 gap-6 md:grid-cols-[12rem_1fr] md:items-center">
                    <div class="relative mx-auto size-32 rounded-full bg-[conic-gradient(#ef4444_0_100%)]">
                        <div class="absolute inset-4 rounded-full bg-card"></div>
                        <WalletCards class="absolute left-1/2 top-1/2 size-8 -translate-x-1/2 -translate-y-1/2 text-muted-foreground" />
                    </div>
                    <div class="space-y-4 text-sm">
                        <div class="flex items-center justify-between gap-4">
                            <span class="flex items-center gap-2"><span class="size-3 rounded-full bg-emerald-500"></span> Sudah Bayar ({{ adminPaymentSummary.paid }})</span>
                            <strong>{{ 100 - adminPaymentSummary.unpaidPercent }}%</strong>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <span class="flex items-center gap-2"><span class="size-3 rounded-full bg-red-500"></span> Belum Bayar ({{ adminPaymentSummary.unpaid }})</span>
                            <strong>{{ adminPaymentSummary.unpaidPercent }}%</strong>
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border bg-card p-6 shadow-sm">
                <h3 class="text-lg font-black">Informasi Dojang</h3>
                <div class="mt-4 divide-y text-sm">
                    <div class="flex items-center justify-between py-4"><span class="text-muted-foreground">Dojang Aktif</span><strong>RTFCM</strong></div>
                    <div class="flex items-center justify-between py-4"><span class="text-muted-foreground">Lokasi</span><strong>Malang</strong></div>
                    <div class="flex items-center justify-between py-4"><span class="text-muted-foreground">Jadwal Hari Ini</span><strong>{{ props.trainingDays.length }} Kelas</strong></div>
                </div>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <Button as-child variant="outline"><Link :href="appRoutes.sessions"><CalendarDays class="mr-2 size-4" />Manajemen Latihan</Link></Button>
                    <Button as-child variant="outline"><Link :href="appRoutes.attendance"><CheckCircle2 class="mr-2 size-4" />Presensi</Link></Button>
                </div>
            </section>

            <DataTable class="xl:col-span-2" title="Recent account activity" description="Live preview of recent admin actions." :columns="dashboardColumns.log" :rows="props.activityPreviewRows">
                <template #row-actions>
                    <Button as-child variant="outline" size="sm"><Link :href="appRoutes.activityLogs">Open full log</Link></Button>
                </template>
            </DataTable>
        </div>

        <div v-if="props.role === 'athlete'" class="grid grid-cols-1 gap-6 lg:grid-cols-5">
            <div class="rounded-xl border bg-card p-6 shadow-sm lg:col-span-3">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Training calendar</h3>
                        <p class="text-xs text-muted-foreground">Training days are highlighted. Attendance status appears after QR check-in.</p>
                    </div>
                    <span class="rounded-full bg-blue-50 px-3 py-1 text-[10px] font-black uppercase tracking-wide text-blue-700 dark:bg-blue-950/40 dark:text-blue-200">Schedule</span>
                </div>

                <div class="mx-auto w-full max-w-sm rounded-xl border bg-background p-4 shadow-sm">
                    <div class="mb-4 flex items-center justify-between">
                        <h4 class="text-sm font-bold tracking-tight text-foreground">{{ monthName }} {{ currentYear }}</h4>
                        <span class="rounded bg-secondary px-2 py-0.5 text-[10px] font-medium uppercase text-muted-foreground">Latihan</span>
                    </div>

                    <div class="mb-2 grid grid-cols-7 gap-1 text-center text-[11px] font-bold text-muted-foreground">
                        <div>Su</div><div>Mo</div><div>Tu</div><div>We</div><div>Th</div><div>Fr</div><div>Sa</div>
                    </div>

                    <div class="grid grid-cols-7 gap-1 text-center text-xs">
                        <div v-for="blank in firstDayOffset" :key="'blank-' + blank"></div>
                        <button
                            v-for="day in daysInMonth"
                            :key="day"
                            type="button"
                            class="relative flex h-10 items-center justify-center rounded-xl font-medium transition"
                            :class="{
                                'ring-2 ring-blue-400': selectedDay === formatDate(day),
                                'bg-blue-600 text-white shadow-sm': getDayTraining(day).length && !getDayAttendanceStatus(day),
                                'bg-emerald-500 text-white shadow-sm': getDayAttendanceStatus(day) === 'PRESENT',
                                'bg-amber-500 text-white shadow-sm': getDayAttendanceStatus(day) === 'EXCUSED',
                                'bg-destructive text-white shadow-sm': getDayAttendanceStatus(day) === 'ABSENT',
                                'bg-muted text-foreground': isToday(day) && !getDayTraining(day).length && !getDayAttendanceStatus(day),
                                'text-foreground hover:bg-muted': !isToday(day) && !getDayTraining(day).length && !getDayAttendanceStatus(day),
                            }"
                            @click="selectDay(day)"
                        >
                            {{ day }}
                            <span v-if="getDayTraining(day).length" class="absolute bottom-1 h-1 w-1 rounded-full bg-current opacity-80"></span>
                        </button>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center justify-center gap-3 border-t pt-3 text-[10px] font-semibold text-muted-foreground">
                        <div class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-blue-600"></span> Training</div>
                        <div class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-emerald-500"></span> Present</div>
                        <div class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-amber-500"></span> Excused</div>
                        <div class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-destructive"></span> Absent</div>
                    </div>

                    <div v-if="selectedDay" class="mt-4 rounded-2xl border bg-muted/40 p-3 text-xs">
                        <p class="font-bold">{{ selectedDay }}</p>
                        <div v-if="selectedTrainingSessions.length" class="mt-2 grid gap-2">
                            <div v-for="session in selectedTrainingSessions" :key="session.id" class="rounded-xl bg-background p-2">
                                <p class="font-semibold">{{ session.title }}</p>
                                <p class="text-muted-foreground">{{ session.time }} · {{ session.branch }} · {{ session.group }}</p>
                            </div>
                        </div>
                        <p v-else class="mt-1 text-muted-foreground">No scheduled training for this day.</p>
                    </div>
                </div>
            </div>

            <div class="flex flex-col justify-between rounded-xl border bg-card p-6 shadow-sm lg:col-span-2">
                <div>
                    <h3 class="mb-2 text-sm font-semibold uppercase tracking-wider text-muted-foreground">QR attendance flow</h3>
                    <div class="flex justify-center py-5 text-4xl font-bold text-blue-600 dark:text-foreground"><h1>{{ timeString }}</h1></div>
                    <div class="mb-4 text-center text-sm text-muted-foreground">{{ currentDate.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}</div>

                    <div class="grid gap-3 rounded-3xl border bg-muted/40 p-4 text-sm">
                        <div class="flex items-start gap-3"><QrCode class="mt-0.5 size-5 text-blue-600" /><div><p class="font-bold">1. Open QR scan menu</p><p class="text-xs text-muted-foreground">Use a phone while logged in as athlete.</p></div></div>
                        <div class="flex items-start gap-3"><QrCode class="mt-0.5 size-5 text-blue-600" /><div><p class="font-bold">2. Scan coach QR</p><p class="text-xs text-muted-foreground">The QR opens the secure attendance page.</p></div></div>
                        <div class="flex items-start gap-3"><CheckCircle2 class="mt-0.5 size-5 text-emerald-600" /><div><p class="font-bold">3. Saved and done</p><p class="text-xs text-muted-foreground">Athletes do not get manual Attend / Not attend buttons.</p></div></div>
                    </div>
                </div>

                <Button as-child class="mt-4 w-full rounded-2xl"><Link :href="appRoutes.attendance">Open QR scan menu</Link></Button>
            </div>
        </div>
    </div>
</template>
