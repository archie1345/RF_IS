export type Role = 'admin' | 'coach' | 'athlete';

export type DemoAccount = {
    email: string;
    name: string;
    roles: Role[];
};

export type NavigationPage = {
    href: string;
    section: 'Utama' | 'Latihan & Absensi' | 'Keuangan & Pengguna' | 'Kompetisi & Informasi';
    roles: Role[];
};

export const allRoles: Role[] = ['admin', 'coach', 'athlete'];

export const accounts: DemoAccount[] = [
    { email: 'admin@rfis.test', name: 'Admin', roles: ['admin'] },
    { email: 'coach@rfis.test', name: 'Coach', roles: ['coach'] },
    { email: 'athlete@rfis.test', name: 'Athlete', roles: ['athlete'] },
    { email: 'multirole@rfis.test', name: 'Coach + athlete', roles: ['coach', 'athlete'] },
    { email: 'admincoach@rfis.test', name: 'Admin + coach', roles: ['admin', 'coach'] },
    { email: 'adminathlete@rfis.test', name: 'Admin + athlete', roles: ['admin', 'athlete'] },
    { email: 'allroles@rfis.test', name: 'All roles', roles: allRoles },
];

export const navigation: NavigationPage[] = [
    { href: '/dashboard', section: 'Utama', roles: allRoles },
    { href: '/training-schedule', section: 'Latihan & Absensi', roles: allRoles },
    { href: '/sessions', section: 'Latihan & Absensi', roles: ['admin', 'coach'] },
    { href: '/attendance', section: 'Latihan & Absensi', roles: ['coach', 'athlete'] },
    { href: '/admin/attendance', section: 'Latihan & Absensi', roles: ['admin'] },
    { href: '/admin/instructor-attendance', section: 'Latihan & Absensi', roles: ['admin'] },
    { href: '/admin/classes', section: 'Latihan & Absensi', roles: ['admin', 'coach'] },
    { href: '/admin/groups', section: 'Latihan & Absensi', roles: ['admin'] },
    { href: '/admin/locations', section: 'Latihan & Absensi', roles: ['admin'] },
    { href: '/payments', section: 'Keuangan & Pengguna', roles: allRoles },
    { href: '/admin/payroll', section: 'Keuangan & Pengguna', roles: ['admin'] },
    { href: '/admin/billing-settings', section: 'Keuangan & Pengguna', roles: ['admin'] },
    { href: '/admin', section: 'Keuangan & Pengguna', roles: ['admin'] },
    { href: '/users', section: 'Keuangan & Pengguna', roles: ['admin'] },
    { href: '/championships', section: 'Kompetisi & Informasi', roles: allRoles },
    { href: '/achievements', section: 'Kompetisi & Informasi', roles: ['coach', 'athlete'] },
    { href: '/admin/events/history', section: 'Kompetisi & Informasi', roles: ['admin'] },
    { href: '/announcements', section: 'Kompetisi & Informasi', roles: allRoles },
    { href: '/admin/whatsapp-template', section: 'Kompetisi & Informasi', roles: ['admin'] },
    { href: '/admin/data-export', section: 'Kompetisi & Informasi', roles: ['admin'] },
    { href: '/admin/activity-logs', section: 'Kompetisi & Informasi', roles: ['admin'] },
];
