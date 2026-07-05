<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { CheckCircle2, QrCode } from 'lucide-vue-next';
import DataTable from '@/components/shared/DataTable.vue';
import { Button } from '@/components/ui/button';
import { dashboardColumns } from '@/data/dashboard';
import { appRoutes } from '@/data/routes';
import type { AppRole, TableRow, AttendanceRow, TrainingDay } from '@/types/resource-table';

let timer: ReturnType<typeof setInterval> | null = null;

const props = defineProps<{
    role: AppRole;
    announcements: TableRow[];
    upcomingEvents: TableRow[];
    profileSummary: Record<string, string>;
    medalRows: TableRow[];
    activityPreviewRows: TableRow[];
    attendanceRows: AttendanceRow[];
    trainingDays: TrainingDay[];
    paymentRows: TableRow[];
}>();

const currentDate = ref(new Date());
const timeString = ref('');
const selectedDay = ref<string | null>(null);

const currentYear = computed(() => currentDate.value.getFullYear());
const currentMonth = computed(() => currentDate.value.getMonth());
const monthName = computed(() => currentDate.value.toLocaleString('default', { month: 'long' }));
const daysInMonth = computed(() => Array.from({ length: new Date(currentYear.value, currentMonth.value + 1, 0).getDate() }, (_, i) => i + 1));
const firstDayOffset = computed(() => new Date(currentYear.value, currentMonth.value, 1).getDay());

const trainingDayMap = computed(() => {
    const map = new Map<string, TrainingDay[]>();
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
        if (date) map.set(date, row.status_value ?? '');
    });
    return map;
});

const selectedTrainingSessions = computed(() => (selectedDay.value ? trainingDayMap.value.get(selectedDay.value) ?? [] : []));

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

    <div v-if="props.role === 'admin'" class="grid gap-6 xl:grid-cols-2">
        <DataTable title="Recent account activity" description="Live preview of recent admin actions." :columns="dashboardColumns.log" :rows="props.activityPreviewRows">
            <template #row-actions>
                <Button as-child variant="outline" size="sm"><Link :href="appRoutes.activityLogs">Open full log</Link></Button>
            </template>
        </DataTable>
    </div>
</template>
