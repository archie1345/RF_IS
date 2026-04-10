import type { AdminAccountRow } from '@/types/admin';

export const adminAccountRows: AdminAccountRow[] = [
    {
        id: 1,
        name: 'Ayu Prameswari',
        email: 'ayu.admin@rfis.test',
        role: 'admin',
        branch: 'Head Office',
        status: 'active',
        createdAt: '02 Apr 2026',
    },
    {
        id: 2,
        name: 'Deni Saputra',
        email: 'deni.coach@rfis.test',
        role: 'coach',
        branch: 'Jakarta Selatan',
        status: 'active',
        createdAt: '04 Apr 2026',
    },
    {
        id: 3,
        name: 'Mira Lestari',
        email: 'mira.parent@rfis.test',
        role: 'parent',
        branch: 'Depok',
        status: 'invited',
        createdAt: '07 Apr 2026',
    },
    {
        id: 4,
        name: 'Raka Pratama',
        email: 'raka.athlete@rfis.test',
        role: 'athlete',
        branch: 'Bekasi',
        status: 'active',
        createdAt: '08 Apr 2026',
    },
    {
        id: 5,
        name: 'Nadia Putri',
        email: 'nadia.athlete@rfis.test',
        role: 'athlete',
        branch: 'Depok',
        status: 'suspended',
        createdAt: '09 Apr 2026',
    },
];
