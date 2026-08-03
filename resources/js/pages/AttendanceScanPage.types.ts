export type AttendanceScanFlash = {
    status?: string;
    message?: string;
};

export type PagePropsWithAttendanceScan = {
    flash?: {
        attendanceScan?: AttendanceScanFlash;
    };
    errors?: Record<string, string>;
};
