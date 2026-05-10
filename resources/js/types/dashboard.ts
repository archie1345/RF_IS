import type { Metric, TableRow } from '@/types/management';

export type DashboardPageProps = {
    metrics: Metric[];
    activityPreviewRows: TableRow[];
    announcements: TableRow[];
    upcomingEvents: TableRow[];
    attendanceRows: TableRow[];
    paymentRows: TableRow[];
    medalRows: TableRow[];
    profileSummary: Record<string, string>;
};

