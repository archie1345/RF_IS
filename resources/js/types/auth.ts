import type { AppRole } from '@/types/resource-table';

export type User = {
    id: number;
    name: string;
    email: string;
    /** Compatibility alias for the currently selected role. */
    role?: AppRole;
    activeRole?: AppRole;
    primaryRole?: AppRole;
    roles?: AppRole[];
    isMultiRole?: boolean;
    avatar?: string | null;
    email_verified_at?: string | null;
    created_at?: string;
    updated_at?: string;
    [key: string]: unknown;
};

export type ParentChild = {
    athlete_id: string;
    user_id: number;
    name: string;
};

export type Auth = {
    user: User | null;
    children?: ParentChild[];
    activeChild?: ParentChild | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
