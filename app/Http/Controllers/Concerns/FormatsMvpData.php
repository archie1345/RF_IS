<?php

namespace App\Http\Controllers\Concerns;

trait FormatsMvpData
{
    protected function badge(string $text, string $tone = 'neutral'): array
    {
        return [
            'kind' => 'badge',
            'text' => $text,
            'tone' => $tone,
        ];
    }

    protected function rupiah(float $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }
}
