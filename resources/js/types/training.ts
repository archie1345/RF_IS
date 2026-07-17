export type SelectOption = { value: number | string; label: string };

export type LocationRecord = {
    id: number;
    name: string;
    location?: string | null;
    address?: string | null;
    city?: string | null;
    province?: string | null;
    latitude?: string | number | null;
    longitude?: string | number | null;
    google_maps_url?: string | null;
    attendance_radius_meters: number;
    timezone?: string | null;
    is_active: boolean;
    groups_count: number;
    athletes_count: number;
};

export type ClassAthleteRecord = {
    id: string | number;
    name: string;
    geup?: string | null;
    branch?: string | null;
    training_group?: string | null;
};

export type ClassScheduleMode = 'weekly' | 'one_day';

export type ClassRecord = {
    id: number;
    name: string;
    training_group_id?: number | string | null;
    training_group?: string | null;
    class_type: string;
    schedule_mode?: ClassScheduleMode;
    single_session_date?: string | null;
    branch_id?: number | null;
    branch: string;
    coach_id?: string | null;
    coach: string;
    day_of_week?: number | null;
    day_label: string;
    start_time: string;
    end_time: string;
    min_belt?: string | null;
    min_belt_label?: string | null;
    description?: string | null;
    athletes_count: number;
    athletes?: ClassAthleteRecord[];
    is_active: boolean;
    weekly_schedule_id?: number | null;
    weekly_schedule_status: string;
};

export type WeeklySchedule = {
    id: number;
    title: string;
    branch_id?: number | null;
    branch?: string | null;
    group_id?: number | null;
    group?: string | null;
    training_group_id?: number | string | null;
    training_group?: string | null;
    dedicated_athlete_id?: number | string | null;
    dedicated_athlete?: string | null;
    session_type?: string | null;
    coach_id?: string | null;
    coach?: string | null;
    day_of_week: number;
    day_label?: string | null;
    start_time?: string | null;
    end_time?: string | null;
    location?: string | null;
    latitude?: string | number | null;
    longitude?: string | number | null;
    google_maps_url?: string | null;
    min_belt?: string | null;
    min_belt_label?: string | null;
    is_active?: boolean;
    generated_sessions_count?: number;
    can_manage?: boolean;
    class_type?: string | null;
    athletes_count?: number | null;
};

export type WeeklyScheduleCard = Omit<
    WeeklySchedule,
    'branch_id' | 'group_id' | 'coach_id' | 'generated_sessions_count'
>;
