import type { TableRow } from '@/types/resource-table';

export type AttendanceStatusValue = 'PRESENT' | 'ABSENT' | 'EXCUSED' | 'LATE';

export type AttendanceUpdateResponse = {
    message?: string;
    row?: TableRow;
};
