import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';
import type { AppRole } from './resource-table';

export type BreadcrumbItem = {
    title: string;
    href?: string;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
    /** Roles that may open this destination. The sidebar switches context automatically when needed. */
    roles?: AppRole[];
};

export type NavSection = {
    label: string;
    items: NavItem[];
};
