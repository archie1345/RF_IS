<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MultiSheetExport implements WithMultipleSheets
{
    /**
     * @param array<int, mixed> $sheets
     */
    public function __construct(private readonly array $sheets) {}

    public function sheets(): array
    {
        return $this->sheets;
    }
}