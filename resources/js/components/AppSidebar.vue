<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    CalendarCheck2,
    CalendarDays,
    CreditCard,
    FileClock,
    LayoutGrid,
    MapPin,
    Trophy,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as achievementsIndex } from '@/routes/achievements';
import {
    dashboard as adminDashboard,
    attendance as adminAttendance,
    instructorAttendance as adminInstructorAttendance,
    payments as adminPayments,
    events as adminEvents,
    locations as adminLocations,
    classes as adminClasses,
    index as adminIndex,
} from '@/routes/admin';
import { index as activityLogsIndex } from '@/routes/admin/activity-logs';
import { history as adminEventHistory } from '@/routes/admin/events';
import { index as announcementsIndex } from '@/routes/announcements';
import { index as attendanceIndex } from '@/routes/attendance';
import { index as championshipsIndex } from '@/routes/championships';
import { index as parentChildrenIndex } from '@/routes/parent/children';
import { index as paymentsIndex } from '@/routes/payments';
import { index as sessionsIndex } from '@/routes/sessions';
import { index as trainingScheduleIndex } from '@/routes/training-schedule';
import { index as usersIndex } from '@/routes/users';
import { type NavItem } from '@/types';
import type { Auth } from '@/types/auth';
import type { AppRole } from '@/types/resource-table';
import AppLogo from './AppLogo.vue';

const page = usePage<{ auth: Auth }>();

const roles = computed<AppRole[]>(() => {
    const assignedRoles = page.props.auth?.user?.roles ?? [];
    const validRoles = assignedRoles.filter((item): item is AppRole =>
        ['admin', 'coach', 'parent', 'athlete'].includes(item),
    );

    if (validRoles.length > 0) {
        return validRoles;
    }

    const userRole = page.props.auth?.user?.role;
    return userRole === 'admin' || userRole === 'coach' || userRole === 'parent' || userRole === 'athlete'
        ? [userRole]
        : ['athlete'];
});

const adminNavItems: NavItem[] = [
    { title: 'Dashboard', href: adminDashboard.url(), icon: LayoutGrid },
    { title: 'Jadwal Latihan', href: trainingScheduleIndex.url(), icon: CalendarDays },
    { title: 'Lokasi Latihan', href: adminLocations.url(), icon: MapPin },
    { title: 'Kelas Latihan', href: adminClasses.url(), icon: Users },
    { title: 'Manajemen Sesi', href: sessionsIndex.url(), icon: CalendarCheck2 },
    { title: 'Presensi Atlet', href: adminAttendance.url(), icon: CalendarCheck2 },
    { title: 'Presensi Coach', href: adminInstructorAttendance.url(), icon: CalendarCheck2 },
    { title: 'Keuangan', href: adminPayments.url(), icon: CreditCard },
    { title: 'Manajemen User', href: adminIndex.url(), icon: Users },
    { title: 'Manajemen Athlete', href: usersIndex.url(),icon: Users},
    { title: 'Event Internal / UKT', href: adminEvents.url(), icon: Trophy },
    { title: 'Riwayat Event & UKT', href: adminEventHistory.url(), icon: FileClock },
    { title: 'Pengumuman', href: announcementsIndex.url(), icon: FileClock },
    { title: 'Log Aktivitas', href: activityLogsIndex.url(), icon: FileClock },
];

const navByRole: Record<AppRole, NavItem[]> = {
    admin: adminNavItems,
    coach: [
        { title: 'Dashboard', href: dashboard.url(), icon: LayoutGrid },
        { title: 'Jadwal Latihan', href: trainingScheduleIndex.url(), icon: CalendarDays },
        { title: 'Payments', href: paymentsIndex.url(), icon: CreditCard },
        { title: 'Attendance', href: attendanceIndex.url(), icon: CalendarCheck2 },
        { title: 'Championships', href: championshipsIndex.url(), icon: Trophy },
        { title: 'Achievements', href: achievementsIndex.url(), icon: Trophy },
        { title: 'Announcements', href: announcementsIndex.url(), icon: FileClock },
    ],
    parent: [
        { title: 'Dashboard', href: dashboard.url(), icon: LayoutGrid },
        { title: 'Jadwal Latihan', href: trainingScheduleIndex.url(), icon: CalendarDays },
        { title: 'Child Profiles', href: parentChildrenIndex.url(), icon: Users },
        { title: 'Payments', href: paymentsIndex.url(), icon: CreditCard },
        { title: 'Attendance', href: attendanceIndex.url(), icon: CalendarCheck2 },
        { title: 'Championships', href: championshipsIndex.url(), icon: Trophy },
        { title: 'Achievements', href: achievementsIndex.url(), icon: Trophy },
        { title: 'Announcements', href: announcementsIndex.url(), icon: FileClock },
    ],
    athlete: [
        { title: 'Dashboard', href: dashboard.url(), icon: LayoutGrid },
        { title: 'Jadwal Latihan', href: trainingScheduleIndex.url(), icon: CalendarDays },
        { title: 'Payments', href: paymentsIndex.url(), icon: CreditCard },
        { title: 'Attendance', href: attendanceIndex.url(), icon: CalendarCheck2 },
        { title: 'Championships', href: championshipsIndex.url(), icon: Trophy },
        { title: 'Achievements', href: achievementsIndex.url(), icon: Trophy },
        { title: 'Announcements', href: announcementsIndex.url(), icon: FileClock },
    ],
};

const mainNavItems = computed(() => {
    const seen = new Set<string>();

    return roles.value
        .flatMap((entry) => navByRole[entry])
        .filter((item) => {
            const href = String(item.href);
            if (seen.has(href)) {
                return false;
            }
            seen.add(href);
            return true;
        });
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard.url()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
</template>
