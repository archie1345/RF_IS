import type { TableFilter, TableRow } from '@/types/resource-table';

export type DataTableFilterValue = string | string[];

export type DataTableFilterColumns = 1 | 2 | 3 | 4 | 5 | 6 | 'auto';

export type DataTableFilterSpan = 1 | 2 | 3 | 4 | 5 | 6 | 'full';

export type DataTableFilter = Omit<TableFilter, 'match'> & {
    multiple?: boolean;
    span?: DataTableFilterSpan;
    match?: (row: TableRow, value: string) => boolean;
};
