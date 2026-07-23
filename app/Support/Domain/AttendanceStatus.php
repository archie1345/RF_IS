<?php

namespace App\Support\Domain;

final class AttendanceStatus
{
    public const PRESENT = 'PRESENT';

    public const ABSENT = 'ABSENT';

    public const LATE = 'LATE';

    public const EXCUSED = 'EXCUSED';

    public const ALL = [self::PRESENT, self::ABSENT, self::LATE, self::EXCUSED];

    public static function label(string $status): string
    {
        return match ($status) {
            self::PRESENT => 'Present',
            self::LATE => 'Late',
            self::EXCUSED => 'Excused',
            default => 'Absent',
        };
    }

    public static function tone(string $status): string
    {
        return match ($status) {
            self::PRESENT => 'success',
            self::LATE => 'warning',
            self::EXCUSED => 'info',
            default => 'danger',
        };
    }
}
