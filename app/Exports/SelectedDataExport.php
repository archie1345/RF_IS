<?php

namespace App\Exports;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SelectedDataExport implements FromQuery, WithColumnWidths, WithHeadings, WithMapping, WithStyles, WithTitle
{
    /**
     * @param  array<int, string>  $headings
     * @param  Closure(mixed): array<int, mixed>  $mapper
     */
    public function __construct(
        private readonly Builder $exportQuery,
        private readonly array $exportHeadings,
        private readonly Closure $mapper,
        private readonly string $sheetTitle,
    ) {}

    public function query(): Builder
    {
        return $this->exportQuery;
    }

    /** @return array<int, mixed> */
    public function map($row): array
    {
        return ($this->mapper)($row);
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return $this->exportHeadings;
    }

    /** @return array<string, float> */
    public function columnWidths(): array
    {
        $widths = [];

        foreach ($this->exportHeadings as $index => $heading) {
            $widths[Coordinate::stringFromColumnIndex($index + 1)] = min(max(mb_strlen($heading) + 4, 14), 32);
        }

        return $widths;
    }

    /** @return array<int, array<string, mixed>> */
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
        $cleanTitle = str_replace([':', '*', '?', '/', '\\', '[', ']'], '', $this->sheetTitle);

        return substr(trim($cleanTitle), 0, 31) ?: 'Export';
    }

    public function getPeriodLabel(): string
    {
        $from = $this->filters['date_from'] ?? null;
        $to = $this->filters['date_to'] ?? null;

        if (blank($from) && blank($to)) {
            return 'All Time';
        }

        if (filled($from) && filled($to)) {
            return "{$from} to {$to}";
        }

        if (filled($from)) {
            return "From {$from}";
        }

        return "Until {$to}";
    }
}
