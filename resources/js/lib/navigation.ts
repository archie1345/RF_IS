import {
    Award,
    BadgeCheck,
    CalendarClock,
    CalendarRange,
    ClipboardCheck,
    Contact,
    Dumbbell,
    FileSpreadsheet,
    History,
    LayoutDashboard,
    MapPinned,
    Megaphone,
    MessageCircleMore,
    Network,
    ReceiptText,
    ScanLine,
    ScrollText,
    Trophy,
    UserCog,
    UsersRound,
    WalletCards,
} from '@lucide/vue';
import { dashboard } from '@/routes';
import { index as achievementsIndex } from '@/routes/achievements';
import { index as activityLogsIndex } from '@/routes/admin/activity-logs';
import {
    attendance as adminAttendance,
    classes as adminClasses,
    index as adminIndex,
    instructorAttendance as adminInstructorAttendance,
    locations as adminLocations,
} from '@/routes/admin';
import { history as adminEventHistory } from '@/routes/admin/events';
import { index as announcementsIndex } from '@/routes/announcements';
import { index as attendanceIndex } from '@/routes/attendance';
import { index as championshipsIndex } from '@/routes/championships';
import { index as parentChildrenIndex } from '@/routes/parent/children';
import { index as paymentsIndex } from '@/routes/payments';
import { index as sessionsIndex } from '@/routes/sessions';
import { index as trainingScheduleIndex } from '@/routes/training-schedule';
import { index as usersIndex } from '@/routes/users';
import type { NavSection, NavItem } from '@/types';
import type { AppRole } from '@/types/resource-table';

export const allRoles: AppRole[] = ['admin', 'coach', 'parent', 'athlete'];

export const navigationSections: NavSection[] = [
    {
        label: 'Utama',
        items: [{ title: 'Beranda', href: dashboard.url(), icon: LayoutDashboard, roles: allRoles }],
    },
    {
        label: 'Latihan & Absensi',
        items: [
            { title: 'Jadwal Latihan', href: trainingScheduleIndex.url(), icon: CalendarRange, roles: allRoles },
            { title: 'Sesi Latihan', href: sessionsIndex.url(), icon: CalendarClock, roles: ['admin', 'coach'] },
            { title: 'Absensi & Check-in', href: attendanceIndex.url(), icon: ScanLine, roles: ['coach', 'parent', 'athlete'] },
            { title: 'Laporan Absensi', href: adminAttendance.url(), icon: ClipboardCheck, roles: ['admin'] },
            { title: 'Kehadiran Pelatih', href: adminInstructorAttendance.url(), icon: BadgeCheck, roles: ['admin'] },
            { title: 'Kelas Latihan', href: adminClasses.url(), icon: Dumbbell, roles: ['admin', 'coach'] },
            { title: 'Kelompok Atlet', href: '/admin/groups', icon: Network, roles: ['admin'] },
            { title: 'Lokasi Latihan', href: adminLocations.url(), icon: MapPinned, roles: ['admin'] },
        ],
    },
    {
        label: 'Keuangan & Pengguna',
        items: [
            { title: 'Keuangan & Bayar', href: paymentsIndex.url(), icon: WalletCards, roles: allRoles },
            { title: 'Payroll Pelatih', href: '/admin/payroll', icon: ReceiptText, roles: ['admin'] },
            { title: 'Aturan Tagihan', href: '/admin/billing-settings', icon: ReceiptText, roles: ['admin'] },
            { title: 'Profil Anak', href: parentChildrenIndex.url(), icon: UsersRound, roles: ['parent'] },
            { title: 'Akun Pengguna', href: adminIndex.url(), icon: UserCog, roles: ['admin'] },
            { title: 'Data Atlet', href: usersIndex.url(), icon: Contact, roles: ['admin'] },
        ],
    },
    {
        label: 'Kompetisi & Informasi',
        items: [
            { title: 'Kejuaraan & UKT', href: championshipsIndex.url(), icon: Trophy, roles: allRoles },
            { title: 'Prestasi & Sertifikat', href: achievementsIndex.url(), icon: Award, roles: ['coach', 'parent', 'athlete'] },
            { title: 'Riwayat Kejuaraan', href: adminEventHistory.url(), icon: History, roles: ['admin'] },
            { title: 'Pengumuman', href: announcementsIndex.url(), icon: Megaphone, roles: allRoles },
            { title: 'Template WA', href: '/admin/whatsapp-template', icon: MessageCircleMore, roles: ['admin'] },
            { title: 'Export Data Excel', href: '/admin/data-export', icon: FileSpreadsheet, roles: ['admin'] },
            { title: 'Log Aktivitas', href: activityLogsIndex.url(), icon: ScrollText, roles: ['admin'] },
        ],
    },
];

export function getVisibleSections(sections: NavSection[], assignedRoles: AppRole[]): NavSection[] {
    return sections
        .map((section) => {
            const items = section.items.filter((item) => {
                return !item.roles?.length || item.roles.some((role) => assignedRoles.includes(role));
            });
            return { ...section, items };
        })
        .filter((section) => section.items.length > 0);
}
