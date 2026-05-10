export type AdminAccountRole = 'admin' | 'coach' | 'parent' | 'athlete';

export type AdminAccountRow = {
    id: number;
    name: string;
    email: string;
    role: AdminAccountRole;
    roles?: AdminAccountRole[];
    branch: string;
    status: 'active' | 'invited' | 'suspended';
    createdAt: string;
    deletedAt?: string | null;
    bio?: string | null;
    profilePictureUrl?: string | null;
};
