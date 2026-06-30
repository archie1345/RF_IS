import type { TableColumn, TableRow } from '@/types/resource-table';

export const dashboardColumns = {
    log: [
        { key: 'time', label: 'Time' },
        { key: 'actor', label: 'Actor' },
        { key: 'action', label: 'Action' },
        { key: 'description', label: 'Description' },
    ] as TableColumn[],
};

export function mapProfileSummary(summary: Record<string, string>): TableRow[] {
    return Object.entries(summary).map(([field, value]) => ({ id: field, field, value }));
}

