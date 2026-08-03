<?php

namespace App\Support\Domain;

final class SessionStatus
{
    public const DRAFT = 'DRAFT';

    public const CONFIRMED = 'CONFIRMED';

    public const NEEDS_ASSISTANT = 'NEEDS_ASSISTANT';

    public const CANCELED = 'CANCELED';

    public const ALL = [self::DRAFT, self::CONFIRMED, self::NEEDS_ASSISTANT, self::CANCELED];

    public static function label(string $status): string
    {
        return match ($status) {
            self::CONFIRMED => 'Confirmed',
            self::NEEDS_ASSISTANT => 'Needs assistant',
            self::CANCELED => 'Canceled',
            default => 'Draft',
        };
    }

    public static function tone(string $status): string
    {
        return match ($status) {
            self::CONFIRMED => 'success',
            self::NEEDS_ASSISTANT => 'warning',
            self::CANCELED => 'danger',
            default => 'neutral',
        };
    }
}
