import type { TableColumn, TableRow } from '@/types/management';

export const dashboardColumns = {
    announcement: [{ key: 'text', label: 'Announcement' }] as TableColumn[],
    event: [
        { key: 'event', label: 'Event' },
        { key: 'date', label: 'Date' },
        { key: 'location', label: 'Location' },
    ] as TableColumn[],
    attendance: [
        { key: 'athlete', label: 'Athlete' },
        { key: 'date', label: 'Date' },
        { key: 'status', label: 'Status' },
    ] as TableColumn[],
    payment: [
        { key: 'athlete', label: 'Athlete' },
        { key: 'total', label: 'Total' },
        { key: 'paid', label: 'Paid' },
        { key: 'remaining', label: 'Remaining' },
        { key: 'status', label: 'Status' },
    ] as TableColumn[],
    medal: [
        { key: 'type', label: 'Medal' },
        { key: 'count', label: 'Count', align: 'right' },
    ] as TableColumn[],
    log: [
        { key: 'time', label: 'Time' },
        { key: 'actor', label: 'Actor' },
        { key: 'action', label: 'Action' },
        { key: 'description', label: 'Description' },
    ] as TableColumn[],
    snapshot: [
        { key: 'module', label: 'Module' },
        { key: 'status', label: 'Status' },
        { key: 'value', label: 'Value' },
    ] as TableColumn[],
    profile: [
        { key: 'field', label: 'Field' },
        { key: 'value', label: 'Value' },
    ] as TableColumn[],
};

export function mapAnnouncements(announcements: string[]): TableRow[] {
    return announcements.map((item, idx) => ({ id: `ann-${idx}`, text: item }));
}

export function mapProfileSummary(summary: Record<string, string>): TableRow[] {
    return Object.entries(summary).map(([field, value]) => ({ id: field, field, value }));
}

