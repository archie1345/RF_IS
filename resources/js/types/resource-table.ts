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
