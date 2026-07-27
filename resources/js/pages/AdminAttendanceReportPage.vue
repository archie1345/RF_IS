<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { CalendarDays, Download } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import FormInputField from '@/components/forms/FormInputField.vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import DataTable from '@/components/shared/DataTable.vue';
import FormModal from '@/components/shared/FormModal.vue';
import PageSection from '@/components/shared/PageSection.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';
import type { AttendanceReportPeriod } from '@/types/admin-feature';
import type { SelectOption, StatusTone, TableBadgeCell, TableColumn, TableFilter, TableRow } from '@/types/resource-table';

const props = withDefaults(
    defineProps<{
        mode: 'attendance' | 'instructor-attendance';
        title: string;
        subtitle: string;
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
const manualCoachForm = useForm({ coach_id: '', training_session_id: '', status: 'TEACH' });

const isCoachReport = computed(() => props.mode === 'instructor-attendance');
const reportColumns = computed<TableColumn[]>(() => props.columns.map((column) => ({ key: column, label: column })));
const reportRows = computed<TableRow[]>(() =>
    props.rows.map((row, index) => ({ id: String(row.No ?? row.ID ?? row.Id ?? row.Coach ?? row.Atlet ?? index), ...row })),
);
const totalCoachRecords = computed(() => reportRows.value.reduce((total, row) => total + Number(row['Total Catatan'] ?? 0), 0));
const totalTeachingRecords = computed(() => reportRows.value.reduce((total, row) => total + Number(row.Mengajar ?? 0), 0));
const coachTeachingRate = computed(() => totalCoachRecords.value > 0 ? Math.round((totalTeachingRecords.value / totalCoachRecords.value) * 100) : 0);

const exportUrl = computed(() => {
    const url = new URL(props.period.exportUrl, window.location.origin);
    url.searchParams.set('from', attendanceRangeStart.value);
    url.searchParams.set('to', attendanceRangeEnd.value);
    if (attendanceMonth.value) url.searchParams.set('month', attendanceMonth.value);
    return `${url.pathname}?${url.searchParams.toString()}`;
});
const usesClassFilter = computed(() =>
    props.mode !== 'instructor-attendance' && props.columns.some((column) => ['Kelas', 'Class'].includes(column)),
);
const reportFilters = computed<TableFilter[]>(() => {
    const filters: TableFilter[] = [];
    if (usesClassFilter.value) {
        filters.push({ key: 'class', label: 'Kelas', type: 'select', placeholder: 'Semua kelas', accessor: (row) => String(row.Kelas ?? row.Class ?? '') });
    }
    if (isCoachReport.value) {
        filters.push({ key: 'coach', label: 'Coach', type: 'select', columnKey: 'Coach', placeholder: 'Semua coach' });
    }
    filters.push({ key: 'status', label: 'Status', type: 'select', columnKey: 'Status', placeholder: 'Semua status' });
    return filters;
});

function cellText(value: unknown): string {
    if (value && typeof value === 'object' && 'text' in value) return String((value as TableBadgeCell).text);
    return String(value ?? '-');
}

function isStatusColumn(key: string): boolean {
    return key.toLowerCase().includes('status');
}

function attendanceTone(value: unknown): StatusTone {
    const status = cellText(value).trim().toUpperCase();
    if (['PRESENT', 'HADIR', 'TEACH', 'MENGAJAR', 'APPROVED'].includes(status)) return 'success';
    if (['EXCUSED', 'IZIN', 'LATE', 'TERLAMBAT'].includes(status)) return 'info';
    if (['PENDING', 'MENUNGGU'].includes(status)) return 'warning';
    if (['ABSENT', 'TIDAK HADIR', 'NOT_TEACH', 'TIDAK MENGAJAR', 'ALPHA'].includes(status)) return 'danger';
    return 'neutral';
}

function formatDate(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function monthRange(monthValue: string) {
    const [year, month] = monthValue.split('-').map(Number);
    if (!year || !month) return null;
    return { from: formatDate(new Date(year, month - 1, 1)), to: formatDate(new Date(year, month, 0)) };
}

function reloadPeriod(params: { month?: string; from: string; to: string }): void {
    router.get(window.location.pathname, params, { preserveScroll: true, preserveState: true });
}

function applyMonth(monthValue = attendanceMonth.value): void {
    const range = monthRange(monthValue);
    if (!range) return;
    attendanceMonth.value = monthValue;
    attendanceRangeStart.value = range.from;
    attendanceRangeEnd.value = range.to;
    reloadPeriod({ month: monthValue, ...range });
}

function applyDateRange(): void {
    reloadPeriod({ month: attendanceMonth.value, from: attendanceRangeStart.value, to: attendanceRangeEnd.value });
}

function cancelManualCoachForm(): void {
    manualCoachForm.reset();
    manualCoachForm.clearErrors();
    showManualCoachForm.value = false;
}

function submitManualCoachAttendance(): void {
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
        <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
            <PageSection :title="props.title" :description="props.subtitle">
                <template v-if="isCoachReport && props.manualCoachAttendanceUrl" #actions>
                    <Button type="button" @click="showManualCoachForm = true">Tambah presensi coach</Button>
                </template>
                <p class="mt-1 text-xs font-semibold tracking-wide text-primary uppercase">{{ props.roleAccess }}</p>

                <div v-if="isCoachReport" class="mt-4 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border bg-card p-4"><p class="text-xs text-muted-foreground">Coach</p><p class="mt-1 text-2xl font-bold">{{ reportRows.length }}</p></div>
                    <div class="rounded-xl border bg-card p-4"><p class="text-xs text-muted-foreground">Catatan mengajar</p><p class="mt-1 text-2xl font-bold">{{ totalTeachingRecords }} / {{ totalCoachRecords }}</p></div>
                    <div class="rounded-xl border bg-card p-4"><p class="text-xs text-muted-foreground">Tingkat mengajar</p><p class="mt-1 text-2xl font-bold">{{ coachTeachingRate }}%</p></div>
                </div>
            </PageSection>

            <FormModal :open="isCoachReport && showManualCoachForm" max-width-class="max-w-4xl" @close="cancelManualCoachForm">
                <PageSection title="Tambah presensi coach manual" description="Gunakan saat catatan mengajar belum tersimpan.">
                    <form class="grid gap-4 lg:grid-cols-[1fr_1.5fr_180px]" @submit.prevent="submitManualCoachAttendance">
                        <FormSelectField id="manual-coach-id" v-model="manualCoachForm.coach_id" label="Coach" :options="props.coachOptions" placeholder="Pilih coach" :multiple="false" :error="manualCoachForm.errors.coach_id" />
                        <FormSelectField id="manual-session-id" v-model="manualCoachForm.training_session_id" label="Sesi" :options="props.sessionOptions" placeholder="Pilih sesi sampai hari ini" :multiple="false" :error="manualCoachForm.errors.training_session_id" />
                        <FormSelectField id="manual-coach-status" v-model="manualCoachForm.status" label="Status" :options="[{ value: 'TEACH', label: 'Mengajar' }, { value: 'NOT_TEACH', label: 'Tidak Mengajar' }]" :multiple="false" :error="manualCoachForm.errors.status" />
                        <div class="flex gap-2 lg:col-span-3"><Button type="submit" :disabled="manualCoachForm.processing">Simpan</Button><Button type="button" variant="outline" @click="cancelManualCoachForm">Batal</Button></div>
                    </form>
                </PageSection>
            </FormModal>

            <section class="rounded-xl border bg-card p-4 shadow-sm sm:p-5">
                <div class="mb-4 grid gap-3 xl:grid-cols-[220px_1fr_1fr_auto]">
                    <label class="grid gap-1 text-sm font-semibold">Bulan<input v-model="attendanceMonth" type="month" class="h-10 rounded-lg border bg-background px-3 text-sm" @change="applyMonth()" /></label>
                    <FormInputField id="attendance-range-start" v-model="attendanceRangeStart" label="Dari" type="date" @update:model-value="applyDateRange" />
                    <FormInputField id="attendance-range-end" v-model="attendanceRangeEnd" label="Sampai" type="date" @update:model-value="applyDateRange" />
                    <div class="flex items-end"><a :href="exportUrl" class="inline-flex h-10 items-center rounded-md bg-primary px-3 text-sm font-semibold text-primary-foreground"><Download class="mr-2 size-4" />Export</a></div>
                </div>
                <div class="mb-4 flex items-center gap-2 rounded-lg border bg-muted/20 px-4 py-3 text-sm font-semibold"><CalendarDays class="size-4" />Periode: {{ props.period.label }}</div>

                <DataTable
                    :title="isCoachReport ? 'Ringkasan presensi coach' : 'Detail presensi atlet'"
                    description="Status diberi warna berbeda agar hadir, izin, terlambat, tidak hadir, mengajar, dan tidak mengajar mudah dibedakan."
                    :columns="reportColumns"
                    :rows="reportRows"
                    :filters="reportFilters"
                    :empty-text="props.emptyText"
                    filterable
                    searchable
                    :filter-columns="isCoachReport ? 3 : 'auto'"
                    search-placeholder="Cari semua kolom presensi..."
                >
                    <template #cell="{ column, value }">
                        <StatusBadge v-if="isStatusColumn(column.key)" :label="cellText(value)" :tone="attendanceTone(value)" />
                        <span v-else>{{ cellText(value) }}</span>
                    </template>
                </DataTable>
            </section>
        </div>
    </AppLayout>
</template>
