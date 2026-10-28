import type { AppRole } from '@/types/resource-table';

export type User = {
    id: string;
    name: string;
    email: string;
    /** Primary (highest privilege) role of the account. */
    role?: AppRole;
    /** Every role assigned to the account. */
    roles?: AppRole[];
    avatar?: string | null;
    email_verified_at?: string | null;
    created_at?: string;
    updated_at?: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
