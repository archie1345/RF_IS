export type AttendanceReportPeriod = {
    from: string;
    to: string;
    month: string;
    label: string;
    exportUrl: string;
};

export type AdminWeeklySession = { title: string; time: string; location: string; date?: string };

export type AdminFeatureWeeklySchedule = {
    id: number;
    title: string;
    branch: string;
    group: string;
    coach: string;
    day_of_week: number;
    time: string;
    location: string;
    is_active: boolean;
};

export type AdminFeatureSelectOption = { value: string | number; label: string };

export type BillingSettings = {
    invoice_day: number;
    invoice_time: string;
    default_amount: string;
    is_active: boolean;
};

export type ManagedLocation = {
    id: number;
    name: string;
    location?: string | null;
    address?: string | null;
    city?: string | null;
    province?: string | null;
    latitude?: string | number | null;
    longitude?: string | number | null;
    attendance_radius_meters: number;
    timezone?: string | null;
    is_active: boolean;
    groups_count?: number;
};

export type ManagedClass = {
    id: number;
    name: string;
    class_type: string;
    coach_id?: string | null;
    coach: string;
    branch_id?: number | string | null;
    branch: string;
    day_of_week: number;
    schedule: string;
    time: string;
    start_time: string;
    end_time: string;
    athletes_count: number;
    min_belt?: string | null;
    description?: string | null;
    is_active: boolean;
};
