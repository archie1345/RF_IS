<script setup lang="ts">
import { CalendarDays, Clock3, Crown, Info, MapPin, RefreshCcw, UserRound } from '@lucide/vue';
import { computed, ref } from 'vue';
import FormModal from '@/components/shared/FormModal.vue';
import LeafletLocationMap from '@/components/shared/LeafletLocationMap.vue';
import type { WeeklyScheduleCard } from '@/types/training';

const props = withDefaults(
    defineProps<{
        schedules: WeeklyScheduleCard[];
        canManage?: boolean;
        showManagementHint?: boolean;
        title?: string;
        subtitle?: string;
    }>(),
    {
        canManage: false,
        showManagementHint: false,
        title: 'Jadwal Mingguan',
        subtitle: 'Jadwal latihan',
    },
);

const emit = defineEmits<{
    edit: [schedule: WeeklyScheduleCard];
    delete: [schedule: WeeklyScheduleCard];
    refresh: [];
}>();

const selectedSchedule = ref<WeeklyScheduleCard | null>(null);

const days = [
    { id: 1, name: 'Senin', english: 'Monday' },
    { id: 2, name: 'Selasa', english: 'Tuesday' },
    { id: 3, name: 'Rabu', english: 'Wednesday' },
    { id: 4, name: 'Kamis', english: 'Thursday' },
    { id: 5, name: 'Jumat', english: 'Friday' },
    { id: 6, name: 'Sabtu', english: 'Saturday' },
    { id: 7, name: 'Minggu', english: 'Sunday' },
];

const today = new Date();
const todayDay = today.getDay() === 0 ? 7 : today.getDay();

const schedulesByDay = computed(() => {
    const grouped = new Map<number, WeeklyScheduleCard[]>();
    props.schedules
        .filter((schedule) => schedule.is_active !== false)
        .forEach((schedule) => {
            grouped.set(schedule.day_of_week, [...(grouped.get(schedule.day_of_week) ?? []), schedule]);
        });
    return grouped;
});

const branchLabel = computed(() => {
    const branches = [...new Set(props.schedules.map((schedule) => schedule.branch).filter(Boolean))];
    if (branches.length === 1) return branches[0];
    if (branches.length > 1) return 'Multiple Dojang';
    return 'Rhino Fighter';
});

function typeLabel(schedule: WeeklyScheduleCard): string {
    return (schedule.session_type || schedule.class_type || schedule.group || 'Reguler')
        .toString()
        .replace(/_/g, ' ')
        .toUpperCase();
}

function typeTone(schedule: WeeklyScheduleCard): string {
    const value = typeLabel(schedule).toLowerCase();
    if (value.includes('prestasi') || value.includes('advanced') || value.includes('senior'))
        return 'border-red-200 bg-red-50 text-red-700 dark:border-red-500/30 dark:bg-red-500/15 dark:text-red-200';
    if (value.includes('private'))
        return 'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-500/30 dark:bg-violet-500/15 dark:text-violet-200';
    if (value.includes('dedicated'))
        return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/15 dark:text-emerald-200';
    return 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-500/30 dark:bg-blue-500/15 dark:text-blue-200';
}

function participantLabel(schedule: WeeklyScheduleCard): string {
    return String(schedule.child || schedule.athletes || schedule.dedicated_athlete || '').trim();
}

function openSchedule(schedule: WeeklyScheduleCard) {
    selectedSchedule.value = schedule;
}
</script>

<template>
    <section class="rounded-2xl border border-border bg-background p-4 text-foreground shadow-sm md:p-6">
        <div class="mb-7 grid gap-4 xl:grid-cols-[1fr_auto] xl:items-start">
            <div>
                <h2 class="text-3xl font-black tracking-tight text-foreground">{{ title }}</h2>
                <p class="mt-1 text-xs font-black text-red-500 uppercase dark:text-red-400">
                    DOJANG: {{ branchLabel }}
                </p>
                <p class="mt-1 text-sm font-medium text-muted-foreground">{{ subtitle }}</p>
            </div>
            <div class="flex justify-start gap-3 xl:justify-end">
                <button
                    type="button"
                    class="inline-flex size-12 items-center justify-center rounded-xl bg-red-100 text-red-600 transition hover:bg-red-200 dark:bg-red-500/15 dark:text-red-300 dark:hover:bg-red-500/25"
                    @click="emit('refresh')"
                >
                    <RefreshCcw class="size-6" />
                </button>
                <div
                    v-if="showManagementHint"
                    class="flex min-h-12 items-center gap-3 rounded-xl border border-border bg-card px-4 text-sm font-black text-muted-foreground uppercase shadow-sm"
                >
                    <Info class="size-5 text-muted-foreground" />
                    Atur jadwal di menu Master Data &gt; Kelas
                </div>
            </div>
        </div>

        <div class="grid gap-4 xl:hidden">
            <section v-for="day in days" :key="`mobile-${day.id}`" class="grid gap-3">
                <div
                    class="rounded-xl border px-5 py-5 shadow-sm transition"
                    :class="
                        day.id === todayDay
                            ? 'border-red-500 bg-red-500 text-white shadow-red-500/20 dark:border-red-400 dark:bg-red-500 dark:text-white'
                            : 'border-border bg-card text-card-foreground'
                    "
                >
                    <p class="text-xl leading-none font-black">{{ day.name }}</p>
                    <p class="mt-1 text-xs" :class="day.id === todayDay ? 'text-white/90' : 'text-muted-foreground'">
                        {{ day.english }}
                    </p>
                </div>

                <div
                    class="min-h-[220px] rounded-xl border border-border bg-gradient-to-b from-slate-50 to-slate-200/80 p-4 shadow-sm dark:from-card dark:to-muted/40"
                >
                    <div
                        v-if="(schedulesByDay.get(day.id) ?? []).length"
                        class="columns-1 gap-3 space-y-3 md:columns-2 xl:columns-1"
                    >
                        <article
                            v-for="schedule in schedulesByDay.get(day.id)"
                            :key="schedule.id"
                            class="mb-3 cursor-pointer break-inside-avoid rounded-xl border border-l-4 border-border border-l-red-500 bg-card p-4 text-card-foreground shadow-md transition hover:-translate-y-0.5 hover:shadow-lg dark:border-l-red-400"
                            @click="openSchedule(schedule)"
                        >
                            <div class="mb-3 border-b border-border pb-3">
                                <h3 class="truncate text-base font-black text-card-foreground">
                                    {{
                                        schedule.session_type === 'private' && schedule.dedicated_athlete
                                            ? schedule.dedicated_athlete
                                            : schedule.title
                                    }}
                                </h3>
                                <p class="mt-1 truncate text-xs font-semibold text-muted-foreground">
                                    {{
                                        schedule.group && schedule.group !== 'All groups'
                                            ? schedule.group
                                            : 'Sesi terbuka'
                                    }}
                                </p>
                                <span
                                    class="mt-2 inline-flex items-center gap-1 rounded-md border px-2 py-1 text-[11px] font-black"
                                    :class="typeTone(schedule)"
                                    ><Crown class="size-3" /> {{ typeLabel(schedule) }}</span
                                >
                            </div>
                            <div class="space-y-4 text-xs font-black text-muted-foreground uppercase">
                                <div class="flex min-w-0 gap-3">
                                    <Clock3 class="mt-0.5 size-4 shrink-0 text-blue-500 dark:text-blue-400" />
                                    <div class="min-w-0">
                                        <p>Waktu</p>
                                        <p class="truncate text-sm text-card-foreground">
                                            {{ schedule.start_time || '--:--' }} - {{ schedule.end_time || '--:--' }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex min-w-0 gap-3">
                                    <MapPin class="mt-0.5 size-4 shrink-0 text-emerald-500 dark:text-emerald-400" />
                                    <div class="min-w-0">
                                        <p>Lokasi</p>
                                        <p class="truncate text-sm text-card-foreground normal-case">
                                            {{ schedule.location || schedule.branch || 'Rhino Fighter' }}
                                        </p>
                                    </div>
                                </div>
                                <div v-if="participantLabel(schedule)" class="flex min-w-0 gap-3">
                                    <UserRound class="mt-0.5 size-4 shrink-0 text-violet-500 dark:text-violet-400" />
                                    <div class="min-w-0">
                                        <p>Atlet / Anak</p>
                                        <p class="text-sm break-words text-card-foreground normal-case">
                                            {{ participantLabel(schedule) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>
                    <div
                        v-else
                        class="flex h-full min-h-[180px] flex-col items-center justify-center rounded-lg border border-dashed border-border bg-muted/50 text-xs font-black text-muted-foreground uppercase"
                    >
                        <CalendarDays class="mb-3 size-8 opacity-70" />Libur
                    </div>
                </div>
            </section>
        </div>

        <div class="hidden gap-3 xl:grid xl:grid-cols-7">
            <div
                v-for="day in days"
                :key="`head-${day.id}`"
                class="rounded-xl border px-5 py-5 shadow-sm transition"
                :class="
                    day.id === todayDay
                        ? 'border-red-500 bg-red-500 text-white shadow-red-500/20 dark:border-red-400 dark:bg-red-500 dark:text-white'
                        : 'border-border bg-card text-card-foreground'
                "
            >
                <p class="text-xl leading-none font-black">{{ day.name }}</p>
                <p class="mt-1 text-xs" :class="day.id === todayDay ? 'text-white/90' : 'text-muted-foreground'">
                    {{ day.english }}
                </p>
            </div>
        </div>

        <div class="mt-4 hidden gap-3 xl:grid xl:grid-cols-7">
            <div
                v-for="day in days"
                :key="`body-${day.id}`"
                class="min-h-[230px] rounded-xl border border-border bg-gradient-to-b from-slate-50 to-slate-200/80 shadow-sm dark:from-card dark:to-muted/40"
            >
                <div v-if="(schedulesByDay.get(day.id) ?? []).length" class="space-y-3">
                    <article
                        v-for="schedule in schedulesByDay.get(day.id)"
                        :key="schedule.id"
                        class="cursor-pointer rounded-xl border border-l-4 border-border border-l-red-500 bg-card p-4 text-card-foreground shadow-md transition hover:-translate-y-0.5 hover:shadow-lg dark:border-l-red-400"
                        @click="openSchedule(schedule)"
                    >
                        <div class="mb-3 border-b border-border pb-3">
                            <h3 class="truncate text-base font-black text-card-foreground">
                                {{
                                    schedule.session_type === 'private' && schedule.dedicated_athlete
                                        ? schedule.dedicated_athlete
                                        : schedule.title
                                }}
                            </h3>
                            <p class="mt-1 truncate text-xs font-semibold text-muted-foreground">
                                {{
                                    schedule.group && schedule.group !== 'All groups' ? schedule.group : 'Sesi terbuka'
                                }}
                            </p>
                            <span
                                class="mt-2 inline-flex items-center gap-1 rounded-md border px-2 py-1 text-[11px] font-black"
                                :class="typeTone(schedule)"
                                ><Crown class="size-3" /> {{ typeLabel(schedule) }}</span
                            >
                        </div>
                        <div class="space-y-4 text-xs font-black text-muted-foreground uppercase">
                            <div class="flex min-w-0 gap-3">
                                <Clock3 class="mt-0.5 size-4 shrink-0 text-blue-500 dark:text-blue-400" />
                                <div class="min-w-0">
                                    <p>Waktu</p>
                                    <p class="truncate text-sm text-card-foreground">
                                        {{ schedule.start_time || '--:--' }} - {{ schedule.end_time || '--:--' }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex min-w-0 gap-3">
                                <MapPin class="mt-0.5 size-4 shrink-0 text-emerald-500 dark:text-emerald-400" />
                                <div class="min-w-0">
                                    <p>Lokasi</p>
                                    <p class="truncate text-sm text-card-foreground normal-case">
                                        {{ schedule.location || schedule.branch || 'Rhino Fighter' }}
                                    </p>
                                </div>
                            </div>
                            <div v-if="participantLabel(schedule)" class="flex min-w-0 gap-3">
                                <UserRound class="mt-0.5 size-4 shrink-0 text-violet-500 dark:text-violet-400" />
                                <div class="min-w-0">
                                    <p>Atlet / Anak</p>
                                    <p class="text-sm break-words text-card-foreground normal-case">
                                        {{ participantLabel(schedule) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
                <div
                    v-else
                    class="flex h-full min-h-[240px] flex-col items-center justify-center rounded-lg border border-dashed border-border bg-muted/50 text-xs font-black text-muted-foreground uppercase"
                >
                    <CalendarDays class="mb-3 size-8 opacity-70" />Libur
                </div>
            </div>
        </div>

        <FormModal :open="Boolean(selectedSchedule)" max-width-class="max-w-3xl" @close="selectedSchedule = null">
            <section v-if="selectedSchedule" class="grid gap-4">
                <div>
                    <p class="text-xs font-black tracking-wide text-red-500 uppercase">Detail Jadwal</p>
                    <h2 class="text-2xl font-black">{{ selectedSchedule.title }}</h2>
                    <p class="text-sm text-muted-foreground">
                        {{ selectedSchedule.day_label }} · {{ selectedSchedule.start_time }} -
                        {{ selectedSchedule.end_time }}
                    </p>
                </div>

                <div class="grid gap-3 rounded-xl border bg-muted/30 p-4 text-sm">
                    <p><span class="font-black">Tipe:</span> {{ typeLabel(selectedSchedule) }}</p>
                    <p><span class="font-black">Kelas:</span> {{ selectedSchedule.group || 'Sesi terbuka' }}</p>
                    <p v-if="participantLabel(selectedSchedule)">
                        <span class="font-black">Atlet / Anak:</span> {{ participantLabel(selectedSchedule) }}
                    </p>
                    <p><span class="font-black">Minimal sabuk:</span> {{ selectedSchedule.min_belt_label || '-' }}</p>
                    <p><span class="font-black">Coach:</span> {{ selectedSchedule.coach || '-' }}</p>
                    <p>
                        <span class="font-black">Lokasi:</span>
                        {{ selectedSchedule.location || selectedSchedule.branch || '-' }}
                    </p>
                </div>

                <LeafletLocationMap
                    v-if="selectedSchedule.latitude && selectedSchedule.longitude"
                    :latitude="selectedSchedule.latitude"
                    :longitude="selectedSchedule.longitude"
                    :marker-label="selectedSchedule.location || selectedSchedule.branch || selectedSchedule.title"
                />
                <div
                    v-else
                    class="rounded-xl border border-dashed p-6 text-center text-sm font-semibold text-muted-foreground"
                >
                    Koordinat lokasi belum diisi.
                </div>
            </section>
        </FormModal>
    </section>
</template>
