<?php

namespace App\Support\Domain;

class BeltRank
{
    /**
     * Higher number means higher belt eligibility.
     * GEUP_10 is the entry belt, GEUP_1 is highest geup, DAN is above all geup ranks.
     */
    private const RANKS = [
        'GEUP_10' => 1,
        'GEUP_9' => 2,
        'GEUP_8' => 3,
        'GEUP_7' => 4,
        'GEUP_6' => 5,
        'GEUP_5' => 6,
        'GEUP_4' => 7,
        'GEUP_3' => 8,
        'GEUP_2' => 9,
        'GEUP_1' => 10,
        'DAN' => 11,
    ];

    public static function options(): array
    {
        return collect(self::RANKS)
            ->keys()
            ->map(fn (string $value) => ['value' => $value, 'label' => self::label($value)])
            ->all();
    }

    public static function values(): array
    {
        return array_keys(self::RANKS);
    }

    public static function label(?string $value): string
    {
        $normalized = self::normalize($value);

        return $normalized === '' ? '-' : str_replace('_', ' ', $normalized);
    }

    public static function normalize(?string $value): string
    {
        $value = strtoupper(trim((string) $value));

        if ($value === '') {
            return '';
        }

        $value = str_replace(['-', ' '], '_', $value);

        if (preg_match('/^GEUP_?(10|9|8|7|6|5|4|3|2|1)$/', $value, $matches)) {
            return 'GEUP_'.$matches[1];
        }

        if (preg_match('/^DAN(_?\d+)?$/', $value)) {
            return 'DAN';
        }

        return $value;
    }

    public static function isAllowed(?string $value): bool
    {
        $normalized = self::normalize($value);

        return $normalized === '' || array_key_exists($normalized, self::RANKS);
    }

    public static function eligible(?string $athleteBelt, ?string $minimumBelt): bool
    {
        $minimum = self::normalize($minimumBelt);

        if ($minimum === '') {
            return true;
        }

        $athlete = self::normalize($athleteBelt);

        if ($athlete === '') {
            return false;
        }

        return (self::RANKS[$athlete] ?? 0) >= (self::RANKS[$minimum] ?? PHP_INT_MAX);
    }
}
