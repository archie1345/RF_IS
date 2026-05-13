export type User = {
    id: number;
    name: string;
    email: string;
    role?: 'admin' | 'coach' | 'parent' | 'athlete';
    roles?: Array<'admin' | 'coach' | 'parent' | 'athlete'>;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type ParentChild = {
    athlete_id: number;
    user_id: number;
    name: string;
};

export type Auth = {
    user: User;
    children?: ParentChild[];
    activeChild?: ParentChild | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
