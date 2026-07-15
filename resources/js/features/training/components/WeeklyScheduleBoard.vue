<script setup lang="ts">
import { CalendarDays, Clock3, Crown, Info, MapPin, RefreshCcw, UserRound, UsersRound } from 'lucide-vue-next';
import { computed } from 'vue';

type WeeklyScheduleCard = {
    id: number;
    title: string;
    branch?: string | null;
    group?: string | null;
    coach?: string | null;
    day_of_week: number;
    day_label?: string | null;
    start_time?: string | null;
    end_time?: string | null;
    location?: string | null;
    is_active?: boolean;
    can_manage?: boolean;
    class_type?: string | null;
    athletes_count?: number | null;
};

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
        subtitle: 'Jadwal latihan rutin RTFCM',
    },
);

const emit = defineEmits<{
    edit: [schedule: WeeklyScheduleCard];
    delete: [schedule: WeeklyScheduleCard];
    refresh: [];
}>();

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
const todayLabel = computed(() =>
    today
        .toLocaleDateString('en-GB', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' })
        .toUpperCase(),
);

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
    return 'RTFCM';
});

const legendItems = [
    { label: 'REGULER', dot: 'bg-blue-500' },
    { label: 'PRESTASI', dot: 'bg-red-500' },
    { label: 'PRIVATE', dot: 'bg-violet-500' },
];

function typeLabel(schedule: WeeklyScheduleCard): string {
    return (schedule.class_type || schedule.group || 'Reguler').toString().toUpperCase();
}

function typeTone(schedule: WeeklyScheduleCard): string {
    const value = typeLabel(schedule).toLowerCase();
    if (value.includes('prestasi') || value.includes('advanced') || value.includes('senior'))
        return 'border-red-200 bg-red-50 text-red-700';
    if (value.includes('private')) return 'border-violet-200 bg-violet-50 text-violet-700';
    return 'border-blue-200 bg-blue-50 text-blue-700';
}
</script>

<template>
    <section class="rounded-2xl border bg-background p-4 text-foreground shadow-sm md:p-6">
        <div class="mb-7 grid gap-4 xl:grid-cols-[1fr_auto_1fr] xl:items-start">
            <div>
                <h2 class="text-3xl font-black tracking-tight">{{ title }}</h2>
                <p class="mt-1 text-xs font-black text-red-500 uppercase">DOJANG: {{ branchLabel }}</p>
                <p class="mt-1 text-sm font-medium text-muted-foreground">{{ subtitle }}</p>
            </div>

            <div
                class="justify-self-start rounded-full border bg-card px-5 py-2 text-sm font-black shadow-sm xl:justify-self-center"
            >
                <span class="mr-2 inline-flex size-2 rounded-full bg-blue-500"></span>
                TODAY: {{ todayLabel }}
            </div>

            <div class="flex justify-start gap-3 xl:justify-end">
                <button
                    type="button"
                    class="inline-flex size-12 items-center justify-center rounded-xl bg-red-100 text-red-600 transition hover:bg-red-200"
                    @click="emit('refresh')"
                >
                    <RefreshCcw class="size-6" />
                </button>
                <div
                    v-if="showManagementHint"
                    class="flex min-h-12 items-center gap-3 rounded-xl border bg-card px-4 text-sm font-black text-muted-foreground uppercase shadow-sm"
                >
                    <Info class="size-5 text-muted-foreground" />
                    Atur jadwal di menu Master Data &gt; Kelas
                </div>
            </div>
        </div>

        <div class="grid gap-3 md:grid-cols-7">
            <div
                v-for="day in days"
                :key="`head-${day.id}`"
                class="rounded-xl border bg-card px-5 py-5 shadow-sm"
                :class="day.id === todayDay ? 'border-red-500 bg-red-500 text-white' : 'border-border text-foreground'"
            >
                <p class="text-xl leading-none font-black">{{ day.name }}</p>
                <p class="mt-1 text-xs" :class="day.id === todayDay ? 'text-white' : 'text-muted-foreground'">
                    {{ day.english }}
                </p>
            </div>
        </div>

        <div class="mt-4 grid gap-3 md:grid-cols-7">
            <div
                v-for="day in days"
                :key="`body-${day.id}`"
                class="min-h-[280px] rounded-xl border bg-card p-4 shadow-sm"
            >
                <template v-if="(schedulesByDay.get(day.id) ?? []).length">
                    <article
                        v-for="schedule in schedulesByDay.get(day.id)"
                        :key="schedule.id"
                        class="mb-3 rounded-xl border-l-4 border-red-500 bg-background p-4 shadow-md"
                    >
                        <div class="mb-3 border-b border-border pb-3">
                            <h3 class="text-base font-black">
                                {{
                                    schedule.group && schedule.group !== 'All groups' ? schedule.group : schedule.title
                                }}
                            </h3>
                            <span
                                class="mt-2 inline-flex items-center gap-1 rounded-md border px-2 py-1 text-[11px] font-black"
                                :class="typeTone(schedule)"
                            >
                                <Crown class="size-3" /> {{ typeLabel(schedule) }}
                            </span>
                        </div>

                        <div class="space-y-4 text-xs font-black text-muted-foreground uppercase">
                            <div class="flex gap-3">
                                <UserRound class="mt-0.5 size-4 shrink-0 text-red-500" />
                                <div>
                                    <p>Instruktur</p>
                                    <p class="text-sm text-foreground normal-case">{{ schedule.coach || 'TBA' }}</p>
                                </div>
                            </div>
                            <div class="flex gap-3">
                                <Clock3 class="mt-0.5 size-4 shrink-0 text-blue-500" />
                                <div>
                                    <p>Waktu</p>
                                    <p class="text-sm text-foreground">
                                        {{ schedule.start_time || '--:--' }} - {{ schedule.end_time || '--:--' }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex gap-3">
                                <UsersRound class="mt-0.5 size-4 shrink-0 text-orange-500" />
                                <div>
                                    <p>Peserta</p>
                                    <p class="text-sm text-foreground">{{ schedule.athletes_count ?? '-' }}</p>
                                </div>
                            </div>
                            <div class="flex gap-3">
                                <MapPin class="mt-0.5 size-4 shrink-0 text-emerald-500" />
                                <div>
                                    <p>Lokasi</p>
                                    <p class="text-sm text-foreground normal-case">
                                        {{ schedule.location || schedule.branch || 'RTFCM' }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="canManage && schedule.can_manage"
                            class="mt-4 flex gap-2 border-t border-border pt-3"
                        >
                            <button
                                type="button"
                                class="rounded-lg border px-3 py-1 text-xs font-bold hover:bg-muted"
                                @click="emit('edit', schedule)"
                            >
                                Edit
                            </button>
                            <button
                                type="button"
                                class="rounded-lg border px-3 py-1 text-xs font-bold text-red-600 hover:bg-red-50"
                                @click="emit('delete', schedule)"
                            >
                                Delete
                            </button>
                        </div>
                    </article>
                </template>
                <div
                    v-else
                    class="flex h-full min-h-[240px] flex-col items-center justify-center text-xs font-black text-muted-foreground/50 uppercase"
                >
                    <CalendarDays class="mb-3 size-8" />
                    Libur
                </div>
            </div>
        </div>

        <div
            class="mx-auto mt-10 flex w-fit flex-wrap items-center justify-center gap-5 rounded-full border bg-card px-8 py-4 text-sm font-bold text-muted-foreground shadow-lg"
        >
            <div class="flex items-center gap-2">
                <Info class="size-4 text-blue-500" /> Jadwal sinkron otomatis dengan Manajemen Kelas
            </div>
            <div class="hidden h-6 w-px bg-border md:block"></div>
            <div
                v-for="item in legendItems"
                :key="item.label"
                class="rounded-full border px-3 py-1 text-xs font-black text-foreground"
            >
                <span class="mr-1 inline-flex size-2 rounded-full" :class="item.dot"></span>{{ item.label }}
            </div>
        </div>
    </section>
</template>
