import type { BreadcrumbItem } from '@/types';

export type Props = {
    breadcrumbs?: BreadcrumbItem[];
    autoRefresh?: boolean;
    autoRefreshIntervalMs?: number;
};
