export type SelectOption = {
    value: string | number;
    label: string;
};

export type Child = {
    id: number;
    name: string;
    email: string;
    gender?: string;
    bday?: string;
    phone?: string;
    roles: string[];
    bio?: string;
    profilePictureUrl?: string | null;
    athleteProfile?: {
        height_cm?: number;
        weight_kg?: number;
        geup?: string;
        nik?: string;
        bpjs?: string;
        phone?: string;
        bday?: string;
        gender?: string;
        alamat?: string;
        branch_id?: string | number | null;
        group_id?: string | number | null;
        branch?: string;
        group?: string;
    } | null;
    achievements: Array<Record<string, unknown>>;
    certifications: Array<Record<string, unknown>>;
};
