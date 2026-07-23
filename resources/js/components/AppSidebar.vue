<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Award,
    BadgeCheck,
    CalendarClock,
    CalendarRange,
    ClipboardCheck,
    Contact,
    Dumbbell,
    HandCoins,
    History,
    LayoutDashboard,
    MapPinned,
    Megaphone,
    Network,
    ReceiptText,
    ScanLine,
    ScrollText,
    Trophy,
    UserCog,
    UsersRound,
    WalletCards,
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
    attendance as adminAttendance,
    classes as adminClasses,
    dashboard as adminDashboard,
    events as adminEvents,
    index as adminIndex,
    instructorAttendance as adminInstructorAttendance,
    locations as adminLocations,
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

const activeRole = computed<AppRole>(() =>
    page.props.auth.user?.activeRole ?? page.props.auth.user?.role ?? 'athlete',
);

const adminNavItems: NavItem[] = [
    { title: 'Ringkasan Operasional', href: adminDashboard.url(), icon: LayoutDashboard },
    { title: 'Jadwal Mingguan', href: trainingScheduleIndex.url(), icon: CalendarRange },
    { title: 'Lokasi Latihan', href: adminLocations.url(), icon: MapPinned },
    { title: 'Kelompok Atlet', href: '/admin/groups', icon: Network },
    { title: 'Kelas Latihan', href: adminClasses.url(), icon: Dumbbell },
    { title: 'Sesi Latihan', href: sessionsIndex.url(), icon: CalendarClock },
    { title: 'Absensi Atlet', href: adminAttendance.url(), icon: ClipboardCheck },
    { title: 'Kehadiran Pelatih', href: adminInstructorAttendance.url(), icon: BadgeCheck },
    { title: 'Keuangan', href: paymentsIndex.url(), icon: WalletCards },
    { title: 'Akun Pengguna', href: adminIndex.url(), icon: UserCog },
    { title: 'Data Atlet', href: usersIndex.url(), icon: Contact },
    { title: 'Kejuaraan & UKT', href: adminEvents.url(), icon: Trophy },
    { title: 'Riwayat Kejuaraan', href: adminEventHistory.url(), icon: History },
    { title: 'Pengumuman', href: announcementsIndex.url(), icon: Megaphone },
    { title: 'Log Aktivitas', href: activityLogsIndex.url(), icon: ScrollText },
];

const navByRole: Record<AppRole, NavItem[]> = {
    admin: adminNavItems,
    coach: [
        { title: 'Ringkasan Pelatih', href: dashboard.url(), icon: LayoutDashboard },
        { title: 'Jadwal Latihan', href: trainingScheduleIndex.url(), icon: CalendarRange },
        { title: 'Kelas Binaan', href: adminClasses.url(), icon: Dumbbell },
        { title: 'Sesi Saya', href: sessionsIndex.url(), icon: CalendarClock },
        { title: 'Honor Pelatih', href: paymentsIndex.url(), icon: HandCoins },
        { title: 'Absensi Latihan', href: attendanceIndex.url(), icon: ScanLine },
        { title: 'Kejuaraan & UKT', href: championshipsIndex.url(), icon: Trophy },
        { title: 'Prestasi & Sertifikat', href: achievementsIndex.url(), icon: Award },
        { title: 'Pengumuman', href: announcementsIndex.url(), icon: Megaphone },
    ],
    parent: [
        { title: 'Ringkasan Anak', href: dashboard.url(), icon: LayoutDashboard },
        { title: 'Jadwal Anak', href: trainingScheduleIndex.url(), icon: CalendarRange },
        { title: 'Profil Anak', href: parentChildrenIndex.url(), icon: UsersRound },
        { title: 'Tagihan & Pembayaran', href: paymentsIndex.url(), icon: ReceiptText },
        { title: 'Riwayat Absensi', href: attendanceIndex.url(), icon: ClipboardCheck },
        { title: 'Kejuaraan & UKT', href: championshipsIndex.url(), icon: Trophy },
        { title: 'Prestasi Anak', href: achievementsIndex.url(), icon: Award },
        { title: 'Pengumuman', href: announcementsIndex.url(), icon: Megaphone },
    ],
    athlete: [
        { title: 'Ringkasan Saya', href: dashboard.url(), icon: LayoutDashboard },
        { title: 'Jadwal Latihan', href: trainingScheduleIndex.url(), icon: CalendarRange },
        { title: 'Tagihan & Pembayaran', href: paymentsIndex.url(), icon: WalletCards },
        { title: 'Absensi Saya', href: attendanceIndex.url(), icon: ScanLine },
        { title: 'Kejuaraan & UKT', href: championshipsIndex.url(), icon: Trophy },
        { title: 'Prestasi Saya', href: achievementsIndex.url(), icon: Award },
        { title: 'Pengumuman', href: announcementsIndex.url(), icon: Megaphone },
    ],
};

const mainNavItems = computed(() => navByRole[activeRole.value]);
const footerNavItems: NavItem[] = [];
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
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
</template>
