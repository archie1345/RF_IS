<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import WeeklyScheduleBoard from '@/features/training/components/WeeklyScheduleBoard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { index as trainingScheduleIndex } from '@/routes/training-schedule';
import type { BreadcrumbItem } from '@/types';
import type { SelectOption, WeeklySchedule } from '@/types/training';

const props = withDefaults(
    defineProps<{
        title?: string;
        subtitle?: string;
        canManageSchedule?: boolean;
        currentCoachId?: string | null;
        weekRange?: { from: string; to: string };
        weeklySchedules?: WeeklySchedule[];
        branchOptions?: SelectOption[];
        groupOptions?: SelectOption[];
        coachOptions?: SelectOption[];
        athleteOptions?: SelectOption[];
    }>(),
    {
        title: 'Jadwal Latihan',
        subtitle: 'Jadwal latihan rutin Rhino Fighter',
        canManageSchedule: false,
        currentCoachId: null,
        weekRange: () => ({ from: '', to: '' }),
        weeklySchedules: () => [],
        branchOptions: () => [],
        groupOptions: () => [],
        coachOptions: () => [],
        athleteOptions: () => [],
    },
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard.url() },
    { title: props.title, href: trainingScheduleIndex.url() },
];
const scheduleView = ref<'cards' | 'table'>('cards');
const selectedChild = ref('');

const childFilterOptions = computed<SelectOption[]>(() => {
    const names = new Set<string>();
    props.weeklySchedules.forEach((schedule) => {
        String(schedule.child ?? '')
            .split(',')
            .map((name) => name.trim())
            .filter(Boolean)
            .forEach((name) => names.add(name));
    });

    return [
        { value: '', label: 'Semua anak' },
        ...Array.from(names).sort().map((name) => ({ value: name, label: name })),
    ];
});

const displayedSchedules = computed(() => {
    if (!selectedChild.value) return props.weeklySchedules;

    return props.weeklySchedules.filter((schedule) =>
        String(schedule.child ?? '')
            .split(',')
            .map((name) => name.trim())
            .includes(selectedChild.value),
    );
});
</script>

<template>
    <Head :title="props.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <FormSelectField
                    v-if="childFilterOptions.length > 2"
                    id="schedule-child-filter"
                    v-model="selectedChild"
                    label="Filter anak"
                    :options="childFilterOptions"
                    :show-placeholder="false"
                    class="w-full sm:max-w-xs"
                />

                <div v-if="props.canManageSchedule" class="flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-lg border px-4 py-2 text-sm font-bold"
                        :class="scheduleView === 'cards' ? 'bg-primary text-primary-foreground' : 'bg-card'"
                        @click="scheduleView = 'cards'"
                    >
                        Kartu
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border px-4 py-2 text-sm font-bold"
                        :class="scheduleView === 'table' ? 'bg-primary text-primary-foreground' : 'bg-card'"
                        @click="scheduleView = 'table'"
                    >
                        Tabel
                    </button>
                </div>
            </div>

            <WeeklyScheduleBoard
                v-if="!props.canManageSchedule || scheduleView === 'cards'"
                :schedules="displayedSchedules"
                :can-manage="props.canManageSchedule"
                :show-management-hint="false"
                :title="props.title"
                :subtitle="props.subtitle"
            />

            <section
                v-if="props.canManageSchedule && scheduleView === 'table'"
                class="rounded-xl border bg-card p-4 shadow-sm sm:p-5"
            >
                <div class="mb-4">
                    <h1 class="text-2xl font-bold">{{ props.title }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">{{ props.subtitle }}</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1050px] text-sm">
                        <thead>
                            <tr class="border-b text-left">
                                <th class="px-3 py-3 font-bold">Jadwal</th>
                                <th class="px-3 py-3 font-bold">Hari</th>
                                <th class="px-3 py-3 font-bold">Waktu</th>
                                <th class="px-3 py-3 font-bold">Lokasi</th>
                                <th class="px-3 py-3 font-bold">Tipe</th>
                                <th class="px-3 py-3 font-bold">Kelas</th>
                                <th class="px-3 py-3 font-bold">Atlet / Anak</th>
                                <th class="px-3 py-3 font-bold">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="displayedSchedules.length === 0">
                                <td colspan="8" class="h-32 px-3 text-center text-muted-foreground">Belum ada jadwal latihan.</td>
                            </tr>
                            <tr v-for="schedule in displayedSchedules" :key="schedule.id" class="border-b hover:bg-muted/30">
                                <td class="px-3 py-4 font-bold">{{ schedule.title }}</td>
                                <td class="px-3 py-4">{{ schedule.day_label ?? '-' }}</td>
                                <td class="px-3 py-4">{{ schedule.start_time || '--:--' }}–{{ schedule.end_time || '--:--' }}</td>
                                <td class="px-3 py-4">{{ schedule.location || schedule.branch || '-' }}</td>
                                <td class="px-3 py-4 capitalize">{{ (schedule.session_type ?? 'reguler').replace('_', ' ') }}</td>
                                <td class="px-3 py-4">{{ schedule.group || 'Semua kelas' }}</td>
                                <td class="px-3 py-4">{{ schedule.child || schedule.athletes || schedule.dedicated_athlete || '-' }}</td>
                                <td class="px-3 py-4">
                                    <span class="rounded-full px-3 py-1 text-xs font-bold" :class="schedule.is_active === false ? 'bg-muted text-muted-foreground' : 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'">
                                        {{ schedule.is_active === false ? 'NONAKTIF' : 'AKTIF' }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
