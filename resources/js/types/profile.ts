export type AthleteProfile = {
    height_cm?: string;
    weight_kg?: string;
    geup?: string;
    nik?: string;
    bpjs?: string;
    nikHash?: string | null;
    bpjsHash?: string | null;
    phone?: string;
    bday?: string;
    gender?: string;
    alamat?: string;
    branch_id?: string | null;
    group_id?: string | null;
    branch?: { branch_name: string };
    group?: { group_name: string };
};

export type ProfileSelectOption = {
    value: string | number;
    label: string;
    branch_id?: string | number | null;
};

export type CoachProfile = {
    status?: string;
    specialization?: string;
    bio?: string;
    nik?: string;
    bpjs?: string;
    nikHash?: string | null;
    bpjsHash?: string | null;
};

export type ParentProfile = {
    phone?: string;
    relation?: string;
    occupation?: string;
    notes?: string;
    athletes?: Array<{
        id: number | string;
        name: string;
        branch?: { branch_name: string };
        group?: { group_name: string };
    }>;
};

export type ProfileCertification = {
    id: number;
    cert_type: string;
    title: string;
    issuer?: string;
    certified_at?: string;
    expires_at?: string;
    notes?: string;
    fileName?: string | null;
    fileUrl?: string | null;
};

export type ProfileAchievement = {
    id: number;
    championship_name: string;
    medal: string;
    location?: string;
    event_date?: string;
    class_name?: string;
    division?: string;
    category?: string;
    notes?: string;
    is_auto_recorded?: boolean;
    fileName?: string | null;
    fileUrl?: string | null;
};

export type ProfileUser = {
    id: number;
    name: string;
    email: string;
    gender?: string;
    bday?: string;
    phone?: string;
    roles: string[];
    bio?: string;
    profilePictureUrl?: string | null;
    athleteProfile?: AthleteProfile | null;
    coachProfile?: CoachProfile | null;
    parentProfile?: ParentProfile | null;
    achievements: ProfileAchievement[];
    certifications: ProfileCertification[];
};
