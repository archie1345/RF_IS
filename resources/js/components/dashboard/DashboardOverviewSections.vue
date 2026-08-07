<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Activity,
    ArrowRight,
    Award,
    CalendarClock,
    CalendarDays,
    CheckCircle2,
    CircleAlert,
    Clock3,
    MapPin,
    Megaphone,
    ReceiptText,
    Trophy,
    UserRound,
    WalletCards,
} from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { index as achievementsIndex } from '@/routes/achievements';
import { index as activityLogsIndex } from '@/routes/admin/activity-logs';
import { index as announcementsIndex } from '@/routes/announcements';
import { index as attendanceIndex } from '@/routes/attendance';
import { index as championshipsIndex } from '@/routes/championships';
import { index as paymentsIndex } from '@/routes/payments';
import { index as sessionsIndex } from '@/routes/sessions';
import { index as trainingScheduleIndex } from '@/routes/training-schedule';
import type { AppRole, TableBadgeCell, TableRow } from '@/types/resource-table';
import type { DashboardAttendanceRow, DashboardTrainingDay, BeltRow } from './DashboardOverviewSections.types';

const props = withDefaults(
    defineProps<{
        role: AppRole;
        announcements?: TableRow[];
        upcomingEvents?: TableRow[];
        profileSummary?: Record<string, string>;
        medalRows?: TableRow[];
        activityPreviewRows?: TableRow[];
        attendanceRows?: DashboardAttendanceRow[];
        trainingDays?: DashboardTrainingDay[];
        paymentRows?: TableRow[];
    }>(),
    {
        announcements: () => [],
        upcomingEvents: () => [],
        profileSummary: () => ({}),
        medalRows: () => [],
        activityPreviewRows: () => [],
        attendanceRows: () => [],
        trainingDays: () => [],
        paymentRows: () => [],
    },
);

const currentDate = new Date();
const today = [
    currentDate.getFullYear(),
    String(currentDate.getMonth() + 1).padStart(2, '0'),
    String(currentDate.getDate()).padStart(2, '0'),
].join('-');

function cellText(value: unknown): string {
    if (value && typeof value === 'object' && 'text' in value) {
        return String((value as TableBadgeCell).text);
    }

    return String(value ?? '');
}

function formatDate(value: string | undefined): string {
    if (!value) return '-';
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return value;

    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(`${value}T00:00:00`));
}

function attendanceLabel(row: DashboardAttendanceRow): string {
    return String(row.status_value || cellText(row.status) || 'UNKNOWN').toUpperCase();
}

function attendanceClass(row: DashboardAttendanceRow): string {
    const status = attendanceLabel(row);

    if (status === 'PRESENT') return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
    if (status === 'EXCUSED' || status === 'LATE') return 'bg-amber-500/10 text-amber-700 dark:text-amber-300';

    return 'bg-rose-500/10 text-rose-700 dark:text-rose-300';
}

function remainingAmount(row: TableRow): number {
    const digits = String(row.remaining ?? '').replace(/[^0-9]/g, '');

    if (digits !== '') return Number(digits);

    const status = cellText(row.status).toLowerCase();
    return ['unpaid', 'partial', 'pending', 'waiting'].some((label) => status.includes(label)) ? 1 : 0;
}

const upcomingTraining = computed(() =>
    [...props.trainingDays]
        .filter((session) => session.date >= today)
        .sort((left, right) => `${left.date} ${left.time}`.localeCompare(`${right.date} ${right.time}`))
        .slice(0, 6),
);

const nextTraining = computed(() => upcomingTraining.value[0] ?? null);
const recentAttendance = computed(() => props.attendanceRows.slice(0, 6));
const attentionPayments = computed(() => props.paymentRows.filter((row) => remainingAmount(row) > 0));
const latestAnnouncements = computed(() => props.announcements.slice(0, 4));
const nextEvents = computed(() => props.upcomingEvents.slice(0, 4));
const latestActivity = computed(() => props.activityPreviewRows.slice(0, 6));

const attendanceSummary = computed(() => {
    const summary = { present: 0, absent: 0, excused: 0 };

    props.attendanceRows.forEach((row) => {
        const status = attendanceLabel(row);
        if (status === 'PRESENT') summary.present += 1;
        else if (status === 'EXCUSED' || status === 'LATE') summary.excused += 1;
        else summary.absent += 1;
    });

    return summary;
});

const beltRows = computed<BeltRow[]>(() => {
    const labels: Record<string, string> = {
        GEUP_1: 'Geup 1',
        GEUP_2: 'Geup 2',
        GEUP_3: 'Geup 3',
        GEUP_4: 'Geup 4',
        GEUP_5: 'Geup 5',
        GEUP_6: 'Geup 6',
        GEUP_7: 'Geup 7',
        GEUP_8: 'Geup 8',
        GEUP_9: 'Geup 9',
        GEUP_10: 'Geup 10',
        DAN: 'Dan',
    };

    return props.medalRows
        .map((row) => {
            const rawLabel = String(row.type ?? row.label ?? 'Belum diisi');
            return { label: labels[rawLabel] ?? rawLabel, count: Number(row.count ?? 0), color: '' };
        })
        .filter((row) => row.count > 0)
        .sort((left, right) => right.count - left.count);
});

const largestBeltCount = computed(() => Math.max(...beltRows.value.map((row) => row.count), 1));

const profileRows = computed(() => {
    const labels: Record<string, string> = {
        geup: 'Tingkat sabuk',
        height: 'Tinggi badan',
        weight: 'Berat badan',
        certifications: 'Sertifikasi',
    };

    return Object.entries(props.profileSummary).map(([key, value]) => ({
        key,
        label: labels[key] ?? key,
        value,
    }));
});
</script>

<template>
    <div class="space-y-6">
        <template v-if="props.role === 'admin'">
            <div class="grid gap-6 xl:grid-cols-[1.45fr_0.85fr]">
                <section class="rounded-xl border bg-card p-5 shadow-sm md:p-6">
                    <div class="mb-5 flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Agenda operasional
                            </p>
                            <h2 class="mt-1 text-lg font-bold">Sesi latihan terdekat</h2>
                        </div>
                        <Button as-child variant="ghost" size="sm" class="gap-2">
                            <Link :href="sessionsIndex.url()">Semua sesi <ArrowRight class="size-4" /></Link>
                        </Button>
                    </div>

                    <div v-if="upcomingTraining.length" class="divide-y">
                        <div
                            v-for="session in upcomingTraining"
                            :key="session.id"
                            class="flex gap-4 py-4 first:pt-0 last:pb-0"
                        >
                            <div
                                class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                            >
                                <CalendarClock class="size-5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <p class="font-semibold">{{ session.title }}</p>
                                        <p class="text-sm text-muted-foreground">{{ session.group }}</p>
                                    </div>
                                    <p class="text-sm font-medium">{{ formatDate(session.date) }}</p>
                                </div>
                                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                    <span class="inline-flex items-center gap-1.5"
                                        ><Clock3 class="size-3.5" />{{ session.time }}</span
                                    >
                                    <span class="inline-flex items-center gap-1.5"
                                        ><MapPin class="size-3.5" />{{ session.branch }}</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                    <p v-else class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                        Belum ada sesi latihan mendatang.
                    </p>
                </section>

                <section class="rounded-xl border bg-card p-5 shadow-sm md:p-6">
                    <div class="mb-5">
                        <p class="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                            Perlu ditindaklanjuti
                        </p>
                        <h2 class="mt-1 text-lg font-bold">Prioritas hari ini</h2>
                    </div>
                    <div class="space-y-3">
                        <Link
                            :href="paymentsIndex.url()"
                            class="flex items-center gap-3 rounded-xl border p-4 transition hover:bg-muted/40"
                        >
                            <div
                                class="flex size-10 items-center justify-center rounded-lg bg-amber-500/10 text-amber-700 dark:text-amber-300"
                            >
                                <WalletCards class="size-5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold">{{ attentionPayments.length }} pembayaran terbuka</p>
                                <p class="text-sm text-muted-foreground">
                                    Belum lunas atau masih menunggu penyelesaian.
                                </p>
                            </div>
                        </Link>
                        <Link
                            :href="attendanceIndex.url()"
                            class="flex items-center gap-3 rounded-xl border p-4 transition hover:bg-muted/40"
                        >
                            <div
                                class="flex size-10 items-center justify-center rounded-lg bg-rose-500/10 text-rose-700 dark:text-rose-300"
                            >
                                <CircleAlert class="size-5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold">{{ attendanceSummary.absent }} catatan tidak hadir</p>
                                <p class="text-sm text-muted-foreground">
                                    Berdasarkan data absensi terbaru yang tampil.
                                </p>
                            </div>
                        </Link>
                        <Link
                            :href="announcementsIndex.url()"
                            class="flex items-center gap-3 rounded-xl border p-4 transition hover:bg-muted/40"
                        >
                            <div
                                class="flex size-10 items-center justify-center rounded-lg bg-sky-500/10 text-sky-700 dark:text-sky-300"
                            >
                                <Megaphone class="size-5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold">{{ props.announcements.length }} pengumuman aktif</p>
                                <p class="text-sm text-muted-foreground">
                                    Informasi yang sedang terlihat oleh pengguna.
                                </p>
                            </div>
                        </Link>
                    </div>
                </section>
            </div>

            <div class="grid col-1">
                <section class="rounded-xl border bg-card p-5 shadow-sm md:p-6">
                    <div class="mb-5 flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold tracking-wider text-muted-foreground uppercase">Agenda klub</p>
                            <h2 class="mt-1 text-lg font-bold">Kejuaraan & UKT</h2>
                        </div>
                        <Button as-child variant="ghost" size="sm"
                            ><Link :href="championshipsIndex.url()">Semua</Link></Button
                        >
                    </div>
                    <div v-if="nextEvents.length" class="space-y-4">
                        <div v-for="row in nextEvents" :key="String(row.id)" class="flex gap-3">
                            <div
                                class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-amber-500/10 text-amber-700 dark:text-amber-300"
                            >
                                <Trophy class="size-4" />
                            </div>
                            <div class="min-w-0">
                                <p class="font-semibold">{{ row.event }}</p>
                                <p class="mt-1 text-sm text-muted-foreground">{{ row.date }} · {{ row.location }}</p>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">Belum ada event mendatang.</p>
                </section>
            </div>
            <div class="grid gap-6 xl:grid-cols-2">
                <section class="rounded-xl border bg-card p-5 shadow-sm md:p-6">
                    <div class="mb-5 flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Audit singkat
                            </p>
                            <h2 class="mt-1 text-lg font-bold">Aktivitas terbaru</h2>
                        </div>
                        <Button as-child variant="ghost" size="sm"
                            ><Link :href="activityLogsIndex.url()">Lihat log</Link></Button
                        >
                    </div>
                    <div v-if="latestActivity.length" class="space-y-4">
                        <div v-for="row in latestActivity" :key="String(row.id)" class="flex gap-3">
                            <div class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-muted">
                                <Activity class="size-4" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-medium">{{ row.description || row.action }}</p>
                                <p class="mt-0.5 text-xs text-muted-foreground">{{ row.actor }} · {{ row.time }}</p>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">Belum ada aktivitas terbaru.</p>
                </section>

                <section class="rounded-xl border bg-card p-5 shadow-sm md:p-6">
                    <div class="mb-5">
                        <p class="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                            Komposisi atlet
                        </p>
                        <h2 class="mt-1 text-lg font-bold">Distribusi tingkat sabuk</h2>
                    </div>
                    <div v-if="beltRows.length" class="space-y-4">
                        <div v-for="row in beltRows" :key="row.label">
                            <div class="mb-1.5 flex items-center justify-between text-sm">
                                <span>{{ row.label }}</span
                                ><strong>{{ row.count }}</strong>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-muted">
                                <div
                                    class="h-full rounded-full bg-primary"
                                    :style="{ width: `${(row.count / largestBeltCount) * 100}%` }"
                                ></div>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">Data sabuk atlet belum tersedia.</p>
                </section>
            </div>
        </template>

        <template v-else-if="props.role === 'coach'">
            <div class="grid gap-6 xl:grid-cols-[1.4fr_0.8fr]">
                <section class="rounded-xl border bg-card p-5 shadow-sm md:p-6">
                    <p class="text-xs font-semibold tracking-wider text-muted-foreground uppercase">Fokus berikutnya</p>
                    <div v-if="nextTraining" class="mt-4">
                        <div class="flex flex-col gap-5 md:flex-row md:items-start md:justify-between">
                            <div>
                                <p class="text-2xl font-bold">{{ nextTraining.title }}</p>
                                <p class="mt-1 text-muted-foreground">{{ nextTraining.group }}</p>
                                <div class="mt-4 flex flex-wrap gap-3 text-sm">
                                    <span class="rounded-lg bg-muted px-3 py-2">{{
                                        formatDate(nextTraining.date)
                                    }}</span>
                                    <span class="rounded-lg bg-muted px-3 py-2">{{ nextTraining.time }}</span>
                                    <span class="rounded-lg bg-muted px-3 py-2">{{ nextTraining.branch }}</span>
                                </div>
                            </div>
                            <Button as-child><Link :href="sessionsIndex.url()">Buka sesi</Link></Button>
                        </div>
                    </div>
                    <p
                        v-else
                        class="mt-4 rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
                    >
                        Tidak ada sesi yang ditugaskan dalam waktu dekat.
                    </p>
                </section>

                <section class="rounded-xl border bg-card p-5 shadow-sm md:p-6">
                    <p class="text-xs font-semibold tracking-wider text-muted-foreground uppercase">Absensi sesi</p>
                    <h2 class="mt-1 text-lg font-bold">Ringkasan terbaru</h2>
                    <div class="mt-5 grid grid-cols-3 gap-3 text-center">
                        <div class="rounded-xl bg-emerald-500/10 p-4">
                            <p class="text-2xl font-bold">{{ attendanceSummary.present }}</p>
                            <p class="text-xs text-muted-foreground">Hadir</p>
                        </div>
                        <div class="rounded-xl bg-amber-500/10 p-4">
                            <p class="text-2xl font-bold">{{ attendanceSummary.excused }}</p>
                            <p class="text-xs text-muted-foreground">Izin/Terlambat</p>
                        </div>
                        <div class="rounded-xl bg-rose-500/10 p-4">
                            <p class="text-2xl font-bold">{{ attendanceSummary.absent }}</p>
                            <p class="text-xs text-muted-foreground">Tidak hadir</p>
                        </div>
                    </div>
                    <Button as-child variant="outline" class="mt-5 w-full"
                        ><Link :href="attendanceIndex.url()">Kelola absensi</Link></Button
                    >
                </section>
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                <section class="rounded-xl border bg-card p-5 shadow-sm md:p-6">
                    <div class="mb-5 flex items-center justify-between gap-3">
                        <h2 class="text-lg font-bold">Jadwal saya</h2>
                        <Button as-child variant="ghost" size="sm"
                            ><Link :href="trainingScheduleIndex.url()">Lihat jadwal</Link></Button
                        >
                    </div>
                    <div v-if="upcomingTraining.length" class="space-y-3">
                        <div
                            v-for="session in upcomingTraining.slice(0, 4)"
                            :key="session.id"
                            class="rounded-xl border p-4"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold">{{ session.title }}</p>
                                    <p class="text-sm text-muted-foreground">
                                        {{ session.group }} · {{ session.branch }}
                                    </p>
                                </div>
                                <p class="text-sm font-medium">{{ formatDate(session.date) }}</p>
                            </div>
                            <p class="mt-2 text-xs text-muted-foreground">{{ session.time }}</p>
                        </div>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">Belum ada jadwal yang ditugaskan.</p>
                </section>

                <section class="rounded-xl border bg-card p-5 shadow-sm md:p-6">
                    <div class="mb-5 flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Honor pelatih
                            </p>
                            <h2 class="mt-1 text-lg font-bold">Catatan pembayaran</h2>
                        </div>
                        <Button as-child variant="ghost" size="sm"
                            ><Link :href="paymentsIndex.url()">Selengkapnya</Link></Button
                        >
                    </div>
                    <div v-if="props.paymentRows.length" class="space-y-3">
                        <div
                            v-for="row in props.paymentRows.slice(0, 4)"
                            :key="String(row.id)"
                            class="flex items-center justify-between gap-4 rounded-xl border p-4"
                        >
                            <div>
                                <p class="font-semibold">{{ row.total || row.athlete || 'Honor' }}</p>
                                <p class="text-sm text-muted-foreground">Sisa: {{ row.remaining || '-' }}</p>
                            </div>
                            <span class="rounded-full bg-muted px-2.5 py-1 text-xs font-medium">{{
                                cellText(row.status)
                            }}</span>
                        </div>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">Belum ada catatan honor untuk akun ini.</p>
                </section>
            </div>
        </template>

        <template v-else-if="props.role === 'parent'">
            <div class="grid gap-4 md:grid-cols-3">
                <section class="rounded-xl border bg-card p-5 shadow-sm md:col-span-2">
                    <p class="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                        Latihan berikutnya
                    </p>
                    <div
                        v-if="nextTraining"
                        class="mt-3 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <h2 class="text-xl font-bold">{{ nextTraining.title }}</h2>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ formatDate(nextTraining.date) }} · {{ nextTraining.time }} ·
                                {{ nextTraining.branch }}
                            </p>
                        </div>
                        <Button as-child variant="outline"
                            ><Link :href="trainingScheduleIndex.url()">Buka jadwal</Link></Button
                        >
                    </div>
                    <p v-else class="mt-3 text-sm text-muted-foreground">
                        Belum ada latihan mendatang untuk anak yang dipilih.
                    </p>
                </section>
                <section class="rounded-xl border bg-card p-5 shadow-sm">
                    <ReceiptText class="size-5 text-primary" />
                    <p class="mt-3 text-2xl font-bold">{{ attentionPayments.length }}</p>
                    <p class="text-sm font-medium">Tagihan perlu perhatian</p>
                    <Link :href="paymentsIndex.url()" class="mt-2 inline-flex text-sm text-primary hover:underline"
                        >Lihat pembayaran</Link
                    >
                </section>
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                <section class="rounded-xl border bg-card p-5 shadow-sm md:p-6">
                    <div class="mb-5 flex items-center justify-between gap-3">
                        <h2 class="text-lg font-bold">Jadwal anak</h2>
                        <Button as-child variant="ghost" size="sm"
                            ><Link :href="trainingScheduleIndex.url()">Semua jadwal</Link></Button
                        >
                    </div>
                    <div v-if="upcomingTraining.length" class="space-y-3">
                        <div
                            v-for="session in upcomingTraining.slice(0, 5)"
                            :key="session.id"
                            class="flex gap-3 rounded-xl border p-4"
                        >
                            <CalendarDays class="mt-0.5 size-5 shrink-0 text-primary" />
                            <div class="min-w-0">
                                <p class="font-semibold">{{ session.title }}</p>
                                <p class="text-sm text-muted-foreground">
                                    {{ formatDate(session.date) }} · {{ session.time }}
                                </p>
                                <p class="text-xs text-muted-foreground">{{ session.branch }} · {{ session.group }}</p>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">Belum ada jadwal yang tersedia.</p>
                </section>

                <section class="rounded-xl border bg-card p-5 shadow-sm md:p-6">
                    <div class="mb-5 flex items-center justify-between gap-3">
                        <h2 class="text-lg font-bold">Kehadiran terbaru</h2>
                        <Button as-child variant="ghost" size="sm"
                            ><Link :href="attendanceIndex.url()">Riwayat lengkap</Link></Button
                        >
                    </div>
                    <div v-if="recentAttendance.length" class="space-y-3">
                        <div
                            v-for="row in recentAttendance"
                            :key="String(row.id)"
                            class="flex items-center justify-between gap-3 rounded-xl border p-4"
                        >
                            <div>
                                <p class="font-medium">{{ formatDate(row.date || row.session_date) }}</p>
                                <p class="text-xs text-muted-foreground">Catatan kehadiran latihan</p>
                            </div>
                            <span
                                class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                :class="attendanceClass(row)"
                                >{{ attendanceLabel(row) }}</span
                            >
                        </div>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">Belum ada riwayat kehadiran.</p>
                </section>
            </div>
        </template>

        <template v-else>
            <div class="grid gap-6 xl:grid-cols-[1.35fr_0.85fr]">
                <section class="relative overflow-hidden rounded-xl border bg-card p-5 shadow-sm md:p-6">
                    <div class="absolute -top-16 -right-16 size-48 rounded-full bg-primary/10"></div>
                    <div class="relative">
                        <p class="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                            Latihan berikutnya
                        </p>
                        <div v-if="nextTraining" class="mt-4">
                            <h2 class="text-2xl font-bold">{{ nextTraining.title }}</h2>
                            <p class="mt-1 text-muted-foreground">{{ nextTraining.group }}</p>
                            <div class="mt-5 grid gap-3 sm:grid-cols-3">
                                <div class="rounded-xl bg-muted/70 p-3">
                                    <p class="text-xs text-muted-foreground">Tanggal</p>
                                    <p class="mt-1 font-semibold">{{ formatDate(nextTraining.date) }}</p>
                                </div>
                                <div class="rounded-xl bg-muted/70 p-3">
                                    <p class="text-xs text-muted-foreground">Waktu</p>
                                    <p class="mt-1 font-semibold">{{ nextTraining.time }}</p>
                                </div>
                                <div class="rounded-xl bg-muted/70 p-3">
                                    <p class="text-xs text-muted-foreground">Lokasi</p>
                                    <p class="mt-1 font-semibold">{{ nextTraining.branch }}</p>
                                </div>
                            </div>
                            <Button as-child class="mt-5"
                                ><Link :href="attendanceIndex.url()">Buka absensi</Link></Button
                            >
                        </div>
                        <p v-else class="mt-4 text-sm text-muted-foreground">Belum ada jadwal latihan mendatang.</p>
                    </div>
                </section>

                <section class="rounded-xl border bg-card p-5 shadow-sm md:p-6">
                    <div class="flex items-center gap-3">
                        <div class="flex size-10 items-center justify-center rounded-full bg-primary/10 text-primary">
                            <UserRound class="size-5" />
                        </div>
                        <div>
                            <p class="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Profil latihan
                            </p>
                            <h2 class="text-lg font-bold">Data saya</h2>
                        </div>
                    </div>
                    <div v-if="profileRows.length" class="mt-5 divide-y">
                        <div
                            v-for="item in profileRows"
                            :key="item.key"
                            class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0"
                        >
                            <span class="text-sm text-muted-foreground">{{ item.label }}</span
                            ><strong class="text-sm">{{ item.value }}</strong>
                        </div>
                    </div>
                    <Button as-child variant="outline" class="mt-5 w-full"
                        ><Link :href="achievementsIndex.url()"
                            ><Award class="mr-2 size-4" />Prestasi & sertifikat</Link
                        ></Button
                    >
                </section>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <section class="rounded-xl border bg-card p-5 shadow-sm">
                    <CheckCircle2 class="size-5 text-emerald-600" />
                    <p class="mt-3 text-2xl font-bold">{{ attendanceSummary.present }}</p>
                    <p class="text-sm font-medium">Kehadiran tercatat</p>
                    <Link :href="attendanceIndex.url()" class="mt-2 inline-flex text-sm text-primary hover:underline"
                        >Lihat riwayat</Link
                    >
                </section>
                <section class="rounded-xl border bg-card p-5 shadow-sm">
                    <WalletCards class="size-5 text-amber-600" />
                    <p class="mt-3 text-2xl font-bold">{{ attentionPayments.length }}</p>
                    <p class="text-sm font-medium">Pembayaran perlu perhatian</p>
                    <Link :href="paymentsIndex.url()" class="mt-2 inline-flex text-sm text-primary hover:underline"
                        >Buka pembayaran</Link
                    >
                </section>
                <section class="rounded-xl border bg-card p-5 shadow-sm">
                    <Trophy class="size-5 text-primary" />
                    <p class="mt-3 text-2xl font-bold">{{ nextEvents.length }}</p>
                    <p class="text-sm font-medium">Event mendatang</p>
                    <Link :href="championshipsIndex.url()" class="mt-2 inline-flex text-sm text-primary hover:underline"
                        >Lihat kejuaraan</Link
                    >
                </section>
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                <section class="rounded-xl border bg-card p-5 shadow-sm md:p-6">
                    <div class="mb-5 flex items-center justify-between gap-3">
                        <h2 class="text-lg font-bold">Jadwal latihan saya</h2>
                        <Button as-child variant="ghost" size="sm"
                            ><Link :href="trainingScheduleIndex.url()">Semua jadwal</Link></Button
                        >
                    </div>
                    <div v-if="upcomingTraining.length" class="space-y-3">
                        <div
                            v-for="session in upcomingTraining.slice(0, 5)"
                            :key="session.id"
                            class="flex gap-3 rounded-xl border p-4"
                        >
                            <CalendarDays class="mt-0.5 size-5 shrink-0 text-primary" />
                            <div>
                                <p class="font-semibold">{{ session.title }}</p>
                                <p class="text-sm text-muted-foreground">
                                    {{ formatDate(session.date) }} · {{ session.time }}
                                </p>
                                <p class="text-xs text-muted-foreground">{{ session.branch }} · {{ session.group }}</p>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">Belum ada jadwal latihan.</p>
                </section>
                <section class="rounded-xl border bg-card p-5 shadow-sm md:p-6">
                    <div class="mb-5 flex items-center justify-between gap-3">
                        <h2 class="text-lg font-bold">Absensi terakhir</h2>
                        <Button as-child variant="ghost" size="sm"
                            ><Link :href="attendanceIndex.url()">Riwayat lengkap</Link></Button
                        >
                    </div>
                    <div v-if="recentAttendance.length" class="space-y-3">
                        <div
                            v-for="row in recentAttendance"
                            :key="String(row.id)"
                            class="flex items-center justify-between gap-3 rounded-xl border p-4"
                        >
                            <p class="font-medium">{{ formatDate(row.date || row.session_date) }}</p>
                            <span
                                class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                :class="attendanceClass(row)"
                                >{{ attendanceLabel(row) }}</span
                            >
                        </div>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">Belum ada riwayat absensi.</p>
                </section>
            </div>
        </template>
    </div>
</template>
