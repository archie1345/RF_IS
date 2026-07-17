<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
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
</script>

<template>
    <Head :title="props.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <div v-if="props.canManageSchedule" class="flex flex-wrap justify-end gap-2">
                <button
                    type="button"
                    class="rounded-lg border px-4 py-2 text-sm font-bold"
                    :class="scheduleView === 'cards' ? 'bg-primary text-primary-foreground' : 'bg-card'"
                    @click="scheduleView = 'cards'"
                >
                    List
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

            <WeeklyScheduleBoard
                v-if="!props.canManageSchedule || scheduleView === 'cards'"
                :schedules="props.weeklySchedules"
                :can-manage="props.canManageSchedule"
                :show-management-hint="false"
                :title="props.title"
                :subtitle="props.subtitle"
            />

            <section
                v-if="props.canManageSchedule && scheduleView === 'table'"
                class="rounded-2xl border bg-card p-5 shadow-sm"
            >
                <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 class="text-3xl font-black">{{ props.title }}</h1>
                        <p class="mt-1 text-sm text-muted-foreground">{{ props.subtitle }}</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[980px] text-sm">
                        <thead>
                            <tr class="border-b text-left">
                                <th class="px-3 py-3 font-black">Jadwal</th>
                                <th class="px-3 py-3 font-black">Hari</th>
                                <th class="px-3 py-3 font-black">Waktu</th>
                                <th class="px-3 py-3 font-black">Lokasi</th>
                                <th class="px-3 py-3 font-black">Tipe</th>
                                <th class="px-3 py-3 font-black">Kelas / Atlet</th>
                                <th class="px-3 py-3 font-black">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="props.weeklySchedules.length === 0">
                                <td colspan="10" class="h-32 px-3 text-center text-muted-foreground">
                                    Belum ada jadwal latihan.
                                </td>
                            </tr>
                            <tr
                                v-for="schedule in props.weeklySchedules"
                                :key="schedule.id"
                                class="border-b hover:bg-muted/40"
                            >
                                <td class="px-3 py-4">
                                    <p class="font-black">{{ schedule.title }}</p>
                                    <p class="text-xs text-muted-foreground">{{ schedule.class_type ?? '-' }}</p>
                                </td>
                                <td class="px-3 py-4">{{ schedule.day_label ?? '-' }}</td>
                                <td class="px-3 py-4">
                                    {{ schedule.start_time || '--:--' }} - {{ schedule.end_time || '--:--' }}
                                </td>
                                <td class="px-3 py-4">{{ schedule.location || schedule.branch || '-' }}</td>
                                <td class="px-3 py-4 font-bold capitalize">
                                    {{ (schedule.session_type ?? 'reguler').replace('_', ' ') }}
                                </td>
                                <td class="px-3 py-4">
                                    {{
                                        schedule.session_type === 'private'
                                            ? schedule.dedicated_athlete || '-'
                                            : schedule.group || 'All groups'
                                    }}
                                </td>
                                <td class="px-3 py-4">
                                    <span
                                        class="rounded-full px-3 py-1 text-xs font-black"
                                        :class="
                                            schedule.is_active === false
                                                ? 'bg-slate-100 text-slate-500'
                                                : 'bg-green-100 text-green-700'
                                        "
                                        >{{ schedule.is_active === false ? 'NONAKTIF' : 'AKTIF' }}</span
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
