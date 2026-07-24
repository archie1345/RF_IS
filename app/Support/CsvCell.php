<?php

namespace App\Support;

final class CsvCell
{
    public static function safe(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = str_replace("\0", '', $value);

        if ($value !== '' && preg_match('/^[=+\-@\t\r]/u', $value) === 1) {
            return "'".$value;
        }

        return $value;
    }

    public static function row(array $values): array
    {
        return array_map(self::safe(...), $values);
    }
}
