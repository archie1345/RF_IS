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
import { appRoutes } from '@/data/routes';
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
    { title: 'Dashboard', href: appRoutes.adminDashboard, icon: LayoutGrid },
    { title: 'Jadwal Latihan', href: appRoutes.trainingSchedule, icon: CalendarDays },
    { title: 'Lokasi Latihan', href: appRoutes.adminLocations, icon: MapPin },
    { title: 'Kelas Latihan', href: appRoutes.adminClasses, icon: Users },
    { title: 'Manajemen Grup', href: appRoutes.adminGroups, icon: Users },
    { title: 'Presensi Atlet', href: appRoutes.adminAttendance, icon: CalendarCheck2 },
    { title: 'Presensi Coach', href: appRoutes.adminInstructorAttendance, icon: CalendarCheck2 },
    { title: 'Keuangan', href: appRoutes.adminPayments, icon: CreditCard },
    { title: 'Manajemen User', href: appRoutes.users, icon: Users },
    { title: 'Event Internal / UKT', href: appRoutes.adminEvents, icon: Trophy },
    { title: 'Riwayat Event & UKT', href: appRoutes.adminEventHistory, icon: FileClock },
    { title: 'Manajemen Sesi', href: appRoutes.sessions, icon: CalendarCheck2 },
    { title: 'Pengumuman', href: appRoutes.announcements, icon: FileClock },
    { title: 'Log Aktivitas', href: appRoutes.activityLogs, icon: FileClock },
];

const navByRole: Record<AppRole, NavItem[]> = {
    admin: adminNavItems,
    coach: [
        { title: 'Dashboard', href: appRoutes.dashboard, icon: LayoutGrid },
        { title: 'Jadwal Latihan', href: appRoutes.trainingSchedule, icon: CalendarDays },
        { title: 'Payments', href: appRoutes.payments, icon: CreditCard },
        { title: 'Attendance', href: appRoutes.attendance, icon: CalendarCheck2 },
        { title: 'Championships', href: appRoutes.championships, icon: Trophy },
        { title: 'Achievements', href: appRoutes.achievements, icon: Trophy },
        { title: 'Announcements', href: appRoutes.announcements, icon: FileClock },
    ],
    parent: [
        { title: 'Dashboard', href: appRoutes.dashboard, icon: LayoutGrid },
        { title: 'Jadwal Latihan', href: appRoutes.trainingSchedule, icon: CalendarDays },
        { title: 'Child Profiles', href: appRoutes.parentChildSwitcher, icon: Users },
        { title: 'Payments', href: appRoutes.payments, icon: CreditCard },
        { title: 'Attendance', href: appRoutes.attendance, icon: CalendarCheck2 },
        { title: 'Championships', href: appRoutes.championships, icon: Trophy },
        { title: 'Achievements', href: appRoutes.achievements, icon: Trophy },
        { title: 'Announcements', href: appRoutes.announcements, icon: FileClock },
    ],
    athlete: [
        { title: 'Dashboard', href: appRoutes.dashboard, icon: LayoutGrid },
        { title: 'Jadwal Latihan', href: appRoutes.trainingSchedule, icon: CalendarDays },
        { title: 'Payments', href: appRoutes.payments, icon: CreditCard },
        { title: 'Attendance', href: appRoutes.attendance, icon: CalendarCheck2 },
        { title: 'Championships', href: appRoutes.championships, icon: Trophy },
        { title: 'Achievements', href: appRoutes.achievements, icon: Trophy },
        { title: 'Announcements', href: appRoutes.announcements, icon: FileClock },
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

const footerNavItems: NavItem[] = [];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="appRoutes.dashboard">
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
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
