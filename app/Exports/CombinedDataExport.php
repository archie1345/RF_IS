<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CombinedDataExport implements FromArray, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    public function __construct(
        private readonly array $data,
        private readonly array $headings,
        private readonly string $sheetTitle = 'Combined Export'
    ) {}

    public function array(): array
    {
        return $this->data;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function columnWidths(): array
    {
        $widths = [];
        foreach ($this->headings as $index => $heading) {
            $widths[Coordinate::stringFromColumnIndex($index + 1)] = min(max(mb_strlen($heading) + 4, 14), 32);
        }
        return $widths;
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');
        $sheet->setAutoFilter($sheet->calculateWorksheetDimension());

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => 'solid',
                    'startColor' => ['rgb' => '1F2937'],
                ],
                'alignment' => ['vertical' => 'center'],
            ],
        ];
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }
}
