<script setup lang="ts">
import { CalendarDays, Clock3, Crown, MapPin, RefreshCcw, UserRound } from 'lucide-vue-next';
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
    { id: 1, name: 'Senin' },
    { id: 2, name: 'Selasa' },
    { id: 3, name: 'Rabu' },
    { id: 4, name: 'Kamis' },
    { id: 5, name: 'Jumat' },
    { id: 6, name: 'Sabtu' },
    { id: 7, name: 'Minggu' },
];
const todayDay = new Date().getDay() === 0 ? 7 : new Date().getDay();

const schedulesByDay = computed(() => {
    const grouped = new Map<number, WeeklyScheduleCard[]>();
    props.schedules
        .filter((schedule) => schedule.is_active !== false)
        .forEach((schedule) => grouped.set(schedule.day_of_week, [...(grouped.get(schedule.day_of_week) ?? []), schedule]));
    return grouped;
});

function typeLabel(schedule: WeeklyScheduleCard): string {
    return (schedule.session_type || schedule.class_type || schedule.group || 'Reguler')
        .toString()
        .replace(/_/g, ' ')
        .toUpperCase();
}

function participantLabel(schedule: WeeklyScheduleCard): string {
    return String(schedule.child || schedule.athletes || schedule.dedicated_athlete || '').trim();
}

function openSchedule(schedule: WeeklyScheduleCard): void {
    selectedSchedule.value = schedule;
}
</script>

<template>
    <section class="rounded-xl border bg-card p-4 shadow-sm md:p-5">
        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold">{{ title }}</h2>
                <p class="mt-1 text-sm text-muted-foreground">{{ subtitle }}</p>
            </div>
            <button
                type="button"
                class="inline-flex size-10 items-center justify-center self-start rounded-lg border bg-background hover:bg-muted"
                title="Muat ulang jadwal"
                @click="emit('refresh')"
            >
                <RefreshCcw class="size-4" />
            </button>
        </div>

        <div class="grid gap-4 xl:grid-cols-7">
            <section v-for="day in days" :key="day.id" class="min-w-0">
                <div
                    class="mb-2 rounded-lg border px-3 py-2"
                    :class="day.id === todayDay ? 'border-primary bg-primary text-primary-foreground' : 'bg-background'"
                >
                    <p class="font-bold">{{ day.name }}</p>
                </div>

                <div class="grid min-h-32 gap-2 rounded-lg border bg-muted/20 p-2">
                    <article
                        v-for="schedule in schedulesByDay.get(day.id) ?? []"
                        :key="schedule.id"
                        class="cursor-pointer rounded-lg border bg-background p-3 transition hover:border-primary/50 hover:bg-muted/20"
                        @click="openSchedule(schedule)"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <h3 class="truncate text-sm font-bold">{{ schedule.title }}</h3>
                                <p class="truncate text-xs text-muted-foreground">{{ schedule.group || 'Sesi terbuka' }}</p>
                            </div>
                            <span class="shrink-0 rounded-md bg-primary/10 px-1.5 py-1 text-[10px] font-bold text-primary">
                                {{ typeLabel(schedule) }}
                            </span>
                        </div>

                        <div class="mt-3 grid gap-1.5 text-xs text-muted-foreground">
                            <p class="flex items-center gap-1.5"><Clock3 class="size-3.5" />{{ schedule.start_time || '--:--' }}–{{ schedule.end_time || '--:--' }}</p>
                            <p class="flex items-center gap-1.5"><MapPin class="size-3.5" />{{ schedule.location || schedule.branch || '-' }}</p>
                            <p v-if="participantLabel(schedule)" class="flex items-start gap-1.5 font-medium text-foreground">
                                <UserRound class="mt-0.5 size-3.5 shrink-0" />
                                <span class="break-words">{{ participantLabel(schedule) }}</span>
                            </p>
                        </div>
                    </article>

                    <div
                        v-if="(schedulesByDay.get(day.id) ?? []).length === 0"
                        class="flex min-h-24 flex-col items-center justify-center text-xs text-muted-foreground"
                    >
                        <CalendarDays class="mb-2 size-5" /> Tidak ada jadwal
                    </div>
                </div>
            </section>
        </div>

        <FormModal :open="Boolean(selectedSchedule)" max-width-class="max-w-2xl" @close="selectedSchedule = null">
            <section v-if="selectedSchedule" class="grid gap-4">
                <div>
                    <p class="text-xs font-bold tracking-wide text-primary uppercase">Detail jadwal</p>
                    <h2 class="text-2xl font-bold">{{ selectedSchedule.title }}</h2>
                    <p class="text-sm text-muted-foreground">
                        {{ selectedSchedule.day_label }} · {{ selectedSchedule.start_time }}–{{ selectedSchedule.end_time }}
                    </p>
                </div>

                <dl class="grid gap-3 rounded-xl border bg-muted/20 p-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-xs text-muted-foreground">Tipe</dt><dd class="font-semibold">{{ typeLabel(selectedSchedule) }}</dd></div>
                    <div><dt class="text-xs text-muted-foreground">Kelas</dt><dd class="font-semibold">{{ selectedSchedule.group || 'Sesi terbuka' }}</dd></div>
                    <div><dt class="text-xs text-muted-foreground">Pelatih</dt><dd class="font-semibold">{{ selectedSchedule.coach || '-' }}</dd></div>
                    <div><dt class="text-xs text-muted-foreground">Lokasi</dt><dd class="font-semibold">{{ selectedSchedule.location || selectedSchedule.branch || '-' }}</dd></div>
                    <div v-if="participantLabel(selectedSchedule)" class="sm:col-span-2">
                        <dt class="text-xs text-muted-foreground">Atlet / anak pada jadwal ini</dt>
                        <dd class="font-semibold">{{ participantLabel(selectedSchedule) }}</dd>
                    </div>
                    <div><dt class="text-xs text-muted-foreground">Minimal sabuk</dt><dd class="font-semibold">{{ selectedSchedule.min_belt_label || '-' }}</dd></div>
                </dl>

                <LeafletLocationMap
                    v-if="selectedSchedule.latitude && selectedSchedule.longitude"
                    :latitude="selectedSchedule.latitude"
                    :longitude="selectedSchedule.longitude"
                    :marker-label="selectedSchedule.location || selectedSchedule.branch || selectedSchedule.title"
                />
            </section>
        </FormModal>
    </section>
</template>
