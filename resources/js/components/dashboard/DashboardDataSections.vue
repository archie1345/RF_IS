<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import DataTable from '@/components/shared/DataTable.vue';
import { Button } from '@/components/ui/button';
import { dashboardColumns } from '@/data/dashboard';
import { managementRoutes } from '@/data/management';
import type { AppRole, TableRow, AttendanceRow } from '@/types/management';
import { router } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';


let timer = 0;

const props = defineProps<{
    role: AppRole;
    announcements: TableRow[];
    upcomingEvents: TableRow[];
    profileSummary: Record<string, string>;
    medalRows: TableRow[];
    activityPreviewRows: TableRow[];
    attendanceRows: AttendanceRow[];
    paymentRows: TableRow[];
}>();

const absenceReason = ref('');
const showReasonField = ref(false);
const currentDate = ref(new Date());
const timeString = ref('');

const currentYear = computed(() => currentDate.value.getFullYear());
const currentMonth = computed(() => currentDate.value.getMonth());
const monthName = computed(() => currentDate.value.toLocaleString('default', { month: 'long' }));

const daysInMonth = computed(() => {
    const days = new Date(currentYear.value, currentMonth.value + 1, 0).getDate();
    return Array.from({ length: days }, (_, i) => i + 1);
});

const firstDayOffset = computed(() => {
    return new Date(currentYear.value, currentMonth.value, 1).getDay();
});

const getDayStatus = (day: number) => {
    const formattedDate = `${currentYear.value}-${String(currentMonth.value + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`
    const record = props.attendanceRows.find(row => row.session_date === formattedDate);
    return record ? record.status : null;
};

const submitAttendanceStatus = (statusType: 'hadir' | 'izin' | 'alfa') => {
    if (statusType === 'izin' && !showReasonField.value) {
        showReasonField.value = true;
        return;
    }

    router.post('/attendance', {
        status: statusType,
        notes: absenceReason.value,
        date: new Date().toISOString().slice(0, 10)
    }, {
        preserveState: true,
        onSuccess: () => {
            showReasonField.value = false;
            absenceReason.value = '';
        }
    });
};

const updateClock = () => {
    const now = new Date();
    timeString.value = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
};


onMounted(() => {
  updateClock();
  timer = setInterval(updateClock, 1000);
});

onUnmounted(() => clearInterval(timer));

const formatDate = (dateValue: unknown): string => {
    if (typeof dateValue === 'string' || typeof dateValue === 'number') {
        return new Date(dateValue).toLocaleDateString();
    }
    return 'Recent';
};
</script>

<template>
    <div class="space-y-6">
        <div v-if="props.role === 'athlete'" class="grid grid-cols-1 gap-6 lg:grid-cols-5">
            
            <div class="bg-card p-6 rounded-xl border shadow-sm lg:col-span-3">
                <h3 class="text-sm font-semibold text-muted-foreground uppercase tracking-wider mb-4">
                    Attendance Tracker Calendar
                </h3>
                <div class="w-full max-w-sm mx-auto bg-background p-4 border rounded-xl shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <h4 class="text-sm font-bold tracking-tight text-foreground">
                {{ monthName }} {{ currentYear }}
            </h4>
            <span class="text-[10px] font-medium text-muted-foreground uppercase px-2 py-0.5 rounded bg-secondary">
                Absensi
            </span>
        </div>

        <div class="grid grid-cols-7 gap-1 text-center text-muted-foreground text-[11px] font-bold mb-2">
            <div>Su</div><div>Mo</div><div>Tu</div><div>We</div><div>Th</div><div>Fr</div><div>Sa</div>
        </div>

        <div class="grid grid-cols-7 gap-1 text-center text-xs">
            <div v-for="blank in firstDayOffset" :key="'blank-' + blank"></div>

            <div v-for="day in daysInMonth" :key="day" class="relative p-2 flex items-center justify-center font-medium">
                <span 
                    class="absolute inset-0 m-auto w-7 h-7 flex items-center justify-center rounded-full transition-all"
                    :class="{
                        'bg-emerald-500 text-white font-semibold shadow-sm': getDayStatus(day) === 'hadir',
                        'bg-amber-500 text-white font-semibold shadow-sm': getDayStatus(day) === 'izin',
                        'bg-destructive text-white font-semibold shadow-sm': getDayStatus(day) === 'alfa',
                        'text-foreground hover:bg-muted': !getDayStatus(day)
                    }"
                >
                    {{ day }}
                </span>
            </div>
        </div>

        <div class="mt-4 pt-3 border-t flex items-center justify-center gap-4 text-[10px] font-semibold text-muted-foreground">
            <div class="flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Hadir
            </div>
            <div class="flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-amber-500"></span> Izin
            </div>
            <div class="flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-destructive"></span> Alfa
            </div>
        </div>
    </div>
            </div>

            <div class="bg-card p-6 rounded-xl border shadow-sm lg:col-span-2 flex flex-col justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-muted-foreground uppercase tracking-wider mb-2">
                        Today's Training Status
                    </h3>
                    <div class="text-4xl font-bold text-blue-600 mb-1 flex items-center gap-2 justify-center py-5 dark:text-foreground">
                        <h1>{{ timeString }}</h1>
                    </div>
                    <div class="text-sm text-muted-foreground text-center mb-4">
                        {{ currentDate.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}
                    </div>
                    <p class="text-xs text-muted-foreground mb-4">
                        Select your attendance status for today's active schedule session.
                    </p>

                    <div class="grid grid-cols-2 gap-2">
                        <button @click="submitAttendanceStatus('hadir')" class="p-3 bg-emerald-500 hover:bg-emerald-600 text-white rounded-lg font-medium text-sm transition">
                            Hadir
                        </button>
                        <button @click="submitAttendanceStatus('izin')" class="p-3 bg-amber-500 hover:bg-amber-600 text-white rounded-lg font-medium text-sm transition">
                            Izin
                        </button>
                    </div>

                    <div v-if="showReasonField" class="mt-4 space-y-2 animate-fadeIn">
                        <label class="text-xs font-medium">Reason for Absence:</label>
                        <input v-model="absenceReason" type="text" placeholder="e.g., Sick, School Exam..." class="w-full text-sm border rounded px-3 py-2 bg-background" />
                        <button @click="submitAttendanceStatus('izin')" class="w-full py-2 bg-primary text-primary-foreground text-xs font-semibold rounded-md">
                            Confirm Reason
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div v-if="props.role === 'admin'" class="grid gap-6 xl:grid-cols-2">
        <DataTable title="Recent account activity" description="Live preview of recent admin actions." :columns="dashboardColumns.log" :rows="props.activityPreviewRows">
            <template #row-actions>
                <Button as-child variant="outline" size="sm">
                    <Link :href="managementRoutes.activityLogs">Open full log</Link>
                </Button>
            </template>
        </DataTable>
    </div>
</template>

