<?php

namespace App\Support\Domain;

final class SessionType
{
    public const REGULER = 'reguler';

    public const PRESTASI = 'prestasi';

    public const PRIVATE = 'private';

    public const ALL = [
        self::REGULER,
        self::PRESTASI,
        self::PRIVATE,
    ];

    public static function normalize(mixed $value): string
    {
        return str((string) $value)->lower()->slug('_')->toString();
    }

    public static function isPrivate(mixed $value): bool
    {
        return self::normalize($value) === self::PRIVATE;
    }
}
