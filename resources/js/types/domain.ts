import type { TableBadgeCell } from '@/types/resource-table';

export type AppRole = 'admin' | 'coach' | 'parent' | 'athlete';
export type AttendanceStatus = 'PRESENT' | 'ABSENT' | 'EXCUSED';
export type PaymentProofStatus = 'NONE' | 'SUBMITTED' | 'APPROVED' | 'REJECTED';

export type AttendanceRow = {
    id: string;
    athlete_id?: string;
    date?: string | null;
    athlete: string;
    session: string;
    session_href?: string;
    is_locked?: boolean;
    can_update?: boolean;
    coach?: string;
    checkin?: string;
    status: TableBadgeCell | string;
};

export type PaymentHistoryEntry = {
    id: string | number;
    amount: string;
    status: string;
    date: string;
    verifier?: string | null;
};

export type PaymentRow = {
    id: string;
    payment_id: number;
    athlete_id?: string | null;
    athlete_user_id?: number | null;
    billable_user_id?: number | null;
    payee_user_id?: number | null;
    proof_status?: PaymentProofStatus;
    transaction_history?: PaymentHistoryEntry[];
    [key: string]: unknown;
};

export type SessionRow = {
    id: string;
    session_id: number;
    session: string;
    coach_id?: string | null;
    can_join?: boolean;
    [key: string]: unknown;
};

export type DashboardMetric = {
    label: string;
    value: string;
    detail: string;
    tone?: 'neutral' | 'success' | 'warning' | 'danger' | 'info';
};

export type SelectOption = {
    value: number | string;
    label: string;
};

export type StatusBadgeData = TableBadgeCell;
