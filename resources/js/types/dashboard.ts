import type { Metric, TableRow } from '@/types/management';

export type DashboardPageProps = {
    metrics: Metric[];
    snapshotRows: TableRow[];
    activityPreviewRows: TableRow[];
    announcements: string[];
    upcomingEvents: TableRow[];
    attendanceRows: TableRow[];
    paymentRows: TableRow[];
    medalRows: TableRow[];
    profileSummary: Record<string, string>;
};

