<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import DataTable from '@/components/shared/DataTable.vue';
import WeeklyScheduleBoard from '@/features/training/components/WeeklyScheduleBoard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { index as trainingScheduleIndex } from '@/routes/training-schedule';
import type { BreadcrumbItem } from '@/types';
import type { TableColumn, TableRow } from '@/types/resource-table';
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
const scheduleColumns: TableColumn[] = [
    { key: 'title', label: 'Jadwal' },
    { key: 'day_label', label: 'Hari' },
    { key: 'time', label: 'Waktu' },
    { key: 'location_display', label: 'Lokasi' },
    { key: 'session_type_display', label: 'Tipe' },
    { key: 'group_display', label: 'Kelas' },
    { key: 'participant_display', label: 'Atlet / Anak' },
    { key: 'status', label: 'Status' },
];

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
        ...Array.from(names)
            .sort()
            .map((name) => ({ value: name, label: name })),
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

const scheduleTableRows = computed<TableRow[]>(() =>
    displayedSchedules.value.map((schedule) => ({
        id: `WS-${schedule.id}`,
        title: schedule.title,
        day_label: schedule.day_label ?? '-',
        time: `${schedule.start_time || '--:--'}–${schedule.end_time || '--:--'}`,
        location_display: schedule.location || schedule.branch || '-',
        session_type_display: String(schedule.session_type ?? 'reguler').replaceAll('_', ' '),
        group_display: schedule.group || 'Semua kelas',
        participant_display: schedule.child || schedule.athletes || schedule.dedicated_athlete || '-',
        status: schedule.is_active === false ? 'INACTIVE' : 'ACTIVE',
    })),
);
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

            <DataTable
                v-if="props.canManageSchedule && scheduleView === 'table'"
                :title="props.title"
                :description="props.subtitle"
                :columns="scheduleColumns"
                :rows="scheduleTableRows"
                searchable
                filterable
                search-placeholder="Cari jadwal, kelas, pelatih, anak, atau atlet"
                empty-text="Belum ada jadwal latihan."
            />
        </div>
    </AppLayout>
</template>
