import type { TableBadgeCell } from '@/types/resource-table';

export type DashboardAttendanceRow = {
    id?: number | string;
    date?: string;
    session_date?: string;
    status: string | TableBadgeCell;
    status_value?: string;
};

export type DashboardTrainingDay = { id: string; date: string; title: string; time: string; branch: string; group: string };

export type BeltRow = { label: string; count: number; color: string };
