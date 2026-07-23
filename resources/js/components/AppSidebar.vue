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
    History,
    LayoutDashboard,
    MapPinned,
    Megaphone,
    Network,
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
import type { NavItem, NavSection } from '@/types';
import type { Auth } from '@/types/auth';
import type { AppRole } from '@/types/resource-table';
import AppLogo from './AppLogo.vue';

const page = usePage<{ auth: Auth }>();

const activeRole = computed<AppRole>(() =>
    page.props.auth.user?.activeRole ?? page.props.auth.user?.role ?? 'athlete',
);

const assignedRoles = computed<AppRole[]>(() => {
    const roles = page.props.auth.user?.roles ?? [];

    return roles.length > 0 ? roles : [activeRole.value];
});

const allRoles: AppRole[] = ['admin', 'coach', 'parent', 'athlete'];

const navigation: NavSection[] = [
    {
        label: 'Utama',
        items: [
            {
                title: 'Beranda',
                href: dashboard.url(),
                icon: LayoutDashboard,
                roles: allRoles,
            },
        ],
    },
    {
        label: 'Latihan',
        items: [
            { title: 'Jadwal Latihan', href: trainingScheduleIndex.url(), icon: CalendarRange, roles: allRoles },
            { title: 'Lokasi Latihan', href: adminLocations.url(), icon: MapPinned, roles: ['admin'] },
            { title: 'Kelompok Atlet', href: '/admin/groups', icon: Network, roles: ['admin'] },
            { title: 'Kelas Latihan', href: adminClasses.url(), icon: Dumbbell, roles: ['admin', 'coach'] },
            { title: 'Sesi Latihan', href: sessionsIndex.url(), icon: CalendarClock, roles: ['admin', 'coach'] },
            {
                title: 'Absensi & Check-in',
                href: attendanceIndex.url(),
                icon: ScanLine,
                roles: ['coach', 'parent', 'athlete'],
            },
            { title: 'Laporan Absensi Atlet', href: adminAttendance.url(), icon: ClipboardCheck, roles: ['admin'] },
            {
                title: 'Kehadiran Pelatih',
                href: adminInstructorAttendance.url(),
                icon: BadgeCheck,
                roles: ['admin'],
            },
        ],
    },
    {
        label: 'Akun & Keluarga',
        items: [
            { title: 'Profil Anak', href: parentChildrenIndex.url(), icon: UsersRound, roles: ['parent'] },
            { title: 'Akun Pengguna', href: adminIndex.url(), icon: UserCog, roles: ['admin'] },
            { title: 'Data Atlet', href: usersIndex.url(), icon: Contact, roles: ['admin'] },
        ],
    },
    {
        label: 'Keuangan',
        items: [
            {
                title: 'Keuangan & Pembayaran',
                href: paymentsIndex.url(),
                icon: WalletCards,
                roles: allRoles,
            },
        ],
    },
    {
        label: 'Kompetisi',
        items: [
            {
                title: 'Kejuaraan & UKT',
                href: championshipsIndex.url(),
                icon: Trophy,
                roles: allRoles,
            },
            {
                title: 'Prestasi & Sertifikat',
                href: achievementsIndex.url(),
                icon: Award,
                roles: ['coach', 'parent', 'athlete'],
            },
            { title: 'Riwayat Kejuaraan', href: adminEventHistory.url(), icon: History, roles: ['admin'] },
        ],
    },
    {
        label: 'Informasi & Sistem',
        items: [
            { title: 'Pengumuman', href: announcementsIndex.url(), icon: Megaphone, roles: allRoles },
            { title: 'Log Aktivitas', href: activityLogsIndex.url(), icon: ScrollText, roles: ['admin'] },
        ],
    },
];

function canSee(item: NavItem): boolean {
    return !item.roles?.length || item.roles.some((role) => assignedRoles.value.includes(role));
}

const mainNavSections = computed<NavSection[]>(() =>
    navigation
        .map((section) => ({
            ...section,
            items: section.items.filter(canSee),
        }))
        .filter((section) => section.items.length > 0),
);

const homeHref = computed(() => (activeRole.value === 'admin' ? adminDashboard.url() : dashboard.url()));
const footerNavItems: NavItem[] = [];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="homeHref">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :sections="mainNavSections" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
</template>
