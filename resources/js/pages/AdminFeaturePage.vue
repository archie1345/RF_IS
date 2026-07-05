<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarDays, Download, Plus, RefreshCcw, Search } from 'lucide-vue-next';
import { computed } from 'vue';
import PageSection from '@/components/shared/PageSection.vue';
import StatCard from '@/components/shared/StatCard.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { Metric } from '@/types/resource-table';

type WeeklySession = {
    title: string;
    time: string;
    location: string;
    date?: string;
};

const props = withDefaults(
    defineProps<{
        mode: string;
        title: string;
        subtitle: string;
        metrics?: Metric[];
        columns?: string[];
        rows?: Record<string, string>[];
        emptyText?: string;
        roleAccess?: string;
        todaySessions?: WeeklySession[];
    }>(),
    {
        metrics: () => [],
        columns: () => [],
        rows: () => [],
        emptyText: 'Tidak ada data',
        roleAccess: 'Admin only',
        todaySessions: () => [],
    },
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: props.title, href: `/admin/${props.mode}` },
];

const dayCards = [
    { id: 1, name: 'Senin', sub: 'Monday' },
    { id: 2, name: 'Selasa', sub: 'Tuesday' },
    { id: 3, name: 'Rabu', sub: 'Wednesday' },
    { id: 4, name: 'Kamis', sub: 'Thursday' },
    { id: 5, name: 'Jumat', sub: 'Friday' },
    { id: 6, name: 'Sabtu', sub: 'Saturday' },
    { id: 7, name: 'Minggu', sub: 'Sunday' },
];

const sessionsByDay = computed(() => {
    const grouped = new Map<number, WeeklySession[]>();
    props.todaySessions.forEach((session) => {
        if (!session.date) return;
        const day = new Date(`${session.date}T00:00:00`).getDay();
        const mondayFirstDay = day === 0 ? 7 : day;
        const sessions = grouped.get(mondayFirstDay) ?? [];
        sessions.push(session);
        grouped.set(mondayFirstDay, sessions);
    });
    return grouped;
});

function generateMonthlyDues() {
    router.post('/admin/monthly-dues/generate', {}, { preserveScroll: true });
}

function generateWeeklySessions() {
    router.post('/admin/schedules/generate-week', {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="props.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageSection :title="props.title" :description="props.subtitle">
                <template #actions>
                    <div class="flex flex-wrap gap-2">
                        <Button v-if="props.mode === 'monthly-dues'" type="button" size="sm" @click="generateMonthlyDues">
                            <Plus class="mr-2 size-4" /> Generate bulan ini
                        </Button>
                        <Button v-if="props.mode === 'weekly-schedule'" type="button" size="sm" @click="generateWeeklySessions">
                            <Plus class="mr-2 size-4" /> Generate sesi minggu ini
                        </Button>
                        <Button v-if="['finance-income', 'finance-output', 'payments', 'monthly-dues'].includes(props.mode)" as-child variant="outline" size="sm">
                            <Link href="/payments">Open Payment Center</Link>
                        </Button>
                        <Button v-if="['members', 'instructors', 'events', 'locations', 'classes'].includes(props.mode)" size="sm">
                            <Plus class="mr-2 size-4" /> Tambah
                        </Button>
                        <Button v-if="['attendance', 'instructor-attendance', 'payments', 'monthly-dues', 'members', 'instructors', 'daily-schedules', 'finance-income', 'finance-output'].includes(props.mode)" variant="outline" size="sm">
                            <Download class="mr-2 size-4" /> Export Excel
                        </Button>
                        <Button variant="secondary" size="sm" @click="router.reload({ preserveScroll: true })">
                            <RefreshCcw class="mr-2 size-4" /> Refresh
                        </Button>
                    </div>
                </template>

                <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-red-500">{{ props.roleAccess }}</p>
                <div v-if="props.metrics.length" class="mt-4 grid gap-4 md:grid-cols-4">
                    <StatCard v-for="metric in props.metrics" :key="metric.label" v-bind="metric" />
                </div>
            </PageSection>

            <section v-if="props.mode === 'weekly-schedule'" class="rounded-xl border bg-card p-5 shadow-sm">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div class="rounded-full border px-4 py-2 text-sm font-black">Today: {{ new Date().toLocaleDateString() }}</div>
                    <p class="text-sm text-muted-foreground">Generated sessions are real training sessions and will appear in Attendance / Session pages.</p>
                </div>

                <div class="grid gap-3 md:grid-cols-7">
                    <div v-for="day in dayCards" :key="day.id" class="rounded-xl border bg-background p-4" :class="day.id === 7 ? 'border-red-400 bg-red-500 text-white' : ''">
                        <p class="text-xl font-black">{{ day.name }}</p>
                        <p class="text-xs opacity-70">{{ day.sub }}</p>
                    </div>
                </div>
                <div class="mt-4 grid gap-3 md:grid-cols-7">
                    <div v-for="day in dayCards" :key="`body-${day.id}`" class="min-h-56 rounded-xl border bg-background p-4">
                        <template v-if="(sessionsByDay.get(day.id) ?? []).length">
                            <div v-for="session in sessionsByDay.get(day.id)" :key="`${session.date}-${session.title}-${session.time}`" class="mb-3 rounded-xl border-l-4 border-red-500 bg-card p-3 shadow-sm">
                                <p class="font-black">{{ session.title }}</p>
                                <p class="mt-2 text-sm">{{ session.time }}</p>
                                <p class="text-xs text-muted-foreground">{{ session.location }}</p>
                            </div>
                        </template>
                        <div v-else class="flex h-full flex-col items-center justify-center text-xs font-bold uppercase text-muted-foreground">
                            <CalendarDays class="mb-2 size-8 opacity-40" /> Libur
                        </div>
                    </div>
                </div>
            </section>

            <section v-else class="rounded-xl border bg-card p-5 shadow-sm">
                <div class="mb-5 grid gap-3 md:grid-cols-[1fr_auto] md:items-center">
                    <div class="relative">
                        <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <input class="h-10 w-full rounded-lg border bg-background pl-10 pr-3 text-sm" placeholder="Cari data..." />
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <select class="h-10 rounded-lg border bg-background px-3 text-sm"><option>Semua Status</option></select>
                        <Button variant="outline" size="sm" @click="router.reload({ preserveScroll: true })"><RefreshCcw class="mr-2 size-4" />Muat Ulang</Button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-sm">
                        <thead>
                            <tr class="border-b text-left">
                                <th v-for="column in props.columns" :key="column" class="px-3 py-3 font-black">{{ column }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="props.rows.length === 0">
                                <td :colspan="Math.max(props.columns.length, 1)" class="h-40 px-3 text-center text-muted-foreground">{{ props.emptyText }}</td>
                            </tr>
                            <tr v-for="(row, index) in props.rows" :key="index" class="border-b hover:bg-muted/40">
                                <td v-for="column in props.columns" :key="column" class="px-3 py-3">{{ row[column] ?? '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="rounded-xl border bg-muted/30 p-4 text-xs text-muted-foreground">
                This page provides the admin workspace, filters, actions, metrics, and table or schedule layout.
                <Link href="/dashboard" class="font-semibold text-primary">Back to dashboard</Link>
            </div>
        </div>
    </AppLayout>
</template>
