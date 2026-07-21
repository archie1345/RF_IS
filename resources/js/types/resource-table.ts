export type AppRole = 'admin' | 'coach' | 'parent' | 'athlete';

export type StatusTone = 'neutral' | 'success' | 'warning' | 'danger' | 'info';

export type Metric = {
    label: string;
    value: string;
    detail: string;
    tone?: StatusTone;
};

export type DashboardPanel = {
    title: string;
    description: string;
    ctaLabel: string;
    ctaHref: string;
    items: string[];
};

export type TableBadgeCell = {
    kind: 'badge';
    text: string;
    tone?: StatusTone;
};

export type TableCell = string | number | boolean | null | TableBadgeCell | Record<string, unknown> | unknown[];

export type TableRow = {
    id: string;
    [key: string]: TableCell;
};

export type TableColumn = {
    key: string;
    label: string;
    align?: 'left' | 'right';
};

export type SelectOption = {
    value: number | string;
    label: string;
};

export type TableFilterType = 'text' | 'select';

export type TableFilterValue = string | string[];

export type TableFilterColumns = 1 | 2 | 3 | 4 | 5 | 6 | 'auto';
export type TableFilterSpan = 1 | 2 | 3 | 4 | 5 | 6 | 'full';

export type TableFilter = {
    key: string;
    label: string;
    type?: TableFilterType;
    columnKey?: string;
    placeholder?: string;
    searchPlaceholder?: string;
    options?: SelectOption[];
    multiple?: boolean;
    span?: TableFilterSpan;
    accessor?: (row: TableRow) => TableCell | string | number | boolean | null | undefined;
    match?: (row: TableRow, value: TableFilterValue) => boolean;
};

export type RoleDashboardContent = {
    headline: string;
    description: string;
    metrics: Metric[];
    panels: DashboardPanel[];
};

export type AttendanceRow = {
    id?: number | string;
    date: string;
    session_date?: string;
    status: string | TableBadgeCell;
    status_value?: 'PRESENT' | 'ABSENT' | 'EXCUSED' | string;
};

export type TrainingDay = {
    id: string;
    date: string;
    title: string;
    time: string;
    branch: string;
    group: string;
};