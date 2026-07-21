
export type AttendanceQrFlash = {
    token?: string;
    scan_url?: string;
    opens_at?: string | null;
    closes_at?: string | null;
    generated_at?: string | null;
};

export type PagePropsWithQrFlash = {
    flash?: {
        attendanceQr?: AttendanceQrFlash;
        attendanceQrStatus?: string;
    };
};
