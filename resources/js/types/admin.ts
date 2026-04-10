export type AdminAccountRole = 'admin' | 'coach' | 'parent' | 'athlete';

export type AdminAccountRow = {
    id: number;
    name: string;
    email: string;
    role: AdminAccountRole;
    branch: string;
    status: 'active' | 'invited' | 'suspended';
    createdAt: string;
};
