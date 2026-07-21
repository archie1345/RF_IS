<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { CalendarDays, Download } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import DataTable from '@/components/shared/DataTable.vue';
import PageSection from '@/components/shared/PageSection.vue';
import StatCard from '@/components/shared/StatCard.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';
import type { AttendanceReportPeriod } from '@/types/admin-feature';
import type { Metric, SelectOption, TableColumn, TableFilter, TableRow } from '@/types/resource-table';

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
        period: AttendanceReportPeriod;
        manualCoachAttendanceUrl?: string;
        coachOptions?: SelectOption[];
        sessionOptions?: SelectOption[];
    }>(),
    {
        metrics: () => [],
        columns: () => [],
        rows: () => [],
        emptyText: 'Tidak ada data',
        coachOptions: () => [],
        sessionOptions: () => [],
    },
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard.url() },
    { title: props.title, href: window.location.pathname },
];

const attendanceMonth = ref(props.period.month);
const attendanceRangeStart = ref(props.period.from);
const attendanceRangeEnd = ref(props.period.to);
const showManualCoachForm = ref(false);
const manualCoachForm = useForm({
    coach_id: '',
    training_session_id: '',
    status: 'TEACH',
});

const reportColumns = computed<TableColumn[]>(() => props.columns.map((column) => ({ key: column, label: column })));
const reportRows = computed<TableRow[]>(() =>
    props.rows.map((row, index) => ({
        id: String(row.No ?? row.ID ?? row.Id ?? index),
        ...row,
    })),
);

const exportUrl = computed(() => {
    const url = new URL(props.period.exportUrl, window.location.origin);
    url.searchParams.set('from', attendanceRangeStart.value);
    url.searchParams.set('to', attendanceRangeEnd.value);
    if (attendanceMonth.value) url.searchParams.set('month', attendanceMonth.value);
    return `${url.pathname}?${url.searchParams.toString()}`;
});

const usesClassFilter = computed(() => props.mode !== 'instructor-attendance' && props.columns.some((column) => ['Kelas', 'Class'].includes(column)));

const reportFilters = computed<TableFilter[]>(() => {
    const filters: TableFilter[] = [
        {
            key: 'member',
            label: props.mode === 'instructor-attendance' ? 'Coach' : 'Member',
            type: 'text',
            placeholder: 'Nama atau kode member...',
            accessor: (row) => [row.Atlet, row.Coach, row.Member, row.Anggota, row.Nama, row.No].filter(Boolean).join(' '),
        },
    ];

    if (usesClassFilter.value) {
        filters.push({
            key: 'class',
            label: 'Kelas',
            type: 'select',
            placeholder: 'Semua kelas',
            accessor: (row) => String(row.Kelas ?? row.Class ?? ''),
        });
    }

    filters.push({ key: 'status', label: 'Status', type: 'select', columnKey: 'Status', placeholder: 'Semua status' });

    return filters;
});

function formatDate(date: Date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function monthRange(monthValue: string) {
    const [year, month] = monthValue.split('-').map(Number);
    if (!year || !month) return null;
    return {
        from: formatDate(new Date(year, month - 1, 1)),
        to: formatDate(new Date(year, month, 0)),
    };
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
    reloadPeriod({ month: monthValue, ...range });
}

function applyDateRange() {
    reloadPeriod({ month: attendanceMonth.value, from: attendanceRangeStart.value, to: attendanceRangeEnd.value });
}

function cancelManualCoachForm() {
    manualCoachForm.reset();
    manualCoachForm.clearErrors();
    showManualCoachForm.value = false;
}

function submitManualCoachAttendance() {
    if (!props.manualCoachAttendanceUrl) return;

    manualCoachForm.post(props.manualCoachAttendanceUrl, {
        preserveScroll: true,
        onSuccess: () => {
            manualCoachForm.reset();
            showManualCoachForm.value = false;
        },
    });
}
</script>

<template>
    <Head :title="props.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageSection :title="props.title" :description="props.subtitle">
                <template v-if="props.mode === 'instructor-attendance' && props.manualCoachAttendanceUrl" #actions>
                    <Button type="button" @click="showManualCoachForm = !showManualCoachForm">
                        {{ showManualCoachForm ? 'Tutup Form Manual' : 'Tambah Presensi Coach' }}
                    </Button>
                </template>

                <p class="mt-1 text-xs font-semibold tracking-wide text-red-500 uppercase">{{ props.roleAccess }}</p>
            </PageSection>

            <section v-if="props.mode === 'instructor-attendance' && showManualCoachForm" class="rounded-xl border bg-card p-5 shadow-sm">
                <div class="mb-4">
                    <h2 class="text-lg font-black">Tambah presensi coach manual</h2>
                    <p class="text-sm text-muted-foreground">
                        Gunakan ini saat coach lupa dicatat. Pilihan sesi dibatasi sampai hari ini; sesi masa depan tidak bisa dipilih.
                    </p>
                </div>
                <form class="grid gap-4 lg:grid-cols-[1fr_1.5fr_180px_auto] lg:items-start" @submit.prevent="submitManualCoachAttendance">
                    <FormSelectField id="manual-coach-id" v-model="manualCoachForm.coach_id" label="Coach" :options="props.coachOptions" placeholder="Pilih coach" :error="manualCoachForm.errors.coach_id" />
                    <FormSelectField id="manual-session-id" v-model="manualCoachForm.training_session_id" label="Pilih sesi" :options="props.sessionOptions" placeholder="Pilih sesi sampai hari ini" search-placeholder="Cari sesi..." :error="manualCoachForm.errors.training_session_id" />
                    <FormSelectField id="manual-coach-status" v-model="manualCoachForm.status" label="Status" :options="[{ value: 'TEACH', label: 'Mengajar' }, { value: 'NOT_TEACH', label: 'Tidak Mengajar' }]" :error="manualCoachForm.errors.status" />
                    <div class="flex gap-2 pt-7">
                        <Button type="submit" :disabled="manualCoachForm.processing">Simpan</Button>
                        <Button type="button" variant="outline" @click="cancelManualCoachForm">Batal</Button>
                    </div>
                </form>
            </section>

            <section class="rounded-xl border bg-card p-5 shadow-sm">
                <div class="mb-5 grid gap-4 xl:grid-cols-[220px_1fr_1fr_auto]">
                    <label class="grid gap-1 text-sm font-semibold">
                        Bulan
                        <input v-model="attendanceMonth" type="month" class="h-10 rounded-lg border bg-background px-3 text-sm" @change="applyMonth()" />
                    </label>
                    <label class="grid gap-1 text-sm font-semibold">
                        Dari
                        <input v-model="attendanceRangeStart" type="date" class="h-10 rounded-lg border bg-background px-3 text-sm" @change="applyDateRange" />
                    </label>
                    <label class="grid gap-1 text-sm font-semibold">
                        Sampai
                        <input v-model="attendanceRangeEnd" type="date" class="h-10 rounded-lg border bg-background px-3 text-sm" @change="applyDateRange" />
                    </label>
                    <div class="flex flex-wrap items-end gap-2">
                        <a :href="exportUrl" class="inline-flex h-10 items-center justify-center rounded-md bg-primary px-3 text-sm font-semibold text-primary-foreground shadow hover:bg-primary/90">
                            <Download class="mr-2 size-4" /> Export
                        </a>
                    </div>
                </div>

                <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-muted/30 px-4 py-3 text-sm">
                    <div class="flex items-center gap-2 font-semibold">
                        <CalendarDays class="size-4 text-muted-foreground" />
                        <span>Periode aktif: {{ props.period.label }}</span>
                    </div>
                </div>

                <DataTable
                    title="Detail Presensi"
                    description="Gunakan filter tabel bersama untuk mencari member, kelas, dan status tanpa komponen tabel khusus."
                    :columns="reportColumns"
                    :rows="reportRows"
                    :filters="reportFilters"
                    :empty-text="props.emptyText"
                    filterable
                    searchable
                    search-placeholder="Cari semua kolom presensi..."
                />
            </section>
        </div>
    </AppLayout>
</template>
