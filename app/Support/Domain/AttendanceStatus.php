<?php

namespace App\Support\Domain;

final class AttendanceStatus
{
    public const PRESENT = 'PRESENT';
    public const ABSENT = 'ABSENT';
    public const EXCUSED = 'EXCUSED';

    public const ALL = [self::PRESENT, self::ABSENT, self::EXCUSED];

    public static function label(string $status): string
    {
        return match ($status) {
            self::PRESENT => 'Present',
            self::EXCUSED => 'Excused',
            default => 'Absent',
        };
    }

    public static function tone(string $status): string
    {
        return match ($status) {
            self::PRESENT => 'success',
            self::EXCUSED => 'info',
            default => 'danger',
        };
    }
}
