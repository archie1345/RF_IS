<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarCheck2, CalendarDays, MapPin, Users } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type Session = {
    id: number;
    weekly_training_schedule_id?: number | null;
    title: string;
    date?: string | null;
    day_label: string;
    time: string;
    branch: string;
    group: string;
    coach: string;
    status: string;
};

const props = withDefaults(
    defineProps<{
        title?: string;
        subtitle?: string;
        branches?: unknown[];
        groups?: unknown[];
        weeklySchedules?: unknown[];
        sessions?: Session[];
    }>(),
    {
        title: 'Manajemen Latihan',
        subtitle: 'Ringkasan training flow. Lokasi, Kelas, dan Jadwal Latihan sudah dipisah ke halaman khusus.',
        branches: () => [],
        groups: () => [],
        weeklySchedules: () => [],
        sessions: () => [],
    },
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: props.title, href: '/admin/training-management' },
];

const managementLinks = [
    {
        title: 'Lokasi Latihan',
        description: 'Kelola dojang fisik, alamat, radius absensi, dan status lokasi.',
        href: '/admin/locations',
        icon: MapPin,
    },
    {
        title: 'Kelas Latihan',
        description: 'Kelola kelas, coach, jadwal dasar kelas, tipe kelas, dan minimal sabuk.',
        href: '/admin/classes',
        icon: Users,
    },
    {
        title: 'Jadwal Latihan',
        description: 'Lihat board jadwal mingguan dan tambah/edit template jadwal untuk admin/coach.',
        href: '/training-schedule',
        icon: CalendarDays,
    },
    {
        title: 'Sesi Latihan',
        description: 'Kelola sesi bertanggal, attendance sheet, QR, dan coach attendance.',
        href: '/sessions',
        icon: CalendarCheck2,
    },
];
</script>

<template>
    <Head :title="props.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <section class="rounded-2xl border bg-card p-5 shadow-sm">
                <p class="text-xs font-black tracking-wide text-brand-coral uppercase">Training Hub</p>
                <h1 class="text-3xl font-black">{{ props.title }}</h1>
                <p class="mt-1 text-sm text-muted-foreground">{{ props.subtitle }}</p>
            </section>

            <section class="grid gap-4 md:grid-cols-4">
                <div class="rounded-2xl border bg-card p-4 shadow-sm">
                    <p class="text-xs font-bold text-muted-foreground uppercase">Lokasi</p>
                    <p class="mt-2 text-3xl font-black">{{ props.branches.length }}</p>
                </div>
                <div class="rounded-2xl border bg-card p-4 shadow-sm">
                    <p class="text-xs font-bold text-muted-foreground uppercase">Kelas</p>
                    <p class="mt-2 text-3xl font-black">{{ props.groups.length }}</p>
                </div>
                <div class="rounded-2xl border bg-card p-4 shadow-sm">
                    <p class="text-xs font-bold text-muted-foreground uppercase">Jadwal Mingguan</p>
                    <p class="mt-2 text-3xl font-black">{{ props.weeklySchedules.length }}</p>
                </div>
                <div class="rounded-2xl border bg-card p-4 shadow-sm">
                    <p class="text-xs font-bold text-muted-foreground uppercase">Sesi di Range</p>
                    <p class="mt-2 text-3xl font-black">{{ props.sessions.length }}</p>
                </div>
            </section>

            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <Link
                    v-for="item in managementLinks"
                    :key="item.href"
                    :href="item.href"
                    class="rounded-2xl border bg-card p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                >
                    <component :is="item.icon" class="size-8 text-brand-coral" />
                    <h2 class="mt-4 text-xl font-black">{{ item.title }}</h2>
                    <p class="mt-2 text-sm text-muted-foreground">{{ item.description }}</p>
                </Link>
            </section>

            <section class="rounded-2xl border bg-card p-5 shadow-sm">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-black">Sesi Latihan Terdekat</h2>
                        <p class="text-sm text-muted-foreground">Sesi tetap dikelola di halaman Manajemen Sesi.</p>
                    </div>
                    <Link href="/sessions" class="rounded-lg border px-4 py-2 text-sm font-bold">Open Sessions</Link>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[860px] text-sm">
                        <thead>
                            <tr class="border-b text-left">
                                <th class="px-3 py-3 font-black">Sesi</th>
                                <th class="px-3 py-3 font-black">Tanggal</th>
                                <th class="px-3 py-3 font-black">Lokasi</th>
                                <th class="px-3 py-3 font-black">Kelas</th>
                                <th class="px-3 py-3 font-black">Coach</th>
                                <th class="px-3 py-3 font-black">Status</th>
                                <th class="px-3 py-3 font-black">Attendance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="props.sessions.length === 0">
                                <td colspan="7" class="h-32 px-3 text-center text-muted-foreground">
                                    Tidak ada sesi di range ini.
                                </td>
                            </tr>
                            <tr v-for="session in props.sessions" :key="session.id" class="border-b hover:bg-muted/40">
                                <td class="px-3 py-3 font-bold">
                                    {{ session.title }}
                                    <p class="text-xs font-normal text-muted-foreground">
                                        Schedule #{{ session.weekly_training_schedule_id ?? '-' }}
                                    </p>
                                </td>
                                <td class="px-3 py-3">
                                    {{ session.day_label }}<br />{{ session.date }} · {{ session.time }}
                                </td>
                                <td class="px-3 py-3">{{ session.branch }}</td>
                                <td class="px-3 py-3">{{ session.group }}</td>
                                <td class="px-3 py-3">{{ session.coach }}</td>
                                <td class="px-3 py-3">{{ session.status }}</td>
                                <td class="px-3 py-3">
                                    <Link class="rounded border px-2 py-1" :href="`/sessions/${session.id}/attendance`"
                                        >Open</Link
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
