<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { CalendarDays, Download, Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import PageSection from '@/components/shared/PageSection.vue';
import StatCard from '@/components/shared/StatCard.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';
import type { AttendanceReportPeriod } from '@/types/admin-feature';
import type { Metric, SelectOption } from '@/types/resource-table';

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
const attendanceSearch = ref('');
const attendanceClass = ref('');
const attendanceStatus = ref('');
const visibleLimit = ref(10);
const showManualCoachForm = ref(false);
const manualCoachForm = useForm({
    coach_id: '',
    training_session_id: '',
    status: 'TEACH',
});
const exportUrl = computed(() => {
    const url = new URL(props.period.exportUrl, window.location.origin);
    url.searchParams.set('from', attendanceRangeStart.value);
    url.searchParams.set('to', attendanceRangeEnd.value);
    if (attendanceMonth.value) url.searchParams.set('month', attendanceMonth.value);
    return `${url.pathname}?${url.searchParams.toString()}`;
});

const usesClassFilter = computed(() => props.mode !== 'instructor-attendance' && props.columns.some((column) => ['Kelas', 'Class'].includes(column)));
const classOptions = computed(() => [
    { value: '', label: 'Semua kelas' },
    ...uniqueValuesFromColumns(['Kelas', 'Class']).map((option) => ({ value: option, label: option })),
]);
const statusOptions = computed(() => [
    { value: '', label: 'Semua status' },
    ...uniqueValuesFromColumns(['Status']).map((option) => ({ value: option, label: option })),
]);

const displayedRows = computed(() => {
    const keyword = attendanceSearch.value.trim().toLowerCase();
    const classValue = attendanceClass.value.trim().toLowerCase();
    const statusValue = attendanceStatus.value.trim().toLowerCase();

    return props.rows.filter((row) => {
        const memberText = [row.Atlet, row.Coach, row.Member, row.Anggota, row.Nama, row.No]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();
        const classText = String(row.Kelas ?? row.Class ?? '').toLowerCase();
        const statusText = String(row.Status ?? '').toLowerCase();

        return (
            (!keyword || memberText.includes(keyword)) &&
            (!usesClassFilter.value || !classValue || classText === classValue) &&
            (!statusValue || statusText === statusValue)
        );
    });
});

const visibleRows = computed(() => displayedRows.value.slice(0, visibleLimit.value));
const canShowMoreRows = computed(() => visibleRows.value.length < displayedRows.value.length);

watch([attendanceSearch, attendanceClass, attendanceStatus, () => props.rows.length], () => {
    visibleLimit.value = 10;
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

function uniqueValuesFromColumns(columns: string[]) {
    const seen = new Set<string>();
    props.rows.forEach((row) => {
        const value = columns
            .map((column) => row[column])
            .find(Boolean)
            ?.trim();
        if (value) seen.add(value);
    });
    return [...seen].sort((a, b) => a.localeCompare(b));
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

function clearFilters() {
    attendanceSearch.value = '';
    attendanceClass.value = '';
    attendanceStatus.value = '';
}

function showMoreRows() {
    visibleLimit.value += 10;
}

function showAllRows() {
    visibleLimit.value = displayedRows.value.length;
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

function isExternalUrl(value: unknown): value is string {
    return typeof value === 'string' && /^https?:\/\//i.test(value);
}

function linkLabel(value: string) {
    return value.includes('wa.me') ? 'Open WA' : 'Open';
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
                <div v-if="props.metrics.length" class="mt-4 grid gap-4 md:grid-cols-4">
                    <StatCard v-for="metric in props.metrics" :key="metric.label" v-bind="metric" />
                </div>
            </PageSection>

            <section v-if="props.mode === 'instructor-attendance' && showManualCoachForm" class="rounded-xl border bg-card p-5 shadow-sm">
                <div class="mb-4">
                    <h2 class="text-lg font-black">Tambah presensi coach manual</h2>
                    <p class="text-sm text-muted-foreground">
                        Gunakan ini saat coach lupa dicatat. Pilihan sesi dibatasi sampai hari ini; sesi masa depan tidak bisa dipilih.
                    </p>
                </div>
                <form class="grid gap-4 lg:grid-cols-[1fr_1.5fr_180px_auto] lg:items-start" @submit.prevent="submitManualCoachAttendance">
                    <FormSelectField
                        id="manual-coach-id"
                        v-model="manualCoachForm.coach_id"
                        label="Coach"
                        :options="props.coachOptions"
                        placeholder="Pilih coach"
                        :error="manualCoachForm.errors.coach_id"
                    />

                    <FormSelectField
                        id="manual-session-id"
                        v-model="manualCoachForm.training_session_id"
                        label="Pilih sesi"
                        :options="props.sessionOptions"
                        placeholder="Pilih sesi sampai hari ini"
                        search-placeholder="Cari sesi..."
                        :error="manualCoachForm.errors.training_session_id"
                    />

                    <FormSelectField
                        id="manual-coach-status"
                        v-model="manualCoachForm.status"
                        label="Status"
                        :options="[
                            { value: 'TEACH', label: 'Mengajar' },
                            { value: 'NOT_TEACH', label: 'Tidak Mengajar' },
                        ]"
                        :error="manualCoachForm.errors.status"
                    />

                    <div class="flex gap-2 pt-7">
                        <Button type="submit" :disabled="manualCoachForm.processing">Simpan</Button>
                        <Button type="button" variant="outline" @click="cancelManualCoachForm">Batal</Button>
                    </div>
                </form>
            </section>

            <section class="rounded-xl border bg-card p-5 shadow-sm">
                <div class="mb-5 grid gap-4 xl:grid-cols-[220px_280px_1fr_1fr_auto]">
                    <label class="grid gap-1 text-sm font-semibold">
                        Bulan
                        <input
                            v-model="attendanceMonth"
                            type="month"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                            @change="applyMonth()"
                        />
                    </label>

                    <label class="grid gap-1 text-sm font-semibold">
                        Cari Member
                        <div class="relative">
                            <Search class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <input
                                v-model="attendanceSearch"
                                class="h-10 w-full rounded-lg border bg-background pr-3 pl-10 text-sm"
                                placeholder="Nama atau Kode Member..."
                            />
                        </div>
                    </label>

                    <FormSelectField
                        v-if="usesClassFilter"
                        id="attendance-class-filter"
                        v-model="attendanceClass"
                        label="Kelas"
                        :options="classOptions"
                        placeholder="Semua kelas"
                    />

                    <FormSelectField
                        id="attendance-status-filter"
                        v-model="attendanceStatus"
                        label="Status"
                        :options="statusOptions"
                        placeholder="Semua status"
                    />

                    <div class="flex flex-wrap items-end gap-2">
                        <a
                            :href="exportUrl"
                            class="inline-flex h-10 items-center justify-center rounded-md bg-primary px-3 text-sm font-semibold text-primary-foreground shadow hover:bg-primary/90"
                        >
                            <Download class="mr-2 size-4" /> Export
                        </a>
                        <Button type="button" variant="ghost" size="sm" class="h-10" @click="clearFilters"
                            >Reset</Button
                        >
                    </div>
                </div>

                <div
                    class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-muted/30 px-4 py-3 text-sm"
                >
                    <div class="flex items-center gap-2 font-semibold">
                        <CalendarDays class="size-4 text-muted-foreground" />
                        <span>Periode aktif: {{ props.period.label }}</span>
                    </div>
                    <span class="text-muted-foreground"
                        >{{ visibleRows.length }} dari {{ displayedRows.length }} baris ditampilkan<span v-if="displayedRows.length !== props.rows.length"> · {{ props.rows.length }} total</span></span
                    >
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-sm">
                        <thead>
                            <tr class="border-b text-left">
                                <th v-for="column in props.columns" :key="column" class="px-3 py-3 font-black">
                                    {{ column }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="visibleRows.length === 0">
                                <td
                                    :colspan="Math.max(props.columns.length, 1)"
                                    class="h-40 px-3 text-center text-muted-foreground"
                                >
                                    {{ props.emptyText }}
                                </td>
                            </tr>
                            <tr v-for="(row, index) in visibleRows" :key="index" class="border-b hover:bg-muted/40">
                                <td v-for="column in props.columns" :key="column" class="px-3 py-3 whitespace-pre-line">
                                    <a
                                        v-if="isExternalUrl(row[column])"
                                        :href="row[column]"
                                        target="_blank"
                                        rel="noreferrer"
                                        class="font-semibold text-primary underline-offset-4 hover:underline"
                                        >{{ linkLabel(row[column]) }}</a
                                    >
                                    <span v-else>{{ row[column] ?? '-' }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="canShowMoreRows" class="flex flex-wrap items-center justify-center gap-2 pt-4">
                    <Button type="button" variant="outline" size="sm" @click="showMoreRows">Tampilkan 10 lagi</Button>
                    <Button type="button" variant="ghost" size="sm" @click="showAllRows">Tampilkan semua {{ displayedRows.length }}</Button>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
